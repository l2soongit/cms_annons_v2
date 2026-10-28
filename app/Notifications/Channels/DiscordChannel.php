<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Сповіщення в Discord-канал власника через вебхук (ТЗ 7.4). Текст — той самий, що для Telegram.
 */
class DiscordChannel
{
    /** Адреса вебхука Discord: https://discord.com/api/webhooks/<id>/<token> */
    public const URL_PATTERN = '#^https://(?:ptb\.|canary\.)?discord(?:app)?\.com/api/webhooks/\d+/[\w-]+$#';

    public static function sendMessage(string $webhook, string $text): bool
    {
        if (! preg_match(self::URL_PATTERN, $webhook)) {
            return false;
        }

        try {
            return Http::timeout(10)->post($webhook, [
                'content' => Str::limit($text, 1900),
                'username' => 'graj.club',
                // Без масових згадок @everyone з тексту відгуку чи повідомлення
                'allowed_mentions' => ['parse' => []],
            ])->successful();
        } catch (\Throwable $e) {
            Log::warning('Discord webhook failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if ($notifiable->discord_webhook && method_exists($notification, 'toTelegram')) {
            self::sendMessage($notifiable->discord_webhook, $notification->toTelegram($notifiable));
        }
    }
}
