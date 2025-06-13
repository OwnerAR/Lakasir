<?php

namespace App\Services\Tenants;

use App\Models\Tenants\Employee;
use App\Models\Tenants\Shift;
use App\Models\Tenants\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WorkScheduleService
{
    private array $lastEmployeeShifts = [];
    private array $employeeRestDays = [];
    private ?int $offShiftId = null;
    private array $shiftGroups = []; // Menyimpan pengelompokan shift
    
    public function __construct()
    {
        $offShift = Shift::where('name', 'like', '%off%')
            ->orWhere('name', 'like', '%libur%')
            ->orWhere('name', 'like', '%rest%')
            ->orWhere('name', 'like', '%free%')
            ->first();
            
        if ($offShift) {
            $this->offShiftId = $offShift->id;
        } else {
            try {
                $newOffShift = Shift::create([
                    'name' => 'Off Day',
                    'start_time' => '00:00:00',
                    'end_time' => '00:00:00'
                ]);
                $this->offShiftId = $newOffShift->id;
            } catch (\Exception $e) {
                $this->offShiftId = Shift::first()->id ?? null;
            }
        }
        

        $this->initializeShiftGroups();
    }

    /**
     * Mengelompokkan shift berdasarkan jam kerja
     * Shift libur tidak termasuk dalam grup shift
     */
    private function initializeShiftGroups(): void
    {
        $this->shiftGroups = [];
        

        $shifts = Shift::when($this->offShiftId, function ($query) {
            return $query->where('id', '!=', $this->offShiftId);
        })->get();
        
        if ($shifts->isEmpty()) {
            return;
        }
        

        $morningShifts = [];
        $afternoonShifts = [];
        $nightShifts = [];
        
        foreach ($shifts as $shift) {
            $startTime = Carbon::parse($shift->start_time);
            $hour = (int) $startTime->format('H');
            
            if ($hour >= 6 && $hour < 12) {
                $morningShifts[] = $shift->id;
            } elseif ($hour >= 12 && $hour < 18) {
                $afternoonShifts[] = $shift->id;
            } else {
                $nightShifts[] = $shift->id;
            }
        }
        

        $groupIndex = 1;
        
        if (!empty($morningShifts)) {
            $this->shiftGroups[$groupIndex++] = $morningShifts;
        }
        
        if (!empty($afternoonShifts)) {
            $this->shiftGroups[$groupIndex++] = $afternoonShifts;
        }
        
        if (!empty($nightShifts)) {
            $this->shiftGroups[$groupIndex++] = $nightShifts;
        }
        
        if (empty($this->shiftGroups)) {
            foreach ($shifts as $shift) {
                $groupId = ($shift->id % count($shifts)) + 1;
                if (!isset($this->shiftGroups[$groupId])) {
                    $this->shiftGroups[$groupId] = [];
                }
                $this->shiftGroups[$groupId][] = $shift->id;
            }
        }
    }

        /**
     * Generate jadwal untuk periode tertentu
     *
     * @param Carbon $startDate
     * @param int $weeks
     * @return bool
     */
    public function generateSchedule(Carbon $startDate, int $weeks = 4): bool
    {

        $employees = Employee::all();
        $shifts = Shift::all();
        
        if ($employees->count() < 2 || $shifts->count() < 2) {
            return false;
        }


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
            
            $currentDate->addWeek();
        }
        
        return true;
    }

    /**
     * Inisialisasi tracking shift terakhir semua karyawan
     */
    private function initializeLastShifts(Collection $employees, Carbon $startDate): void
    {
        foreach ($employees as $employee) {
            // Cek jadwal terakhir sebelum tanggal mulai
            $lastSchedule = WorkSchedule::where('employee_id', $employee->id)
                ->where('date', '<', $startDate->format('Y-m-d'))
                ->orderBy('date', 'desc')
                ->first();
            
            if ($lastSchedule) {
                $lastDate = Carbon::parse($lastSchedule->date);
                $this->lastEmployeeShifts[$employee->id] = [
                    'shift_id' => $lastSchedule->shift_id,
                    'date' => $lastDate,
                ];
            } else {
                $this->lastEmployeeShifts[$employee->id] = [
                    'shift_id' => null,
                    'date' => null,
                ];
            }
        }
    }
    
    /**
     * Membagi karyawan ke dalam grup shift
     * Sekarang mendukung jumlah grup dinamis berdasarkan shiftGroups
     */
    private function divideEmployeesIntoGroups(Collection $employees): array
    {
        $groups = [];
        

        foreach (array_keys($this->shiftGroups) as $groupId) {
            $groups[$groupId] = [];
        }
        

        if (empty($groups)) {
            $groups[1] = array_fill(0, $employees->count(), null);
            return $groups;
        }
        
        $totalEmployees = $employees->count();
        $totalGroups = count($groups);
        

        $employeesPerGroup = ceil($totalEmployees / $totalGroups);
        

        $employeesPerGroup = max(1, $employeesPerGroup);
        

        $assignedEmployees = 0;
        $groupCounts = array_fill(1, $totalGroups, 0);
        

        foreach ($employees as $employee) {
            if (!$employee->rotated && $employee->shift_id) {
                $groupId = $this->getShiftGroupId($employee->shift_id);
                if (isset($groups[$groupId])) {
                    $groups[$groupId][] = $employee->id;
                    $groupCounts[$groupId]++;
                    $assignedEmployees++;
                }
            }
        }
        

        $currentGroup = 1;
        foreach ($employees as $employee) {
            if ($employee->rotated || !$employee->shift_id) {

                $minCount = min($groupCounts);
                $currentGroup = array_search($minCount, $groupCounts);
                
                $groups[$currentGroup][] = $employee->id;
                $groupCounts[$currentGroup]++;
                $assignedEmployees++;
            }
        }
        

        foreach ($groups as $groupId => $employeeIds) {
            if (empty($employeeIds) && $totalEmployees > 0) {

                $maxCount = max($groupCounts);
                $maxGroup = array_search($maxCount, $groupCounts);
                
                if ($maxCount > 1) {
                    $employeeToMove = array_pop($groups[$maxGroup]);
                    $groups[$groupId][] = $employeeToMove;
                    $groupCounts[$maxGroup]--;
                    $groupCounts[$groupId]++;
                }
            }
        }
        
        return $groups;
    }
    
    /**
     * Generate jadwal untuk satu minggu
     * Mendukung jumlah grup dinamis
     */
    private function generateWeeklySchedule(Carbon $startDate, array $employeeGroups, Collection $shifts): void
    {
        $endDate = $startDate->copy()->addDays(6);
        $currentDate = $startDate->copy();
        

        if ($shifts->count() < 2) {
            return;
        }
        
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
     * Mendukung jumlah grup dinamis
     */
    private function addDistributedRestDays(Carbon $startDate, array $employeeGroups): void
    {
        // Skip jika tidak ada shift untuk libur
        if (!$this->offShiftId) {
            return;
        }
        
        // Gabungkan semua karyawan dengan info grup shift mereka
        $employeeWithGroup = [];
        foreach ($employeeGroups as $groupId => $employeeIds) {
            foreach ($employeeIds as $employeeId) {
                $employeeWithGroup[] = [
                    'employee_id' => $employeeId,
                    'group' => $groupId
                ];
            }
        }
        
        // Acak urutan karyawan untuk variasi
        shuffle($employeeWithGroup);
        
        // Initialize day usage tracker
        $dayHasRest = array_fill(0, 7, false);
        $maxDaysOff = min(7, count($employeeWithGroup));
        
        // Count days assigned
        $daysAssigned = 0;
        
        // Alokasikan maksimal 1 karyawan libur per hari
        foreach ($employeeWithGroup as $employee) {
            // Stop once we've assigned all available days
            if ($daysAssigned >= $maxDaysOff) {
                break;
            }
            
            $employeeId = $employee['employee_id'];
            $group = $employee['group'];
            
            // Find a day without anyone assigned yet
            $restDay = null;
            for ($day = 0; $day < 7; $day++) {
                if (!$dayHasRest[$day]) {
                    $restDay = $day;
                    break;
                }
            }
            
            // If no day available, skip this employee
            if ($restDay === null) {
                continue;
            }
            
            // Mark this day as used
            $dayHasRest[$restDay] = true;
            $daysAssigned++;
            
            // Calculate rest date
            $restDate = $startDate->copy()->addDays($restDay);
            
            // Check if there's already a schedule for this date
            $existingSchedule = WorkSchedule::where('employee_id', $employeeId)
                ->where('date', $restDate->format('Y-m-d'))
                ->first();
            
            // Only update if there's a previous schedule
            if ($existingSchedule) {
                $existingSchedule->update([
                    'shift_id' => $this->offShiftId,
                    'status' => 'off'
                ]);
                
                // Record this employee's rest day and previous group
                $this->employeeRestDays[$employeeId] = [
                    'date' => $restDate->copy(),
                    'prev_group' => $group
                ];
                
                // Update last shift tracking
                $this->lastEmployeeShifts[$employeeId] = [
                    'shift_id' => $this->offShiftId,
                    'date' => $restDate->copy(),
                ];
            }
        }
        
        // Ensure we handle group minimums properly
        $this->ensureMinimumWorkersPerShift($startDate, $employeeGroups);
    }
    
    /**
     * Memastikan setiap grup memiliki minimal 1 karyawan yang bekerja setiap hari
     * Mendukung jumlah grup dinamis
     */
    private function ensureMinimumWorkersPerShift(Carbon $startDate, array $employeeGroups): void
    {
        for ($day = 0; $day < 7; $day++) {
            $date = $startDate->copy()->addDays($day);
            $dateStr = $date->format('Y-m-d');
            
            // Check each group
            foreach (array_keys($employeeGroups) as $groupId) {
                // Count active workers for this group on this day
                $activeWorkers = 0;
                
                foreach ($employeeGroups[$groupId] as $employeeId) {
                    $schedule = WorkSchedule::where('employee_id', $employeeId)
                        ->where('date', $dateStr)
                        ->first();
                    
                    if ($schedule && $schedule->shift_id !== $this->offShiftId) {
                        $activeWorkers++;
                    }
                }
                
                // If no active workers, cancel the day off for one employee in this group
                if ($activeWorkers < 1 && !empty($employeeGroups[$groupId])) {
                    foreach ($employeeGroups[$groupId] as $employeeId) {
                        $schedule = WorkSchedule::where('employee_id', $employeeId)
                            ->where('date', $dateStr)
                            ->where('shift_id', $this->offShiftId)
                            ->first();
                        
                        if ($schedule) {
                            // Determine which shift to assign from this group's shifts
                            $shiftId = $this->getShiftForDate($groupId, $date);
                            
                            // Revert off day to working day
                            $schedule->update([
                                'shift_id' => $shiftId,
                                'status' => 'scheduled'
                            ]);
                            
                            // Remove from rest days tracking
                            if (isset($this->employeeRestDays[$employeeId])) {
                                unset($this->employeeRestDays[$employeeId]);
                            }
                            
                            // Update tracking
                            $this->lastEmployeeShifts[$employeeId] = [
                                'shift_id' => $shiftId,
                                'date' => $date->copy(),
                            ];
                            
                            // We fixed this group, move to next
                            break;
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Membuat jadwal kerja untuk satu karyawan pada tanggal tertentu
     */
    private function createSchedule(int $employeeId, int $shiftId, Carbon $date): void
    {
        // Cek jadwal sebelumnya untuk memastikan cukup waktu istirahat
        $lastSchedule = WorkSchedule::where('employee_id', $employeeId)
            ->where('date', '<', $date->format('Y-m-d'))
            ->orderBy('date', 'desc')
            ->first();
            
        if ($lastSchedule) {
            // Jika jadwal sebelumnya adalah shift malam dan sekarang akan shift pagi
            // Dan tanggalnya berurutan (hari berikutnya), maka skip jadwal ini
            $lastShift = Shift::find($lastSchedule->shift_id);
            $currentShift = Shift::find($shiftId);
            
            if ($lastShift && $currentShift) {
                $lastEndTime = Carbon::parse($lastShift->end_time);
                $currentStartTime = Carbon::parse($currentShift->start_time);
                
                // Jika shift terakhir berakhir setelah tengah malam dan shift baru mulai pagi hari
                if ($lastEndTime->hour < 8 && $currentStartTime->hour < 10) {
                    $lastDate = Carbon::parse($lastSchedule->date);
                    if ($lastDate->addDay()->isSameDay($date)) {
                        // Skip - tidak cukup waktu istirahat
                        return;
                    }
                }
            }
        }
        
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
        

        $this->lastEmployeeShifts[$employeeId] = [
            'shift_id' => $shiftId,
            'date' => $date->copy(),
        ];
    }

    /**
     * Rotasi karyawan berdasarkan hari libur mereka
     * Mendukung jumlah grup dinamis
     */
    private function rotateEmployeesAfterRest(array $groups, Carbon $weekEndDate): array
    {
        $totalGroups = count($this->shiftGroups);
        
        // Inisialisasi grup baru
        $newGroups = [];
        foreach (array_keys($this->shiftGroups) as $groupId) {
            $newGroups[$groupId] = [];
        }
        
        // Jika tidak ada grup shift, kembalikan grup yang sudah ada
        if (empty($newGroups)) {
            return $groups;
        }
        
        // Gabungkan semua employee ID
        $allEmployeeIds = [];
        foreach ($groups as $employeeIds) {
            $allEmployeeIds = array_merge($allEmployeeIds, $employeeIds);
        }
        
        // Ambil semua employee yang dapat dirotasi beserta data shift_id mereka
        $employees = Employee::whereIn('id', $allEmployeeIds)
            ->select('id', 'rotated', 'shift_id')
            ->get();
        
        $rotatableEmployees = $employees->where('rotated', true)->pluck('id')->toArray();
        $nonRotatableEmployees = $employees->where('rotated', false);
        
        $previousGroupMap = [];
        foreach ($groups as $groupId => $employeeIds) {
            foreach ($employeeIds as $employeeId) {
                $previousGroupMap[$employeeId] = $groupId;
            }
        }
        
        // 1. Karyawan yang tidak dapat dirotasi berdasarkan shift_id di tabel employees
        foreach ($nonRotatableEmployees as $employee) {
            // Konversi shift_id database ke nomor grup
            $shiftGroup = $this->getShiftGroupId($employee->shift_id);
            
            // Tambahkan karyawan ke grup yang sesuai dengan shift_id mereka di database
            if (isset($newGroups[$shiftGroup])) {
                $newGroups[$shiftGroup][] = $employee->id;
            } else {
                // Jika grup tidak ada, tempatkan di grup pertama
                $firstGroupId = array_key_first($newGroups);
                $newGroups[$firstGroupId][] = $employee->id;
            }
        }
        
        // 2. Karyawan yang dapat dirotasi dan memiliki catatan libur
        $employeesWithRestRecord = [];
        foreach ($this->employeeRestDays as $employeeId => $restInfo) {
            if (!in_array($employeeId, $rotatableEmployees)) continue;
            
            $employeesWithRestRecord[] = $employeeId;
            $prevGroup = $restInfo['prev_group'];
            
            // Tentukan grup berikutnya (rotasi ke grup berikutnya)
            // Untuk kasus dengan lebih dari 2 grup, kita rotasi secara siklis
            $nextGroup = $prevGroup + 1;
            if ($nextGroup > $totalGroups) {
                $nextGroup = 1;
            }
            
            if (isset($newGroups[$nextGroup])) {
                $newGroups[$nextGroup][] = $employeeId;
            }
        }
        
        // 3. Karyawan yang dapat dirotasi tapi tidak memiliki catatan libur
        foreach ($rotatableEmployees as $employeeId) {
            // Skip karyawan yang sudah diproses (memiliki catatan libur)
            if (in_array($employeeId, $employeesWithRestRecord)) continue;
            
            // Skip karyawan yang sudah diproses sebelumnya
            $alreadyAssigned = false;
            foreach ($newGroups as $groupEmployees) {
                if (in_array($employeeId, $groupEmployees)) {
                    $alreadyAssigned = true;
                    break;
                }
            }
            if ($alreadyAssigned) continue;
            
            // Tetap di grup semula
            $currentGroup = $previousGroupMap[$employeeId] ?? 1;
            if (isset($newGroups[$currentGroup])) {
                $newGroups[$currentGroup][] = $employeeId;
            }
        }
        
        // 4. Pastikan setiap grup memiliki minimal 1 karyawan
        $this->balanceGroups($newGroups, $totalGroups);
        
        return $newGroups;
    }

    /**
     * Menyeimbangkan jumlah karyawan di setiap grup
     */
    private function balanceGroups(array &$groups, int $totalGroups): void
    {
        $minEmployeesPerGroup = 1;
        
        // Hitung karyawan per grup
        $employeesPerGroup = [];
        foreach ($groups as $groupId => $employees) {
            $employeesPerGroup[$groupId] = count($employees);
        }
        
        // Cek grup yang kurang dari minimum
        foreach ($employeesPerGroup as $groupId => $count) {
            if ($count < $minEmployeesPerGroup) {
                // Cari grup dengan karyawan terbanyak
                $maxEmployees = max($employeesPerGroup);
                $maxGroupId = array_search($maxEmployees, $employeesPerGroup);
                
                // Pastikan grup dengan karyawan terbanyak memiliki lebih dari minimum
                if ($maxEmployees > $minEmployeesPerGroup) {
                    // Berapa banyak karyawan yang perlu dipindahkan
                    $neededEmployees = $minEmployeesPerGroup - $count;
                    
                    // Pindahkan karyawan dari grup dengan jumlah terbanyak
                    for ($i = 0; $i < $neededEmployees; $i++) {
                        if (!empty($groups[$maxGroupId])) {
                            $employeeToMove = array_pop($groups[$maxGroupId]);
                            $groups[$groupId][] = $employeeToMove;
                            
                            // Update counter
                            $employeesPerGroup[$maxGroupId]--;
                            $employeesPerGroup[$groupId]++;
                        }
                    }
                }
            }
        }
    }

    /**
     * Menentukan grup shift berdasarkan shift_id dari database
     * 
     * @param int|string|null $shiftId
     * @return int
     */
    private function getShiftGroupId($shiftId): int
    {
        if (empty($shiftId)) {
            return array_key_first($this->shiftGroups) ?? 1;
        }
        
        // Cari shift ID di grup yang ada
        foreach ($this->shiftGroups as $groupId => $shiftIds) {
            if (in_array($shiftId, $shiftIds)) {
                return $groupId;
            }
        }
        
        // Jika shift tidak ditemukan di grup manapun, gunakan grup default
        return array_key_first($this->shiftGroups) ?? 1;
    }
    
    /**
     * Mendapatkan shift yang harus digunakan untuk grup dan tanggal tertentu
     */
    private function getShiftForDate(int $groupId, Carbon $date): int
    {
        if (!isset($this->shiftGroups[$groupId]) || empty($this->shiftGroups[$groupId])) {
            // Return shift default jika tidak ada shift untuk grup ini
            return Shift::first()->id ?? 1;
        }
        
        // Jika ada lebih dari satu shift dalam grup, kita bisa melakukan rotasi berdasarkan hari
        $shiftCount = count($this->shiftGroups[$groupId]);
        $dayOfWeek = $date->dayOfWeek; // 0 (Minggu) hingga 6 (Sabtu)
        
        $shiftIndex = $dayOfWeek % $shiftCount;
        return $this->shiftGroups[$groupId][$shiftIndex];
    }

    /**
     * Rotasi karyawan antar shift dengan memperhatikan kolom rotated
     */
    // private function rotateEmployees(array $groups): array
    // {
    //     $newGroups = [
    //         1 => [],
    //         2 => []
    //     ];
        
    //     // Ambil semua employee yang dapat dirotasi
    //     $employees = Employee::whereIn('id', array_merge($groups[1], $groups[2]))->get();
    //     $rotatableEmployeesIds = $employees->where('rotated', true)->pluck('id')->toArray();
    //     $nonRotatableEmployeesIds = $employees->where('rotated', false)->pluck('id')->toArray();
        
    //     // Karyawan yang tidak dapat dirotasi tetap di grup yang sama
    //     foreach ($groups as $shiftId => $employeeIds) {
    //         foreach ($employeeIds as $employeeId) {
    //             if (in_array($employeeId, $nonRotatableEmployeesIds)) {
    //                 $newGroups[$shiftId][] = $employeeId;
    //             }
    //         }
    //     }
        
    //     // Hitung berapa karyawan yang perlu dirotasi
    //     $rotatableG1 = array_intersect($groups[1], $rotatableEmployeesIds);
    //     $rotatableG2 = array_intersect($groups[2], $rotatableEmployeesIds);
        
    //     // Hitung berapa karyawan yang perlu dipindahkan dari setiap grup
    //     // Mempertimbangkan kebutuhan minimal 2 karyawan per grup
    //     $moveFromG1Count = min(
    //         ceil(count($rotatableG1) / 2),
    //         count($rotatableG1) - max(0, 2 - count($newGroups[1]))
    //     );
        
    //     $moveFromG2Count = min(
    //         floor(count($rotatableG2) / 2),
    //         count($rotatableG2) - max(0, 2 - count($newGroups[2]))
    //     );
        
    //     // Jika setelah rotasi, ada grup yang kurang dari 2 orang, sesuaikan
    //     if (count($newGroups[1]) + count($rotatableG2) - $moveFromG2Count < 2) {
    //         $moveFromG2Count = count($rotatableG2) - (2 - count($newGroups[1]));
    //         $moveFromG2Count = max(0, $moveFromG2Count); // Pastikan tidak negatif
    //     }
        
    //     if (count($newGroups[2]) + count($rotatableG1) - $moveFromG1Count < 2) {
    //         $moveFromG1Count = count($rotatableG1) - (2 - count($newGroups[2]));
    //         $moveFromG1Count = max(0, $moveFromG1Count); // Pastikan tidak negatif
    //     }
        
    //     // Karyawan yang tetap di grup 1
    //     $stayInG1 = array_slice($rotatableG1, 0, count($rotatableG1) - $moveFromG1Count);
    //     foreach ($stayInG1 as $employeeId) {
    //         $newGroups[1][] = $employeeId;
    //     }
        
    //     // Karyawan dari grup 2 yang pindah ke grup 1
    //     $moveToG1 = array_slice($rotatableG2, 0, $moveFromG2Count);
    //     foreach ($moveToG1 as $employeeId) {
    //         $newGroups[1][] = $employeeId;
    //     }
        
    //     // Karyawan dari grup 1 yang pindah ke grup 2
    //     $moveToG2 = array_slice($rotatableG1, count($rotatableG1) - $moveFromG1Count);
    //     foreach ($moveToG2 as $employeeId) {
    //         $newGroups[2][] = $employeeId;
    //     }
        
    //     // Karyawan yang tetap di grup 2
    //     $stayInG2 = array_slice($rotatableG2, $moveFromG2Count);
    //     foreach ($stayInG2 as $employeeId) {
    //         $newGroups[2][] = $employeeId;
    //     }
        
    //     // Pastikan setiap grup minimal 2 orang
    //     if (count($newGroups[1]) < 2 || count($newGroups[2]) < 2) {
    //         // Jika gagal memenuhi minimal 2 orang, kembalikan grup asli
    //         return $groups;
    //     }
        
    //     return $newGroups;
    // }
}