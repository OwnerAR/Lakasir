<?php

namespace App\Filament\Tenant\Resources\IntegrasiAPIResource\Pages;

use App\Filament\Tenant\Resources\IntegrasiAPIResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateIntegrasiAPI extends CreateRecord
{
    protected static string $resource = IntegrasiAPIResource::class;

    public function getRedirectUrl(): string
    {
        return '/member/integrasi-a-p-is';
    }
}
