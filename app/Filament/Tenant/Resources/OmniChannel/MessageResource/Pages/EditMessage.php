<?php

namespace App\Filament\Tenant\Resources\OmniChannel\MessageResource\Pages;

use App\Filament\Tenant\Resources\MessageResource;
use Filament\Resources\Pages\EditRecord;

class EditMessage extends EditRecord
{
    protected static string $resource = MessageResource::class;
    protected static ?string $navigationLabel = 'Live Chat';   

    public function getTitle(): string 
    {
        return 'Live Chat';
    }
}
