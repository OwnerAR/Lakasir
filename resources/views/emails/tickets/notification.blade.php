@component('mail::message')
# {{ $greeting }}

@foreach($lines as $line)
{{ $line }}

@endforeach

@if($actionText && $actionUrl)
@component('mail::button', ['url' => $actionUrl])
{{ $actionText }}
@endcomponent
@endif

@if($footerText)
---
{{ $footerText }}
@endif

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent 