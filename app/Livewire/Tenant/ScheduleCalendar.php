<?php

namespace App\Livewire\Tenant;

use App\Models\Tenants\Employee;
use App\Models\Tenants\Shift;
use App\Models\Tenants\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ScheduleCalendar extends Component
{
    public $weekStart;
    public $weekEnd;
    public $currentDate;
    public $scheduleData = [];
    
    public $isModalOpen = false;
    public $selectedDate;
    public $selectedEmployeeId;
    public $selectedShiftId;
    public $scheduleId;
    
    protected $listeners = [
        'refreshSchedule' => 'loadWeekSchedule',
        'closeModal' => 'closeModal',
    ];
    public function mount()
    {
        $this->currentDate = Carbon::now();
        $this->weekStart = $this->currentDate->copy()->startOfWeek(Carbon::SUNDAY);
        $this->weekEnd = $this->currentDate->copy()->endOfWeek(Carbon::SATURDAY);
        $this->loadWeekSchedule();
    }
    

    public function nextWeek()
    {
        $this->currentDate = $this->currentDate->copy()->addWeek();
        $this->weekStart = $this->currentDate->copy()->startOfWeek(Carbon::SUNDAY);
        $this->weekEnd = $this->currentDate->copy()->endOfWeek(Carbon::SATURDAY);
        $this->loadWeekSchedule();
    }
    
    public function prevWeek()
    {
        $this->currentDate = $this->currentDate->copy()->subWeek();
        $this->weekStart = $this->currentDate->copy()->startOfWeek(Carbon::SUNDAY);
        $this->weekEnd = $this->currentDate->copy()->endOfWeek(Carbon::SATURDAY);
        $this->loadWeekSchedule();
    }
    

    public function loadWeekSchedule()
    {
        $this->scheduleData = [];
        

        $startDate = $this->weekStart->copy();
        $endDate = $this->weekEnd->copy();
        
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            $this->scheduleData[$dateString] = [
                'shifts' => []
            ];
        }
        
        $shifts = Shift::all();
        
        $schedules = WorkSchedule::whereBetween('date', [
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        ])->with(['employee', 'shift'])->get();
        
        foreach ($schedules as $schedule) {
            $date = $schedule->date;
                if ($date instanceof \Carbon\Carbon) {
                    $date = $date->format('Y-m-d');
                } elseif ($date instanceof \DateTime) {
                    $date = Carbon::instance($date)->format('Y-m-d');
                } elseif (is_string($date)) {
                    $date = Carbon::parse($date)->format('Y-m-d');
                }
                
                $shiftId = (int) $schedule->shift_id;
                
                if (!isset($this->scheduleData[$date])) {
                    $this->scheduleData[$date] = [
                        'shifts' => []
                    ];
                }
                
                if (!isset($this->scheduleData[$date]['shifts'][$shiftId])) {
                    $shift = $shifts->find($shiftId);
                    if (!$shift) continue;
                    
                    $this->scheduleData[$date]['shifts'][$shiftId] = [
                        'shift_id' => $shiftId,
                        'shift_name' => $shift->name,
                        'shift_time' => $shift->start_time . ' - ' . $shift->end_time,
                        'off' => $shift->off,
                        'employees' => []
                    ];
                }
            
            if ($schedule->employee) {
                $this->scheduleData[$date]['shifts'][$shiftId]['employees'][] = [
                    'id' => $schedule->employee_id,
                    'name' => $schedule->employee->name,
                    'status' => $schedule->status
                ];
            }
        }
        
        foreach ($this->scheduleData as $date => $data) {
            if (isset($data['shifts']) && is_array($data['shifts'])) {
                $this->scheduleData[$date]['shifts'] = array_values($data['shifts']);
            }
        }
    }
    
    public function openEditModal($date, $employeeId = null, $shiftId = null)
    {
        $this->selectedDate = $date;
        $this->selectedEmployeeId = $employeeId;
        $this->selectedShiftId = $shiftId;
        
        if ($employeeId) {
            $schedule = WorkSchedule::where('date', $date)
                ->where('employee_id', $employeeId)
                ->first();
                
            if ($schedule) {
                $this->scheduleId = $schedule->id;
                $this->selectedShiftId = $schedule->shift_id;
            } else {
                $this->scheduleId = null;
            }
        } else {
            $this->scheduleId = null;
        }
        
        $this->isModalOpen = true;
    }
    
    public function saveSchedule()
    {
        $this->validate([
            'selectedEmployeeId' => 'required',
            'selectedShiftId' => 'required',
            'selectedDate' => 'required|date',
        ]);
        
        try {
            if ($this->scheduleId) {
                WorkSchedule::find($this->scheduleId)->update([
                    'employee_id' => $this->selectedEmployeeId,
                    'shift_id' => $this->selectedShiftId,
                ]);
                
                session()->flash('message', 'Schedule updated successfully.');
            } else {
                WorkSchedule::create([
                    'date' => $this->selectedDate,
                    'employee_id' => $this->selectedEmployeeId,
                    'shift_id' => $this->selectedShiftId,
                    'status' => 'scheduled',
                ]);
                
                session()->flash('message', 'Schedule created successfully.');
            }
            
            $this->closeModal();
            $this->loadWeekSchedule();
            
        } catch (\Exception $e) {
            session()->flash('error', 'Error: ' . $e->getMessage());
            Log::error('Failed to save schedule: ' . $e->getMessage());
        }
    }
    
    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->reset(['selectedDate', 'selectedEmployeeId', 'selectedShiftId', 'scheduleId']);
    }
    
    public function render()
    {
        return view('livewire.tenant.schedule-calendar', [
            'employees' => Employee::where('is_active', true)->get(),
            'shifts' => Shift::all(),
        ]);
    }
}
