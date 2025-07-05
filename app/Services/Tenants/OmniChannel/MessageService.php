<?php

namespace App\Services\Tenants\OmniChannel;

use App\Models\Tenants\OmniChannel\Ticket;
use App\Models\Tenants\OmniChannel\Message;
use App\Models\User;
use App\Models\Agent;

class MessageService
{
    /**
     * Handle incoming message from user, assign ticket to agent if needed, and store message.
     */
    public function handleIncomingMessage(int $userId, string $message)
    {
        // Cari ticket aktif user, jika tidak ada buat baru dan assign agent
        $ticket = Ticket::where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if (!$ticket) {
            $agent = $this->findAvailableAgent();
            $ticket = Ticket::create([
                'user_id' => $userId,
                'agent_id' => $agent ? $agent->id : null,
                'status' => 'open',
            ]);
        }

        // Simpan pesan
        Message::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'user',
            'sender_id' => $userId,
            'message' => $message,
        ]);

        return $ticket;
    }

    /**
     * Find available agent to assign ticket (simple round robin or least busy)
     */
    protected function findAvailableAgent()
    {
        // Contoh: pilih agent dengan ticket open paling sedikit
        return \App\Models\Agent::withCount(['tickets' => function($q) {
            $q->where('status', 'open');
        }])->orderBy('tickets_count')->first();
    }
}
