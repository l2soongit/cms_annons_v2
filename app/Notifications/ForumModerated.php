<?php

namespace App\Notifications;

use App\Models\ForumTopic;
use App\Notifications\Concerns\UsesUserChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Рішення модератора щодо теми: approved | rejected | hidden */
class ForumModerated extends Notification implements ShouldQueue
{
    use Queueable, UsesUserChannels;

    public function __construct(public ForumTopic $topic, public string $decision, public ?string $reason = null) {}

    protected function event(): string
    {
        return 'moderation';
    }

    private function line(string $locale): string
    {
        return __("community.mail.{$this->decision}", ['title' => $this->topic->title], $locale);
    }

    private function url(): string
    {
        return route('community.topic', $this->topic);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'uk';
        $mail = (new MailMessage)->subject($this->line($locale))->line($this->line($locale));
        if ($this->reason) {
            $mail->line(__('community.mail.reason', ['reason' => $this->reason], $locale));
        }

        return $mail->action(__('community.mail.open', [], $locale), $this->url());
    }

    public function toTelegram(object $notifiable): string
    {
        $locale = $notifiable->locale ?? 'uk';

        return collect([$this->line($locale), $this->reason ? __('community.mail.reason', ['reason' => $this->reason], $locale) : null, $this->url()])
            ->filter()->implode("\n\n");
    }

    public function toArray(object $notifiable): array
    {
        return ['text' => $this->line($notifiable->locale ?? 'uk'), 'url' => $this->url(), 'reason' => $this->reason];
    }
}
