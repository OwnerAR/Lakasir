<?php

namespace App\Filament\Tenant\Resources\OmniChannel\MessageResource\Pages;

use App\Filament\Tenant\Resources\MessageResource;
use Filament\Resources\Pages\ListRecords;

class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;
}
