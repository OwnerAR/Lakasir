<?php

namespace App\Models\Tenants\OmniChannel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $table = 'omni_channel_tickets';
    
    protected $fillable = [
        'user_id',
        'agent_id',
        'status',
        'priority',
        'category',
        'description',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenants\User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenants\User::class, 'agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'ticket_id');
    }

    // Scopes
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('agent_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', 'waiting');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function scopePriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['resolved', 'closed']);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('agent_id', $userId);
    }

    // Attributes
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date) {
            return false;
        }
        
        return $this->due_date->isPast() && 
            !in_array($this->status, ['resolved', 'closed']);
    }

    public function getTimeToResolutionAttribute(): ?string
    {
        if (!$this->created_at || !in_array($this->status, ['resolved', 'closed'])) {
            return null;
        }

        $resolvedAt = $this->updated_at;
        return $resolvedAt->diffForHumans($this->created_at, true);
    }
}
