<?php

namespace App\Jobs;

use App\Models\Tenants\OmniChannel\Message;
use App\Models\Tenants\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessMessageQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    public function __construct()
    {
        $this->onQueue('messages');
    }

    public function handle()
    {
        // Get available agents (not currently handling any in-progress chats)
        $availableAgents = User::whereHas('roles', function($query) {
            $query->where('name', 'agent');
        })->whereDoesntHave('messages', function($query) {
            $query->where('status', 'in_progress');
        })->get();

        if ($availableAgents->isEmpty()) {
            return; // No available agents
        }

        // Get queued messages ordered by priority and queue position
        $queuedMessages = Message::queued()->unassigned()->get();

        foreach ($queuedMessages as $message) {
            // Find the agent with the least number of completed chats
            $selectedAgent = $availableAgents->sortBy(function($agent) {
                return $agent->messages()
                            ->where('status', 'completed')
                            ->count();
            })->first();

            if (!$selectedAgent) {
                break; // No more available agents
            }

            DB::transaction(function() use ($message, $selectedAgent) {
                // Update message status and assign to agent
                $message->update([
                    'status' => 'in_progress',
                    'assigned_to' => $selectedAgent->id
                ]);

                // Remove the agent from available pool
                $availableAgents = $availableAgents->except($selectedAgent->id);
            });
        }

        // Reorder remaining queue positions
        Message::queued()->orderBy('created_at')->get()
            ->each(function($message, $index) {
                $message->update(['queue_position' => $index + 1]);
            });
    }
} 