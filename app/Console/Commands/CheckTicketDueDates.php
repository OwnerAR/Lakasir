<?php

namespace App\Console\Commands;

use App\Models\Tenants\OmniChannel\Ticket;
use App\Notifications\Tickets\TicketDueDateNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckTicketDueDates extends Command
{
    protected $signature = 'tickets:check-due-dates';
    protected $description = 'Check ticket due dates and send notifications';

    public function handle()
    {
        // Check for tickets due in the next 24 hours
        $dueSoonTickets = Ticket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('due_date')
            ->where('due_date', '>', now())
            ->where('due_date', '<=', now()->addHours(24))
            ->get();

        foreach ($dueSoonTickets as $ticket) {
            $notifiables = collect([$ticket->user]);
            if ($ticket->agent) {
                $notifiables->push($ticket->agent);
            }

            Notification::send(
                $notifiables,
                new TicketDueDateNotification($ticket, false)
            );
        }

        // Check for overdue tickets
        $overdueTickets = Ticket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->get();

        foreach ($overdueTickets as $ticket) {
            $notifiables = collect([$ticket->user, $ticket->agent]);
            
            // Also notify admins about overdue tickets
            $admins = \App\Models\Tenants\User::role('admin')->get();
            $notifiables = $notifiables->merge($admins)->unique('id');

            Notification::send(
                $notifiables,
                new TicketDueDateNotification($ticket, true)
            );
        }

        $this->info('Ticket due date notifications sent successfully.');
    }
} 