<?php
namespace App\Livewire\Tenant;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use App\Services\WhatsappService;

class WhatsappQrWidget extends Component
{
    public $qr = null;

    public function mount()
    {
        $this->fetchQr();
    }

    public function fetchQr()
    {
        $response = WhatsappService::getStatus();
        dd($response);
        if ($response['status'] === 'success') {
            $this->qr = $response['data']['qr'];
        }
    }

    public function render()
    {
        return view('livewire.tenant.whatsapp-qr-widget');
    }
}