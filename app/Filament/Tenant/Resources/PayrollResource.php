<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PayrollResource\Pages;
use App\Filament\Tenant\Resources\PayrollResource\RelationManagers;
use App\Models\Tenants\Payroll;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PayrollResource extends Resource
{
    protected static ?string $model = Payroll::class;
    protected static ?string $label = 'Payroll';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('employee_id')
                    ->label(__('Employee ID'))
                    ->required()
                    ->translateLabel(),
                Forms\Components\TextInput::make('amount')
                    ->label(__('Amount'))
                    ->required()
                    ->translateLabel(),
                Forms\Components\TextInput::make('date')
                    ->label(__('Date'))
                    ->required()
                    ->translateLabel(),
                Textarea::make('note')
                    ->label(__('Note'))
                    ->translateLabel(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_id')
                    ->searchable()
                    ->translateLabel()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->searchable()
                    ->translateLabel()
                    ->sortable(),
                Tables\Columns\TextColumn::make('period')
                    ->date()
                    ->searchable()
                    ->translateLabel()
                    ->sortable(),
                TextColumn::make('note')
                    ->translateLabel()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('period')
                    ->form([
                        Forms\Components\DatePicker::make('period')
                            ->label(__('Select Period'))
                            ->required()
                            ->translateLabel(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->whereDate('period', $data['period']);
                    }),
                    Tables\Filters\Filter::make('employee_id')
                    ->form([
                        Forms\Components\TextInput::make('employee_id')
                            ->label(__('Employee ID'))
                            ->required()
                            ->translateLabel(),
                    ]),
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
            'index' => Pages\ListPayrolls::route('/'),
            'create' => Pages\CreatePayroll::route('/create'),
            'edit' => Pages\EditPayroll::route('/{record}/edit'),
        ];
    }
}
