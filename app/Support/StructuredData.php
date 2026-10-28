<?php

namespace App\Support;

use App\Models\Game;
use App\Models\Server;

/**
 * Розмітка schema.org для сторінок (ТЗ 4): хлібні крихти й рейтинг сервера за відгуками.
 */
final class StructuredData
{
    /** @param  list<array{0: string, 1: string}>  $items  [назва, адреса] */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item[0], 'item' => $item[1],
            ])->all(),
        ];
    }

    public static function game(Game $game, string $filters = ''): array
    {
        $items = [[__('ui.game.home'), route('home')], [$game->name, route('games.show', $game)]];
        if ($filters !== '') {
            $items[] = [$game->name.' '.$filters, url()->current()];
        }

        return self::breadcrumbs($items);
    }

    /**
     * Сервер — ігровий застосунок з рейтингом за опублікованими відгуками (AggregateRating, 1–5).
     *
     * @return list<array<string, mixed>>
     */
    public static function server(Server $server, ?float $average, int $count, ?string $image): array
    {
        $app = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => $server->name,
            'applicationCategory' => 'GameApplication',
            'operatingSystem' => 'Windows',
            'url' => route('servers.show', $server),
            'image' => $image,
            'description' => HtmlSanitizer::text($server->description) ?: null,
            'aggregateRating' => $count > 0 ? [
                '@type' => 'AggregateRating', 'ratingValue' => round((float) $average, 1), 'ratingCount' => $count, 'bestRating' => 5, 'worstRating' => 1,
            ] : null,
        ]);

        return [
            self::breadcrumbs([[__('ui.game.home'), route('home')], [$server->game->name, route('games.show', $server->game)], [$server->name, route('servers.show', $server)]]),
            $app,
        ];
    }
}
