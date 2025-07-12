<?php

namespace App\Notifications\Tickets;

use Illuminate\Notifications\Messages\MailMessage;

class TicketStatusChangedNotification extends BaseTicketNotification
{
    private string $oldStatus;
    private string $newStatus;

    public function __construct($ticket, string $oldStatus, string $newStatus)
    {
        parent::__construct($ticket);
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Ticket Status Updated')
            ->line("The status of ticket #{$this->ticket->id} has been updated.")
            ->line("From: {$this->oldStatus}")
            ->line("To: {$this->newStatus}")
            ->line("Category: {$this->ticket->category}")
            ->line("Priority: {$this->ticket->priority}")
            ->action('View Ticket', $this->getActionUrl());

        if ($this->newStatus === 'waiting') {
            $message->line('Please provide the requested information to proceed with your ticket.');
        } elseif ($this->newStatus === 'resolved') {
            $message->line('Please review the resolution and close the ticket if you are satisfied.');
        }

        return $message;
    }

    protected function getTitle(): string
    {
        return 'Ticket Status Updated';
    }

    protected function getMessage(): string
    {
        return "Ticket #{$this->ticket->id} status changed from {$this->oldStatus} to {$this->newStatus}.";
    }
} 