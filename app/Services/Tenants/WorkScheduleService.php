<?php

namespace App\Services\Tenants;

use App\Models\Tenants\Employee;
use App\Models\Tenants\Shift;
use App\Models\Tenants\WorkSchedule;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WorkScheduleService
{
    private array $lastEmployeeShifts = [];
    private array $employeeRestDays = [];
    private ?int $offShiftId = null;
    private array $shiftGroups = [];
    private WhatsappService $whatsappService;

    public function __construct(WhatsappService $whatsappService)
    {
        $this->whatsappService = $whatsappService;

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
     * Send WhatsApp notifications with detailed schedules for each week
     *
     * @param Carbon $startDate
     * @param int $weeks
     * @return void
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
            
            $this->whatsappService->sendMessage(config('app.whatsapp_id'), $message);
            
            if ($week < $weeks - 1) {
                sleep(1);
            }
        }
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
        $this->sendScheduleNotification($startDate, $weeks);
        
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
     * Membagi karyawan ke dalam grup shift dengan memperhatikan kepadatan shift
     * Group 1 (shift siang) harus memiliki jumlah karyawan >= Group 2 (shift malam)
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
        
        $isTwoShiftPattern = $totalGroups === 2;
        
        $firstGroupHasPriority = false;
        if ($isTwoShiftPattern) {
            $firstGroupHasPriority = $this->firstGroupRequiresMoreStaff();
        }
        
        if ($isTwoShiftPattern && $firstGroupHasPriority) {
            $group1Ratio = 0.6;
            $group2Ratio = 0.4;
            
            $minGroup1 = max(2, ceil($totalEmployees * $group1Ratio));
            $maxGroup2 = min(floor($totalEmployees * $group2Ratio), $totalEmployees - $minGroup1);
        } else {
            $employeesPerGroup = ceil($totalEmployees / $totalGroups);
            $minGroup1 = $employeesPerGroup;
            $maxGroup2 = $employeesPerGroup;
        }
        
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
        
        if ($isTwoShiftPattern && $firstGroupHasPriority && $groupCounts[2] > $maxGroup2) {
            $excessCount = $groupCounts[2] - $maxGroup2;
            
            $movedCount = 0;
            $employeesToMove = [];
            
            foreach ($employees as $employee) {
                if (!$employee->rotated && $employee->shift_id) {
                    $groupId = $this->getShiftGroupId($employee->shift_id);
                    if ($groupId == 2 && $movedCount < $excessCount) {
                        $employeesToMove[] = $employee->id;
                        $movedCount++;
                    }
                }
            }
            
            foreach ($employeesToMove as $employeeId) {
                $index = array_search($employeeId, $groups[2]);
                if ($index !== false) {
                    unset($groups[2][$index]);
                    $groups[1][] = $employeeId;
                    $groupCounts[2]--;
                    $groupCounts[1]++;
                }
            }
        }
        
        $rotatableEmployees = [];
        foreach ($employees as $employee) {
            if ($employee->rotated || !$employee->shift_id) {
                $rotatableEmployees[] = $employee->id;
            }
        }
        
        shuffle($rotatableEmployees);
        
        foreach ($rotatableEmployees as $employeeId) {
            if ($isTwoShiftPattern && $firstGroupHasPriority) {
                if ($groupCounts[2] < $maxGroup2) {
                    $targetGroup = 2;
                } else {
                    $targetGroup = 1;
                }
            } else {
                $minCount = min($groupCounts);
                $targetGroup = array_search($minCount, $groupCounts);
            }
            
            $groups[$targetGroup][] = $employeeId;
            $groupCounts[$targetGroup]++;
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
     * Menentukan apakah grup pertama membutuhkan lebih banyak staf
     * berdasarkan pola waktu shift
     */
    private function firstGroupRequiresMoreStaff(): bool
    {
        if (count($this->shiftGroups) < 2) {
            return false;
        }
        
        $group1Shifts = $this->shiftGroups[1] ?? [];
        $group2Shifts = $this->shiftGroups[2] ?? [];
        
        if (empty($group1Shifts) || empty($group2Shifts)) {
            return false;
        }
        
        $shift1 = Shift::find($group1Shifts[0]);
        $shift2 = Shift::find($group2Shifts[0]);
        
        if (!$shift1 || !$shift2) {
            return false;
        }
        
        $start1 = Carbon::parse($shift1->start_time)->format('H:i');
        $end1 = Carbon::parse($shift1->end_time)->format('H:i');
        $start2 = Carbon::parse($shift2->start_time)->format('H:i');
        $end2 = Carbon::parse($shift2->end_time)->format('H:i');
        
        $isGroup1DayShift = (strpos($start1, '06:') === 0 || strpos($start1, '07:') === 0 || strpos($start1, '08:') === 0) && 
                            (strpos($end1, '17:') === 0 || strpos($end1, '18:') === 0 || strpos($end1, '19:') === 0);
                            
        $isGroup2NightShift = (strpos($start2, '18:') === 0 || strpos($start2, '19:') === 0 || strpos($start2, '20:') === 0) &&
                            (strpos($end2, '06:') === 0 || strpos($end2, '07:') === 0 || strpos($end2, '08:') === 0);
        
        return $isGroup1DayShift && $isGroup2NightShift;
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
        if (!$this->offShiftId) {
            return;
        }
        
        $employeeWithGroup = [];
        foreach ($employeeGroups as $groupId => $employeeIds) {
            foreach ($employeeIds as $employeeId) {
                $employeeWithGroup[] = [
                    'employee_id' => $employeeId,
                    'group' => $groupId
                ];
            }
        }
        
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
            
            $restDay = null;
            for ($day = 0; $day < 7; $day++) {
                if (!$dayHasRest[$day]) {
                    $restDay = $day;
                    break;
                }
            }
            
            if ($restDay === null) {
                continue;
            }
            
            $dayHasRest[$restDay] = true;
            $daysAssigned++;
            
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
            
            foreach (array_keys($employeeGroups) as $groupId) {
                $activeWorkers = 0;
                
                foreach ($employeeGroups[$groupId] as $employeeId) {
                    $schedule = WorkSchedule::where('employee_id', $employeeId)
                        ->where('date', $dateStr)
                        ->first();
                    
                    if ($schedule && $schedule->shift_id !== $this->offShiftId) {
                        $activeWorkers++;
                    }
                }
                
                if ($activeWorkers < 1 && !empty($employeeGroups[$groupId])) {
                    foreach ($employeeGroups[$groupId] as $employeeId) {
                        $schedule = WorkSchedule::where('employee_id', $employeeId)
                            ->where('date', $dateStr)
                            ->where('shift_id', $this->offShiftId)
                            ->first();
                        
                        if ($schedule) {
                            $shiftId = $this->getShiftForDate($groupId, $date);
                            
                            $schedule->update([
                                'shift_id' => $shiftId,
                                'status' => 'scheduled'
                            ]);
                            
                            if (isset($this->employeeRestDays[$employeeId])) {
                                unset($this->employeeRestDays[$employeeId]);
                            }
                            
                            $this->lastEmployeeShifts[$employeeId] = [
                                'shift_id' => $shiftId,
                                'date' => $date->copy(),
                            ];
                            
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
        $lastSchedule = WorkSchedule::where('employee_id', $employeeId)
            ->where('date', '<', $date->format('Y-m-d'))
            ->orderBy('date', 'desc')
            ->first();
            
        if ($lastSchedule) {
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
        
        $newGroups = [];
        foreach (array_keys($this->shiftGroups) as $groupId) {
            $newGroups[$groupId] = [];
        }
        
        if (empty($newGroups)) {
            return $groups;
        }
        
        $allEmployeeIds = [];
        foreach ($groups as $employeeIds) {
            $allEmployeeIds = array_merge($allEmployeeIds, $employeeIds);
        }
        
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
        
        foreach ($nonRotatableEmployees as $employee) {
            $shiftGroup = $this->getShiftGroupId($employee->shift_id);
            
            if (isset($newGroups[$shiftGroup])) {
                $newGroups[$shiftGroup][] = $employee->id;
            } else {
                $firstGroupId = array_key_first($newGroups);
                $newGroups[$firstGroupId][] = $employee->id;
            }
        }
        
        $employeesWithRestRecord = [];
        foreach ($this->employeeRestDays as $employeeId => $restInfo) {
            if (!in_array($employeeId, $rotatableEmployees)) continue;
            
            $employeesWithRestRecord[] = $employeeId;
            $prevGroup = $restInfo['prev_group'];
            
            $nextGroup = $prevGroup + 1;
            if ($nextGroup > $totalGroups) {
                $nextGroup = 1;
            }
            
            if (isset($newGroups[$nextGroup])) {
                $newGroups[$nextGroup][] = $employeeId;
            }
        }
        

        foreach ($rotatableEmployees as $employeeId) {
            if (in_array($employeeId, $employeesWithRestRecord)) continue;

            $alreadyAssigned = false;
            foreach ($newGroups as $groupEmployees) {
                if (in_array($employeeId, $groupEmployees)) {
                    $alreadyAssigned = true;
                    break;
                }
            }
            if ($alreadyAssigned) continue;
            
            $currentGroup = $previousGroupMap[$employeeId] ?? 1;
            if (isset($newGroups[$currentGroup])) {
                $newGroups[$currentGroup][] = $employeeId;
            }
        }
        
        $this->balanceGroups($newGroups, $totalGroups);
        
        return $newGroups;
    }

    /**
     * Menyeimbangkan jumlah karyawan di setiap grup dengan tetap
     * mempertahankan prioritas grup 1 jika perlu
     */
    private function balanceGroups(array &$groups, int $totalGroups): void
    {
        $minEmployeesPerGroup = 1;
        
        $isTwoShiftPattern = $totalGroups === 2;
        $firstGroupHasPriority = false;
        
        if ($isTwoShiftPattern) {
            $firstGroupHasPriority = $this->firstGroupRequiresMoreStaff();
        }
        
        $employeesPerGroup = [];
        $totalEmployees = 0;
        foreach ($groups as $groupId => $employees) {
            $employeesPerGroup[$groupId] = count($employees);
            $totalEmployees += count($employees);
        }
        
        if ($isTwoShiftPattern && $firstGroupHasPriority) {
            if ($employeesPerGroup[1] < $employeesPerGroup[2]) {
                $employeesToMove = $employeesPerGroup[2] - $employeesPerGroup[1] + 1;
                
                $minGroup1 = ceil($totalEmployees * 0.55);
                $maxGroup2 = floor($totalEmployees * 0.45);
                
                if ($employeesPerGroup[1] < $minGroup1 && $employeesPerGroup[2] > $maxGroup2) {
                    $toMove = min($employeesPerGroup[2] - $maxGroup2, $minGroup1 - $employeesPerGroup[1]);
                    
                    for ($i = 0; $i < $toMove; $i++) {
                        if (!empty($groups[2])) {
                            $employeeToMove = array_pop($groups[2]);
                            $groups[1][] = $employeeToMove;
                        }
                    }
                }
            }
        }
        
        foreach ($employeesPerGroup as $groupId => $count) {
            if ($count < $minEmployeesPerGroup) {
                $maxGroupId = null;
                $maxEmployees = 0;
                
                foreach ($employeesPerGroup as $gId => $gCount) {
                    if ($gCount > $maxEmployees) {
                        if (!($firstGroupHasPriority && $gId == 1 && $gCount <= ceil($totalEmployees * 0.55))) {
                            $maxEmployees = $gCount;
                            $maxGroupId = $gId;
                        }
                    }
                }
                
                if ($maxGroupId !== null && $maxEmployees > $minEmployeesPerGroup) {
                    $neededEmployees = $minEmployeesPerGroup - $count;
                    
                    for ($i = 0; $i < $neededEmployees; $i++) {
                        if (!empty($groups[$maxGroupId])) {
                            $employeeToMove = array_pop($groups[$maxGroupId]);
                            $groups[$groupId][] = $employeeToMove;
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