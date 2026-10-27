<?php

namespace App\Notifications;

use App\Models\Server;
use App\Notifications\Concerns\UsesUserChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServerWentOffline extends Notification implements ShouldQueue
{
    use Queueable, UsesUserChannels;

    public function __construct(public Server $server) {}

    protected function event(): string
    {
        return 'server_offline';
    }

    private function text(string $locale): string
    {
        return __('notifications.offline.line', [
            'server' => $this->server->name,
            'address' => $this->server->address(),
            'time' => $this->server->offline_since?->locale($locale)->diffForHumans(),
        ], $locale);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'uk';

        return (new MailMessage)
            ->subject(__('notifications.offline.subject', ['server' => $this->server->name], $locale))
            ->line($this->text($locale))
            ->action(__('moderation.mail.open', [], $locale), route('cabinet.servers.show', $this->server));
    }

    public function toTelegram(object $notifiable): string
    {
        return $this->text($notifiable->locale ?? 'uk')."\n\n".route('cabinet.servers.show', $this->server);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'text' => __('notifications.offline.subject', ['server' => $this->server->name], $notifiable->locale ?? 'uk'),
            'url' => route('cabinet.servers.show', $this->server),
        ];
    }
}
