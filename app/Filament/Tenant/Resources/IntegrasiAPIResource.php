<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\IntegrasiAPIResource\Pages;
use App\Filament\Tenant\Resources\IntegrasiAPIResource\RelationManagers;
use App\Models\Tenants\IntegrasiAPI;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class IntegrasiAPIResource extends Resource
{
    protected static ?string $model = IntegrasiAPI::class;
    protected static ?string $label = 'Integrasi API';

    protected static ?string $navigationIcon = 'heroicon-o-code-bracket';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->translateLabel(),
                Forms\Components\TextInput::make('base_url')
                    ->required()
                    ->translateLabel(),
                Forms\Components\TextInput::make('username')
                    ->required()
                    ->translateLabel(),
                Forms\Components\TextInput::make('password')
                    ->required()
                    ->password()
                    ->translateLabel(),
                Forms\Components\TextInput::make('pin')
                    ->required()
                    ->password()
                    ->translateLabel(),
                Forms\Components\Select::make('type')
                    ->required()
                    ->options([
                        1 => 'TigaPutri',
                        2 => 'OtomaX',
                    ])
                    ->translateLabel(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->translateLabel(),
                Tables\Columns\TextColumn::make('base_url')
                    ->translateLabel(),
                Tables\Columns\TextColumn::make('username')
                    ->translateLabel(),
                Tables\Columns\TextColumn::make('type')
                    ->translateLabel()
                    ->formatStateUsing(fn ($state) => [
                        1 => 'TigaPutri',
                        2 => 'OtomaX',
                    ][$state] ?? '-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        1 => 'TigaPutri',
                        2 => 'OtomaX',
                    ])
                    ->translateLabel(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIntegrasiAPIS::route('/'),
            'create' => Pages\CreateIntegrasiAPI::route('/create'),
            'edit' => Pages\EditIntegrasiAPI::route('/{record}/edit'),
        ];
    }
}
