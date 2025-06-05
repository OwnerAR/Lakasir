<?php

namespace App\Filament\Tenant\Resources\PayrollResource\Pages;

use App\Filament\Tenant\Resources\PayrollResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePayroll extends CreateRecord
{
    protected static string $resource = PayrollResource::class;

    public function getRedirectUrl(): string
    {
        return '/member/payrolls';
    }
}
