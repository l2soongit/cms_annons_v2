<?php

namespace App\Community;

use App\Models\ForumCategory;

/**
 * Стандартна структура форуму (демо design/community/community-v2-d.html):
 * у кожної гри — підрозділи «Новини серверів», «Обговорення», «Гайди», «Клани»;
 * у «Власникам серверів» — «Технічна підтримка» й «Пропозиції»; у «Різне» — «Скриншоти й відео».
 * Створює лише те, чого ще немає (за slug), тож безпечно викликати повторно.
 */
class ForumSections
{
    /** Підрозділи гри: суфікс slug => [назви, іконка, дозволені типи тем] */
    public const GAME = [
        'news' => [['uk' => 'Новини серверів', 'en' => 'Server news', 'ru' => 'Новости серверов', 'pl' => 'Aktualności serwerów'], '📣', ['server_news']],
        'talk' => [['uk' => 'Обговорення', 'en' => 'Discussion', 'ru' => 'Обсуждение', 'pl' => 'Dyskusja'], '💬', ['discussion', 'question']],
        'guides' => [['uk' => 'Гайди', 'en' => 'Guides', 'ru' => 'Гайды', 'pl' => 'Poradniki'], '📘', ['guide']],
        'clans' => [['uk' => 'Клани', 'en' => 'Clans', 'ru' => 'Кланы', 'pl' => 'Klany'], '⚔️', ['clan']],
    ];

    public static function ensureAll(): void
    {
        ForumCategory::whereNull('parent_id')->whereNotNull('game_id')->get()->each(fn (ForumCategory $c) => self::ensureGame($c));

        if ($owners = ForumCategory::where('slug', 'owners')->first()) {
            self::child($owners, 'owners-support', ['uk' => 'Технічна підтримка', 'en' => 'Tech support', 'ru' => 'Техподдержка', 'pl' => 'Wsparcie techniczne'], '🧰', null, 1);
            self::child($owners, 'owners-ideas', ['uk' => 'Пропозиції', 'en' => 'Suggestions', 'ru' => 'Предложения', 'pl' => 'Propozycje'], '💡', null, 2);
        }

        if (! ForumCategory::where('slug', 'media')->exists()) {
            ForumCategory::create([
                'slug' => 'media',
                'name' => ['uk' => 'Скриншоти й відео', 'en' => 'Screenshots & videos', 'ru' => 'Скриншоты и видео', 'pl' => 'Zrzuty ekranu i wideo'],
                'description' => ['uk' => 'Ваші найкращі моменти з ігор', 'en' => 'Your best gaming moments', 'ru' => 'Ваши лучшие моменты из игр', 'pl' => 'Twoje najlepsze momenty z gier'],
                'icon' => '🖼', 'group' => 'other', 'sort' => (int) ForumCategory::max('sort') + 10,
            ]);
        }
    }

    /** Підрозділи одного розділу гри */
    public static function ensureGame(ForumCategory $game): void
    {
        $i = 0;
        foreach (self::GAME as $suffix => [$name, $icon, $types]) {
            self::child($game, $game->slug.'-'.$suffix, $name, $icon, $types, ++$i);
        }
    }

    private static function child(ForumCategory $parent, string $slug, array $name, string $icon, ?array $types, int $i): void
    {
        if (ForumCategory::where('slug', $slug)->exists()) {
            return;
        }

        ForumCategory::create([
            'parent_id' => $parent->id,
            'game_id' => $parent->game_id,
            'slug' => $slug,
            'name' => $name,
            'icon' => $icon,
            'group' => $parent->group,
            'sort' => $parent->sort + $i,
            'topic_types' => $types,
        ]);
    }
}
