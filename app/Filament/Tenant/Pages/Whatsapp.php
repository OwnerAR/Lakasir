<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use App\Traits\HasTranslatableResource;

class Whatsapp extends Page                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       
{
    use HasTranslatableResource;
    protected static ?string $label = 'Whatsapp';
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string $view = 'filament.tenant.pages.whatsapp';
}