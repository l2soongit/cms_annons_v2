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

    public function block(Server $server, string $reason): void
    {
        $server->update(['status' => ServerStatus::Blocked, 'rank' => null]);
        ModerationLog::record($server, 'block', $reason);
        $server->owner->notify(new ServerModerated($server, 'blocked', $reason));
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
