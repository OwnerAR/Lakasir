<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\AttendanceResource\Pages;
use App\Filament\Tenant\Resources\AttendanceResource\RelationManagers;
use App\Traits\HasTranslatableResource;
use App\Models\Tenants\Attendance;
use App\Models\Tenants\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
// text input column
use Filament\Forms\Components\TextInputColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AttendanceResource extends Resource
{
    use HasTranslatableResource;

    protected static ?string $model = Attendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('employee_id')
                    ->label(__('Employee ID'))
                    ->required()
                    ->translateLabel(),
                DatePicker::make('date')
                    ->label(__('Date'))
                    ->required()
                    ->translateLabel(),
                TimePicker::make('clock_in')
                    ->label(__('Clock In'))
                    ->translateLabel()
                    ->time(),
                TimePicker::make('clock_out')
                    ->label(__('Clock Out'))
                    ->translateLabel()
                    ->time(),
                Select::make('status')
                    ->label(__('Status'))
                    ->options([
                        'present' => __('Present'),
                        'absent' => __('Absent'),
                        'late' => __('Late'),
                    ])
                    ->default('present')
                    ->required()
                    ->translateLabel(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.name')
                    ->label(__('Employee Name'))
                    ->searchable()
                    ->translateLabel()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->searchable()
                    ->translateLabel()
                    ->sortable(),
                Tables\Columns\TextColumn::make('clock_in')
                    ->time()
                    ->searchable()
                    ->translateLabel()
                    ->sortable(),
                Tables\Columns\TextColumn::make('clock_out')
                    ->time()
                    ->searchable()
                    ->translateLabel()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable()
                    ->translateLabel()
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'present' => 'success',
                        'absent' => 'danger',
                        'late' => 'warning',
                        default => 'secondary',
                    })
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->translateLabel()
                    ->sortable(),
            ])
            ->filters([
                // Add any filters you need here
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'present' => __('Present'),
                        'absent' => __('Absent'),
                        'leave' => __('Leave'),
                    ]),
            ])->defaultSort('created_at', 'desc')
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
            'index' => Pages\ListAttendances::route('/'),
            'create' => Pages\CreateAttendance::route('/create'),
            'edit' => Pages\EditAttendance::route('/{record}/edit'),
        ];
    }
}
