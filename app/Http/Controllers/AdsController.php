<?php

namespace App\Http\Controllers;

use App\Billing\Billing;
use App\Billing\BillingException;
use App\Enums\ServerStatus;
use App\Models\Banner;
use App\Models\Server;
use App\Models\Tariff;
use App\Support\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Рекламні банери в кабінеті: бронювання місця з дати, оплата з балансу, статистика показів і кліків.
 */
class AdsController extends Controller
{
    public function index(Request $request, Billing $billing): View
    {
        $user = $request->user();
        $servers = $this->servers($request);
        $gameIds = $servers->pluck('game_id')->unique();

        $tariffs = Tariff::where('service', 'banner')->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('game_id')->orWhereIn('game_id', $gameIds))
            ->with('game')->orderBy('placement')->orderBy('days')->get()
            // Бічна колонка гри — лише там, де стиль сторінки її має
            ->reject(fn (Tariff $t) => $t->placement === 'game_side' && ! in_array($t->game?->themeKey(), Banner::SIDE_COLUMN_THEMES, true))
            ->values();

        // Зайняті дні: шапка й сайдбар — спільні, місця на сторінці гри — для кожної гри окремо
        $busy = [];
        foreach ($tariffs as $tariff) {
            $gameId = Banner::isPerGame($tariff->placement) ? $tariff->game_id : null;
            $busy[$tariff->placement.'-'.$gameId] ??= $billing->busyDays($tariff->placement, $gameId);
        }

        return view('cabinet.ads', [
            'user' => $user,
            'servers' => $servers,
            'tariffs' => $tariffs,
            'busy' => $busy,
            'banners' => Banner::with(['server', 'game', 'order'])
                ->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
                ->latest('starts_at')->get(),
        ]);
    }

    public function store(Request $request, Billing $billing): RedirectResponse
    {
        $servers = $this->servers($request);
        $data = $request->validate([
            'server_id' => ['required', Rule::in($servers->pluck('id'))],
            'tariff_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'image' => ['required', 'image', 'mimes:webp,png,jpg,jpeg,gif', 'max:2048'],
            'link' => ['required', Rule::in(['server', 'site'])],
            'alt' => ['nullable', 'string', 'max:120'],
            'promo_code' => ['nullable', 'string', 'max:32'],
        ]);

        $server = $servers->firstWhere('id', (int) $data['server_id']);
        $tariff = Tariff::where('service', 'banner')->findOrFail($data['tariff_id']);
        $url = $data['link'] === 'site' && $server->website ? $server->website : route('servers.show', $server);
        $image = ImageOptimizer::store($request->file('image'), 'banners', 'banner');

        try {
            $banner = $billing->bookBanner($request->user(), $server, $tariff, Carbon::parse($data['starts_at']), $image, $url, $data['alt'] ?? null, $data['promo_code'] ?? null);
        } catch (BillingException $e) {
            Storage::disk('public')->delete($image);

            return back()->withInput()->withErrors(['tariff_id' => $e->getMessage()]);
        }

        return redirect()->route('cabinet.ads')->with('status', __('billing.ads.booked', [
            'from' => $banner->starts_at->format('d.m.Y'),
            'to' => $banner->ends_at->copy()->subSecond()->format('d.m.Y'),
        ]));
    }

    /** Перехід з банера: рахуємо клік і ведемо на сервер */
    public function click(Banner $banner): RedirectResponse
    {
        abort_unless($banner->is_active, 404);
        // Зі сторінки, відкритої до кінця показу, перехід працює, але в статистику йде лише в оплачені дні
        if ($banner->starts_at <= now() && $banner->ends_at > now()) {
            $banner->increment('clicks');
        }

        return redirect()->away($banner->url);
    }

    /** Опубліковані сервери, якими користувач керує (власні й спільні) */
    private function servers(Request $request)
    {
        $user = $request->user();

        return Server::where('status', ServerStatus::Published)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereHas('coOwners', fn ($c) => $c->whereKey($user->id)))
            ->with('game')->orderBy('name')->get();
    }
}
