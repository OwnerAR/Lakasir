<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class ReturnSelling extends Model
{
    protected $table = 'return_sellings';
    
    protected $fillable = [
        'selling_id',
        'return_number',
        'total_amount',
        'return_reason',
        'refund_method',
        'status',
        'created_by',
    ];

    public function selling(): BelongsTo
    {
        return $this->belongsTo(Selling::class);
    }
    public function returnDetails(): HasMany
    {
        return $this->hasMany(ReturnSellingDetail::class, 'return_selling_id');
    }
}
