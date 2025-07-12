<?php

namespace App\Notifications\Tickets;

class NewTicketNotification extends BaseTicketNotification
{
    protected function getMailData(): array
    {
        return [
            'subject' => 'New Support Ticket Created',
            'greeting' => 'New Support Ticket',
            'lines' => [
                'A new support ticket has been created.',
                "Ticket ID: #{$this->ticket->id}",
                "Category: {$this->ticket->category}",
                "Priority: {$this->ticket->priority}",
                "Description: {$this->ticket->description}",
                'Please review and assign this ticket.',
            ],
            'action_text' => 'View Ticket',
        ];
    }

    protected function getTitle(): string
    {
        return 'New Support Ticket';
    }

    protected function getMessage(): string
    {
        return "New ticket #{$this->ticket->id} has been created in the {$this->ticket->category} category.";
    }
} 