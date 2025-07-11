<?php

namespace App\Filament\Tenant\Resources\WorkScheduleResource\Pages;

use App\Filament\Tenant\Resources\WorkScheduleResource;
use App\Services\Tenants\WorkScheduleService;
use Carbon\Carbon;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Actions;
use Filament\Resources\Pages\Page;
use App\Traits\HasTranslatableResource;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class GenerateSchedule extends Page implements HasForms
{
    use HasTranslatableResource;
    use InteractsWithForms;
    
    public ?array $data = [];
    
    protected static string $resource = WorkScheduleResource::class;
    protected static ?string $navigationLabel = 'Generate Work Schedule';
    protected static string $view = 'filament.tenant.resources.work-schedule-resource.pages.generate-schedule';
    
    public function mount(): void
    {
        $this->form->fill();
    }
    
    public function form(Form $form): Form
    {
        return $form->schema($this->getFormSchema())
            ->statePath('data');
    }
    
    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('start_date')
                ->label('Start Date')
                ->required()
                ->native(false)
                ->format('Y-m-d')
                ->displayFormat('d F Y')
                ->minDate(now())
                ->default(now())
                ->columnSpan('full'),
            
            TextInput::make('weeks')
                ->label('Weeks to Generate')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(12)
                ->default(1),
        ];
    }
    
    public function generate()
    {
        $data = $this->form->getState();
        
        $startDate = Carbon::parse($data['start_date']);
        $weeks = (int) $data['weeks'];
        
        $scheduleService = app(WorkScheduleService::class);
        $result = $scheduleService->generateSchedule($startDate, $weeks);
        
        if ($result) {
            Notification::make()
                ->title('Success')
                ->body('Schedule generated successfully for ' . $weeks . ' weeks.')
                ->success()
                ->send();
                
            $this->redirect(WorkScheduleResource::getUrl());
        } else {
            Notification::make()
                ->title('Error')
                ->body('Failed to generate schedule. Make sure you have at least 2 employees and 2 shifts.')
                ->danger()
                ->send();
        }
    }
}