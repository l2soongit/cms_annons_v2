<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesUserChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Попередження модератора користувачу (санкція без блокування).
 */
class UserWarned extends Notification implements ShouldQueue
{
    use Queueable, UsesUserChannels;

    public function __construct(public string $reason) {}

    protected function event(): string
    {
        return 'moderation';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'uk';

        return (new MailMessage)
            ->subject(__('moderation.mail.user_warned.subject', [], $locale))
            ->line(__('moderation.mail.user_warned.line', [], $locale))
            ->line(__('moderation.mail.reason', ['reason' => $this->reason], $locale));
    }

    public function toTelegram(object $notifiable): string
    {
        $locale = $notifiable->locale ?? 'uk';

        return __('moderation.mail.user_warned.line', [], $locale)."\n\n".__('moderation.mail.reason', ['reason' => $this->reason], $locale);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'text' => __('moderation.mail.user_warned.subject', [], $notifiable->locale ?? 'uk'),
            'reason' => $this->reason,
        ];
    }
}
