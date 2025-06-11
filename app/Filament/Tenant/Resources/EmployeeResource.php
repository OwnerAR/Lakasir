<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\EmployeeResource\Pages;
use App\Filament\Tenant\Resources\EmployeeResource\RelationManagers;
use App\Models\Tenants\Employee;
use App\Models\Tenants\Setting;
use App\Models\Tenants\UploadedFile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;


class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static ?string $label = 'Employee';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('employee_id')
                    ->label(__('Employee ID'))
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->translateLabel(),
                Forms\Components\TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->translateLabel(),
                Forms\Components\TextInput::make('whatsapp_id')
                    ->label(__('WhatsApp ID'))
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->translateLabel(),
                Forms\Components\TextInput::make('position')
                    ->label(__('Position'))
                    ->required()
                    ->translateLabel(),
                Forms\Components\Select::make('shift')
                    ->label(__('Shift'))
                    ->options([
                        'pagi' => __('Morning'),
                        'sore' => __('Afternoon'),
                    ])
                    ->required()
                    ->translateLabel(),
                Forms\Components\TextInput::make('salary')
                    ->label(__('Salary'))
                    ->required()
                    ->numeric()
                    ->translateLabel(),
                // Upload profile picture
                Forms\Components\FileUpload::make('foto_url')
                    ->label(__('Profile Picture'))
                    ->disk('public')
                    ->directory('employees')
                    ->image()
                    ->maxSize(1024)
                    ->acceptedFileTypes(['image/*'])
                    ->translateLabel()
                    ->nullable(),
                Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true)
                    ->translateLabel(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('ID'))
                    ->sortable()
                    ->searchable()
                    ->translateLabel(),
                Tables\Columns\TextColumn::make('employee_id')
                    ->label(__('Employee ID'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->translateLabel()
                    ->sortable(),
                Tables\Columns\TextColumn::make('whatsapp_id')
                    ->label(__('WhatsApp ID'))
                    ->searchable()
                    ->translateLabel()
                    ->sortable(),
                Tables\Columns\TextColumn::make('position')
                    ->label(__('Position'))
                    ->searchable()
                    ->translateLabel()
                    ->sortable(),
                Tables\Columns\TextColumn::make('shift')
                    ->label(__('Shift'))
                    ->searchable()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pagi' => __('Morning'),
                        'sore' => __('Afternoon'),
                        default => $state,
                    })
                    ->translateLabel()
                    ->sortable(),
                Tables\Columns\TextColumn::make('salary')
                    ->label(__('Salary'))
                    ->money(Setting::get('currency', 'IDR'))
                    ->translateLabel()
                    ->sortable(),
                Tables\Columns\TextColumn::make('foto_url')
                    ->label(__('Profile Picture'))
                    ->copyable()
                    ->translateLabel()
                    ->formatStateUsing(fn ($state) => $state ?: '-'),
                ToggleColumn::make('is_active')
                    ->label(__('Active'))
                    ->translateLabel()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', true))
                    ->label(__('Active')),
                Tables\Filters\Filter::make('inactive')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', false))
                    ->label(__('Inactive')),
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
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
