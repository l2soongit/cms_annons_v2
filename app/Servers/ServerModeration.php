<?php

namespace App\Servers;

use App\Enums\ServerStatus;
use App\Models\ModerationLog;
use App\Models\Server;
use App\Notifications\ServerModerated;

/**
 * Рішення модератора щодо сервера: журнал + сповіщення власнику.
 */
class ServerModeration
{
    public function __construct(private ServerSubmission $submission) {}

    public function approve(Server $server): void
    {
        if ($server->status === ServerStatus::Published && $server->pending_changes) {
            $changes = $this->diff($server);
            $this->submission->applyPendingChanges($server);
            ModerationLog::record($server, 'approve_changes', changes: $changes);
            $server->owner->notify(new ServerModerated($server, 'changes_approved'));

            return;
        }

        $server->update(['status' => ServerStatus::Published, 'rejection_reason' => null]);
        ModerationLog::record($server, 'approve');
        $server->owner->notify(new ServerModerated($server, 'approved'));
    }

    public function reject(Server $server, string $reason): void
    {
        if ($server->status === ServerStatus::Published && $server->pending_changes) {
            $changes = $this->diff($server);
            $server->update(['pending_changes' => null]);
            ModerationLog::record($server, 'reject_changes', $reason, $changes);
            $server->owner->notify(new ServerModerated($server, 'changes_rejected', $reason));

            return;
        }

        $server->update(['status' => ServerStatus::Rejected, 'rejection_reason' => $reason]);
        ModerationLog::record($server, 'reject', $reason);
        $server->owner->notify(new ServerModerated($server, 'rejected', $reason));
    }

    /**
     * Блокування сервера: на $days днів (потім повертається в рейтинг автоматично) або назавжди (null).
     */
    public function block(Server $server, string $reason, ?int $days = null): void
    {
        $until = $days ? now()->addDays($days) : null;
        $server->update(['status' => ServerStatus::Blocked, 'blocked_until' => $until, 'rank' => null]);
        ModerationLog::record($server, 'block', $reason, $days ? ['days' => $days] : null);
        $server->owner->notify(new ServerModerated($server, $until ? 'blocked_temp' : 'blocked', $reason, $until));
    }

    /** Зняти блокування: вручну модератором або коли минув термін */
    public function unblock(Server $server): void
    {
        $server->update(['status' => ServerStatus::Published, 'blocked_until' => null]);
        ModerationLog::record($server, 'unblock');
        $server->owner->notify(new ServerModerated($server, 'unblocked'));
    }

    /** Попередження власнику: сервер лишається в рейтингу, запис у журналі й сповіщення */
    public function warn(Server $server, string $reason): void
    {
        ModerationLog::record($server, 'warn', $reason);
        $server->owner->notify(new ServerModerated($server, 'warned', $reason));
    }

    /** Повернути в рейтинг сервери, чий термін блокування минув */
    public function releaseExpiredBlocks(): int
    {
        $servers = Server::where('status', ServerStatus::Blocked)->whereNotNull('blocked_until')->where('blocked_until', '<=', now())->get();
        $servers->each(fn (Server $server) => $this->unblock($server));

        return $servers->count();
    }

    public function setStatus(Server $server, ServerStatus $status, ?string $reason = null): void
    {
        $server->update(['status' => $status]);
        ModerationLog::record($server, 'status_'.$status->value, $reason);
    }

    /**
     * @return array{before: array, after: array}
     */
    public function diff(Server $server): array
    {
        $after = $server->pending_changes ?? [];

        return [
            'before' => collect($after)->mapWithKeys(fn ($v, $k) => [$k => $server->getAttribute($k)])->all(),
            'after' => $after,
        ];
    }
}
