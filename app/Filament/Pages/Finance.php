<?php

namespace App\Filament\Pages;

use App\Models\Tariff;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Фінансовий звіт (ТЗ 3.7): поповнення, списання за послуги й повернення по місяцях, виручка за послугами.
 */
class Finance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Платні послуги';

    protected static ?string $navigationLabel = 'Фінансовий звіт';

    protected static ?string $title = 'Фінансовий звіт';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.finance';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('billing.manage') ?? false;
    }

    /** Останні 12 місяців: поповнення, списання (додатним числом), повернення, коригування */
    public function months(): array
    {
        $from = now()->subMonths(11)->startOfMonth();
        $rows = DB::table('transactions')->where('status', 'succeeded')->where('created_at', '>=', $from)
            ->get(['type', 'amount', 'created_at'])
            ->groupBy(fn ($t) => Carbon::parse($t->created_at)->format('Y-m'));

        return collect(range(0, 11))->map(function (int $i) use ($from, $rows) {
            $month = $from->copy()->addMonths($i);
            $list = $rows->get($month->format('Y-m'), collect());
            $sum = fn (string $type) => (float) $list->where('type', $type)->sum('amount');

            return [
                'label' => $month->translatedFormat('F Y'),
                'deposit' => $sum('deposit'),
                'charge' => -$sum('charge'),
                'refund' => $sum('refund'),
                'adjustment' => $sum('adjustment'),
            ];
        })->reverse()->values()->all();
    }

    /** Виручка за послугами в поточному місяці (за вирахуванням повернень) */
    public function services(): array
    {
        $rows = DB::table('orders')->join('tariffs', 'tariffs.id', '=', 'orders.tariff_id')
            ->where('orders.created_at', '>=', now()->startOfMonth())
            ->whereNotIn('orders.status', ['refunded'])
            ->groupBy('tariffs.service')
            ->selectRaw('tariffs.service, count(*) as n, sum(orders.amount) as total')
            ->get()->keyBy('service');

        return collect(Tariff::SERVICES)->map(fn ($label, $service) => [
            'label' => $label,
            'count' => (int) ($rows[$service]->n ?? 0),
            'total' => (float) ($rows[$service]->total ?? 0),
        ])->all();
    }

    /** Скільки коштів зараз лежить на балансах користувачів (зобов’язання порталу) */
    public function balances(): float
    {
        return (float) DB::table('users')->sum('balance');
    }
}
