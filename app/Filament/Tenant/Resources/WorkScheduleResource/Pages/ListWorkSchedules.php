<?php

namespace App\Filament\Tenant\Resources\WorkScheduleResource\Pages;

use App\Filament\Tenant\Resources\WorkScheduleResource;
use App\Filament\Tenant\Resources\WorkScheduleResource\Widgets\ScheduleCalendar;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;


class ListWorkSchedules extends ListRecords
{
    protected static string $resource = WorkScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
