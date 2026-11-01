<?php

namespace App\Filament\Pages;

use App\Analytics\Business;
use App\Analytics\Labels;
use App\Analytics\Report;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Аналітика порталу: відвідуваність, джерела, вміст, аудиторія, дії й воронки, бізнес-показники, роботи, «зараз на сайті».
 * Дані — власні (без Google Analytics і cookies), зведення оновлюються кожні 5 хвилин.
 */
class Analytics extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Аналітика';

    protected static ?string $title = 'Аналітика';

    protected static ?string $slug = 'analytics';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.analytics';

    public const TABS = [
        'overview' => 'Огляд',
        'sources' => 'Джерела',
        'content' => 'Вміст',
        'audience' => 'Аудиторія',
        'actions' => 'Дії й конверсії',
        'business' => 'Бізнес',
        'bots' => 'Роботи',
        'realtime' => 'Зараз на сайті',
    ];

    public const PERIODS = ['today' => 'Сьогодні', 'yesterday' => 'Вчора', '7' => '7 днів', '30' => '30 днів', 'month' => 'Цей місяць', '90' => '90 днів', '365' => 'Рік', 'custom' => 'Свій період'];

    #[Url]
    public string $tab = 'overview';

    #[Url]
    public string $period = '30';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('analytics.view') ?? false;
    }

    public static function canSeeMoney(): bool
    {
        return auth()->user()?->can('billing.manage') ?? false;
    }

    public function mount(): void
    {
        if (! array_key_exists($this->tab, self::TABS) || ($this->tab === 'business' && ! self::canSeeMoney())) {
            $this->tab = 'overview';
        }
        if (! array_key_exists($this->period, self::PERIODS)) {
            $this->period = '30';
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) && ($tab !== 'business' || self::canSeeMoney()) ? $tab : 'overview';
    }

    public function setPeriod(string $period): void
    {
        $this->period = array_key_exists($period, self::PERIODS) ? $period : '30';
        if ($period === 'custom') {
            $this->from ??= today()->subDays(29)->toDateString();
            $this->to ??= today()->toDateString();
        }
    }

    public function report(): Report
    {
        return Report::period($this->period, $this->from, $this->to);
    }

    private ?Labels $labels = null;

    public function labels(): Labels
    {
        return $this->labels ??= new Labels;
    }

    public function business(): Business
    {
        return new Business($this->report());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Експорт CSV')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn () => $this->export()),
            Action::make('refresh')->label('Оновити зараз')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->action(function () {
                    Artisan::call('analytics:rollup');
                    Notification::make()->title('Статистику за сьогодні перераховано')->success()->send();
                }),
        ];
    }

    /** Усі виміри за період одним файлом: вимір, значення, перегляди, відвідувачі, входи */
    public function export(): StreamedResponse
    {
        $report = $this->report();
        $labels = $this->labels();
        $rows = DB::table('analytics_stats')->where('server_id', 0)
            ->whereBetween('date', [$report->from->toDateString(), $report->to->toDateString()])
            ->groupBy('dim', 'key')
            ->selectRaw('dim, '.DB::connection()->getQueryGrammar()->wrap('key').' as k, SUM(views) as views, SUM(visitors) as visitors, SUM(entries) as entries, SUM(sessions) as sessions, SUM(bounces) as bounces, SUM(duration) as duration')
            ->orderBy('dim')->orderByDesc('views')->get();
        foreach (['game', 'server'] as $dim) {
            $labels->preload($dim, $rows->where('dim', $dim)->map(fn ($r) => ['key' => (string) $r->k]));
        }
        $name = 'analytics-'.$report->from->toDateString().'-'.$report->to->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows, $labels, $report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 для Excel
            fputcsv($out, ['Період', $report->from->toDateString().' — '.$report->to->toDateString()], ';');
            fputcsv($out, ['Вимір', 'Значення', 'Назва', 'Перегляди', 'Відвідувачі', 'Входи', 'Візити', 'Відмови', 'Тривалість, с'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [$r->dim, $r->k, $labels->label($r->dim, (string) $r->k), $r->views, $r->visitors, $r->entries, $r->sessions, $r->bounces, $r->duration], ';');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
