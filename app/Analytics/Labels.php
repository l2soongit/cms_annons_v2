<?php

namespace App\Analytics;

use App\Models\Game;
use App\Models\Server;
use Illuminate\Support\Collection;
use Locale;

/**
 * Людські назви значень вимірів: джерела, пристрої, події, типи сторінок, країни й мови, ігри й сервери.
 */
final class Labels
{
    /** @var array<string, array<int|string, string>> */
    private array $names = [];

    /** @param  Collection<int, array{key: string}>|iterable<array{key: string}>  $rows */
    public function preload(string $dim, iterable $rows): void
    {
        $ids = collect($rows)->pluck('key')->filter(fn ($k) => ctype_digit((string) $k))->map(fn ($k) => (int) $k)->all();
        if (! $ids) {
            return;
        }
        $this->names[$dim] = match ($dim) {
            'game' => Game::whereKey($ids)->get(['id', 'name'])->mapWithKeys(fn (Game $g) => [$g->id => $g->name])->all(),
            'server' => Server::withTrashed()->whereKey($ids)->pluck('name', 'id')->all(),
            default => [],
        };
    }

    public function label(string $dim, string $key): string
    {
        if ($key === '') {
            return __('analytics.unknown');
        }

        return match ($dim) {
            'source', 'device', 'event', 'funnel' => trans()->has("analytics.{$dim}.{$key}") ? __("analytics.{$dim}.{$key}") : $key,
            'route' => trans()->has('analytics.route.'.str_replace('.', '_', $key)) ? __('analytics.route.'.str_replace('.', '_', $key)) : $key,
            'country' => self::country($key),
            'lang' => self::language($key),
            'locale' => strtoupper($key),
            'game', 'server' => $this->names[$dim][(int) $key] ?? "#{$key}",
            'search' => str_ends_with($key, ' ∅') ? mb_substr($key, 0, -2).' — '.__('analytics.no_results') : $key,
            default => $key,
        };
    }

    public static function country(string $code): string
    {
        $name = class_exists(Locale::class) ? Locale::getDisplayRegion('-'.$code, app()->getLocale()) : '';

        return ($name && $name !== $code ? $name : $code).' '.self::flag($code);
    }

    public static function language(string $code): string
    {
        $name = class_exists(Locale::class) ? Locale::getDisplayLanguage($code, app()->getLocale()) : '';

        return $name && $name !== $code ? mb_convert_case(mb_substr($name, 0, 1), MB_CASE_UPPER).mb_substr($name, 1) : strtoupper($code);
    }

    /** Прапор країни з емодзі регіональних літер */
    public static function flag(string $code): string
    {
        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);
    }

    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        return $seconds >= 60 ? intdiv($seconds, 60).' '.__('analytics.min').' '.($seconds % 60).' '.__('analytics.sec') : $seconds.' '.__('analytics.sec');
    }
}
