<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\PayrollResource\Pages;
use App\Filament\Tenant\Resources\PayrollResource\RelationManagers;
use App\Models\Tenants\Payroll;
use App\Models\Tenants\Employee;
use App\Models\Tenants\Setting;
use App\Services\TigaPutriService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TextInputColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Filament\Notifications\Notification;

class PayrollResource extends Resource
{
    protected static ?string $model = Payroll::class;
    protected static ?string $label = 'Payroll';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('employee_id')
                    ->label(__('Employee'))
                    ->required()
                    ->translateLabel()
                    ->options(Employee::all()->pluck('name', 'id')),
                TextInput::make('amount')
                    ->label(__('Amount'))
                    ->required()
                    ->translateLabel(),
                DatePicker::make('period')
                    ->label(__('Period'))
                    ->required()
                    ->translateLabel(),
                Select::make('status')
                    ->label(__('Status'))
                    ->required()
                    ->options([
                        'paid' => __('Paid'),
                        'unpaid' => __('Unpaid'),
                    ])
                    ->translateLabel(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.name')
                    ->label(__('Employee'))
                    ->translateLabel()
                    ->sortable(),
                TextColumn::make('amount')
                    ->searchable()
                    ->money(Setting::get('currency', 'IDR'))
                    ->translateLabel()
                    ->sortable(),
                TextColumn::make('period')
                    ->date()
                    ->searchable()
                    ->translateLabel()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->translateLabel()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'paid' => __('Paid'),
                        'unpaid' => __('Unpaid'),
                        default => __('Unknown'),
                    })
                    ->color(fn ($state) => match ($state) {
                        'paid' => 'success',
                        'unpaid' => 'danger',
                        default => 'secondary',
                    })
                    ->sortable(),
            ])
            ->filters([
                Filter::make('period')
                    ->form([
                        Forms\Components\DatePicker::make('period')
                            ->label(__('Select Period'))
                            ->required()
                            ->translateLabel(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['period'])) {
                            return $query->whereDate('period', $data['period']);
                        }
                        return $query;
                    }),
                SelectFilter::make('employee_id')
                    ->label(__('Employee'))
                    ->searchable()
                    ->options(fn () => Employee::pluck('name', 'id')->toArray())
                    ->translateLabel(),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options([
                        'paid' => __('Paid'),
                        'unpaid' => __('Unpaid'),
                    ])
                    ->translateLabel(),
            ])
            ->actions([
                Action::make('send')
                    ->label(__('Send'))
                    ->icon('heroicon-o-paper-airplane')
                    ->action(function (Payroll $record) {
                        try {
                            $amountParse = str_replace('.00', '', $record->amount);
                            $service = new TigaPutriService();
                            if ($record->status !== 'unpaid') {
                                Notification::make()
                                    ->danger()
                                    ->title(__('Error'))
                                    ->body(__('Payroll is already paid.'))
                                    ->send();
                                return;
                            }
                            $response = $service->commandNonTransaction(
                                'TS.' . $record->employee->employee_id . '.' . $amountParse,
                                Carbon::now()->format('His'),
                            );
                            if ($response) {
                                Notification::make()
                                    ->success()
                                    ->title(__('Success'))
                                    ->body(__('Payroll sent successfully.'))
                                    ->send();
                                $record->update(['status' => 'paid']);
                            } else {
                                Notification::make()
                                    ->danger()
                                    ->title(__('Error'))
                                    ->body(__('Failed to send payroll.'))
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title(__('Error'))
                                ->body(__('An error occurred while sending payroll: :message', ['message' => $e->getMessage()]))
                                ->send();
                        }
                    })
                    ->translateLabel(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    BulkAction::make('markAsPaid')
                        ->label(__('Mark as Paid'))
                        ->action(function (Collection $records) {
                            $records->each(fn ($record) => $record->update(['status' => 'paid']));
                        })
                        ->requiresConfirmation()
                        ->icon('heroicon-o-check-circle'),
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
