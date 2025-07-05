<?php

namespace App\Models\Tenants\OmniChannel;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'omni_channel_messages';
    protected $fillable = [
        'ticket_id',
        'sender_type', // 'user' atau 'agent'
        'sender_id',
        'message',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }
}
