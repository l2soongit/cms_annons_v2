<?php

namespace App\Jobs;

use App\Models\Vote;
use App\Support\PublicHost;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * POST на сервер власника після голосу, щоб той видав бонус у грі.
 * Тіло підписане HMAC-SHA256 секретом сервера (заголовок X-Graj-Signature).
 */
class DeliverVoteCallback implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $voteId, public string $ip) {}

    public function tries(): int
    {
        return count(config('graj.voting.callback_backoff'));
    }

    public function backoff(): array
    {
        return config('graj.voting.callback_backoff');
    }

    public function handle(): void
    {
        $vote = Vote::with('server')->find($this->voteId);

        if (! $vote || ! $vote->server?->callback_url) {
            return;
        }

        // Перевірка ще раз під час відправки: DNS міг змінитися після збереження URL
        if (! PublicHost::isPublicUrl($vote->server->callback_url)) {
            $vote->update(['callback_status' => 'failed']);

            return;
        }

        $payload = self::payload($vote, $this->ip);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $vote->increment('callback_attempts');

        $response = Http::timeout(10)
            ->withoutRedirecting()
            ->withHeaders([
                'X-Graj-Signature' => hash_hmac('sha256', $body, $vote->server->callback_secret),
                'User-Agent' => 'graj.club vote-callback',
            ])
            ->withBody($body, 'application/json')
            ->post($vote->server->callback_url);

        if (! $response->successful()) {
            $vote->update(['callback_status' => 'failed']);
            $this->release($this->backoff()[$this->attempts() - 1] ?? 3600);

            return;
        }

        $vote->update(['callback_status' => 'delivered']);
    }

    public static function payload(Vote $vote, string $ip): array
    {
        return [
            'server_id' => $vote->server_id,
            'vote_id' => $vote->id,
            'nickname' => $vote->nickname,
            'ip' => $ip,
            'timestamp' => $vote->created_at->timestamp,
        ];
    }
}
