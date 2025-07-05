<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\OmniChannel\TicketResource\Pages;
use App\Models\Tenants\OmniChannel\Ticket;
use Filament\Resources\Resource;
use App\Traits\HasTranslatableResource;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;

class TicketResource extends Resource
{
    use HasTranslatableResource;
    protected static ?string $model = Ticket::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Omni Channel';
    protected static ?string $navigationLabel = 'Tickets';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_id')->required(),
                Forms\Components\TextInput::make('agent_id'),
                Forms\Components\Select::make('status')
                    ->options([
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ])->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user_id'),
                Tables\Columns\TextColumn::make('agent_id'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                // Tambahkan filter jika perlu
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
        ];
    }
}
