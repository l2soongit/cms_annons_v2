<?php

namespace App\Analytics;

use App\Models\Game;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Запис переглядів і подій. Помилка аналітики ніколи не ламає сторінку — запис просто пропускається.
 */
final class Tracker
{
    /** Події, які приймає /a/e з браузера (решту пишуть контролери самі) */
    public const CLIENT_EVENTS = ['website', 'discord', 'youtube', 'copy_ip', 'vote_click', 'outbound', 'share'];

    public const EVENTS = [...self::CLIENT_EVENTS, 'vote', 'search', 'banner_click', 'register', 'server_add', 'review', 'deposit'];

    public static function enabled(Request $request): bool
    {
        if (! config('graj.analytics.enabled')) {
            return false;
        }
        if (config('graj.analytics.respect_dnt') && ($request->headers->get('DNT') === '1' || $request->headers->get('Sec-GPC') === '1')) {
            return false;
        }

        return ! (config('graj.analytics.exclude_staff') && $request->user()?->isStaff());
    }

    /** Чи варто рахувати відповідь як перегляд сторінки */
    public static function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }
        // AJAX, Livewire, підвантаження списку на сторінці гри, попереднє завантаження браузером
        if ($request->ajax() || $request->headers->has('X-Livewire') || $request->expectsJson()
            || in_array($request->headers->get('Sec-Purpose') ?? $request->headers->get('Purpose'), ['prefetch', 'prerender'], true)) {
            return false;
        }

        return ! preg_match('#^(admin|livewire|filament|api|a/|up$|install)#', ltrim($request->path(), '/'));
    }

    public function view(Request $request): void
    {
        try {
            $visitor = Visitor::from($request);
            $route = $request->route();
            [$game, $server] = $this->context($route?->parameter('game'), $route?->parameter('server'));
            // Власник, що переглядає свій сервер, не накручує йому статистику
            $model = $route?->parameter('server');
            if ($model instanceof Server && $request->user()?->canManage($model)) {
                $server = null;
            }

            DB::table('analytics_views')->insert([
                'visitor' => $visitor->id,
                'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
                'route' => $route?->getName() ? mb_substr($route->getName(), 0, 48) : null,
                'game_id' => $game,
                'server_id' => $server,
                'user_id' => $request->user()?->id,
                'locale' => app()->getLocale(),
                'entry' => $visitor->entry,
                'source' => $visitor->source,
                'referrer' => $visitor->referrer,
                'utm_source' => $visitor->utmSource,
                'utm_medium' => $visitor->utmMedium,
                'utm_campaign' => $visitor->utmCampaign,
                'device' => $visitor->device,
                'browser' => $visitor->browser,
                'os' => $visitor->os,
                'lang' => $visitor->lang,
                'country' => $visitor->country,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Подія: дія відвідувача. Для подій із браузера $request — запит beacon (той самий IP і браузер, що й у перегляду).
     */
    public function event(Request $request, string $name, Server|int|null $server = null, Game|int|null $game = null, ?string $label = null): void
    {
        if (! self::enabled($request) || ! in_array($name, self::EVENTS, true)) {
            return;
        }

        try {
            $visitor = Visitor::from($request);
            if ($visitor->isBot()) {
                return;
            }
            if ($server instanceof Server) {
                $game ??= $server->game_id;
            }

            DB::table('analytics_events')->insert([
                'visitor' => $visitor->id,
                'name' => $name,
                'label' => $label !== null && $label !== '' ? mb_substr(trim($label), 0, 128) : null,
                'game_id' => $game instanceof Game ? $game->id : $game,
                'server_id' => $server instanceof Server ? $server->id : $server,
                'user_id' => $request->user()?->id,
                'source' => null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return array{0: ?int, 1: ?int} id гри й сервера з параметрів маршруту */
    private function context(mixed $game, mixed $server): array
    {
        if ($server instanceof Server) {
            return [$server->game_id, $server->id];
        }
        if ($game instanceof Game) {
            return [$game->id, null];
        }
        if (is_string($game) && $game !== '') {
            return [Game::where('slug', $game)->value('id'), null];
        }

        return [null, null];
    }
}
