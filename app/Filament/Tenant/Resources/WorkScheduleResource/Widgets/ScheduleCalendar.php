<?php

namespace App\Filament\Tenant\Resources\WorkScheduleResource\Widgets;

use App\Models\Tenants\WorkSchedule;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ScheduleCalendar extends Widget
{
    protected static string $view = 'filament.tenant.resources.work-schedule-resource.widgets.schedule-calendar';
    
    protected int | string | array $columnSpan = 'full';
    protected static ?string $wrapperClasses = 'full-width-widget';
    
    public $weekStart;
    public $weekEnd;
    public $currentDate;
    public $scheduleData = [];
    
    public function mount()
    {
        $this->currentDate = now();
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
                    'shift_name' => $schedule->shift->name,
                    'shift_time' => $schedule->shift->start_time . ' - ' . $schedule->shift->end_time,
                    'employees' => []
                ];
            }
            
            $this->scheduleData[$date]['shifts'][$schedule->shift_id]['employees'][] = [
                'name' => $schedule->employee->name,
                'status' => $schedule->status
            ];
        }
    }
}