@php
    $qrImage = null;
    if (!empty($qr ?? null)) {
        $qrImage = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(256)->generate($qr);
    }
@endphp
<div class="flex flex-col items-center justify-center py-6">
    @if($status === 'ready')
        <div class="text-green-600 font-bold text-lg mb-4">Status WhatsApp siap dipakai</div>
    @elseif($qrImage)
        {!! $qrImage !!}
        <div class="mt-2 text-center text-sm text-gray-500">Scan QR dengan WhatsApp Anda</div>
    @else
        <div class="text-center text-gray-500">Memuat QR code...</div>
    @endif
</div>