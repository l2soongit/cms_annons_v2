<?php

namespace App\Notifications;

use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Notifications\Concerns\UsesUserChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Нове в підписках: відповідь у темі (reply) або нова тема в розділі чи від сервера (topic) */
class ForumActivity extends Notification implements ShouldQueue
{
    use Queueable, UsesUserChannels;

    public function __construct(public ForumTopic $topic, public ?ForumPost $post = null) {}

    protected function event(): string
    {
        return 'community';
    }

    private function line(string $locale): string
    {
        return $this->post
            ? __('community.mail.reply', ['user' => $this->post->user->nickname ?: $this->post->user->name, 'title' => $this->topic->title], $locale)
            : __('community.mail.topic', ['title' => $this->topic->title, 'category' => $this->topic->category->getTranslation('name', $locale)], $locale);
    }

    private function url(): string
    {
        return route('community.topic', $this->topic).($this->post ? '#post-'.$this->post->id : '');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'uk';

        return (new MailMessage)->subject($this->line($locale))->line($this->line($locale))
            ->action(__('community.mail.open', [], $locale), $this->url());
    }

    public function toTelegram(object $notifiable): string
    {
        return $this->line($notifiable->locale ?? 'uk')."\n\n".$this->url();
    }

    public function toArray(object $notifiable): array
    {
        return ['text' => $this->line($notifiable->locale ?? 'uk'), 'url' => $this->url()];
    }
}
