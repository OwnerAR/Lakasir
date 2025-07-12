@php
    $whatsappNumber = $getRecord()->whatsapp_number ?? null;
    $messages = \App\Models\Tenants\OmniChannel\Message::query()
        ->when($whatsappNumber, function ($query) use ($whatsappNumber) {
            $query->where('whatsapp_number', $whatsappNumber);
        })
        ->orderBy('created_at', 'desc')
        ->get();
@endphp

<div class="flex flex-col space-y-4 p-4 bg-gray-50 rounded-lg">
    @if($messages->isEmpty())
        <div class="text-center text-gray-500 py-4">
            No messages yet
        </div>
    @else
        @foreach($messages as $message)
            <div class="flex {{ $message->direction === 'outbound' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-lg {{ $message->direction === 'outbound' ? 'bg-primary-100 text-primary-900' : 'bg-white' }} rounded-lg shadow p-4">
                    <div class="flex items-start">
                        <div class="flex-1">
                            <p class="text-sm font-medium {{ $message->direction === 'outbound' ? 'text-primary-900' : 'text-gray-900' }}">
                                {{ $message->direction === 'outbound' ? ($message->agent?->name ?? 'System') : ($message->customer_name ?? 'Customer') }}
                            </p>
                            <p class="text-xs text-gray-500">{{ $message->created_at->format('M j, Y g:i A') }}</p>
                        </div>
                    </div>
                    
                    <div class="mt-2">
                        @if($message->message_type === 'text')
                            <p class="text-sm whitespace-pre-wrap">{{ $message->message }}</p>
                        @elseif($message->message_type === 'image')
                            <img src="{{ Storage::url($message->media_url) }}" alt="Image" class="max-w-full rounded">
                            @if($message->message)
                                <p class="text-sm mt-2 whitespace-pre-wrap">{{ $message->message }}</p>
                            @endif
                        @elseif($message->message_type === 'file')
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <a href="{{ Storage::url($message->media_url) }}" target="_blank" class="text-sm text-primary-600 hover:text-primary-800">
                                    Download Attachment
                                </a>
                            </div>
                            @if($message->message)
                                <p class="text-sm mt-2 whitespace-pre-wrap">{{ $message->message }}</p>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div> 