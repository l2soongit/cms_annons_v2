<?php

namespace App\Filament\Pages;

use App\Update\Updater;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnitEnum;

/**
 * Оновлення сайту: поточна й нова версія, список змін, автооновлення, ручне оновлення, архів вручну, відкат.
 * Важку роботу (завантаження, заміна файлів) робить cron — `site:update --tick`, без обмеження часу запиту.
 * Якщо cron не працює, ті самі кроки виконуються в браузері: заміна файлів, потім міграції в наступному запиті.
 */
class Updates extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Система';

    protected static ?string $navigationLabel = 'Оновлення';

    protected static ?string $title = 'Оновлення сайту';

    protected static ?string $slug = 'updates';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.updates';

    /** @var list<string> */
    public array $log = [];

    public static function canAccess(): bool
    {
        // Заміна коду сайту — найвищий рівень доступу
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return app(Updater::class)->hasUpdate() ? 'нове' : null;
    }

    public function mount(Updater $updater): void
    {
        // Файли замінено в попередньому запиті — тут уже новий код: міграції й кеш
        if ($updater->pending() && ! Updater::cronAlive()) {
            $this->log = $updater->finalize();
            $last = $updater->history()[0] ?? null;
            Notification::make()->title($last && $last['ok'] ? "Оновлено до {$last['to']}" : 'Оновлення завершилося з помилкою')
                ->{$last && $last['ok'] ? 'success' : 'danger'}()->send();
        }
    }

    public function updater(): Updater
    {
        return app(Updater::class);
    }

    protected function getHeaderActions(): array
    {
        $u = $this->updater();
        $state = $u->state();
        $busy = $u->pending() || ! empty($state['queued']);
        $blocked = $u->isGitCheckout() || $busy;

        return [
            Action::make('check')->label('Перевірити')->icon(Heroicon::OutlinedMagnifyingGlass)->color('gray')
                ->action(function () use ($u) {
                    try {
                        $latest = $u->latest();
                        Notification::make()->title($u->hasUpdate($latest) ? "Доступна версія {$latest['tag']}" : 'У вас остання версія')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Не вдалося перевірити')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('update')->label(fn () => 'Оновити до '.($state['latest']['tag'] ?? ''))->icon(Heroicon::OutlinedArrowDownTray)
                ->visible($u->hasUpdate())->disabled($blocked)
                ->requiresConfirmation()
                ->modalHeading('Оновити сайт?')
                ->modalDescription('Перед оновленням зберігається резервна копія коду — відкат буде доступний тут же. Файли .env і теку storage оновлення не чіпає.')
                ->action(fn () => $this->run(['type' => 'latest'], fn () => $u->updateToLatest('manual'))),

            Action::make('upload')->label('Архів вручну')->icon(Heroicon::OutlinedArrowUpTray)->color('gray')->disabled($blocked)
                ->modalHeading('Оновити з архіву')
                ->modalDescription('Архів graj-club-….zip з релізу на GitHub або зібраний командою php tools/build-release.php.')
                ->schema([
                    FileUpload::make('archive')->label('Архів .zip')->disk('local')->directory('updates-upload')
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])->maxSize(200 * 1024)->required(),
                ])
                ->action(function (array $data) use ($u) {
                    $file = Storage::disk('local')->path($data['archive']);
                    $this->run(['type' => 'file', 'file' => $file], function () use ($u, $file) {
                        try {
                            return $u->stage($file, 'upload');
                        } finally {
                            @unlink($file);
                        }
                    });
                }),

            Action::make('rollback')->label('Відкотити')->icon(Heroicon::OutlinedArrowUturnLeft)->color('danger')
                ->visible(collect($u->history())->contains(fn ($h) => ! empty($h['backup'])))->disabled($blocked)
                ->requiresConfirmation()
                ->modalHeading('Повернути попередню версію коду?')
                ->modalDescription('Код повернеться з резервної копії, зробленої перед останнім оновленням. Зміни бази даних (міграції) не відкочуються.')
                ->action(fn () => $this->run(['type' => 'rollback'], fn () => $u->rollback())),
        ];
    }

    public function toggleAuto(): void
    {
        $u = $this->updater();
        $u->setAuto(! $u->autoEnabled());
        Notification::make()->title($u->autoEnabled() ? 'Автооновлення ввімкнено' : 'Автооновлення вимкнено')->success()->send();
    }

    /**
     * З cron — у чергу; без cron — одразу тут, а міграції — після перезавантаження сторінки (уже з новим кодом).
     *
     * @param  array<string, string>  $job
     */
    private function run(array $job, callable $now): void
    {
        $u = $this->updater();
        if (Updater::cronAlive()) {
            $u->queue($job);
            Notification::make()->title('Оновлення почнеться протягом хвилини')->body('Сторінка оновлюватиметься сама.')->success()->send();

            return;
        }

        try {
            @set_time_limit(0);
            $version = $now();
            if ($version === null) {
                Notification::make()->title('Оновлень немає')->success()->send();

                return;
            }
        } catch (Throwable $e) {
            $u->setState(['error' => $e->getMessage(), 'error_at' => time()]);
            Notification::make()->title('Оновлення не вдалося')->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        // Наступний запит виконає міграції вже з новим кодом (mount)
        $this->redirect(static::getUrl());
    }
}
