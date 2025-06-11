<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\Employee;
use App\Models\Tenants\Attendance;
use App\Models\Tenants\Payroll;
use Carbon\Carbon;

class WeeklyPayrollRecap extends Command
{
    protected $signature = 'payroll:weekly-recap';
    protected $description = 'Rekap payroll mingguan berdasarkan attendance';

    public function handle()
    {
        $startOfWeek = Carbon::now()->startOfWeek(Carbon::SUNDAY); // hari Minggu
        $endOfWeek = Carbon::now()->endOfWeek(Carbon::SATURDAY);     // hari Sabtu

        $employees = Employee::where('is_active', true)->get();

        foreach ($employees as $employee) {
            $workDays = Attendance::where('employee_id', $employee->id)
                ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                ->count();

            if ($workDays > 0) {
                $salaryPerDay = $employee->salary / 29;
                $amount = $workDays * $salaryPerDay;

                Payroll::create([
                    'employee_id' => $employee->id,
                    'amount' => round($amount, 2),
                    'period' => $startOfWeek->toDateString(),
                    'status' => 'unpaid',
                ]);
            }
        }

        $this->info('Payroll mingguan berhasil direkap.');
    }
}