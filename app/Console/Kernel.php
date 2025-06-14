<?php

namespace App\Console;

use App\Console\Commands\DeleteTempFile;
use App\Console\Commands\FCM;
use App\Console\Commands\WeeklyPayrollRecap;
use App\Console\Commands\WeeklyAttendanceRecap;
use App\Console\Commands\DailyScheduleRecap;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        $schedule->command(DeleteTempFile::class)->daily();
        $schedule->command(FCM::class)->daily();
        $schedule->command(WeeklyAttendanceRecap::class)->weeklyOn(7, config('filament.tenancy.attendance.recap_time', '00:00'));
        $schedule->command(WeeklyPayrollRecap::class)->weeklyOn(7, config('filament.tenancy.payroll.recap_time', '01:00'));
        $schedule->command(DailyScheduleRecap::class)->dailyAt(config('filament.tenancy.schedule.recap_time', '02:00'));
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
