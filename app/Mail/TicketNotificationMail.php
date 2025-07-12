<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class TicketNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subject,
        public string $greeting,
        public array $lines,
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public ?string $footerText = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subject,
            replyTo: [
                new Address(
                    config('mail.reply_to.address'),
                    config('mail.reply_to.name')
                ),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tickets.notification',
        );
    }
} 