<div class="filament-widget w-full p-0" style="width: 100% !important; max-width: none !important;">
    <x-filament::section class="p-0 m-0" style="width: 100% !important; max-width: none !important; padding: 0 !important; margin: 0 !important;">
        <div class="w-full" style="width: 100% !important; max-width: none !important;">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium">{{ __('Calendar') }}</h3>
                <div class="flex space-x-2">
                    <button wire:click="prevWeek" class="px-3 py-1 bg-gray-200 rounded text-gray-700 hover:bg-gray-300">
                        &larr; {{ __('Previous Week') }}
                    </button>
                    
                    <span class="px-3 py-1 bg-blue-100 rounded text-blue-800 font-medium">
                        {{ $weekStart->format('M d') }} - {{ $weekEnd->format('M d, Y') }}
                    </span>
                    
                    <button wire:click="nextWeek" class="px-3 py-1 bg-gray-200 rounded text-gray-700 hover:bg-gray-300">
                        {{ __('Next Week') }} &rarr;
                    </button>
                </div>
            </div>

            <div class="w-full overflow-x-auto" style="width: 100% !important;">
                <table class="w-full table-fixed divide-y divide-gray-200 border-collapse" style="width: 100% !important;">
                    <thead>
                        <tr>
                            @for ($day = 0; $day <= 6; $day++)
                                @php
                                    $date = $weekStart->copy()->addDays($day);
                                    $isToday = $date->isToday();
                                    $headerClass = $isToday ? 'bg-blue-100' : 'bg-gray-50';
                                @endphp
                                <th class="{{ $headerClass }} px-4 py-2 text-center" style="width: 14.285714%">
                                    <div>{{ __($date->format('D')) }}</div>
                                    <div class="text-xs text-gray-500">{{ $date->format('M d') }}</div>
                                </th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="h-full">
                            @foreach ($scheduleData as $date => $dayData)
                                <td class="border px-4 py-2 align-top h-full">
                                    @foreach ($dayData['shifts'] as $shift)
                                        <div class="mb-3">
                                            <div class="font-medium text-sm text-blue-600">{{ $shift['shift_name'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $shift['shift_time'] }}</div>
                                            <ul class="mt-1 space-y-1">
                                                @foreach ($shift['employees'] as $employee)
                                                    @php
                                                        $statusColor = 'text-gray-700';
                                                        if ($employee['status'] === 'completed') $statusColor = 'text-green-600';
                                                        if ($employee['status'] === 'absent') $statusColor = 'text-red-600';
                                                    @endphp
                                                    <li class="text-sm {{ $statusColor }}">{{ $employee['name'] }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach

                                    @if (count($dayData['shifts']) === 0)
                                        <div class="text-xs text-gray-400">{{ __('No schedules') }}</div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </x-filament::section>
</div>