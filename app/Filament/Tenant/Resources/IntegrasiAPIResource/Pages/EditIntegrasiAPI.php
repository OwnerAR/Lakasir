<?php

namespace App\Filament\Tenant\Resources\IntegrasiAPIResource\Pages;

use App\Filament\Tenant\Resources\IntegrasiAPIResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIntegrasiAPI extends EditRecord
{
    protected static string $resource = IntegrasiAPIResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
