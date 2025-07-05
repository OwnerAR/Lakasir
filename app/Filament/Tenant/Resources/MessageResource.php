<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\OmniChannel\MessageResource\Pages;
use App\Models\Tenants\OmniChannel\Message;
use Filament\Resources\Resource;
use App\Traits\HasTranslatableResource;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;

class MessageResource extends Resource
{
    use HasTranslatableResource;
    protected static ?string $model = Message::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Omni Channel';
    protected static ?string $navigationLabel = 'Messages';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('ticket_id')->required(),
                Forms\Components\TextInput::make('sender_type')->required(),
                Forms\Components\TextInput::make('sender_id')->required(),
                Forms\Components\Textarea::make('message')->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('ticket_id'),
                Tables\Columns\TextColumn::make('sender_type'),
                Tables\Columns\TextColumn::make('sender_id'),
                Tables\Columns\TextColumn::make('message')->limit(50),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                // Tambahkan filter jika perlu
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessages::route('/'),
            'create' => Pages\CreateMessage::route('/create'),
            'edit' => Pages\EditMessage::route('/{record}/edit'),
        ];
    }
}
