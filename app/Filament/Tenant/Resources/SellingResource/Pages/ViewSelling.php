<?php

namespace App\Filament\Tenant\Resources\SellingResource\Pages;

use App\Features\PrintSellingA5;
use App\Filament\Tenant\Resources\SellingDetailResource\RelationManagers\SellingDetailsRelationManager;
use App\Filament\Tenant\Resources\SellingResource;
use App\Models\Tenants\About;
use App\Models\Tenants\Selling;
use App\Models\Tenants\ReturnSelling;
use App\Models\Tenants\ReturnSellingDetail;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class ViewSelling extends ViewRecord
{
    protected static string $resource = SellingResource::class;

    public ?About $about = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->about = About::first();
    }

    public function getTitle(): string|Htmlable
    {
        return 'View '.$this->getRecord()->code;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make(__('Print invoice'))
                ->icon('heroicon-s-printer')
                ->extraAttributes([
                    'id' => 'printInvoice',
                ])
                ->color(Color::Teal)
                ->visible(can('can print selling') && feature(PrintSellingA5::class)),
            Action::make(__('Print receipt'))
                ->icon('heroicon-s-printer')
                ->extraAttributes([
                    'id' => 'printButton',
                ])
                ->visible(can('can print selling')),
            Action::make(__('Return'))
                ->icon('heroicon-s-arrow-left')
                ->color(Color::Rose)
                ->visible(
                    fn (Selling $record) => 
                        can('can process returns') && 
                        $record->status === 'completed' && 
                        !$record->is_fully_returned
                )
                ->form([
                    Forms\Components\Repeater::make('items')
                        ->schema([
                            Forms\Components\Select::make('selling_detail_id')
                                ->label('Product')
                                ->options(function (Selling $record) {
                                    return $record->sellingDetails->mapWithKeys(function ($detail) {
                                        return [$detail->id => "{$detail->product->name} - {$detail->quantity} x " . 
                                            number_format($detail->price, 0, ',', '.')];
                                    });
                                })
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set, Selling $record) {
                                    $detail = $record->sellingDetails->firstWhere('id', $state);
                                    if ($detail) {
                                        $set('product_id', $detail->product_id);
                                        $set('price', $detail->price);
                                        $set('available_quantity', $detail->quantity);
                                        $set('max_quantity', $detail->quantity);
                                    }
                                }),
                            Forms\Components\Hidden::make('product_id'),
                            Forms\Components\Hidden::make('price'),
                            Forms\Components\TextInput::make('quantity')
                                ->label('Return Quantity')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(function (callable $get) {
                                    return $get('available_quantity');
                                })
                                ->helperText(function (callable $get) {
                                    return 'Available: ' . $get('available_quantity');
                                }),
                            Forms\Components\Select::make('reason')
                                ->label('Return Reason')
                                ->options([
                                    'defective' => 'Product Defective',
                                    'wrong_item' => 'Wrong Item',
                                    'not_as_described' => 'Not As Described',
                                    'customer_changed_mind' => 'Customer Changed Mind',
                                    'other' => 'Other',
                                ])
                                ->required(),
                            Forms\Components\Textarea::make('notes')
                                ->label('Additional Notes')
                                ->rows(2),
                        ])
                        ->columns(2)
                        ->required()
                        ->minItems(1),
                    Forms\Components\Select::make('refund_method')
                        ->label('Refund Method')
                        ->options([
                            'cash' => 'Cash Refund',
                            'store_credit' => 'Store Credit',
                            'replace_item' => 'Replace Item',
                        ])
                        ->required(),
                ])
                ->action(function (array $data, Selling $record) {
                    try {
                        DB::beginTransaction();
                        
                        // Create return transaction
                        $return = $record->returnSellings()->create([
                            'return_number' => 'RTN-' . now()->format('Ymd') . '-' . rand(1000, 9999),
                            'refund_method' => $data['refund_method'] ?? 'cash',
                            'total_amount' => 0,
                            'status' => 'processed',
                            'created_by' => auth()->id(),
                        ]);
                        
                        $totalReturnAmount = 0;
                        
                        // Process each returned item
                        foreach ($data['items'] as $item) {
                            $sellingDetail = $record->sellingDetails()->find($item['selling_detail_id']);
                            
                            if (!$sellingDetail) continue;
                            
                            $subtotal = $item['quantity'] * $item['price'];
                            $totalReturnAmount += $subtotal;
                            
                            // Create return detail
                            ReturnSellingDetail::create([
                                'return_selling_id' => $return->id,
                                'selling_detail_id' => $sellingDetail->id,
                                'product_id' => $item['product_id'],
                                'quantity' => $item['quantity'],
                                'price' => $item['price'],
                                'subtotal' => $subtotal,
                                'reason' => $item['reason'],
                                'notes' => $item['notes'] ?? null,
                            ]);
                        }

                        // Update total amount
                        $return->update(['total_amount' => $totalReturnAmount]);
                        
                        DB::commit();
                        
                        Notification::make()
                            ->title('Return processed successfully')
                            ->success()
                            ->send();
                            
                        $this->redirect($this->getResource()::getUrl('view', ['record' => $record->id]));
                        
                    } catch (\Exception $e) {
                        DB::rollBack();
                        
                        Notification::make()
                            ->title('Error processing return')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
        ];
    }

    public function getView(): string
    {
        return 'filament.tenant.resources.sellings.pages.view-selling';
    }

    public function getRecord(): Selling
    {
        return $this->record->load(['sellingDetails.product', 'returnSellings.returnDetails.product']);
    }

    public function getRelationManagers(): array
    {
        return [
            SellingDetailsRelationManager::make(),
        ];
    }
}
