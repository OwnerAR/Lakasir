<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ReturnSellingResource\Pages;
use App\Models\Tenants\ReturnSelling;
use App\Traits\HasTranslatableResource;
use Filament\Resources\Resource;

class ReturnSellingResource extends Resource
{
    use HasTranslatableResource;

    protected static ?string $model = ReturnSelling::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-left';
    protected static ?string $navigationGroup = 'Selling';
}