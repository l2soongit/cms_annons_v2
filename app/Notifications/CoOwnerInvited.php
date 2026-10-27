<?php

namespace App\Notifications;

use App\Models\ServerInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoOwnerInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ServerInvitation $invitation, public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('owners.mail.subject', ['server' => $this->invitation->server->name]))
            ->line(__('owners.mail.line', ['inviter' => $this->invitation->inviter->name, 'server' => $this->invitation->server->name]))
            ->action(__('owners.mail.accept'), route('cabinet.invitations.accept', $this->token))
            ->line(__('owners.mail.expires', ['date' => $this->invitation->expires_at->format('d.m.Y')]));
    }
}
