<?php

namespace App\Billing;

use App\Enums\ServerStatus;
use App\Models\Banner;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\Server;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ServiceNotice;
use App\Support\HomePageData;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Баланс і платні послуги (ТЗ 3.7): поповнення через платіжну систему, оплата послуг з балансу,
 * промокоди, автопродовження, нагадування й вимкнення після терміну. Усі рухи коштів — у transactions:
 * поповнення й повернення зі знаком «+», списання — «−».
 */
class Billing
{
    public function __construct(private GatewayRegistry $gateways, private ExchangeRates $rates) {}

    /**
     * Створити поповнення, що чекає оплати в платіжній системі. Сума — у валюті оплати;
     * на баланс зараховується в гривнях за курсом НБУ на момент створення.
     */
    public function startDeposit(User $user, int $cents, string $gateway, ?string $currency = null): Transaction
    {
        $base = (string) config('graj.billing.currency');
        $currency ??= $base;
        if (! in_array($currency, $this->rates->depositCurrencies(), true)) {
            throw BillingException::because('currency');
        }

        $rate = (float) $this->rates->rate($currency);
        $baseCents = $this->rates->toBase($cents, $rate);

        $min = (int) config('graj.billing.min_deposit') * 100;
        $max = (int) config('graj.billing.max_deposit') * 100;
        if ($baseCents < $min || $baseCents > $max) {
            throw BillingException::because('deposit_range', ['min' => Money::format($min, true), 'max' => Money::format($max, true)]);
        }
        if (! isset($this->gateways->available()[$gateway])) {
            throw BillingException::because('gateway');
        }

        return $user->transactions()->create([
            'type' => 'deposit',
            'amount' => Money::decimal($baseCents),
            'currency' => $base,
            'paid_amount' => $currency === $base ? null : Money::decimal($cents),
            'paid_currency' => $currency === $base ? null : $currency,
            'rate' => $currency === $base ? null : $rate,
            'provider' => $gateway,
            'status' => 'pending',
        ]);
    }

    /**
     * Результат від платіжної системи. Повторні виклики з тим самим результатом нічого не змінюють.
     */
    public function completeDeposit(string $gateway, PaymentResult $result): ?Transaction
    {
        return DB::transaction(function () use ($gateway, $result) {
            $deposit = Transaction::whereKey($result->transactionId)->where('type', 'deposit')->where('provider', $gateway)->lockForUpdate()->first();
            if (! $deposit || $deposit->status !== 'pending' || $result->status === PaymentResult::PENDING) {
                return $deposit;
            }

            // Сума й валюта з відповіді мають збігатися з тими, що ми виставили
            $succeeded = $result->status === PaymentResult::SUCCEEDED
                && $result->amountCents === $deposit->chargeCents()
                && ($result->currency === null || strtoupper($result->currency) === $deposit->chargeCurrency());
            $deposit->update([
                'status' => $succeeded ? 'succeeded' : 'failed',
                'external_id' => $result->externalId,
                'payload' => $result->payload ?: null,
            ]);

            if ($succeeded) {
                User::whereKey($deposit->user_id)->lockForUpdate()->first()->increment('balance', Money::cents($deposit->amount) / 100);
            }

            return $deposit;
        });
    }

    /** Ціна послуги в копійках з урахуванням промокоду */
    public function price(Tariff $tariff, ?PromoCode $promo = null): int
    {
        $cents = Money::cents($tariff->price);

        return $promo && $promo->appliesTo($tariff) ? $promo->apply($cents) : $cents;
    }

    public function findPromo(?string $code): ?PromoCode
    {
        $code = trim((string) $code);

        return $code === '' ? null : PromoCode::whereRaw('lower(code) = ?', [mb_strtolower($code)])->first();
    }

    /**
     * Купити послугу для сервера з балансу. Повторна купівля тієї ж послуги продовжує її від кінця поточної.
     */
    public function purchase(User $user, Server $server, Tariff $tariff, ?string $promoCode = null, bool $autoRenew = false, ?Order $renewing = null): Order
    {
        // Банер купується через bookBanner(): потрібні дати, зображення й посилання
        if (! $tariff->is_active || ($tariff->game_id && $tariff->game_id !== $server->game_id) || ! isset(Tariff::SERVICES[$tariff->service]) || $tariff->service === 'banner') {
            throw BillingException::because('tariff');
        }
        if ($server->status !== ServerStatus::Published) {
            throw BillingException::because('not_published');
        }

        $server->loadMissing('game');
        $promo = $this->findPromo($promoCode);
        if ($promoCode && ! $promo?->appliesTo($tariff)) {
            throw BillingException::because('promo');
        }

        $order = DB::transaction(function () use ($user, $server, $tariff, $promo, $autoRenew, $renewing) {
            $user = User::whereKey($user->id)->lockForUpdate()->first();
            [$startsAt, $endsAt] = $this->period($server, $tariff);
            $cents = $this->price($tariff, $promo);

            if (Money::cents($user->balance) < $cents) {
                throw BillingException::because('balance', ['price' => Money::format($cents, true)]);
            }

            $order = $user->orders()->create([
                'server_id' => $server->id,
                'tariff_id' => $tariff->id,
                'promo_code_id' => $promo?->id,
                'renewed_from_id' => $renewing?->id,
                'amount' => Money::decimal($cents),
                'status' => 'active',
                'auto_renew' => $autoRenew && $tariff->service !== 'coming_soon' && $tariff->service !== 'boost',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            $user->decrement('balance', $cents / 100);
            $user->transactions()->create([
                'order_id' => $order->id,
                'type' => 'charge',
                'amount' => Money::decimal(-$cents),
                'currency' => config('graj.billing.currency'),
                'status' => 'succeeded',
            ]);
            $promo?->increment('used');

            if ($tariff->service === 'highlight') {
                $server->forceFill(['highlighted_until' => $endsAt])->saveQuietly();
            }

            return $order;
        });

        HomePageData::forget();

        return $order;
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function period(Server $server, Tariff $tariff): array
    {
        if ($tariff->service === 'boost') {
            // Бонусні бали діють до кінця місяця: 1-го числа рейтинг починається заново
            return [now(), now()->endOfMonth()];
        }

        if ($tariff->service === 'coming_soon') {
            if (! $server->opens_at?->isFuture()) {
                throw BillingException::because('no_opening');
            }
            if ($this->latestEnd($server, 'coming_soon')) {
                throw BillingException::because('already_active');
            }

            return [now(), $server->opens_at->copy()->endOfDay()];
        }

        $current = $this->latestEnd($server, $tariff->service);

        // VIP-місць на гру обмежено: нове місце — лише якщо сервер ще не у VIP і вільні місця є
        if ($tariff->service === 'vip' && ! $current && $this->vipTaken($server) >= $server->game->vip_slots) {
            throw BillingException::because('vip_full');
        }

        $start = $current && $current->isFuture() ? $current->copy() : now();

        return [$start, $start->copy()->addDays((int) $tariff->days)];
    }

    /** Кінець останньої активної (або вже оплаченої наперед) послуги цього типу */
    private function latestEnd(Server $server, string $service): ?Carbon
    {
        $end = Order::where('server_id', $server->id)->where('status', 'active')->where('ends_at', '>', now())
            ->whereHas('tariff', fn ($q) => $q->where('service', $service))
            ->max('ends_at');

        return $end ? Carbon::parse($end) : null;
    }

    /** Скільки серверів гри зараз займають VIP-місця */
    public function vipTaken(Server $server): int
    {
        return Order::active()
            ->whereHas('tariff', fn ($q) => $q->where('service', 'vip'))
            ->whereHas('server', fn ($q) => $q->where('game_id', $server->game_id))
            ->distinct()->count('server_id');
    }

    /**
     * Нагадати про закінчення послуг за кілька днів (один раз на замовлення).
     */
    public function remindExpiring(): int
    {
        $orders = Order::with(['tariff', 'server', 'user'])->where('status', 'active')->whereNull('reminded_at')
            ->whereBetween('ends_at', [now(), now()->addDays((int) config('graj.billing.remind_days'))])
            ->get()
            ->reject(fn (Order $o) => $this->hasSuccessor($o));

        foreach ($orders as $order) {
            $order->update(['reminded_at' => now()]);
            $order->user?->notify(new ServiceNotice($order, $order->auto_renew ? 'will_renew' : 'expiring'));
        }

        return $orders->count();
    }

    /**
     * Завершити послуги, у яких минув термін; з автопродовженням — купити наступний період з балансу.
     */
    public function expire(): int
    {
        $orders = Order::with(['tariff', 'server.game', 'user'])->where('status', 'active')->whereNotNull('ends_at')->where('ends_at', '<=', now())->get();

        foreach ($orders as $order) {
            $order->update(['status' => 'expired']);
            if ($this->hasSuccessor($order) || ! $order->user || ! $order->server) {
                continue;
            }

            if ($order->auto_renew) {
                try {
                    $renewed = $this->purchase($order->user, $order->server, $order->tariff, autoRenew: true, renewing: $order);
                    $order->user->notify(new ServiceNotice($renewed, 'renewed'));

                    continue;
                } catch (BillingException $e) {
                    $order->user->notify(new ServiceNotice($order, 'renew_failed', $e->getMessage()));

                    continue;
                }
            }

            $order->user->notify(new ServiceNotice($order, 'expired'));
        }

        return $orders->count();
    }

    /** Послугу вже продовжено наступним оплаченим замовленням */
    private function hasSuccessor(Order $order): bool
    {
        return Order::where('server_id', $order->server_id)->whereKeyNot($order->id)
            ->where('status', 'active')->where('ends_at', '>', $order->ends_at)
            ->whereHas('tariff', fn ($q) => $q->where('service', $order->tariff->service))
            ->exists();
    }

    /**
     * Забронювати рекламне місце з дати $start на термін тарифу й оплатити з балансу.
     * На місці (і грі — для місця «game») одночасно показується лише один банер.
     */
    public function bookBanner(User $user, Server $server, Tariff $tariff, CarbonInterface $start, string $image, string $url, ?string $alt = null, ?string $promoCode = null): Banner
    {
        if (! $tariff->is_active || $tariff->service !== 'banner' || ! isset(Banner::PLACEMENTS[$tariff->placement]) || ($tariff->game_id && $tariff->game_id !== $server->game_id)) {
            throw BillingException::because('tariff');
        }
        if ($server->status !== ServerStatus::Published) {
            throw BillingException::because('not_published');
        }
        $start = Carbon::instance($start)->startOfDay();
        if ($start->lt(today()) || $start->gt(today()->addDays((int) config('graj.billing.banner_booking_days')))) {
            throw BillingException::because('banner_date');
        }
        $promo = $this->findPromo($promoCode);
        if ($promoCode && ! $promo?->appliesTo($tariff)) {
            throw BillingException::because('promo');
        }

        $gameId = $tariff->placement === 'game' ? $server->game_id : null;
        $end = $start->copy()->addDays((int) $tariff->days);

        $banner = DB::transaction(function () use ($user, $server, $tariff, $promo, $start, $end, $gameId, $image, $url, $alt) {
            $user = User::whereKey($user->id)->lockForUpdate()->first();
            if ($this->bannerTaken($tariff->placement, $gameId, $start, $end)) {
                throw BillingException::because('banner_busy');
            }
            $cents = $this->price($tariff, $promo);
            if (Money::cents($user->balance) < $cents) {
                throw BillingException::because('balance', ['price' => Money::format($cents, true)]);
            }

            $order = $user->orders()->create([
                'server_id' => $server->id,
                'tariff_id' => $tariff->id,
                'promo_code_id' => $promo?->id,
                'amount' => Money::decimal($cents),
                'status' => 'active',
                'auto_renew' => false,
                'starts_at' => $start,
                'ends_at' => $end,
            ]);
            $user->decrement('balance', $cents / 100);
            $user->transactions()->create([
                'order_id' => $order->id,
                'type' => 'charge',
                'amount' => Money::decimal(-$cents),
                'currency' => config('graj.billing.currency'),
                'status' => 'succeeded',
            ]);
            $promo?->increment('used');

            return Banner::create([
                'order_id' => $order->id,
                'server_id' => $server->id,
                'game_id' => $gameId,
                'placement' => $tariff->placement,
                'image' => $image,
                'alt' => $alt ?: $server->name,
                'url' => $url,
                'starts_at' => $start,
                'ends_at' => $end,
            ]);
        });

        return $banner;
    }

    /** Чи перетинається період з уже заброньованим банером на тому ж місці */
    public function bannerTaken(string $placement, ?int $gameId, CarbonInterface $start, CarbonInterface $end): bool
    {
        return $this->bannersOn($placement, $gameId)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
    }

    /**
     * Зайняті дні місця на найближчий період бронювання (для календаря в кабінеті).
     *
     * @return list<string> дати Y-m-d
     */
    public function busyDays(string $placement, ?int $gameId): array
    {
        $from = today();
        $to = today()->addDays((int) config('graj.billing.banner_booking_days') + 31);
        $days = [];

        foreach ($this->bannersOn($placement, $gameId)->where('ends_at', '>', $from)->where('starts_at', '<', $to)->get(['starts_at', 'ends_at']) as $b) {
            for ($d = $b->starts_at->copy()->startOfDay(); $d->lt($b->ends_at); $d->addDay()) {
                $days[] = $d->toDateString();
            }
        }

        return array_values(array_unique($days));
    }

    /** @return Builder<Banner> */
    private function bannersOn(string $placement, ?int $gameId): Builder
    {
        return Banner::where('placement', $placement)
            ->where('is_active', true)
            ->when($placement === 'game', fn ($q) => $q->where('game_id', $gameId), fn ($q) => $q->whereNull('game_id'))
            ->where(fn ($q) => $q->whereNull('order_id')->orWhereHas('order', fn ($o) => $o->whereNotIn('status', ['cancelled', 'refunded'])));
    }

    /** Скасувати замовлення адміністратором; з поверненням — сума повертається на баланс */
    public function cancel(Order $order, bool $refund): void
    {
        $order->loadMissing(['tariff', 'server', 'user']);
        DB::transaction(function () use ($order, $refund) {
            $order->update(['status' => $refund ? 'refunded' : 'cancelled', 'auto_renew' => false]);
            if ($order->tariff->service === 'highlight') {
                $order->server?->forceFill(['highlighted_until' => $this->latestEnd($order->server, 'highlight')])->saveQuietly();
            }
            if ($refund && $order->user) {
                User::whereKey($order->user_id)->lockForUpdate()->first()->increment('balance', Money::cents($order->amount) / 100);
                $order->user->transactions()->create([
                    'order_id' => $order->id,
                    'type' => 'refund',
                    'amount' => $order->amount,
                    'currency' => config('graj.billing.currency'),
                    'status' => 'succeeded',
                ]);
            }
        });

        HomePageData::forget();
    }

    /** Ручне коригування балансу адміністратором (компенсація, помилка оплати) */
    public function adjust(User $user, int $cents, string $reason): Transaction
    {
        return DB::transaction(function () use ($user, $cents, $reason) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();
            if (Money::cents($locked->balance) + $cents < 0) {
                throw BillingException::because('negative');
            }
            $locked->increment('balance', $cents / 100);

            return $locked->transactions()->create([
                'type' => 'adjustment',
                'amount' => Money::decimal($cents),
                'currency' => config('graj.billing.currency'),
                'status' => 'succeeded',
                'payload' => ['reason' => $reason, 'by' => auth()->id()],
            ]);
        });
    }
}
