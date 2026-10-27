<?php

namespace App\Notifications\Channels;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Надсилання сповіщень через Telegram Bot API. Сповіщення має реалізувати toTelegram(): string.
 */
class TelegramChannel
{
    public static function enabled(): bool
    {
        return filled(config('graj.telegram.bot_token')) && filled(config('graj.telegram.bot_username'));
    }

    public static function linkUrl(User $user): ?string
    {
        if (! self::enabled() || ! $user->telegram_link_token) {
            return null;
        }

        return 'https://t.me/'.config('graj.telegram.bot_username').'?start='.$user->telegram_link_token;
    }

    public static function sendMessage(string $chatId, string $text): bool
    {
        if (! self::enabled()) {
            return false;
        }

        try {
            return Http::timeout(10)
                ->post('https://api.telegram.org/bot'.config('graj.telegram.bot_token').'/sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ])
                ->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram send failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if ($notifiable->telegram_chat_id && method_exists($notification, 'toTelegram')) {
            self::sendMessage($notifiable->telegram_chat_id, $notification->toTelegram($notifiable));
        }
    }
}
