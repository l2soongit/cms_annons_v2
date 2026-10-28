<?php

namespace App\Messaging;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Server;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewMessage;
use App\Support\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Приватні повідомлення й тікети (ТЗ 3.8). Доставка — опитуванням сторінки (на хостингу немає WebSocket);
 * сповіщення надсилається лише на перше непрочитане повідомлення, щоб не засипати пошту.
 */
class Messenger
{
    /** Написати власнику сервера: продовжує наявний діалог про цей сервер або починає новий */
    public function start(User $from, Server $server, string $body, ?UploadedFile $attachment = null): Conversation
    {
        $owner = $server->owner;
        if (! $owner || $owner->is($from)) {
            throw MessagingException::because('self');
        }

        $conversation = Conversation::where('server_id', $server->id)->whereDoesntHave('ticket')
            ->whereHas('participants', fn ($q) => $q->whereKey($from->id))
            ->whereHas('participants', fn ($q) => $q->whereKey($owner->id))
            ->first();

        if (! $conversation) {
            $this->guardNotBlocked($from, $owner);
            $limit = (int) config('graj.messages.daily_new_conversations');
            if (Conversation::where('user_id', $from->id)->where('created_at', '>=', now()->subDay())->count() >= $limit) {
                throw MessagingException::because('daily_limit', ['n' => $limit]);
            }

            $conversation = Conversation::create(['user_id' => $from->id, 'server_id' => $server->id]);
            $conversation->participants()->attach([$from->id, $owner->id]);
        }

        $this->send($conversation, $from, $body, $attachment);

        return $conversation;
    }

    /**
     * @param  bool  $asSupport  відповідь з адмінки від імені підтримки (лише для тікетів і з дозволом tickets.manage)
     */
    public function send(Conversation $conversation, User $from, string $body, ?UploadedFile $attachment = null, bool $asSupport = false): Message
    {
        $body = trim($body);
        if ($body === '' && ! $attachment) {
            throw MessagingException::because('empty');
        }

        $conversation->loadMissing(['participants', 'ticket']);
        $ticket = $conversation->ticket;
        $isParticipant = $conversation->participants->contains($from);
        // Співробітник може бути й автором тікета — тоді від імені підтримки пише лише явно, з адмінки
        $isSupport = $ticket && ($asSupport || ! $isParticipant) && $from->can('tickets.manage');
        if (($asSupport && ! $isSupport) || (! $isParticipant && ! $isSupport)) {
            throw MessagingException::because('forbidden');
        }
        if ($from->isBanned()) {
            throw MessagingException::because('banned');
        }
        if (! $ticket && ($other = $conversation->other($from))) {
            $this->guardNotBlocked($from, $other);
        }

        $path = $attachment ? ImageOptimizer::store($attachment, "messages/{$conversation->id}", 'message', 'local') : null;

        // Кому сповіщення: учасникам, у яких до цього не було непрочитаних (тікет від підтримки — автору)
        $notify = $conversation->participants->reject(fn (User $u) => $u->is($from))
            ->filter(fn (User $u) => ! $conversation->isUnreadFor($u));

        $message = DB::transaction(function () use ($conversation, $from, $body, $path, $ticket, $isSupport) {
            $message = $conversation->messages()->create(['user_id' => $from->id, 'body' => $body, 'attachment' => $path, 'is_support' => $isSupport]);
            $conversation->update(['last_message_at' => $message->created_at, 'last_message_id' => $message->id]);
            if ($conversation->participants->contains($from)) {
                $conversation->participants()->updateExistingPivot($from->id, ['read_at' => $message->created_at, 'last_read_id' => $message->id]);
            }
            // Тікет: відповідь підтримки — «відповіли», повідомлення автора — знову «відкрито»
            $ticket?->update(['status' => $isSupport ? 'answered' : 'open', 'closed_at' => null]);

            return $message;
        });

        foreach ($notify as $user) {
            $user->notify(new NewMessage($message, $isSupport));
        }

        return $message;
    }

    public function markRead(Conversation $conversation, User $user): void
    {
        if ($conversation->last_message_id && $conversation->lastReadId($user) < $conversation->last_message_id) {
            $conversation->participants()->updateExistingPivot($user->id, ['read_at' => now(), 'last_read_id' => $conversation->last_message_id]);
        }
    }

    /** Скільки діалогів і тікетів мають непрочитані повідомлення */
    public function unreadCount(User $user): int
    {
        return Conversation::whereHas('participants', fn ($q) => $q->whereKey($user->id)
            ->where(fn ($p) => $p->whereNull('conversation_user.last_read_id')->orWhereColumn('conversation_user.last_read_id', '<', 'conversations.last_message_id')))
            ->whereNotNull('last_message_id')
            ->count();
    }

    public function block(User $user, User $blocked): void
    {
        $user->blockedUsers()->syncWithoutDetaching([$blocked->id => ['created_at' => now()]]);
    }

    public function unblock(User $user, User $blocked): void
    {
        $user->blockedUsers()->detach($blocked->id);
    }

    public function openTicket(User $user, string $category, string $subject, string $body, ?UploadedFile $attachment = null): Ticket
    {
        $conversation = DB::transaction(function () use ($user) {
            $conversation = Conversation::create(['user_id' => $user->id]);
            $conversation->participants()->attach($user->id);

            return $conversation;
        });

        $ticket = Ticket::create(['user_id' => $user->id, 'conversation_id' => $conversation->id, 'category' => $category, 'subject' => $subject, 'status' => 'open']);
        $this->send($conversation->setRelation('ticket', $ticket), $user, $body, $attachment);

        return $ticket;
    }

    public function closeTicket(Ticket $ticket): void
    {
        $ticket->update(['status' => 'closed', 'closed_at' => now()]);
    }

    private function guardNotBlocked(User $from, User $to): void
    {
        if ($to->hasBlocked($from)) {
            throw MessagingException::because('blocked');
        }
    }
}
