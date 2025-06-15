<?php
namespace App\Filament\Tenant\Resources\WorkScheduleResource\Pages;

use App\Filament\Tenant\Resources\WorkScheduleResource;
use Filament\Resources\Pages\Page;
use App\Traits\HasTranslatableResource;

class CalendarWorkSchedule extends Page
{
    use HasTranslatableResource;
    protected static string $resource = WorkScheduleResource::class;
    protected static string $view = 'filament.tenant.resources.work-schedule-resource.pages.calendar';
}