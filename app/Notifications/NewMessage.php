<?php

namespace App\Notifications;

use App\Models\Message;
use App\Notifications\Concerns\UsesUserChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Нове приватне повідомлення або відповідь підтримки на тікет.
 */
class NewMessage extends Notification implements ShouldQueue
{
    use Queueable, UsesUserChannels;

    public function __construct(public Message $message, public bool $fromSupport = false) {}

    protected function event(): string
    {
        return 'messages';
    }

    private function url(): string
    {
        $this->message->loadMissing('conversation.ticket');
        $ticket = $this->message->conversation->ticket;

        return $ticket ? route('cabinet.support.show', $ticket) : route('cabinet.messages.show', $this->message->conversation);
    }

    private function subject(string $locale): string
    {
        $this->message->loadMissing(['user', 'conversation.ticket']);

        return $this->fromSupport
            ? __('messages.notice.support', ['subject' => $this->message->conversation->ticket?->subject], $locale)
            : __('messages.notice.direct', ['name' => $this->message->user?->name], $locale);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'uk';

        return (new MailMessage)
            ->subject($this->subject($locale))
            ->line(Str::limit($this->message->body, 300))
            ->action(__('messages.notice.open', [], $locale), $this->url());
    }

    public function toTelegram(object $notifiable): string
    {
        $locale = $notifiable->locale ?? 'uk';

        return $this->subject($locale)."\n\n".Str::limit($this->message->body, 300)."\n\n".$this->url();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'text' => $this->subject($notifiable->locale ?? 'uk'),
            'url' => $this->url(),
        ];
    }
}
