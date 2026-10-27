<?php

namespace App\Notifications\Concerns;

use App\Notifications\Channels\TelegramChannel;

/**
 * Канали сповіщення з налаштувань користувача + завжди запис у базу (дзвіночок на сайті).
 */
trait UsesUserChannels
{
    abstract protected function event(): string;

    public function via(object $notifiable): array
    {
        $channels = method_exists($notifiable, 'channelsFor') ? $notifiable->channelsFor($this->event()) : ['mail'];

        return array_merge(['database'], array_map(
            fn ($channel) => $channel === 'telegram' ? TelegramChannel::class : $channel,
            $channels,
        ));
    }
}
