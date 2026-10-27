<?php

namespace App\Notifications;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServerModerated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $decision  approved | rejected | changes_approved | changes_rejected | blocked
     */
    public function __construct(public Server $server, public string $decision, public ?string $reason = null) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'uk';

        $mail = (new MailMessage)
            ->subject(__("moderation.mail.{$this->decision}.subject", ['server' => $this->server->name], $locale))
            ->line(__("moderation.mail.{$this->decision}.line", ['server' => $this->server->name], $locale));

        if ($this->reason) {
            $mail->line(__('moderation.mail.reason', ['reason' => $this->reason], $locale));
        }

        return $mail->action(__('moderation.mail.open', [], $locale), route('cabinet.servers.show', $this->server));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'decision' => $this->decision,
            'reason' => $this->reason,
        ];
    }
}
