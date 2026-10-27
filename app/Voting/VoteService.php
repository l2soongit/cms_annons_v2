<?php

namespace App\Voting;

use App\Enums\ServerStatus;
use App\Jobs\DeliverVoteCallback;
use App\Models\Server;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VoteService
{
    public function __construct(private ProxyDetector $proxies) {}

    public static function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, config('app.key'));
    }

    public static function hashSubnet(string $ip): string
    {
        // IPv4 → /24, IPv6 → /64
        $subnet = str_contains($ip, ':')
            ? implode(':', array_slice(explode(':', inet_ntop(inet_pton($ip)) ?: $ip), 0, 4))
            : implode('.', array_slice(explode('.', $ip), 0, 3));

        return hash_hmac('sha256', $subnet, config('app.key'));
    }

    /**
     * Коли цей IP / акаунт / пристрій зможе знову голосувати за сервер (null — вже може).
     */
    public function nextVoteAt(Server $server, string $ip, ?User $user = null, ?string $fingerprint = null): ?Carbon
    {
        $hours = config('graj.voting.interval_hours');

        $last = Vote::where('server_id', $server->id)
            ->where('created_at', '>', now()->subHours($hours))
            ->where('status', '!=', 'annulled')
            ->where(function ($q) use ($ip, $user, $fingerprint) {
                $q->where('ip_hash', self::hashIp($ip));
                if ($user) {
                    $q->orWhere('user_id', $user->id);
                }
                if ($fingerprint) {
                    $q->orWhere('fingerprint', $fingerprint);
                }
            })
            ->max('created_at');

        return $last ? Carbon::parse($last)->addHours($hours) : null;
    }

    /**
     * @throws VoteRejected
     */
    public function cast(Server $server, string $ip, ?string $nickname, ?User $user = null, ?string $fingerprint = null): Vote
    {
        if ($server->status !== ServerStatus::Published) {
            throw new VoteRejected('unavailable');
        }

        if ($user?->isBanned()) {
            throw new VoteRejected('banned');
        }

        if ($this->proxies->isProxy($ip)) {
            throw new VoteRejected('proxy');
        }

        return DB::transaction(function () use ($server, $ip, $nickname, $user, $fingerprint) {
            if ($retryAt = $this->nextVoteAt($server, $ip, $user, $fingerprint)) {
                throw new VoteRejected('too_soon', $retryAt);
            }

            $subnetHash = self::hashSubnet($ip);
            $subnetVotes = Vote::where('server_id', $server->id)
                ->where('subnet_hash', $subnetHash)
                ->where('created_at', '>', now()->subHours(config('graj.voting.interval_hours')))
                ->count();

            $vote = Vote::create([
                'server_id' => $server->id,
                'user_id' => $user?->id,
                'ip_hash' => self::hashIp($ip),
                'subnet_hash' => $subnetHash,
                'fingerprint' => $fingerprint,
                'nickname' => $nickname,
                // Понад ліміт з однієї підмережі — голос не зараховується до перевірки модератором
                'status' => $subnetVotes >= config('graj.voting.subnet_limit') ? 'suspicious' : 'counted',
                'callback_status' => $server->callback_url ? 'pending' : 'none',
                'created_at' => now(),
            ]);

            if ($vote->status === 'counted') {
                $server->increment('votes_month');
            }

            if ($server->callback_url) {
                DeliverVoteCallback::dispatch($vote->id, $ip)->afterCommit();
            }

            return $vote;
        });
    }
}
