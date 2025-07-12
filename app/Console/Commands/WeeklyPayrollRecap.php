<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\Payroll;
use App\Models\Tenants\Attendance;
use App\Services\TigaPutriService;
use App\Services\WhatsappService;
use Carbon\Carbon;

class WeeklyPayrollRecap extends Command
{
    protected $signature = 'payroll:weekly-recap';
    protected $description = 'Rekap payroll mingguan berdasarkan attendance';

    public function __construct(
        protected TigaPutriService $tigaPutriService, 
        protected WhatsappService $whatsappService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $service = $this->tigaPutriService;
            $this->info('Mulai merekap payroll mingguan...');
            $payrolls = Payroll::where('status', 'unpaid')->get();
            if ($payrolls->isEmpty()) {
                $this->info('Tidak ada payroll yang perlu direkap.');
                return;
            }
            foreach ($payrolls as $payroll) {
                $amountParse = ceil($payroll->amount);
                if ($payroll->status != 'unpaid') {
                    $this->warn("Payroll untuk karyawan {$payroll->employee->name} sudah terbayar.");
                    continue;
                }
                $response = $service->commandNonTransaction(
                    'TS.' . $payroll->employee->employee_id . '.' . $amountParse,
                    Carbon::now()->format('His'),
                );
                if ($response) {
                    $payroll->update(['status' => 'paid']);
                    $this->info("Payroll untuk karyawan {$payroll->employee->name} berhasil di kirim.");
                    Attendance::where('employee_id', $payroll->employee->id)
                        ->where('status', 'processed')
                        ->update(['status' => 'paid']);
                    
                    // Kirim notifikasi WhatsApp
                    $NotificationService = $this->whatsappService;
                    $message = "Payroll Anda telah berhasil direkap. Jumlah: Rp" . number_format($payroll->amount, 2, ',', '.')."\n\n".$payroll->note;
                    $NotificationService->sendMessage($payroll->employee->whatsapp_id, $message);
                } else {
                    $this->error("Gagal merekap payroll untuk karyawan {$payroll->employee->name}.");
                    // Kirim notifikasi WhatsApp jika gagal
                    $NotificationService = $this->whatsappService;
                    $message = "Gagal merekap payroll Anda. Silakan hubungi admin.";
                    $NotificationService->sendMessage($payroll->employee->whatsapp_id, $message);
                }

            }
        } catch (\Exception $e) {
            $this->error('Terjadi kesalahan: ' . $e->getMessage());
            return;
        }


        $this->info('Payroll mingguan berhasil direkap.');
    }
}