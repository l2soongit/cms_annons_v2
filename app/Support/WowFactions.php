<?php

namespace App\Support;

use App\Models\Game;
use App\Models\Server;

/**
 * «Війна фракцій» на сторінці WoW: гравці Альянсу й Орди онлайн на реалмах, що надсилають баланс через API
 * (приватні сервери WoW не відповідають на query-запити, тож без API баланс невідомий і не вигадується).
 */
final class WowFactions
{
    /** Частка гравців фракції, з якої реалм вважається рівним (гравці часто шукають саме такі) */
    public const BALANCED_MIN = 40;

    /**
     * @return array{alliance: int, horde: int, realms: int, alliance_pct: float|null, rows: list<array{label: string, alliance: int, horde: int, pct: float}>}
     */
    public static function summary(Game $game, ?string $byField = 'expansion'): array
    {
        $reporting = $game->servers()->published()->where('is_online', true)
            ->whereNotNull('alliance')->whereNotNull('horde')
            ->with('fieldValues.field')
            ->get(['id', 'alliance', 'horde']);

        $alliance = (int) $reporting->sum('alliance');
        $horde = (int) $reporting->sum('horde');
        $total = $alliance + $horde;

        // Розклад за версією клієнта: Classic, TBC, WotLK…
        $rows = $reporting
            ->groupBy(fn (Server $s) => $s->fieldOption($byField)['label'] ?? null)
            ->filter(fn ($group, $label) => $label !== '')
            ->map(function ($group, $label) {
                $a = (int) $group->sum('alliance');
                $h = (int) $group->sum('horde');

                return ['label' => (string) $label, 'alliance' => $a, 'horde' => $h, 'pct' => $a + $h ? round($a / ($a + $h) * 100, 1) : 50.0];
            })
            ->filter(fn ($row) => $row['alliance'] + $row['horde'] > 0)
            ->sortByDesc(fn ($row) => $row['alliance'] + $row['horde'])
            ->values()->all();

        return [
            'alliance' => $alliance,
            'horde' => $horde,
            'realms' => $reporting->count(),
            'alliance_pct' => $total ? round($alliance / $total * 100, 1) : null,
            'rows' => $rows,
        ];
    }

    /** Частка Альянсу на реалмі або null, якщо сервер не надсилає баланс */
    public static function share(Server $server): ?int
    {
        $total = (int) $server->alliance + (int) $server->horde;

        return $server->alliance !== null && $server->horde !== null && $total > 0 ? (int) round($server->alliance / $total * 100) : null;
    }

    /** Колір рейтів як якість предметів у грі: x1 білий, далі зелений, синій, фіолетовий, помаранчевий */
    public static function rateColor(?string $label): string
    {
        $x = (int) preg_replace('/\D/', '', (string) $label);

        return match (true) {
            $x <= 1 => '#f2f2f2',
            $x <= 5 => '#1eff00',
            $x <= 50 => '#3b9bff',
            $x <= 300 => '#b86cf5',
            default => '#ff8000',
        };
    }

    /** Рядок для клієнта гри: set realmlist адреса (порт — лише нестандартний) */
    public static function realmlist(Server $server): string
    {
        $port = $server->port && $server->port !== 3724 ? ':'.$server->port : '';

        return 'set realmlist '.$server->host.$port;
    }
}
