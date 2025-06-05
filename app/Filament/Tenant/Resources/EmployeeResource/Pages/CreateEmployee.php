<?php

namespace App\Filament\Tenant\Resources\EmployeeResource\Pages;

use App\Filament\Tenant\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getRedirectUrl(): string
    {
        return '/member/employees';
    }
}
