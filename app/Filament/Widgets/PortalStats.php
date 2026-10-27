<?php

namespace App\Filament\Widgets;

use App\Enums\ServerStatus;
use App\Models\Report;
use App\Models\Review;
use App\Models\Server;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vote;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Дашборд адмінки (ТЗ 3.9): нові сервери, голоси, дохід, черга модерації.
 */
class PortalStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $queue = Server::where('status', ServerStatus::Pending)->orWhereNotNull('pending_changes')->count()
            + Review::where('status', 'pending')->count()
            + Report::where('status', 'open')->count()
            + Vote::where('status', 'suspicious')->count();

        $revenue = Transaction::where('type', 'deposit')->where('status', 'succeeded')
            ->where('created_at', '>=', now()->startOfMonth())->sum('amount');

        return [
            Stat::make('Черга модерації', $queue)
                ->description('сервери, відгуки, скарги, підозрілі голоси')
                ->color($queue > 0 ? 'warning' : 'success')
                ->url(route('filament.admin.resources.servers.index')),
            Stat::make('Опубліковані сервери', Server::where('status', ServerStatus::Published)->count())
                ->description('+'.Server::where('created_at', '>=', now()->subDays(7))->count().' нових за 7 днів'),
            Stat::make('Голоси сьогодні', Vote::where('status', 'counted')->where('created_at', '>=', today())->count())
                ->description(Vote::where('status', 'counted')->where('created_at', '>=', now()->startOfMonth())->count().' за місяць'),
            Stat::make('Онлайн зараз', Server::where('status', ServerStatus::Published)->where('is_online', true)->count())
                ->description('серверів відповідають на запити'),
            Stat::make('Користувачі', User::count())
                ->description('+'.User::where('created_at', '>=', now()->subDays(7))->count().' за 7 днів'),
            Stat::make('Дохід за місяць', number_format((float) $revenue, 0, ',', ' ').' ₴')
                ->description('з’явиться з платними послугами (етап 3)'),
        ];
    }
}
