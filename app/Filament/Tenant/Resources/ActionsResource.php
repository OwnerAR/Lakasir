<?php
namespace App\Filament\Tenant\Resources;

use Filament\Actions\Action;

class ActionsResource extends \Filament\Resources\Resource
{
    protected static ?string $model = \App\Models\Actions::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected function getActions(): array
    {
        return [
            Action::make('editSchedule')
                ->label('Edit Jadwal')
                ->mountUsing(fn (Action $action, array $arguments) => 
                    $action->form->fill([
                        'employeeId' => $arguments['employeeId'],
                        'shiftId' => $arguments['shiftId'],
                    ])
                )
                ->form([
                    Select::make('employeeId')->label('Employee')
                        ->options(Employee::where('is_active', true)->pluck('name', 'id')),
                    Select::make('shiftId')->label('Shift')
                        ->options(Shift::pluck('name', 'id')),
                ])
                ->action(function (array $data) {
                    // Logic untuk save schedule
                })
        ];
    }
}