<?php

namespace App\Models\Tenants\OmniChannel;

use Illuminate\Database\Eloquent\Model;
use App\Models\Tenants\User;

class Message extends Model
{
    protected $table = 'omni_channel_messages';

    protected $fillable = [
        'whatsapp_number',
        'customer_name',
        'message',
        'message_type',
        'media_url',
        'direction',
        'status',
        'assigned_to'
    ];

    protected static function booted()
    {
        static::creating(function ($message) {
            if ($message->direction === 'inbound' && !$message->assigned_to) {
                $message->status = 'in_progress';
                $message->assigned_to = auth()->id();
            }
        });
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function appendAgentSignature()
    {
        if ($this->direction === 'outbound' && $this->agent) {
            $this->message .= "\n\nRegard,\n" . $this->agent->name;
        }
    }
}
