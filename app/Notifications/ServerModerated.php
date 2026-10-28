<?php

namespace App\Notifications;

use App\Models\Server;
use App\Notifications\Concerns\UsesUserChannels;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class ServerModerated extends Notification implements ShouldQueue
{
    use Queueable, UsesUserChannels;

    /**
     * @param  string  $decision  approved | rejected | changes_approved | changes_rejected | blocked | blocked_temp | unblocked | warned
     * @param  DateTimeInterface|null  $until  кінець тимчасового блокування
     */
    public function __construct(public Server $server, public string $decision, public ?string $reason = null, public ?DateTimeInterface $until = null) {}

    /** Підстановки для тексту: назва сервера й дата кінця блокування */
    private function params(string $locale): array
    {
        return ['server' => $this->server->name, 'until' => $this->until ? Carbon::instance($this->until)->locale($locale)->translatedFormat('j F Y, H:i') : ''];
    }

    protected function event(): string
    {
        return 'moderation';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'uk';

        $mail = (new MailMessage)
            ->subject(__("moderation.mail.{$this->decision}.subject", $this->params($locale), $locale))
            ->line(__("moderation.mail.{$this->decision}.line", $this->params($locale), $locale));

        if ($this->reason) {
            $mail->line(__('moderation.mail.reason', ['reason' => $this->reason], $locale));
        }

        return $mail->action(__('moderation.mail.open', [], $locale), route('cabinet.servers.show', $this->server));
    }

    public function toTelegram(object $notifiable): string
    {
        $locale = $notifiable->locale ?? 'uk';

        return collect([
            __("moderation.mail.{$this->decision}.line", $this->params($locale), $locale),
            $this->reason ? __('moderation.mail.reason', ['reason' => $this->reason], $locale) : null,
            route('cabinet.servers.show', $this->server),
        ])->filter()->implode("\n\n");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'text' => __("moderation.mail.{$this->decision}.subject", $this->params($notifiable->locale ?? 'uk'), $notifiable->locale ?? 'uk'),
            'url' => route('cabinet.servers.show', $this->server),
            'reason' => $this->reason,
        ];
    }
}
