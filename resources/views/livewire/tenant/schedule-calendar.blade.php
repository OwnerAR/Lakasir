<div>
    @if (session()->has('message'))
        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">
            {{ session('message') }}
        </div>
    @endif
    
    @if (session()->has('error'))
        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg">
            {{ session('error') }}
        </div>
    @endif

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

    <div class="w-full overflow-x-auto">
        <table class="w-full table-fixed divide-y divide-gray-200 border-collapse">
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
                            @if (isset($dayData['shifts']) && is_array($dayData['shifts']) && count($dayData['shifts']) > 0)
                                @foreach ($dayData['shifts'] as $shift)
                                    <div class="mb-3">
                                        <div class="font-medium text-sm {{ $shift['off'] ? 'text-red-600' : 'text-blue-600' }}">{{ $shift['shift_name'] }}</div>
                                        <div class="text-xs text-gray-500">{{ $shift['shift_time'] }}</div>
                                        
                                        <ul class="mt-1 space-y-1">
                                            @if(isset($shift['employees']) && count($shift['employees']) > 0)
                                                @foreach($shift['employees'] as $employee)
                                                    <li class="flex items-center">
                                                        <button 
                                                            wire:click="openEditModal('{{ $date }}', {{ $employee['id'] }}, {{ $shift['shift_id'] }})"
                                                            class="text-sm hover:underline"
                                                        >
                                                            {{ $employee['name'] }}
                                                        </button>
                                                    </li>
                                                @endforeach
                                            @endif
                                            
                                            <li>
                                                <button 
                                                    wire:click="openEditModal('{{ $date }}', null, {{ $shift['shift_id'] }})"
                                                    class="text-xs text-gray-500 hover:underline"
                                                >
                                                    + Add employee
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-xs text-gray-400">{{ __('No schedules') }}</div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>

    <div 
        x-data="{ show: @entangle('isModalOpen') }"
        x-show="show" 
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none"
    >
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
            
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all max-w-lg w-full">
                <div class="px-6 py-4">
                    <div class="text-lg font-bold mb-4">{{ __('Edit Schedule') }}</div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Employee') }}</label>
                            <select wire:model="selectedEmployeeId" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                                <option value="">{{ __('Select Employee') }}</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                            @error('selectedEmployeeId') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">{{ __('Shift') }}</label>
                            <select wire:model="selectedShiftId" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 rounded-md">
                                <option value="">{{ __('Select Shift') }}</option>
                                @foreach($shifts as $shift)
                                    <option value="{{ $shift->id }}">{{ $shift->name }} ({{ $shift->start_time }} - {{ $shift->end_time }})</option>
                                @endforeach
                            </select>
                            @error('selectedShiftId') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
                
                <div class="px-6 py-4 bg-gray-50 text-right">
                    <button wire:click="closeModal" type="button" class="py-2 px-4 border border-gray-300 rounded-md text-sm leading-5 font-medium text-gray-700 hover:text-gray-500 focus:outline-none focus:border-blue-300 focus:shadow-outline-blue active:bg-gray-50 active:text-gray-800">
                        {{ __('Cancel') }}
                    </button>
                    <button wire:click="saveSchedule" type="button" class="ml-2 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-lakasir-primary hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        {{ __('Save') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>