<?php

namespace App\Notifications\Tickets;

use Illuminate\Notifications\Messages\MailMessage;

class TicketAssignedNotification extends BaseTicketNotification
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Support Ticket Assigned')
            ->line('A support ticket has been assigned to you.')
            ->line("Ticket ID: #{$this->ticket->id}")
            ->line("Category: {$this->ticket->category}")
            ->line("Priority: {$this->ticket->priority}")
            ->line("Description: {$this->ticket->description}")
            ->action('View Ticket', $this->getActionUrl())
            ->line('Please review and start working on this ticket.');
    }

    protected function getTitle(): string
    {
        return 'Ticket Assigned';
    }

    protected function getMessage(): string
    {
        return "Ticket #{$this->ticket->id} has been assigned to you.";
    }
} 