<?php

namespace App\Notifications\Tickets;

use Illuminate\Notifications\Messages\MailMessage;

class TicketDueDateNotification extends BaseTicketNotification
{
    private bool $isOverdue;

    public function __construct($ticket, bool $isOverdue = false)
    {
        parent::__construct($ticket);
        $this->isOverdue = $isOverdue;
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->isOverdue ? 'Ticket Overdue' : 'Ticket Due Soon')
            ->line($this->isOverdue 
                ? "Ticket #{$this->ticket->id} is overdue!"
                : "Ticket #{$this->ticket->id} is due soon.")
            ->line("Category: {$this->ticket->category}")
            ->line("Priority: {$this->ticket->priority}")
            ->line("Due Date: {$this->ticket->due_date->format('Y-m-d H:i:s')}")
            ->action('View Ticket', $this->getActionUrl());

        if ($this->isOverdue) {
            $message->line('Please update the ticket status or adjust the due date if needed.');
        } else {
            $message->line('Please ensure this ticket is resolved before the due date.');
        }

        return $message;
    }

    protected function getTitle(): string
    {
        return $this->isOverdue ? 'Ticket Overdue' : 'Ticket Due Soon';
    }

    protected function getMessage(): string
    {
        return $this->isOverdue
            ? "Ticket #{$this->ticket->id} has passed its due date of {$this->ticket->due_date->format('Y-m-d H:i:s')}."
            : "Ticket #{$this->ticket->id} is due on {$this->ticket->due_date->format('Y-m-d H:i:s')}.";
    }
} 