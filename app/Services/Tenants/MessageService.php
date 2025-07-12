<?php

namespace App\Services\Tenants;

use App\Models\Tenants\OmniChannel\Message;
use App\Jobs\ProcessMessageQueue;
use Illuminate\Support\Facades\DB;

class MessageService
{
    public function createMessage(array $data): Message
    {
        return DB::transaction(function() use ($data) {
            // Set initial queue position
            $lastPosition = Message::queued()->max('queue_position') ?? 0;
            $data['queue_position'] = $lastPosition + 1;
            
            // Create the message
            $message = Message::create($data);
            
            // Dispatch queue processing job
            ProcessMessageQueue::dispatch();
            
            return $message;
        });
    }

    public function takeChat(Message $message, int $agentId): bool
    {
        if ($message->status !== 'queued') {
            return false;
        }

        return DB::transaction(function() use ($message, $agentId) {
            $message->update([
                'status' => 'in_progress',
                'assigned_to' => $agentId
            ]);

            // Reprocess queue
            ProcessMessageQueue::dispatch();

            return true;
        });
    }

    public function completeChat(Message $message): bool
    {
        if ($message->status !== 'in_progress') {
            return false;
        }

        return DB::transaction(function() use ($message) {
            $message->update([
                'status' => 'completed'
            ]);

            // Reprocess queue
            ProcessMessageQueue::dispatch();

            return true;
        });
    }

    public function reorderQueue(): void
    {
        Message::queued()
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->each(function($message, $index) {
                $message->update(['queue_position' => $index + 1]);
            });
    }
} 