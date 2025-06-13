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
    
    public function __construct()
    {
        // Kode constructor tetap sama
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
        // Ambil semua karyawan dan shift
        $employees = Employee::all();
        $shifts = Shift::all();
        
        if ($employees->count() < 4 || $shifts->count() < 2) {
            return false;
        }

        // Inisialisasi tracking shift terakhir karyawan
        $this->initializeLastShifts($employees, $startDate);
        
        // Bagi karyawan ke dalam grup shift
        $employeeGroups = $this->divideEmployeesIntoGroups($employees);
        $this->employeeRestDays = []; // Inisialisasi tracking hari libur
        
        // Generate jadwal per minggu
        $currentDate = $startDate->copy();
        
        for ($week = 0; $week < $weeks; $week++) {
            // Buat jadwal minggu ini
            $this->generateWeeklySchedule($currentDate, $employeeGroups, $shifts);
            
            // Buat jadwal libur berbeda untuk setiap karyawan dalam seminggu
            $this->addDistributedRestDays($currentDate, $employeeGroups);
            
            // Jika masih ada minggu berikutnya, rotasi karyawan
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
     */
    private function divideEmployeesIntoGroups(Collection $employees): array
    {
        $groups = [
            1 => [], // Shift 1
            2 => []  // Shift 2
        ];
        
        $totalEmployees = $employees->count();
        $firstGroupSize = ceil($totalEmployees / 2); // Bulatkan ke atas untuk grup pertama
        
        // Minimal 2 karyawan per grup
        if ($firstGroupSize < 2) $firstGroupSize = 2;
        if ($totalEmployees - $firstGroupSize < 2) $firstGroupSize = $totalEmployees - 2;
        
        foreach ($employees as $index => $employee) {
            if ($index < $firstGroupSize) {
                $groups[1][] = $employee->id;
            } else {
                $groups[2][] = $employee->id;
            }
        }
        
        return $groups;
    }
    
    /**
     * Generate jadwal untuk satu minggu
     */
    private function generateWeeklySchedule(Carbon $startDate, array $groups, Collection $shifts): void
    {
        $endDate = $startDate->copy()->addDays(6);
        $currentDate = $startDate->copy();
        
        // Pastikan ada shift yang tersedia
        if ($shifts->count() < 2) {
            return;
        }
        
        $firstShift = $shifts[0];
        $secondShift = $shifts[1];
        
        while ($currentDate->lte($endDate)) {
            // Buat jadwal shift 1
            foreach ($groups[1] as $employeeId) {
                $this->createSchedule($employeeId, $firstShift->id, $currentDate);
            }
            
            // Buat jadwal shift 2
            foreach ($groups[2] as $employeeId) {
                $this->createSchedule($employeeId, $secondShift->id, $currentDate);
            }
            
            $currentDate->addDay();
        }
    }

    /**
     * Menambahkan hari libur terdistribusi dalam seminggu
     * Setiap karyawan akan mendapatkan hari libur yang berbeda
     * Memastikan maksimal 1 orang libur per hari
     */
    private function addDistributedRestDays(Carbon $startDate, array $employeeGroups): void
    {
        // Skip jika tidak ada shift untuk libur
        if (!$this->offShiftId) {
            return;
        }
        
        // Gabungkan semua karyawan dengan info grup shift mereka
        $employeeWithGroup = [];
        foreach ($employeeGroups[1] as $employeeId) {
            $employeeWithGroup[] = [
                'employee_id' => $employeeId,
                'group' => 1
            ];
        }
        foreach ($employeeGroups[2] as $employeeId) {
            $employeeWithGroup[] = [
                'employee_id' => $employeeId,
                'group' => 2
            ];
        }
        
        // Acak urutan karyawan untuk variasi
        shuffle($employeeWithGroup);
        
        // Initialize day usage tracker - track which days already have someone on leave
        $dayHasRest = array_fill(0, 7, false);
        $maxDaysOff = min(7, count($employeeWithGroup)); // Maximum 7 days or employee count
        
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
     */
    private function ensureMinimumWorkersPerShift(Carbon $startDate, array $employeeGroups): void
    {
        for ($day = 0; $day < 7; $day++) {
            $date = $startDate->copy()->addDays($day);
            $dateStr = $date->format('Y-m-d');
            
            // Check each group
            foreach ([1, 2] as $groupId) {
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
                if ($activeWorkers < 1) {
                    foreach ($employeeGroups[$groupId] as $employeeId) {
                        $schedule = WorkSchedule::where('employee_id', $employeeId)
                            ->where('date', $dateStr)
                            ->where('shift_id', $this->offShiftId)
                            ->first();
                        
                        if ($schedule) {
                            // Determine which shift to assign based on group
                            $shiftId = ($groupId == 1) ? 1 : 2;
                            
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
            // Jika jadwal sebelumnya adalah shift 2 (malam) dan sekarang akan shift 1 (pagi)
            // Dan tanggalnya berurutan (hari berikutnya), maka skip jadwal ini
            if ($lastSchedule->shift_id == 2 && $shiftId == 1) {
                $lastDate = Carbon::parse($lastSchedule->date);
                if ($lastDate->addDay()->isSameDay($date)) {
                    // Skip - tidak cukup waktu istirahat (kurang dari 12 jam)
                    return;
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
        
        // Update tracking shift terakhir
        $this->lastEmployeeShifts[$employeeId] = [
            'shift_id' => $shiftId,
            'date' => $date->copy(),
        ];
    }

    /**
     * Rotasi karyawan berdasarkan hari libur mereka
     * Karyawan yang selesai libur akan masuk ke shift yang berbeda
     */
    private function rotateEmployeesAfterRest(array $groups, Carbon $weekEndDate): array
    {
        $newGroups = [
            1 => [],
            2 => []
        ];
        
        // Ambil semua employee yang dapat dirotasi
        $employees = Employee::whereIn('id', array_merge($groups[1], $groups[2]))->get();
        $rotatableEmployees = $employees->where('rotated', true)->pluck('id')->toArray();
        $nonRotatableEmployees = $employees->where('rotated', false)->pluck('id')->toArray();
        
        // Simpan mapping karyawan -> grup sebelumnya untuk referensi
        $previousGroupMap = [];
        foreach ($groups[1] as $empId) $previousGroupMap[$empId] = 1;
        foreach ($groups[2] as $empId) $previousGroupMap[$empId] = 2;
        
        // 1. Karyawan yang tidak dapat dirotasi tetap di grup yang sama
        foreach ($groups as $shiftId => $employeeIds) {
            foreach ($employeeIds as $employeeId) {
                if (in_array($employeeId, $nonRotatableEmployees)) {
                    $newGroups[$shiftId][] = $employeeId;
                }
            }
        }
        
        // 2. Semua karyawan yang dapat dirotasi dan memiliki catatan libur
        $employeesWithRestRecord = [];
        foreach ($this->employeeRestDays as $employeeId => $restInfo) {
            // Pastikan karyawan ini dapat dirotasi
            if (!in_array($employeeId, $rotatableEmployees)) continue;
            
            $employeesWithRestRecord[] = $employeeId;
            $prevGroup = $restInfo['prev_group'];
            
            // Ubah grup (1->2 atau 2->1) tanpa memandang kapan liburnya
            $newGroup = ($prevGroup == 1) ? 2 : 1;
            $newGroups[$newGroup][] = $employeeId;
        }
        
        // 3. Karyawan yang dapat dirotasi tapi tidak memiliki catatan libur
        foreach ($rotatableEmployees as $employeeId) {
            // Skip karyawan yang sudah diproses (memiliki catatan libur)
            if (in_array($employeeId, $employeesWithRestRecord)) continue;
            
            // Skip karyawan yang sudah diproses sebelumnya
            if (in_array($employeeId, array_merge($newGroups[1], $newGroups[2]))) continue;
            
            // Tetap di grup semula
            $currentGroup = $previousGroupMap[$employeeId] ?? 1;
            $newGroups[$currentGroup][] = $employeeId;
        }
        
        // 4. Pastikan setiap grup memiliki minimal 2 karyawan
        if (count($newGroups[1]) < 2 || count($newGroups[2]) < 2) {
            // Gabungkan semua karyawan dan coba bagi ulang
            $allEmployees = array_merge($newGroups[1], $newGroups[2]);
            
            if (count($allEmployees) < 4) {
                return $groups; // Kembalikan grup asli jika terlalu sedikit karyawan
            }
            
            // Bagi ulang dengan memastikan minimal 2 orang per grup
            $firstGroupSize = max(2, (int)ceil(count($allEmployees) / 2));
            $newGroups[1] = array_slice($allEmployees, 0, $firstGroupSize);
            $newGroups[2] = array_slice($allEmployees, $firstGroupSize);
            
            if (empty($newGroups[2])) {
                // Jika grup 2 kosong, pindahkan 2 karyawan dari grup 1
                $newGroups[2] = array_slice($newGroups[1], -2);
                $newGroups[1] = array_slice($newGroups[1], 0, -2);
            }
        }
        
        return $newGroups;
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