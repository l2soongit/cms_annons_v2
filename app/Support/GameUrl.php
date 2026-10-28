<?php

namespace App\Support;

use App\Models\Game;
use App\Models\GameField;
use Illuminate\Support\Collection;

/**
 * ЧПУ сторінки гри (ТЗ 4, SEO): значення полів з позначкою «У URL» — сегменти шляху в порядку полів
 * (graj.club/lineage2/interlude/x100), решта фільтрів, сортування й пошук — параметри запиту.
 */
final class GameUrl
{
    /** @param  array<string, string|null>  $params  фільтри (ключ поля => значення), sort, q, page */
    public static function to(Game $game, array $params = []): string
    {
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
        $segments = [];

        foreach (self::pathFields($game) as $field) {
            if (isset($params[$field->key])) {
                $segments[] = $params[$field->key];
                unset($params[$field->key]);
            }
        }

        return route('games.show', ['game' => $game->slug, 'filters' => $segments ? implode('/', $segments) : null] + $params);
    }

    /**
     * Сегменти шляху → фільтри. null — сегмент не відповідає жодному варіанту (сторінки немає).
     *
     * @return array<string, string>|null
     */
    public static function parse(Game $game, ?string $path): ?array
    {
        $active = [];
        $fields = self::pathFields($game);

        foreach (array_filter(explode('/', (string) $path)) as $segment) {
            $field = $fields->first(fn (GameField $f) => ! isset($active[$f->key]) && collect($f->options)->contains('value', $segment));
            if (! $field) {
                return null;
            }
            $active[$field->key] = $segment;
        }

        return $active;
    }

    /** @return Collection<int, GameField> поля-фільтри, що йдуть у шлях, у порядку полів */
    public static function pathFields(Game $game): Collection
    {
        return $game->fields->where('is_filterable', true)->where('in_url', true)->sortBy('sort')->values();
    }
}
