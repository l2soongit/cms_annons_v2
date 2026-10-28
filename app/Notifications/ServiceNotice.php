<?php

namespace App\Notifications;

use App\Billing\Money;
use App\Models\Order;
use App\Notifications\Concerns\UsesUserChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Події платних послуг: скоро закінчиться, буде продовжено, продовжено, не вдалося продовжити, закінчилася.
 */
class ServiceNotice extends Notification implements ShouldQueue
{
    use Queueable, UsesUserChannels;

    /**
     * @param  string  $kind  expiring | will_renew | renewed | renew_failed | expired
     */
    public function __construct(public Order $order, public string $kind, public ?string $reason = null) {}

    protected function event(): string
    {
        return 'billing';
    }

    private function params(string $locale): array
    {
        $this->order->loadMissing(['tariff', 'server']);

        return [
            'service' => $this->order->tariff->getTranslation('name', $locale),
            'server' => $this->order->server?->name,
            'date' => $this->order->ends_at?->locale($locale)->translatedFormat('j F Y, H:i'),
            'amount' => Money::format($this->order->amount),
        ];
    }

    private function url(): string
    {
        return $this->order->server ? route('cabinet.servers.show', $this->order->server).'#s-promo' : route('cabinet.balance');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'uk';
        $mail = (new MailMessage)
            ->subject(__("billing.notice.{$this->kind}.subject", $this->params($locale), $locale))
            ->line(__("billing.notice.{$this->kind}.line", $this->params($locale), $locale));

        if ($this->reason) {
            $mail->line($this->reason);
        }

        return $mail->action(__('billing.notice.open', [], $locale), $this->url());
    }

    public function toTelegram(object $notifiable): string
    {
        $locale = $notifiable->locale ?? 'uk';

        return collect([__("billing.notice.{$this->kind}.line", $this->params($locale), $locale), $this->reason, $this->url()])->filter()->implode("\n\n");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'text' => __("billing.notice.{$this->kind}.subject", $this->params($notifiable->locale ?? 'uk'), $notifiable->locale ?? 'uk'),
            'url' => $this->url(),
        ];
    }
}
