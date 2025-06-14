<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants\WorkSchedule;
use Carbon\Carbon;
use App\Services\WhatsappService;
use Illuminate\Support\Facades\Log;

class DailyScheduleRecap extends Command
{
    protected $signature = 'schedule:daily-recap';
    protected $description = 'Rekap jadwal kerja harian berdasarkan data yang ada';
    
    public function __construct(protected WhatsappService $whatsappService)
    {
        parent::__construct();
    }
    
    public function handle()
    {
        try {
            $this->info('Mulai merekap jadwal kerja harian...');
            $today = now()->toDateString();
            
            // Eager load relasi untuk mengurangi jumlah query
            $schedules = WorkSchedule::with(['employee', 'shift'])
                ->whereDate('date', $today)
                ->get();

            if ($schedules->isEmpty()) {
                $this->info('Tidak ada jadwal kerja yang perlu direkap hari ini.');
                return;
            }

            // Buat satu pesan rangkuman untuk semua jadwal
            $message = "*JADWAL KERJA HARIAN*\n";
            $message .= "Tanggal: " . Carbon::parse($today)->format('d M Y') . "\n\n";
            
            // Kelompokkan jadwal berdasarkan shift
            $schedulesByShift = $schedules->groupBy('shift.name');
            
            foreach ($schedulesByShift as $shiftName => $shiftSchedules) {
                // Jika shift name adalah null, gunakan "Tidak Ada Shift"
                $displayShiftName = $shiftName ?? "Tidak Ada Shift";
                $message .= "*Shift: {$displayShiftName}*\n";
                
                // Tambahkan informasi waktu jika tersedia
                $firstSchedule = $shiftSchedules->first();
                if ($firstSchedule->shift) {
                    $message .= "Waktu: {$firstSchedule->shift->start_time} - {$firstSchedule->shift->end_time}\n";
                }
                
                $message .= "Karyawan:\n";
                foreach ($shiftSchedules as $schedule) {
                    if ($schedule->employee) {
                        $message .= "- {$schedule->employee->name}\n";
                    }
                }
                $message .= "\n";
            }
            
            // Tambahkan pesan penutup
            $message .= "Harap semua karyawan datang tepat waktu sesuai jadwal. Terima kasih.";
            
            // Kirim pesan rangkuman ke grup WhatsApp
            $this->info("Mengirim rekap jadwal ke grup WhatsApp...");
            $this->whatsappService->sendMessage(config('app.whatsapp_id'), $message);
            $this->info("Berhasil mengirim rekap jadwal ke grup WhatsApp.");
            
        } catch (\Exception $e) {
            $this->error('Terjadi kesalahan: ' . $e->getMessage());
            Log::error('Error in daily schedule recap: ' . $e->getMessage());
        }
    }
}