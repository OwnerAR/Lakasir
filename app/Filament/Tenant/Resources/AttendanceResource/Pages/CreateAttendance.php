<?php

namespace App\Filament\Tenant\Resources\AttendanceResource\Pages;

use App\Filament\Tenant\Resources\AttendanceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendance extends CreateRecord
{
    protected static string $resource = AttendanceResource::class;

    public function getRedirectUrl(): string
    {
        return '/member/attendances';
    }
}
