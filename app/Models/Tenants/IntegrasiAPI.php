<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;

class IntegrasiAPI extends Model
{
    protected $table = 'integrasi_api';
    protected $fillable = [
        'name',
        'type',
        'base_url',
        'username',
        'password',
        'pin',
    ];

    protected $casts = [
        'type' => 'integer',
    ];

    public function getTypeLabelAttribute()
    {
        return match ($this->type) {
            1 => 'TigaPutri',
            2 => 'OtomaX',
            default => 'Unknown',
        };
    }
}
