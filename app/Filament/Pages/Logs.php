<?php

namespace App\Filament\Pages;

use App\Support\LogReader;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * Логи сайту: файли storage/logs, останні записи зверху, фільтр за рівнем, пошук, повний текст помилки,
 * завантаження й очищення файлу. Лише для адміністраторів — у логах бувають адреси, IP і фрагменти запитів.
 */
class Logs extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Система';

    protected static ?string $navigationLabel = 'Логи';

    protected static ?string $title = 'Логи сайту';

    protected static ?string $slug = 'logs';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.logs';

    #[Url]
    public string $file = '';

    #[Url]
    public string $level = '';

    #[Url]
    public string $search = '';

    public int $limit = 100;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function reader(): LogReader
    {
        return app(LogReader::class);
    }

    public function mount(): void
    {
        // Невідомий файл у посиланні — показуємо найсвіжіший
        if ($this->reader()->path($this->file) === null) {
            $this->file = $this->reader()->files()[0]['name'] ?? '';
        }
        if (! in_array($this->level, LogReader::LEVELS, true)) {
            $this->level = '';
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['file', 'level', 'search'], true)) {
            $this->limit = 100;
        }
        if ($property === 'file' && $this->reader()->path($this->file) === null) {
            $this->file = $this->reader()->files()[0]['name'] ?? '';
        }
        if ($property === 'level' && ! in_array($this->level, LogReader::LEVELS, true)) {
            $this->level = '';
        }
    }

    public function more(): void
    {
        $this->limit = min($this->limit + 100, 1000);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')->label('Оновити')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->action(fn () => null),
            Action::make('download')->label('Завантажити')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->visible(fn () => $this->reader()->path($this->file) !== null)
                ->action(fn (): BinaryFileResponse => response()->download($this->reader()->path($this->file), $this->file)),
            Action::make('clear')->label('Очистити')->icon(Heroicon::OutlinedTrash)->color('danger')
                ->visible(fn () => $this->reader()->path($this->file) !== null)
                ->requiresConfirmation()
                ->modalHeading(fn () => "Очистити {$this->file}?")
                ->modalDescription('Усі записи цього файлу буде видалено. Нові помилки й далі писатимуться в нього.')
                ->action(function () {
                    $this->reader()->clear($this->file)
                        ? Notification::make()->title('Лог очищено')->success()->send()
                        : Notification::make()->title('Не вдалося очистити файл')->danger()->send();
                }),
        ];
    }
}
