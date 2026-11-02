<?php

namespace App\Filament\Widgets;

use App\Models\Server;
use App\Models\User;
use App\Models\Vote;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Інфопанель: голоси, нові сервери й реєстрації за останні 14 днів — видно, чи росте сайт.
 */
class ActivityChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Активність за 14 днів';

    protected ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $from = today()->subDays(13);
        $days = collect(range(0, 13))->map(fn (int $i) => $from->copy()->addDays($i));
        $perDay = fn ($query) => $query->where('created_at', '>=', $from)->get(['created_at'])
            ->countBy(fn ($row) => Carbon::parse($row->created_at)->toDateString());

        $votes = $perDay(Vote::where('status', 'counted'));
        $servers = $perDay(Server::query());
        $users = $perDay(User::query());
        $series = fn ($counts) => $days->map(fn (Carbon $d) => $counts[$d->toDateString()] ?? 0)->all();

        return [
            'datasets' => [
                ['label' => 'Голоси', 'data' => $series($votes), 'borderColor' => '#f59e0b', 'backgroundColor' => '#f59e0b22', 'fill' => true, 'tension' => .35],
                ['label' => 'Нові сервери', 'data' => $series($servers), 'borderColor' => '#10b981', 'backgroundColor' => 'transparent', 'tension' => .35],
                ['label' => 'Реєстрації', 'data' => $series($users), 'borderColor' => '#6366f1', 'backgroundColor' => 'transparent', 'tension' => .35],
            ],
            'labels' => $days->map(fn (Carbon $d) => $d->format('d.m'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
