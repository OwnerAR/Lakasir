<?php
namespace App\Livewire\Tenant;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use App\Services\WhatsappService;

class WhatsappQrWidget extends Component
{
    public $status = null;
    public $qr = null;

    public function mount()
    {
        $this->fetchQr();
    }

    public function fetchQr()
    {
        $service = new WhatsappService();
        $response = $service->getStatus();
        $this->status = $response['status'] ?? null;
        if ($response['qrCode'] ?? false) {
            $this->qr = $response['qrCode'];
        }
    }

    public function render()
    {
        return view('livewire.tenant.whatsapp-qr-widget');
    }
}