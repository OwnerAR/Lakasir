{{-- filepath: resources/views/livewire/tenant/whatsapp-qr-widget.blade.php --}}
<div class="flex flex-col items-center justify-center py-6">
    @if($qr)
        <img src="data:image/png;base64,{{ $qr }}" alt="WhatsApp QR" class="w-64 h-64" />
        <div class="mt-2 text-center text-sm text-gray-500">Scan QR dengan WhatsApp Anda</div>
    @else
        <div class="text-center text-gray-500">Memuat QR code...</div>
    @endif
    <button wire:click="fetchQr" class="mt-4 px-4 py-2 bg-blue-500 text-white rounded">Refresh QR</button>
</div>