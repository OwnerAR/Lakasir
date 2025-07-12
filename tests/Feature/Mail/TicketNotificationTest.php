<?php

namespace Tests\Feature\Mail;

use App\Mail\TicketNotificationMail;
use App\Models\Tenants\OmniChannel\Ticket;
use App\Models\Tenants\User;
use App\Notifications\Tickets\NewTicketNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_notification_email_contains_correct_data()
    {
        Mail::fake();
        Notification::fake();

        $user = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'user_id' => $user->id,
            'category' => 'technical',
            'priority' => 'high',
            'description' => 'Test ticket description',
        ]);

        $notification = new NewTicketNotification($ticket);
        $mailData = $this->getPrivateProperty($notification, 'getMailData');

        $mailable = new TicketNotificationMail(
            subject: 'New Support Ticket Created',
            greeting: 'New Support Ticket',
            lines: [
                'A new support ticket has been created.',
                "Ticket ID: #{$ticket->id}",
                "Category: {$ticket->category}",
                "Priority: {$ticket->priority}",
                "Description: {$ticket->description}",
                'Please review and assign this ticket.',
            ],
            actionText: 'View Ticket',
            actionUrl: route('filament.tenant.resources.tickets.edit', ['record' => $ticket])
        );

        Mail::to($user)->send($mailable);

        Mail::assertSent(TicketNotificationMail::class, function ($mail) use ($ticket) {
            return $mail->subject === 'New Support Ticket Created' &&
                   str_contains($mail->lines[1], (string) $ticket->id) &&
                   str_contains($mail->lines[2], $ticket->category) &&
                   str_contains($mail->lines[3], $ticket->priority);
        });
    }

    private function getPrivateProperty($object, $methodName)
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invoke($object);
    }
} 