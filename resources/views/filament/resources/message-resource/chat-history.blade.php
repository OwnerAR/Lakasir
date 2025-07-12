<div class="flex flex-col space-y-4 p-4 bg-gray-50 rounded-lg">
    @foreach($messages as $message)
        <div class="flex {{ $message->direction === 'outbound' ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-lg {{ $message->direction === 'outbound' ? 'bg-primary-100 text-primary-900' : 'bg-white' }} rounded-lg shadow p-4">
                <div class="flex items-start">
                    <div class="flex-1">
                        <p class="text-sm font-medium {{ $message->direction === 'outbound' ? 'text-primary-900' : 'text-gray-900' }}">
                            {{ $message->direction === 'outbound' ? $message->agent->name : $message->customer_name }}
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
                            <x-heroicon-o-document class="w-5 h-5 text-gray-400"/>
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
</div> 