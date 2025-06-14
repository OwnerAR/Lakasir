<x-filament::page>
    <x-filament::card>        
        <p class="mt-2 text-sm text-gray-500">
            {{ __('Generate work schedules for employees with rolling shifts each week. The system will ensure employees have adequate rest time between shifts.') }}
        </p>
        
        <div class="mt-4">
            <ul class="list-disc pl-5 text-sm text-gray-600">
                <li class="mb-1">{{ __('Employees will be divided into shift groups') }}</li>
                <li class="mb-1">{{ __('Each week, groups will rotate between shifts') }}</li>
                <li class="mb-1">{{ __('The system enforces 12+ hours rest between shifts') }}</li>
                <li class="mb-1">{{ __('Generated schedules can be manually adjusted afterward') }}</li>
            </ul>
        </div>
    </x-filament::card>

    <div class="mt-8">
        <form wire:submit.prevent="generate">
            {{ $this->form }}
            
            <div class="mt-4 flex justify-end">
                <x-filament::button 
                    type="submit" 
                    color="primary"
                >
                    {{ __('Generate Schedule') }}
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament::page>