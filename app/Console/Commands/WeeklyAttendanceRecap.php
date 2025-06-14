<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\Employee;
use App\Models\Tenants\Attendance;
use App\Models\Tenants\Payroll;
use Carbon\Carbon;

class WeeklyAttendanceRecap extends Command
{
    protected $signature = 'attendance:weekly-recap';
    protected $description = 'Rekap attendance mingguan berdasarkan kehadiran karyawan';

    public function handle()
    {
        try {
            $previousWeek = Carbon::now()->subWeek();
            $startOfWeek = $previousWeek->copy()->startOfWeek(Carbon::SUNDAY); 
            $endOfWeek = $previousWeek->copy()->endOfWeek(Carbon::SATURDAY);

            $this->info("Periode: {$startOfWeek->format('d M Y')} - {$endOfWeek->format('d M Y')}");

            $employees = Employee::where('is_active', true)->get();
            $processedCount = 0;
            $skipCount = 0;
            $errorCount = 0;

            foreach ($employees as $employee) {
                $existingPayroll = Payroll::where('employee_id', $employee->id)
                        ->where('period', $startOfWeek->toDateString())
                        ->first();
                        
                if ($existingPayroll) {
                    $this->warn("Payroll untuk {$employee->name} pada periode ini sudah ada. Dilewati.");
                    $skipCount++;
                    continue;
                }
                $workDays = Attendance::where('employee_id', $employee->id)
                        ->whereBetween('date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
                        ->whereIn('status', ['present', 'late'])
                        ->count();

                if ($workDays > 0) {
                    $salaryPerDay = $employee->salary / 29;
                    $amount = $workDays * $salaryPerDay;

                    try {
                        Payroll::create([
                            'employee_id' => $employee->id,
                            'amount' => round($amount, 2),
                            'period' => $startOfWeek->toDateString(),
                            'status' => 'unpaid',
                        ]);
                        // update attendance status
                        Attendance::where('employee_id', $employee->id)
                                    ->whereBetween('date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
                                    ->whereIn('status', ['present', 'late'])
                                    ->update(['status' => 'processed']);
                                
                        $this->info("Payroll untuk {$employee->name} dibuat: {$workDays} hari kerja, Rp " . 
                            number_format(round($amount, 2), 2, ',', '.'));
                        $processedCount++;
                    } catch (\Exception $e) {
                        $this->error("Gagal membuat payroll untuk {$employee->name}: " . $e->getMessage());
                        $errorCount++;
                    }
                } else {
                    $this->warn("Tidak ada kehadiran untuk {$employee->name} pada periode ini. Dilewati.");
                    $skipCount++;
                }
            }
            $this->info("Total karyawan diproses: {$processedCount}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Terjadi kesalahan: " . $e->getMessage());
            return 1;
        }

        $this->info('Rekap attendance mingguan berhasil.');
    }
}