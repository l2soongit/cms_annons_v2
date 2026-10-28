<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Віджети для сайтів серверів (ТЗ 7.3): кнопка «Голосувати» і банер-статус у кількох розмірах.
 * Картинки — SVG (кирилиця без шрифтів на сервері), кешуються на 5 хвилин.
 */
class WidgetController extends Controller
{
    /** Розміри: ключ => [ширина, висота] */
    public const VOTE_SIZES = ['88x31' => [88, 31], '120x40' => [120, 40], '200x60' => [200, 60]];

    public const STATUS_SIZES = ['468x60' => [468, 60], '350x80' => [350, 80], '88x31' => [88, 31]];

    private const TTL = 300;

    public function vote(Request $request, Server $server): Response
    {
        $size = array_key_exists($request->query('size'), self::VOTE_SIZES) ? $request->query('size') : '120x40';

        return $this->svg("widget:vote:{$server->id}:{$size}:".app()->getLocale(), fn () => $this->voteSvg($server, ...self::VOTE_SIZES[$size]));
    }

    public function status(Request $request, Server $server): Response
    {
        $size = array_key_exists($request->query('size'), self::STATUS_SIZES) ? $request->query('size') : '468x60';

        $server->loadMissing('game');

        return $this->svg("widget:status:{$server->id}:{$size}:".app()->getLocale(), fn () => $this->statusSvg($server, ...self::STATUS_SIZES[$size]));
    }

    private function svg(string $key, callable $render): Response
    {
        return response(Cache::remember($key, self::TTL, $render), 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age='.self::TTL,
            'X-Content-Type-Options' => 'nosniff',
            // Віджет вставляють на чужі сайти: картинку можна показувати будь-де, але не виконувати як документ
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]);
    }

    private function voteSvg(Server $server, int $w, int $h): string
    {
        $label = e(__('widgets.vote'));
        $font = max(10, (int) round($h * 0.36));
        $small = $h < 40;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}" role="img" aria-label="{$label} — graj.club">
<defs><linearGradient id="g" x1="0" x2="1"><stop offset="0" stop-color="#fbbf24"/><stop offset="1" stop-color="#f97316"/></linearGradient></defs>
<rect width="{$w}" height="{$h}" rx="6" fill="url(#g)"/>
<text x="50%" y="{$this->baseline($h, $small ? 0.5 : 0.42)}" text-anchor="middle" font-family="system-ui,Segoe UI,Arial,sans-serif" font-size="{$font}" font-weight="800" fill="#1c1003">{$label}</text>
{$this->when(! $small, '<text x="50%" y="'.$this->baseline($h, 0.82).'" text-anchor="middle" font-family="system-ui,Segoe UI,Arial,sans-serif" font-size="'.max(8, (int) round($h * 0.2)).'" font-weight="600" fill="#1c1003" opacity=".75">graj.club</text>')}
</svg>
SVG;
    }

    private function statusSvg(Server $server, int $w, int $h): string
    {
        $published = $server->status === ServerStatus::Published;
        $online = $published && $server->is_online;
        $name = e(Str::limit($server->name, $w < 200 ? 10 : 28));
        $dot = $online ? '#34d399' : '#ef4444';
        $state = e(! $published ? __('widgets.unavailable') : ($online ? __('widgets.players', ['n' => $server->players, 'max' => $server->max_players]) : __('widgets.offline')));
        $rank = $published && $server->rank ? e(__('widgets.rank', ['n' => $server->rank])) : '';

        if ($w < 200) {
            // Мініатюра 88×31: крапка статусу, назва й онлайн
            return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}" role="img" aria-label="{$name}: {$state}">
<rect width="{$w}" height="{$h}" rx="4" fill="#0f141c" stroke="#2a3342"/>
<circle cx="8" cy="10" r="3" fill="{$dot}"/>
<text x="15" y="13" font-family="system-ui,Segoe UI,Arial,sans-serif" font-size="9" font-weight="700" fill="#eaf2fb">{$name}</text>
<text x="6" y="25" font-family="system-ui,Segoe UI,Arial,sans-serif" font-size="8" fill="#93a4b8">{$state}</text>
</svg>
SVG;
        }

        $game = e($server->game->name);
        $nameY = $this->baseline($h, 0.4);
        $metaY = $this->baseline($h, 0.75);
        $right = $w - 12;

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}" role="img" aria-label="{$name}: {$state}">
<defs><linearGradient id="b" x1="0" x2="1"><stop offset="0" stop-color="#0f141c"/><stop offset="1" stop-color="#1a2230"/></linearGradient></defs>
<rect width="{$w}" height="{$h}" rx="8" fill="url(#b)" stroke="#2a3342"/>
<circle cx="18" cy="{$this->baseline($h, 0.32)}" r="5" fill="{$dot}"/>
<text x="30" y="{$nameY}" font-family="system-ui,Segoe UI,Arial,sans-serif" font-size="{$this->px($h, 0.26)}" font-weight="800" fill="#eaf2fb">{$name}</text>
<text x="14" y="{$metaY}" font-family="system-ui,Segoe UI,Arial,sans-serif" font-size="{$this->px($h, 0.19)}" fill="#93a4b8">{$game} · {$state}</text>
<text x="{$right}" y="{$nameY}" text-anchor="end" font-family="system-ui,Segoe UI,Arial,sans-serif" font-size="{$this->px($h, 0.24)}" font-weight="800" fill="#fbbf24">{$rank}</text>
<text x="{$right}" y="{$metaY}" text-anchor="end" font-family="system-ui,Segoe UI,Arial,sans-serif" font-size="{$this->px($h, 0.17)}" font-weight="700" fill="#f97316">graj.club</text>
</svg>
SVG;
    }

    private function baseline(int $h, float $ratio): int
    {
        return (int) round($h * $ratio);
    }

    private function px(int $h, float $ratio): int
    {
        return max(9, (int) round($h * $ratio));
    }

    private function when(bool $condition, string $svg): string
    {
        return $condition ? $svg : '';
    }
}
