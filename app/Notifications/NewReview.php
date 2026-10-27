<?php

namespace App\Notifications;

use App\Models\Review;
use App\Notifications\Concerns\UsesUserChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewReview extends Notification implements ShouldQueue
{
    use Queueable, UsesUserChannels;

    public function __construct(public Review $review) {}

    protected function event(): string
    {
        return 'new_review';
    }

    private function url(): string
    {
        return route('servers.show', $this->review->server).'#reviews';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'uk';

        return (new MailMessage)
            ->subject(__('notifications.review.subject', ['server' => $this->review->server->name], $locale))
            ->line(__('notifications.review.line', ['rating' => $this->review->rating], $locale))
            ->line('«'.Str::limit($this->review->body, 300).'»')
            ->action(__('notifications.review.open', [], $locale), $this->url());
    }

    public function toTelegram(object $notifiable): string
    {
        $locale = $notifiable->locale ?? 'uk';

        return __('notifications.review.subject', ['server' => $this->review->server->name], $locale)
            ."\n".str_repeat('★', $this->review->rating)."\n\n".Str::limit($this->review->body, 300)."\n\n".$this->url();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'text' => __('notifications.review.subject', ['server' => $this->review->server->name], $notifiable->locale ?? 'uk'),
            'url' => $this->url(),
        ];
    }
}
