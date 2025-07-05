<?php

namespace App\Services\Tenants;

use App\Models\Tenants\Employee;
use App\Models\Tenants\Shift;
use App\Models\Tenants\WorkSchedule;
use App\Services\WhatsappService;
use App\Services\Tenants\Helpers\EmployeeGroupHelper;
use App\Services\Tenants\Helpers\ShiftGroupHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkScheduleService
{
    private array $lastEmployeeShifts = [];
    private array $employeeRestDays = [];
    private ?int $offShiftId = null;
    private array $shiftGroups = [];
    private WhatsappService $whatsappService;
    
    /**
     * Inisialisasi service dengan dependencies yang diperlukan
     */
    public function __construct(WhatsappService $whatsappService)
    {
        $this->whatsappService = $whatsappService;

        $offShift = Shift::where(function($query) {
            $query->where('name', 'like', '%off%')
                ->orWhere('name', 'like', '%libur%')
                ->orWhere('name', 'like', '%rest%')
                ->orWhere('name', 'like', '%free%');
        })->first();
            
        if ($offShift) {
            $this->offShiftId = $offShift->id;
        } else {
            try {
                $newOffShift = Shift::create([
                    'name' => 'Off Day',
                    'start_time' => '00:00:00',
                    'end_time' => '23:59:00'
                ]);
                $this->offShiftId = $newOffShift->id;
            } catch (\Exception $e) {
                Log::error("Failed to create off day shift: " . $e->getMessage());
                $this->offShiftId = Shift::first()->id ?? null;
            }
        }
        
        $this->initializeShiftGroups();
    }

    /**
     * Send WhatsApp notifications with detailed schedules for each week
     */
    private function sendScheduleNotification(Carbon $startDate, int $weeks): void
    {
        for ($week = 0; $week < $weeks; $week++) {
            $weekStartDate = $startDate->copy()->addWeeks($week);
            $weekEndDate = $weekStartDate->copy()->addDays(6);
            
            $messageTitle = "Jadwal Kerja: " . 
                $weekStartDate->format('d M Y') . " - " . 
                $weekEndDate->format('d M Y') . "\n\n";
            
            $schedules = WorkSchedule::with(['employee', 'shift'])
                ->whereBetween('date', [
                    $weekStartDate->format('Y-m-d'),
                    $weekEndDate->format('Y-m-d')
                ])
                ->orderBy('date')
                ->orderBy('employee_id')
                ->get();
            
            if ($schedules->isEmpty()) {
                continue;
            }
            
            $schedulesByDate = [];
            foreach ($schedules as $schedule) {
                $dateStr = Carbon::parse($schedule->date)->format('d M Y (D)');
                
                if (!isset($schedulesByDate[$dateStr])) {
                    $schedulesByDate[$dateStr] = [];
                }
                
                if ($schedule->employee && $schedule->shift) {
                    $schedulesByDate[$dateStr][] = [
                        'name' => $schedule->employee->name,
                        'shift' => $schedule->shift->name,
                        'time' => $schedule->shift->start_time . '-' . $schedule->shift->end_time
                    ];
                }
            }
            
            $message = $messageTitle;
            
            foreach ($schedulesByDate as $date => $daySchedules) {
                $message .= "*{$date}*\n";
                
                foreach ($daySchedules as $schedule) {
                    $message .= "- {$schedule['name']}: {$schedule['shift']} ({$schedule['time']})\n";
                }
                
                $message .= "\n";
            }
            
            $message .= "Mohon periksa jadwal Anda dan konfirmasi kehadiran.\nRegarding any issues, please contact the HR department.\n\n";
            
            try {
                $this->whatsappService->sendMessage(config('app.whatsapp_id'), $message);
                
                if ($week < $weeks - 1) {
                    sleep(1);
                }
            } catch (\Exception $e) {
                Log::error("Failed to send WhatsApp notification: " . $e->getMessage());
            }
        }
    }

    /**
     * Mengelompokkan shift berdasarkan jam kerja
     * Shift libur tidak termasuk dalam grup shift
     */
    private function initializeShiftGroups(): void
    {
        $this->shiftGroups = ShiftGroupHelper::initializeShiftGroups($this->offShiftId);
    }

    /**
     * Membuat grup shift fallback ketika shift standar tidak ditemukan
     */
    private function createFallbackShiftGroups($shifts): void
    {
        if ($shifts->isEmpty()) {
            return;
        }
        
        $shiftsCount = $shifts->count();
        $optimalGroupCount = min(3, max(1, ceil($shiftsCount / 2)));
        
        for ($i = 1; $i <= $optimalGroupCount; $i++) {
            $this->shiftGroups[$i] = [];
        }
        
        foreach ($shifts as $index => $shift) {
            $groupId = ($index % $optimalGroupCount) + 1;
            $this->shiftGroups[$groupId][] = $shift->id;
        }
    }

    /**
     * Generate jadwal untuk periode tertentu
     */
    public function generateSchedule(Carbon $startDate, int $weeks = 4): bool
    {
        try {
            // Mengambil hanya karyawan non-admin
            $employees = Employee::where('is_active', true)
                ->where('is_admin', false)
                ->get();
                
            $shifts = Shift::all();
            
            if ($employees->count() < 2 || $shifts->count() < 2) {
                Log::warning("Not enough employees or shifts to generate schedule");
                return false;
            }

            DB::beginTransaction();

            // Inisialisasi data untuk tracking
            $this->initializeLastShifts($employees, $startDate);
            $employeeGroups = $this->divideEmployeesIntoGroups($employees);
            $this->employeeRestDays = [];
            
            $currentDate = $startDate->copy();
            
            for ($week = 0; $week < $weeks; $week++) {
                $this->generateWeeklySchedule($currentDate, $employeeGroups, $shifts);
                $this->addDistributedRestDays($currentDate, $employeeGroups);

                if ($week < $weeks - 1) {
                    $employeeGroups = $this->rotateEmployeesAfterRest($employeeGroups, $currentDate);
                }
                
                // Pastikan shift Admin hanya untuk karyawan admin
                $this->ensureAdminShiftsForAdminsOnly($currentDate);
                
                // Pastikan semua shift memiliki minimal satu karyawan setiap hari
                $this->ensureMinimumWorkersPerShift($currentDate, $employeeGroups);

                for ($day = 0; $day < 7; $day++) {
                    $this->ensureCorrectShiftDistribution($currentDate->copy()->addDays($day));
                }
                
                $currentDate->addWeek();
            }
            
            DB::commit();
            
            $this->sendScheduleNotification($startDate, $weeks);
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error generating schedule: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Verifikasi bahwa shift Admin hanya diberikan ke karyawan Admin
     */
    private function ensureAdminShiftsForAdminsOnly(Carbon $startDate): void
    {
        // Identifikasi shift admin berdasarkan nama
        $adminShifts = Shift::where('name', 'like', '%admin%')->pluck('id')->toArray();
        
        if (empty($adminShifts)) {
            return;
        }
        
        // Cari jadwal yang memberikan shift admin ke non-admin
        $wrongAssignments = WorkSchedule::whereBetween('date', [
                $startDate->format('Y-m-d'),
                $startDate->copy()->addDays(6)->format('Y-m-d')
            ])
            ->whereIn('shift_id', $adminShifts)
            ->whereHas('employee', function($query) {
                $query->where('is_admin', false);
            })
            ->with('employee')
            ->get();
        
        foreach ($wrongAssignments as $assignment) {
            // Jika ada non-admin yang terjadwal di shift admin, ganti dengan shift lain
            $alternativeShift = $this->findAlternativeShiftForEmployee(
                $assignment->employee_id,
                $adminShifts,
                Carbon::parse($assignment->date)
            );
            
            if ($alternativeShift) {
                $assignment->update([
                    'shift_id' => $alternativeShift
                ]);
                
                Log::info("Fixed admin shift assignment for non-admin employee #{$assignment->employee_id} on {$assignment->date}");
            }
        }
    }
    
    /**
     * Mencari shift alternatif yang sesuai untuk karyawan
     */
    private function findAlternativeShiftForEmployee(int $employeeId, array $excludeShiftIds, Carbon $date): ?int
    {
        $employeeGroup = $this->getEmployeeGroup($employeeId);
        
        if ($employeeGroup && isset($this->shiftGroups[$employeeGroup])) {
            foreach ($this->shiftGroups[$employeeGroup] as $shiftId) {
                if (!in_array($shiftId, $excludeShiftIds)) {
                    return $shiftId;
                }
            }
        }
        
        $alternativeShift = Shift::whereNotIn('id', $excludeShiftIds)
            ->when($this->offShiftId, function($query) {
                return $query->where('id', '!=', $this->offShiftId);
            })
            ->first();
            
        return $alternativeShift ? $alternativeShift->id : $this->offShiftId;
    }
    
    /**
     * Menentukan grup dari karyawan berdasarkan jadwal yang ada
     */
    private function getEmployeeGroup(int $employeeId): ?int
    {
        foreach ($this->shiftGroups as $groupId => $shiftIds) {
            $lastWorkSchedule = WorkSchedule::where('employee_id', $employeeId)
                ->whereIn('shift_id', $shiftIds)
                ->orderBy('date', 'desc')
                ->first();
                
            if ($lastWorkSchedule) {
                return $groupId;
            }
        }
        
        return null;
    }

    /**
     * Inisialisasi tracking shift terakhir untuk semua karyawan
     */
    private function initializeLastShifts(Collection $employees, Carbon $startDate): void
    {
        $employeeIds = $employees->pluck('id')->toArray();
        
        $lastSchedules = WorkSchedule::whereIn('employee_id', $employeeIds)
            ->where('date', '<', $startDate->format('Y-m-d'))
            ->select('employee_id', 'shift_id', 'date')
            ->orderBy('date', 'desc')
            ->get()
            ->groupBy('employee_id')
            ->map(function ($items) {
                return $items->first();
            });
        
        foreach ($employees as $employee) {
            $lastSchedule = $lastSchedules->get($employee->id);
            
            $this->lastEmployeeShifts[$employee->id] = [
                'shift_id' => $lastSchedule->shift_id ?? null,
                'date' => $lastSchedule ? Carbon::parse($lastSchedule->date) : null,
            ];
        }
    }
    
    /**
     * Membagi karyawan ke dalam grup shift dengan memperhatikan kepadatan shift
     * Group 1 (shift pagi) harus memiliki jumlah karyawan >= Group 2 (shift malam)
     */
    private function divideEmployeesIntoGroups(Collection $employees): array
    {
        return EmployeeGroupHelper::divideEmployeesIntoGroups(
            $employees,
            $this->shiftGroups,
            fn($shiftId) => $this->getShiftGroupId($shiftId),
            fn() => $this->firstGroupRequiresMoreStaff()
        );
    }

    /**
     * Hitung distribusi optimal karyawan di seluruh grup
     */
    private function calculateGroupDistribution(int $totalEmployees, int $totalGroups, bool $isTwoShiftPattern, bool $firstGroupHasPriority): array
    {
        $groupDistribution = [];
        
        if ($isTwoShiftPattern && $firstGroupHasPriority) {
            $group1Ratio = 0.50;
            $group2Ratio = 0.25;
            $group3Ratio = 0.25;

            $minGroup1 = max(2, ceil($totalEmployees * $group1Ratio));
            $maxGroup2 = min(floor($totalEmployees * $group2Ratio), $totalEmployees - $minGroup1);
            $maxGroup3 = min(floor($totalEmployees * $group3Ratio), $totalEmployees - $minGroup1 - $maxGroup2);
        } else {
            // Distribusikan karyawan secara merata
            $baseEmployeesPerGroup = floor($totalEmployees / $totalGroups);
            $remainingEmployees = $totalEmployees % $totalGroups;
            
            for ($i = 1; $i <= $totalGroups; $i++) {
                $groupDistribution[$i] = $baseEmployeesPerGroup + ($i <= $remainingEmployees ? 1 : 0);
            }
            
            $minGroup1 = $groupDistribution[1] ?? $baseEmployeesPerGroup;
            $maxGroup2 = $groupDistribution[2] ?? $baseEmployeesPerGroup;
            $maxGroup3 = $groupDistribution[3] ?? $baseEmployeesPerGroup;
        }

        return [$groupDistribution, $minGroup1, $maxGroup2, $maxGroup3];
    }

    private function ensureCorrectShiftDistribution(Carbon $date): void
    {
        // Dapatkan shift pagi dan malam
        $morningShiftIds = $this->shiftGroups[1] ?? [];
        $eveningShiftIds = $this->shiftGroups[2] ?? [];
        $nightShiftIds = $this->shiftGroups[3] ?? [];

        if (empty($morningShiftIds) || empty($eveningShiftIds) || empty($nightShiftIds)) {
            return;
        }
        
        $dateStr = $date->format('Y-m-d');
        
        // Hitung karyawan di shift pagi dan malam
        $morningStaffCount = WorkSchedule::where('date', $dateStr)
            ->whereIn('shift_id', $morningShiftIds)
            ->count();
            
        $eveningStaffCount = WorkSchedule::where('date', $dateStr)
            ->whereIn('shift_id', $eveningShiftIds)
            ->count();
        $nightStaffCount = WorkSchedule::where('date', $dateStr)
            ->whereIn('shift_id', $nightShiftIds)
            ->count();
        
        // Jika shift malam memiliki lebih banyak atau sama dengan shift pagi
        if ($morningStaffCount <= $eveningStaffCount) {
            // Pindahkan karyawan dari shift malam ke pagi
            $staffToMove = min(
                $eveningStaffCount - $morningStaffCount + 1,
                $eveningStaffCount - 1
            );
            
            if ($staffToMove > 0) {
                // Cari karyawan rotatable dari shift malam yang bisa dipindahkan
                $movableStaff = DB::table('work_schedules')
                    ->join('employees', 'work_schedules.employee_id', '=', 'employees.id')
                    ->where('work_schedules.date', $dateStr)
                    ->whereIn('work_schedules.shift_id', $eveningShiftIds)
                    ->where('employees.rotated', true)
                    ->select('work_schedules.id', 'work_schedules.employee_id')
                    ->limit($staffToMove)
                    ->get();
                
                foreach ($movableStaff as $staff) {
                    // Pilih shift pagi secara acak
                    $morningShiftId = $morningShiftIds[array_rand($morningShiftIds)];
                    
                    // Update jadwal
                    WorkSchedule::where('id', $staff->id)->update([
                        'shift_id' => $morningShiftId
                    ]);
                }
            }
        }
    }

    /**
     * Tetapkan karyawan non-rotatable ke grup preferensi mereka
     */
    private function assignNonRotatableEmployees(Collection $employees, array &$groups): int
    {
        $assignedEmployees = 0;
        
        foreach ($employees as $employee) {
            if (!$employee->rotated && $employee->shift_id) {
                $groupId = $this->getShiftGroupId($employee->shift_id);
                if (isset($groups[$groupId])) {
                    $groups[$groupId][] = $employee->id;
                    $assignedEmployees++;
                }
            }
        }
        
        return $assignedEmployees;
    }

    /**
     * Hitung karyawan di setiap grup
     */
    private function countEmployeesPerGroup(array $groups): array
    {
        $counts = [];
        foreach ($groups as $groupId => $employeeIds) {
            $counts[$groupId] = count($employeeIds);
        }
        return $counts;
    }

    /**
     * Seimbangkan dua grup pertama ketika grup pertama membutuhkan prioritas
     */
    private function balanceFirstTwoGroups(array &$groups, array &$groupCounts, int $maxGroup2): void
    {
        if (!isset($groups[2]) || !isset($groupCounts[2])) {
            return;
        }
        
        // Pastikan shift malam tidak memiliki lebih banyak staff dari shift pagi
        if ($groupCounts[1] <= $groupCounts[2]) {
            // Berapa karyawan yang perlu dipindahkan agar shift pagi > shift malam
            $excessCount = $groupCounts[2] - $groupCounts[1] + 1;
            $employeesToMove = min($excessCount, $groupCounts[2] - 1); // Minimal 1 karyawan tetap di grup 2
            
            if ($employeesToMove > 0) {
                $movedEmployees = array_splice($groups[2], -$employeesToMove);
                $groups[1] = array_merge($groups[1], $movedEmployees);
                
                $groupCounts[2] -= $employeesToMove;
                $groupCounts[1] += $employeesToMove;
            }
        }
    }

    /**
     * Tetapkan karyawan yang dapat dirotasi ke grup
     */
    private function assignRotatableEmployees(Collection $employees, array &$groups, array &$groupCounts, bool $isTwoShiftPattern, bool $firstGroupHasPriority, int $maxGroup2): void
    {
        $rotatableEmployees = $employees->filter(function($employee) {
            return $employee->rotated || !$employee->shift_id;
        })->pluck('id')->toArray();
        
        shuffle($rotatableEmployees);
        
        foreach ($rotatableEmployees as $employeeId) {
            // Periksa jika karyawan sudah ditetapkan
            $alreadyAssigned = false;
            foreach ($groups as $groupEmployees) {
                if (in_array($employeeId, $groupEmployees)) {
                    $alreadyAssigned = true;
                    break;
                }
            }
            
            if ($alreadyAssigned) {
                continue;
            }
            
            $targetGroup = $this->determineTargetGroup($groupCounts, $isTwoShiftPattern, $firstGroupHasPriority, $maxGroup2);
            
            $groups[$targetGroup][] = $employeeId;
            $groupCounts[$targetGroup]++;
        }
    }

    /**
     * Tentukan grup mana yang harus menerima karyawan berikutnya
     */
    private function determineTargetGroup(array $groupCounts, bool $isTwoShiftPattern, bool $firstGroupHasPriority, int $maxGroup2): int
    {
        if ($isTwoShiftPattern && $firstGroupHasPriority) {
            if (($groupCounts[2] ?? 0) < $maxGroup2) {
                return 2;
            }
            return 1;
        } 
        
        $minCount = min($groupCounts);
        return array_search($minCount, $groupCounts) ?: 1;
    }

    /**
     * Pastikan tidak ada grup yang kosong jika ada cukup karyawan
     */
    private function ensureNoEmptyGroups(array &$groups, array $groupCounts, int $totalEmployees): void
    {
        foreach ($groups as $groupId => $employeeIds) {
            if (empty($employeeIds) && $totalEmployees > 0) {
                $maxGroupId = array_search(max($groupCounts), $groupCounts);
                
                if ($maxGroupId && $groupCounts[$maxGroupId] > 1) {
                    $employeeToMove = array_pop($groups[$maxGroupId]);
                    $groups[$groupId][] = $employeeToMove;
                    $groupCounts[$maxGroupId]--;
                    $groupCounts[$groupId]++;
                }
            }
        }
    }

    /**
     * Menentukan apakah grup pertama membutuhkan lebih banyak staf
     * berdasarkan pola waktu shift (shift pagi > shift malam)
     */
    private function firstGroupRequiresMoreStaff(): bool
    {
        return true;
        // if (count($this->shiftGroups) < 2 || empty($this->shiftGroups[1]) || empty($this->shiftGroups[2])) {
        //     return false;
        // }
        
        // try {
        //     $shift1 = Shift::find($this->shiftGroups[1][0]);
        //     $shift2 = Shift::find($this->shiftGroups[2][0]);
            
        //     if (!$shift1 || !$shift2) {
        //         return false;
        //     }
            
        //     $start1 = Carbon::parse($shift1->start_time);
        //     $end1 = Carbon::parse($shift1->end_time);
        //     $start2 = Carbon::parse($shift2->start_time);
        //     $end2 = Carbon::parse($shift2->end_time);
            
        //     // Shift 1 adalah shift "pagi" jika dimulai antara 05:00-12:00 
        //     // dan berakhir antara 15:00-22:00
        //     $isGroup1DayShift = $start1->hour >= 5 && $start1->hour <= 12 && 
        //                        ($end1->hour >= 15 && $end1->hour <= 22);
            
        //     // Shift 2 adalah shift "malam" jika dimulai setelah 15:00
        //     // atau berakhir sebelum 09:00 (menandakan shift yang melewati tengah malam)
        //     $isGroup2NightShift = $start2->hour >= 15 || $end2->hour <= 9;
            
        //     return $isGroup1DayShift && $isGroup2NightShift;
        // } catch (\Exception $e) {
        //     Log::warning("Error in firstGroupRequiresMoreStaff: " . $e->getMessage());
        //     return false;
        // }
    }
    
    /**
     * Generate jadwal untuk satu minggu dengan mendukung jumlah grup dinamis
     */
    private function generateWeeklySchedule(Carbon $startDate, array $employeeGroups, Collection $shifts): void
    {
        if ($shifts->count() < 2) {
            Log::warning("Not enough shifts to generate weekly schedule");
            return;
        }
        
        $endDate = $startDate->copy()->addDays(6);
        $currentDate = $startDate->copy();
        
        $employeeIds = $this->getAllEmployeeIds($employeeGroups);
        $fixedShiftEmployees = Employee::select('id', 'shift_id', 'rotated')
            ->whereIn('id', $employeeIds)
            ->where('rotated', false)
            ->whereNotNull('shift_id')
            ->get()
            ->keyBy('id');

        while ($currentDate->lte($endDate)) {
            foreach ($employeeGroups as $groupId => $employeeIds) {
                if (!isset($this->shiftGroups[$groupId])) continue;
                
                $shiftId = $this->getShiftForDate($groupId, $currentDate);
                
                foreach ($employeeIds as $employeeId) {
                    $this->createSchedule($employeeId, $shiftId, $currentDate);
                }
            }
            
            $currentDate->addDay();
        }
    }

    /**
     * Menambahkan hari libur terdistribusi dalam seminggu
     */
    private function addDistributedRestDays(Carbon $startDate, array $employeeGroups): void
    {
        if (!$this->offShiftId) {
            Log::info("No off shift ID defined, skipping rest day distribution");
            return;
        }
        
        try {
            $employeeWithGroup = $this->prepareEmployeesForRestDays($employeeGroups);
            
            shuffle($employeeWithGroup);
            
            $dayHasRest = array_fill(0, 7, false);
            $maxDaysOff = min(7, count($employeeWithGroup));
            
            $daysAssigned = 0;
            
            foreach ($employeeWithGroup as $employee) {
                if ($daysAssigned >= $maxDaysOff) {
                    break;
                }
                
                $employeeId = $employee['employee_id'];
                $group = $employee['group'];
                
                $restDay = $this->findAvailableRestDay($dayHasRest);
                if ($restDay === null) continue;
                
                $dayHasRest[$restDay] = true;
                $daysAssigned++;
                
                $this->assignRestDay($employeeId, $group, $restDay, $startDate);
            }
            
            $this->ensureMinimumWorkersPerShift($startDate, $employeeGroups);
        } catch (\Exception $e) {
            Log::error("Error adding distributed rest days: " . $e->getMessage());
        }
    }
    
    /**
     * Siapkan array karyawan untuk pemberian hari libur
     */
    private function prepareEmployeesForRestDays(array $employeeGroups): array
    {
        $result = [];
        foreach ($employeeGroups as $groupId => $employeeIds) {
            foreach ($employeeIds as $employeeId) {
                $result[] = [
                    'employee_id' => $employeeId,
                    'group' => $groupId
                ];
            }
        }
        return $result;
    }
    
    /**
     * Cari hari yang belum memiliki jadwal libur
     */
    private function findAvailableRestDay(array $dayHasRest): ?int
    {
        for ($day = 0; $day < 7; $day++) {
            if (!$dayHasRest[$day]) {
                return $day;
            }
        }
        return null;
    }
    
    /**
     * Tetapkan hari libur ke seorang karyawan
     */
    private function assignRestDay(int $employeeId, int $group, int $restDay, Carbon $startDate): void
    {
        $restDate = $startDate->copy()->addDays($restDay);
        
        $existingSchedule = WorkSchedule::where('employee_id', $employeeId)
            ->where('date', $restDate->format('Y-m-d'))
            ->first();
        
        if ($existingSchedule) {
            $existingSchedule->update([
                'shift_id' => $this->offShiftId,
                'status' => 'absent'
            ]);
            
            $this->employeeRestDays[$employeeId] = [
                'date' => $restDate->copy(),
                'prev_group' => $group
            ];
            
            $this->lastEmployeeShifts[$employeeId] = [
                'shift_id' => $this->offShiftId,
                'date' => $restDate->copy(),
            ];
        }
    }
    
    /**
     * Memastikan setiap shift memiliki minimal 1 karyawan yang bekerja setiap hari
     */
    private function ensureMinimumWorkersPerShift(Carbon $startDate, array $employeeGroups): void
    {
        // Ambil semua shift aktif (bukan off/libur)
        $activeShifts = Shift::when($this->offShiftId, function($query) {
            return $query->where('id', '!=', $this->offShiftId);
        })->where(function($query) {
            $query->whereRaw("LOWER(name) NOT LIKE '%admin%'")
                  ->whereRaw("LOWER(name) NOT LIKE '%off%'")
                  ->whereRaw("LOWER(name) NOT LIKE '%libur%'")
                  ->whereRaw("LOWER(name) NOT LIKE '%rest%'")
                  ->whereRaw("LOWER(name) NOT LIKE '%free%'");
        })->pluck('id')->toArray();
        
        if (empty($activeShifts)) {
            return;
        }

        // Periksa setiap hari dalam seminggu
        for ($day = 0; $day < 7; $day++) {
            $date = $startDate->copy()->addDays($day);
            $dateStr = $date->format('Y-m-d');
            
            // Periksa apakah setiap jenis shift memiliki setidaknya satu karyawan
            $shiftCoverage = array_fill_keys($activeShifts, 0);
            
            // Hitung berapa karyawan untuk setiap shift pada hari ini
            $schedules = WorkSchedule::where('date', $dateStr)
                ->whereIn('shift_id', $activeShifts)
                ->get();
                
            foreach ($schedules as $schedule) {
                if (isset($shiftCoverage[$schedule->shift_id])) {
                    $shiftCoverage[$schedule->shift_id]++;
                }
            }
            
            // Identifikasi shift yang tidak memiliki karyawan
            $uncoveredShifts = [];
            foreach ($shiftCoverage as $shiftId => $count) {
                if ($count === 0) {
                    $uncoveredShifts[] = $shiftId;
                }
            }
            
            if (!empty($uncoveredShifts)) {
                // Cari karyawan yang bisa dipindahkan untuk mengisi shift kosong
                foreach ($uncoveredShifts as $uncoveredShiftId) {
                    $shiftGroup = $this->getShiftGroupId($uncoveredShiftId);
                    
                    // Coba temukan karyawan untuk dipindahkan
                    $employeeFound = false;
                    
                    // Strategi 1: Cari karyawan yang sedang libur hari ini
                    $restingEmployee = $this->findRestingEmployeeForShift($dateStr);
                    
                    if ($restingEmployee) {
                        // Periksa jika karyawan yang libur adalah non-admin jika shift yang kosong adalah admin
                        $isAdminShift = $this->isAdminShift($uncoveredShiftId);
                        $isEmployeeAdmin = $this->isEmployeeAdmin($restingEmployee);
                        
                        // Jika shift kosong adalah admin shift dan karyawan non-admin, cari karyawan lain
                        if (!($isAdminShift && !$isEmployeeAdmin)) {
                            $this->assignEmployeeToShift($restingEmployee, $uncoveredShiftId, $date);
                            $employeeFound = true;
                        }
                    }
                    
                    // Strategi 2: Jika tidak ada yang libur, cari karyawan dari shift lain dengan coverage > 1
                    if (!$employeeFound) {
                        foreach ($shiftCoverage as $shiftId => $count) {
                            if ($count > 1 && $shiftId !== $uncoveredShiftId) {
                                $employeeToMove = $this->findEmployeeFromShift($dateStr, $shiftId, $uncoveredShiftId);
                                
                                if ($employeeToMove) {
                                    $this->assignEmployeeToShift($employeeToMove, $uncoveredShiftId, $date);
                                    $employeeFound = true;
                                    break;
                                }
                            }
                        }
                    }
                    
                    // Strategi 3: Jika masih belum ada, ambil karyawan dari shift manapun
                    if (!$employeeFound) {
                        foreach ($activeShifts as $shiftId) {
                            if ($shiftId !== $uncoveredShiftId) {
                                $employeeToMove = $this->findEmployeeFromShift($dateStr, $shiftId, $uncoveredShiftId);
                                
                                if ($employeeToMove) {
                                    $this->assignEmployeeToShift($employeeToMove, $uncoveredShiftId, $date);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Memeriksa apakah shift tertentu adalah shift Admin
     */
    private function isAdminShift(int $shiftId): bool
    {
        static $adminShiftCache = [];
        
        if (isset($adminShiftCache[$shiftId])) {
            return $adminShiftCache[$shiftId];
        }
        
        $shift = Shift::find($shiftId);
        
        if (!$shift) {
            return false;
        }
        
        $isAdmin = stripos($shift->name, 'admin') !== false ||
                stripos($shift->name, 'kantor') !== false ||
                stripos($shift->name, 'office') !== false;
                
        $adminShiftCache[$shiftId] = $isAdmin;
        return $isAdmin;
        }
    
    /**
     * Memeriksa apakah karyawan adalah admin
     */
    private function isEmployeeAdmin(int $employeeId): bool
    {
        static $employeeAdminCache = [];
        
        if (isset($employeeAdminCache[$employeeId])) {
            return $employeeAdminCache[$employeeId];
        }
        
        // Gunakan query yang lebih spesifik dan direct
        $isAdmin = Employee::where('id', $employeeId)
            ->value('is_admin') === 1; // Pastikan perbandingan ketat
        
        $employeeAdminCache[$employeeId] = $isAdmin;
        return $isAdmin;
    }

    /**
     * Temukan karyawan yang sedang libur pada hari tertentu
     */
    private function findRestingEmployeeForShift(string $dateStr): ?int
    {
        $restingEmployee = WorkSchedule::where('date', $dateStr)
            ->where('shift_id', $this->offShiftId)
            ->first();
        
        return $restingEmployee ? $restingEmployee->employee_id : null;
    }

    /**
     * Temukan karyawan yang bekerja pada shift tertentu pada hari tertentu
     * yang dapat dipindahkan ke shift target
     */
    private function findEmployeeFromShift(string $dateStr, int $sourceShiftId, int $targetShiftId): ?int
    {
        $isTargetAdminShift = $this->isAdminShift($targetShiftId);
        
        // Ambil karyawan dari shift sumber
        $schedules = WorkSchedule::with('employee')
            ->where('date', $dateStr)
            ->where('shift_id', $sourceShiftId)
            ->get();
            
        foreach ($schedules as $schedule) {
            if (!$schedule->employee) continue;
            
            // Jika shift target adalah admin, hanya admin yang bisa dipindahkan ke sana
            if ($isTargetAdminShift && !$schedule->employee->is_admin) {
                continue;
            }
            
            return $schedule->employee_id;
        }
        
        return null;
    }

    /**
     * Tugaskan karyawan ke shift tertentu
     */
    private function assignEmployeeToShift(int $employeeId, int $shiftId, Carbon $date): void
    {
        // Periksa jika karyawan adalah admin untuk shift admin
        if ($this->isAdminShift($shiftId)) {
            $isAdmin = $this->isEmployeeAdmin($employeeId);
            
            if (!$isAdmin) {
                // Jika bukan admin, cari shift alternatif
                $alternativeShift = $this->findAlternativeShiftForEmployee($employeeId, [$shiftId], $date);
                if ($alternativeShift) {
                    $shiftId = $alternativeShift;
                }
            }
        }
        
        $dateStr = $date->format('Y-m-d');
        
        // Gunakan updateOrCreate untuk menghindari duplikasi
        WorkSchedule::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'date' => $dateStr
            ],
            [
                'shift_id' => $shiftId,
                'status' => 'scheduled'
            ]
        );
        
        // Update tracking
        $this->lastEmployeeShifts[$employeeId] = [
            'shift_id' => $shiftId,
            'date' => $date->copy(),
        ];
    }
    
    /**
     * Membuat jadwal kerja untuk satu karyawan pada tanggal tertentu
     */
    private function createSchedule(int $employeeId, int $shiftId, Carbon $date): void
    {
        try {
            $employee = Employee::select('id', 'rotated', 'shift_id', 'is_admin')
            ->find($employeeId);
            if (!$employee) {
                Log::warning("Employee #{$employeeId} not found");
                return;
            }

            if ($employee && !$employee->rotated && $employee->shift_id) {
                $shiftId = $employee->shift_id;
            }
            // Periksa jika perlu periode istirahat antara shift
            $needsRestPeriod = $this->needsRestPeriodBetweenShifts($employeeId, $shiftId, $date);
            
            if ($needsRestPeriod) {
                // Jika butuh istirahat, lewati pembuatan jadwal
                return;
            }
            
            // Periksa jika karyawan non-admin mencoba ditetapkan ke shift admin
            $isAdminShift = $this->isAdminShift($shiftId);
            $isEmployeeAdmin = $this->isEmployeeAdmin($employeeId);
            
            if ($isAdminShift && !$isEmployeeAdmin) {
                // Cari shift alternatif untuk karyawan non-admin
                $alternativeShift = $this->findAlternativeShiftForEmployee($employeeId, [$shiftId], $date);
                if ($alternativeShift) {
                    $shiftId = $alternativeShift;
                }
            }
            
            // Buat atau perbarui jadwal
            WorkSchedule::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'date' => $date->format('Y-m-d')
                ],
                [
                    'shift_id' => $shiftId,
                    'status' => 'scheduled'
                ]
            );
            
            // Update tracking
            $this->lastEmployeeShifts[$employeeId] = [
                'shift_id' => $shiftId,
                'date' => $date->copy(),
            ];
        } catch (\Exception $e) {
            Log::error("Error creating schedule for employee #{$employeeId}: " . $e->getMessage());
        }
    }
    
    /**
     * Tentukan apakah karyawan membutuhkan istirahat antara shift
     */
    private function needsRestPeriodBetweenShifts(int $employeeId, int $shiftId, Carbon $date): bool
    {
        $lastSchedule = WorkSchedule::where('employee_id', $employeeId)
            ->where('date', '<', $date->format('Y-m-d'))
            ->orderBy('date', 'desc')
            ->first();
            
        if (!$lastSchedule) {
            return false;
        }
        
        try {
            $lastShift = Shift::find($lastSchedule->shift_id);
            $currentShift = Shift::find($shiftId);
            
            if (!$lastShift || !$currentShift) {
                return false;
            }
            
            $lastEndTime = Carbon::parse($lastShift->end_time);
            $currentStartTime = Carbon::parse($currentShift->start_time);
            
            // Jika shift terakhir selesai setelah tengah malam dan shift baru mulai dini hari
            if ($lastEndTime->hour < 8 && $currentStartTime->hour < 10) {
                $lastDate = Carbon::parse($lastSchedule->date);
                return $lastDate->addDay()->isSameDay($date);
            }
            
            return false;
        } catch (\Exception $e) {
            Log::warning("Error checking rest period: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Rotasi karyawan berdasarkan hari libur mereka dengan dukungan jumlah grup dinamis
     */
    private function rotateEmployeesAfterRest(array $groups, Carbon $weekEndDate): array
    {
        try {
            $totalGroups = count($this->shiftGroups);
            if ($totalGroups === 0) {
                return $groups;
            }
            
            $newGroups = array_fill_keys(array_keys($this->shiftGroups), []);
            
            $allEmployeeIds = $this->getAllEmployeeIds($groups);
            
            $employees = Employee::whereIn('id', $allEmployeeIds)
                ->select('id', 'rotated', 'shift_id', 'is_admin')
                ->get();
            
            // Buat map dari ID karyawan ke grup sebelumnya
            $previousGroupMap = $this->buildPreviousGroupMap($groups);
            
            // Pertama tangani karyawan non-rotatable
            $this->assignNonRotatableEmployeesToNewGroups($employees, $newGroups);
            
            // Kemudian tangani karyawan yang memiliki hari istirahat - rotasi ke grup berikutnya
            $employeesWithRestRecord = $this->rotateEmployeesWithRestDays($newGroups, $totalGroups);
            
            // Terakhir tangani karyawan rotatable yang tersisa
            $this->assignRemainingRotatableEmployees($employees, $newGroups, $previousGroupMap, $employeesWithRestRecord);
            
            // Seimbangkan grup untuk memastikan tidak ada yang kosong
            $this->balanceGroups($newGroups, $totalGroups);
            
            return $newGroups;
        } catch (\Exception $e) {
            Log::error("Error rotating employees: " . $e->getMessage());
            return $groups;
        }
    }
    
    /**
     * Dapatkan semua ID karyawan dari semua grup
     */
    private function getAllEmployeeIds(array $groups): array
    {
        $allIds = [];
        foreach ($groups as $employeeIds) {
            $allIds = array_merge($allIds, $employeeIds);
        }
        return $allIds;
    }
    
    /**
     * Buat map dari ID karyawan ke grup sebelumnya mereka
     */
    private function buildPreviousGroupMap(array $groups): array
    {
        $map = [];
        foreach ($groups as $groupId => $employeeIds) {
            foreach ($employeeIds as $employeeId) {
                $map[$employeeId] = $groupId;
            }
        }
        return $map;
    }
    
    /**
     * Tetapkan karyawan non-rotatable ke grup shift spesifik mereka
     */
    private function assignNonRotatableEmployeesToNewGroups(Collection $employees, array &$newGroups): void
    {
        $nonRotatableEmployees = $employees->where('rotated', false);
        
        foreach ($nonRotatableEmployees as $employee) {
            $shiftGroup = $this->getShiftGroupId($employee->shift_id);
            
            if (isset($newGroups[$shiftGroup])) {
                $newGroups[$shiftGroup][] = $employee->id;
            } else {
                $firstGroupId = array_key_first($newGroups);
                if ($firstGroupId) {
                    $newGroups[$firstGroupId][] = $employee->id;
                }
            }
        }
    }
    
    /**
     * Rotasi karyawan yang memiliki hari libur ke grup berikutnya
     */
    private function rotateEmployeesWithRestDays(array &$newGroups, int $totalGroups): array
    {
        $employeesProcessed = [];
        
        foreach ($this->employeeRestDays as $employeeId => $restInfo) {
            $prevGroup = $restInfo['prev_group'];
            
            $nextGroup = $prevGroup + 1;
            if ($nextGroup > $totalGroups) {
                $nextGroup = 1;
            }
            
            if (isset($newGroups[$nextGroup])) {
                $newGroups[$nextGroup][] = $employeeId;
                $employeesProcessed[] = $employeeId;
            }
        }
        
        return $employeesProcessed;
    }
    
    /**
     * Tetapkan karyawan rotatable yang tersisa ke grup
     */
    private function assignRemainingRotatableEmployees(Collection $employees, array &$newGroups, array $previousGroupMap, array $employeesProcessed): void
    {
        $rotatableEmployees = $employees->where('rotated', true)->pluck('id');
        
        foreach ($rotatableEmployees as $employeeId) {
            if (in_array($employeeId, $employeesProcessed)) {
                continue;
            }
            
            if ($this->isEmployeeAlreadyAssigned($employeeId, $newGroups)) {
                continue;
            }
            
            $currentGroup = $previousGroupMap[$employeeId] ?? 1;
            if (isset($newGroups[$currentGroup])) {
                $newGroups[$currentGroup][] = $employeeId;
            }
        }
    }
    
    /**
     * Periksa apakah karyawan sudah ditetapkan ke grup manapun
     */
    private function isEmployeeAlreadyAssigned(int $employeeId, array $groups): bool
    {
        foreach ($groups as $groupEmployees) {
            if (in_array($employeeId, $groupEmployees)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Seimbangkan distribusi karyawan di seluruh grup
     */
    private function balanceGroups(array &$groups, int $totalGroups): void
    {
        if ($totalGroups <= 1) {
            return;
        }
        
        // Hitung total karyawan dan jumlah ideal per grup
        $totalEmployees = 0;
        foreach ($groups as $groupEmployees) {
            $totalEmployees += count($groupEmployees);
        }
        
        $targetSize = ceil($totalEmployees / $totalGroups);
        
        // PERUBAHAN: Jika pola 2 shift dan grup 1 prioritas (shift siang),
        // selalu pastikan grup 1 memiliki minimal sama banyak dengan grup 2
        if ($totalGroups == 2 && $this->firstGroupRequiresMoreStaff()) {
            // Jika shift malam memiliki lebih banyak karyawan dari shift siang
            if (count($groups[1]) <= count($groups[2])) {
                // Hitung berapa karyawan yang perlu dipindahkan agar shift siang > shift malam
                $employeesToMove = count($groups[2]) - count($groups[1]) + 1;
                
                // Pastikan kita tidak memindahkan terlalu banyak karyawan
                // Minimal tetap 1 karyawan di grup 2
                $employeesToMove = min($employeesToMove, count($groups[2]) - 1);
                
                // Pindahkan karyawan dari grup 2 ke grup 1
                if ($employeesToMove > 0) {
                    $movedEmployees = array_splice($groups[2], -$employeesToMove);
                    $groups[1] = array_merge($groups[1], $movedEmployees);
                }
            }
        }
        
        // Identifikasi grup dengan kelebihan karyawan
        $excessGroups = [];
        $deficitGroups = [];
        
        foreach ($groups as $groupId => $groupEmployees) {
            $count = count($groupEmployees);
            $diff = $count - $targetSize;
            
            if ($diff > 0) {
                $excessGroups[$groupId] = $diff;
            } elseif ($diff < 0) {
                $deficitGroups[$groupId] = abs($diff);
            }
        }
        
        // Pastikan minimal 1 karyawan per grup
        foreach ($groups as $groupId => $employeeIds) {
            if (empty($employeeIds)) {
                // Cari grup dengan karyawan terbanyak
                $maxGroupId = array_search(max(array_map('count', $groups)), $groups);
                
                if ($maxGroupId && count($groups[$maxGroupId]) > 1) {
                    $employeeToMove = array_pop($groups[$maxGroupId]);
                    $groups[$groupId][] = $employeeToMove;
                }
            }
        }
        
        // Pindahkan karyawan dari grup berlebih ke grup defisit
        foreach ($excessGroups as $fromGroupId => $excessCount) {
            foreach ($deficitGroups as $toGroupId => $neededCount) {
                if ($excessCount <= 0 || $neededCount <= 0) {
                    continue;
                }
                
                $employeesToMove = min($excessCount, $neededCount);
                if ($employeesToMove <= 0) {
                    continue;
                }
                
                $movedEmployees = array_splice($groups[$fromGroupId], -$employeesToMove);
                $groups[$toGroupId] = array_merge($groups[$toGroupId], $movedEmployees);
                
                $excessGroups[$fromGroupId] -= $employeesToMove;
                $deficitGroups[$toGroupId] -= $employeesToMove;
            }
        }
    }

    /**
     * Menentukan grup shift berdasarkan shift_id dari database
     */
    private function getShiftGroupId($shiftId): int
    {
        if (empty($shiftId) || empty($this->shiftGroups)) {
            return array_key_first($this->shiftGroups) ?? 1;
        }
        
        foreach ($this->shiftGroups as $groupId => $shiftIds) {
            if (in_array($shiftId, $shiftIds)) {
                return $groupId;
            }
        }
        
        return array_key_first($this->shiftGroups) ?? 1;
    }
    
    /**
     * Mendapatkan shift yang harus digunakan untuk grup dan tanggal tertentu
     */
    private function getShiftForDate(int $groupId, Carbon $date): int
    {
        if (!isset($this->shiftGroups[$groupId]) || empty($this->shiftGroups[$groupId])) {
            return Shift::first()->id ?? 1;
        }
        
        $shiftCount = count($this->shiftGroups[$groupId]);
        $dayOfWeek = $date->dayOfWeek;
        
        $shiftIndex = $dayOfWeek % $shiftCount;
        return $this->shiftGroups[$groupId][$shiftIndex] ?? $this->shiftGroups[$groupId][0];
    }
}