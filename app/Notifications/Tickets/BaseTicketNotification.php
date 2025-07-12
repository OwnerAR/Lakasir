<?php

namespace App\Notifications\Tickets;

use App\Mail\TicketNotificationMail;
use App\Models\Tenants\OmniChannel\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;

abstract class BaseTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Ticket $ticket;

    public function __construct(Ticket $ticket)
    {
        $this->ticket = $ticket;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database', FcmChannel::class];
    }

    abstract protected function getMailData(): array;

    public function toMail($notifiable): TicketNotificationMail
    {
        $mailData = $this->getMailData();
        
        return new TicketNotificationMail(
            subject: $mailData['subject'] ?? $this->getTitle(),
            greeting: $mailData['greeting'] ?? $this->getTitle(),
            lines: $mailData['lines'] ?? [$this->getMessage()],
            actionText: $mailData['action_text'] ?? 'View Ticket',
            actionUrl: $mailData['action_url'] ?? $this->getActionUrl(),
            footerText: $mailData['footer_text'] ?? null
        );
    }

    public function toArray($notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'title' => $this->getTitle(),
            'message' => $this->getMessage(),
            'action_url' => $this->getActionUrl(),
        ];
    }

    public function toFcm($notifiable): FcmMessage
    {
        return FcmMessage::create()
            ->setData([
                'ticket_id' => (string) $this->ticket->id,
                'action_url' => $this->getActionUrl(),
            ])
            ->setNotification(\NotificationChannels\Fcm\Resources\Notification::create()
                ->setTitle($this->getTitle())
                ->setBody($this->getMessage()));
    }

    abstract protected function getTitle(): string;
    abstract protected function getMessage(): string;
    
    protected function getActionUrl(): string
    {
        return route('filament.tenant.resources.tickets.edit', ['record' => $this->ticket]);
    }
} 