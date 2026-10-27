<?php

namespace App\Notifications;

use App\Models\Server;
use App\Notifications\Concerns\UsesUserChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServerModerated extends Notification implements ShouldQueue
{
    use Queueable, UsesUserChannels;

    /**
     * @param  string  $decision  approved | rejected | changes_approved | changes_rejected | blocked
     */
    public function __construct(public Server $server, public string $decision, public ?string $reason = null) {}

    protected function event(): string
    {
        return 'moderation';
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

    public function toTelegram(object $notifiable): string
    {
        $locale = $notifiable->locale ?? 'uk';

        return collect([
            __("moderation.mail.{$this->decision}.line", ['server' => $this->server->name], $locale),
            $this->reason ? __('moderation.mail.reason', ['reason' => $this->reason], $locale) : null,
            route('cabinet.servers.show', $this->server),
        ])->filter()->implode("\n\n");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'text' => __("moderation.mail.{$this->decision}.subject", ['server' => $this->server->name], $notifiable->locale ?? 'uk'),
            'url' => route('cabinet.servers.show', $this->server),
            'reason' => $this->reason,
        ];
    }
}
