<?php

namespace App\Models\Tenants\OmniChannel;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'omni_channel_tickets';
    protected $fillable = [
        'user_id',
        'agent_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function agent()
    {
        return $this->belongsTo(\App\Models\Agent::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'ticket_id');
    }
}
