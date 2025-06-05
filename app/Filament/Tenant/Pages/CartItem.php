<?php

namespace App\Filament\Tenant\Pages;

use App\Filament\Tenant\Pages\Traits\CartInteraction;
use App\Models\Tenants\CartItem as TenantsCartItem;
use App\Models\Tenants\Product;
use App\Traits\HasTranslatableResource;
use App\Models\Tenants\Selling;
use Filament\Pages\Page;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;

class CartItem extends Page
{
    use CartInteraction, HasTranslatableResource;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.tenant.pages.pos.cart-item';

    protected static string $layout = 'filament-panels::components.layout.base';

    public $cartItems = [];

    public function mount(): void
    {
        $this->refreshCart();
    }

    public function refreshCart(): void
    {
        $this->cartItems = TenantsCartItem::with('product')->get();
    }

    public function incrementQuantity(Product $product): void
    {
        $this->addCart($product);
        $this->dispatch('cartUpdated', $this->cartItems);
    }

    public function decrementQuantity(Product $product): void
    {
        $this->reduceCart($product);
        $this->dispatch('cartUpdated', $this->cartItems);
    }

    public function generateSellingCode(): string
    {
        $lastSelling = Selling::query()->latest()->first();
        $lastCode = $lastSelling ? $lastSelling->code : '0000';
        $nextCode = str_pad((int)$lastCode + 1, 4, '0', STR_PAD_LEFT);
        return $nextCode;
    }

    public function proceedThePayment($data)
    {
        $cartItems = $data['cartItems'] ?? [];
        $total = $data['total'] ?? 0;

        if (empty($cartItems)) {
            throw ValidationException::withMessages([
                'cartItems' => __('filament-panels::tenant/cart-item.messages.empty_cart'),
            ]);
        }

        $selling = Selling::create([
            'total' => $total,
            'total_price' => $data['total_price'] ?? 0,
            'tax_price' => $data['tax_price'] ?? 0,
            'grand_total_price' => $data['grand_total_price'] ?? 0,
            'code' => $this->generateSellingCode(),
            'customer_number' => $data['customer_number'] ?? null,
            'date' => now(),
            'user_id' => auth()->id(),
            'tenant_id' => tenant('id'),
        ]);

        foreach ($cartItems as $item) {
            $selling->sellingDetails()->create([
                'product_id' => $item['product_id'],
                'qty' => $item['qty'],
                'price' => $item['price'],
                'discount_price' => $item['discount_price'] ?? 0,
            ]);
        }
        TenantsCartItem::query()->delete();

        Notification::make()
            ->title(__('filament-panels::tenant/cart-item.messages.payment_success'))
            ->success()
            ->send();

        $this->clearCart();
        $this->dispatch('paymentProcessed', ['message' => 'Payment processed successfully!']);
    }
}
