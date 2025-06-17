<?php
namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReturnSellingDetail extends Model
{
    protected $table = 'return_selling_details';

    protected $fillable = [
        'return_selling_id',
        'selling_detail_id',
        'product_id',
        'quantity',
        'price',
        'subtotal',
        'reason',
        'notes',
    ];

    public function selling(): BelongsTo
    {
        return $this->belongsTo(Selling::class);
    }

    public function sellingDetail(): BelongsTo
    {
        return $this->belongsTo(SellingDetail::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}