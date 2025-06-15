<?php

namespace App\Filament\Tenant\Resources\WorkScheduleResource\Widgets;

use App\Models\Tenants\Employee;
use App\Models\Tenants\Shift;
use App\Models\Tenants\WorkSchedule;
use Carbon\Carbon;
use Filament\Forms\Form;  // Tambahkan ini
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Log;



class ScheduleCalendar extends Widget
{
    use InteractsWithActions;
    use InteractsWithForms;
    protected static string $view = 'filament.tenant.resources.work-schedule-resource.widgets.schedule-calendar';
    
    protected int | string | array $columnSpan = 'full';
    
    
    public $weekStart;
    public $weekEnd;
    public $currentDate;
    public $scheduleData = [];

    protected $listeners = ['refreshCalendar' => 'loadWeekSchedule'];
    
    
    public function mount()
    {
        $this->currentDate = Carbon::now();
        $this->weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $this->weekEnd = Carbon::now()->endOfWeek(Carbon::SUNDAY);
        
        // Load data untuk kalendar
        $this->loadWeekSchedule();
    }

    public function nextWeek()
    {
        $this->currentDate = $this->currentDate->addWeek();
        $this->loadWeekSchedule();
    }
    
    public function prevWeek()
    {
        $this->currentDate = $this->currentDate->subWeek();
        $this->loadWeekSchedule();
    }
    
    protected function loadWeekSchedule()
    {
        // Calculate week start (Monday) and end (Sunday)
        $this->weekStart = $this->currentDate->copy()->startOfWeek();
        $this->weekEnd = $this->weekStart->copy()->addDays(6);
        
        // Get schedules for this week grouped by date and shift
        $schedules = WorkSchedule::with(['employee', 'shift'])
            ->whereBetween('date', [$this->weekStart->format('Y-m-d'), $this->weekEnd->format('Y-m-d')])
            ->get();
            
        // Organize data by date and shift
        $this->scheduleData = [];
        
        // Initialize array for each day
        for ($day = 0; $day <= 6; $day++) {
            $date = $this->weekStart->copy()->addDays($day)->format('Y-m-d');
            $this->scheduleData[$date] = [
                'date' => $date,
                'day_name' => $this->weekStart->copy()->addDays($day)->format('l'),
                'shifts' => [],
            ];
        }
        
        // Fill with schedule data
        foreach ($schedules as $schedule) {
            $date = $schedule->date->format('Y-m-d');
            if (!isset($this->scheduleData[$date]['shifts'][$schedule->shift_id])) {
                $this->scheduleData[$date]['shifts'][$schedule->shift_id] = [
                    'shift_id' => $schedule->shift_id,
                    'shift_name' => $schedule->shift->name,
                    'shift_time' => $schedule->shift->start_time . ' - ' . $schedule->shift->end_time,
                    'employees' => []
                ];
            }
            
            $this->scheduleData[$date]['shifts'][$schedule->shift_id]['employees'][] = [
                'id' => $schedule->employee->id,
                'name' => $schedule->employee->name,
                'status' => $schedule->status
            ];
        }
    }

    public function editScheduleAction(): Action
    {
        return Action::make('editSchedule')
            ->label(fn (array $arguments) => $arguments['employeeName'] ?? 'Edit')
            ->mountUsing(function (Action $action, array $arguments) {
                // Fill form with data from arguments
                $action->form->fill([
                    'employeeId' => $arguments['employeeId'] ?? null,
                    'shiftId' => $arguments['shiftId'] ?? null,
                    'date' => $arguments['date'] ?? null,
                ]);
                
                // Look up existing schedule if any
                if (isset($arguments['date']) && isset($arguments['employeeId'])) {
                    $schedule = WorkSchedule::where('date', $arguments['date'])
                        ->where('employee_id', $arguments['employeeId'])
                        ->first();
                    
                    if ($schedule) {
                        $action->record($schedule);
                    }
                }
            })
            ->form([
                Select::make('employeeId')
                    ->label('Employee')
                    ->options(function () {
                        return Employee::where('is_active', true)->pluck('name', 'id');
                    })
                    ->required(),
                Select::make('shiftId')
                    ->label('Shift')
                    ->options(function () {
                        return Shift::pluck('name', 'id');
                    })
                    ->required(),
                Hidden::make('date'),
            ])
            ->action(function (array $data, ?WorkSchedule $record) {
                try {
                    if ($record) {
                        // Update existing schedule
                        $record->update([
                            'employee_id' => $data['employeeId'],
                            'shift_id' => $data['shiftId'],
                        ]);
                        
                        $this->dispatch('notify', [
                            'type' => 'success',
                            'message' => 'Schedule updated successfully',
                        ]);
                    } else {
                        // Create new schedule
                        WorkSchedule::create([
                            'employee_id' => $data['employeeId'],
                            'shift_id' => $data['shiftId'],
                            'date' => $data['date'],
                            'status' => 'scheduled',
                        ]);
                        
                        $this->dispatch('notify', [
                            'type' => 'success',
                            'message' => 'Schedule created successfully',
                        ]);
                    }
                    
                    // Refresh schedule data
                    $this->loadWeekSchedule();
                } catch (\Exception $e) {
                    Log::error('Failed to save schedule: ' . $e->getMessage());
                    
                    $this->dispatch('notify', [
                        'type' => 'danger',
                        'message' => 'Failed to save schedule: ' . $e->getMessage(),
                    ]);
                }
            });
    }

    public static function canView(): bool
    {
        return true;
    }

    // Render method
    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function getEmployeesProperty()
    {
        return Employee::where('is_active', true)->get();
    }
    
    public function getShiftsProperty()
    {
        return Shift::all();
    }
}