<?php

namespace App\Observers;

use App\Models\Tenants\OmniChannel\Ticket;
use App\Notifications\Tickets\NewTicketNotification;
use App\Notifications\Tickets\TicketAssignedNotification;
use App\Notifications\Tickets\TicketStatusChangedNotification;
use Illuminate\Support\Facades\Notification;

class TicketObserver
{
    public function created(Ticket $ticket): void
    {
        // Notify all admin users about new ticket
        $admins = \App\Models\Tenants\User::role('admin')->get();
        Notification::send($admins, new NewTicketNotification($ticket));
    }

    public function updated(Ticket $ticket): void
    {
        $changes = $ticket->getDirty();

        // Handle agent assignment
        if (isset($changes['agent_id']) && $ticket->agent_id !== null) {
            $ticket->agent->notify(new TicketAssignedNotification($ticket));
        }

        // Handle status changes
        if (isset($changes['status'])) {
            $oldStatus = $ticket->getOriginal('status');
            $newStatus = $ticket->status;

            // Notify both the user and the agent (if assigned)
            $notifiables = collect([$ticket->user]);
            if ($ticket->agent) {
                $notifiables->push($ticket->agent);
            }

            Notification::send(
                $notifiables, 
                new TicketStatusChangedNotification($ticket, $oldStatus, $newStatus)
            );

            // Update resolved_at timestamp when status changes to resolved
            if ($newStatus === 'resolved' && $oldStatus !== 'resolved') {
                $ticket->resolved_at = now();
                $ticket->saveQuietly();
            }
        }
    }
} 