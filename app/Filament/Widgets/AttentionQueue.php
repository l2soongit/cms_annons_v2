<?php

namespace App\Filament\Widgets;

use App\Enums\ServerStatus;
use App\Filament\Pages\Logs;
use App\Filament\Pages\Updates;
use App\Filament\Resources\ReportResource;
use App\Filament\Resources\ReviewResource;
use App\Filament\Resources\ServerResource;
use App\Filament\Resources\TicketResource;
use App\Filament\Resources\VoteResource;
use App\Models\Report;
use App\Models\Review;
use App\Models\Server;
use App\Models\Ticket;
use App\Models\Vote;
use App\Support\LogReader;
use App\Update\Updater;
use Filament\Widgets\Widget;
use Throwable;

/**
 * Інфопанель: «Потребує уваги» — що чекає на модератора чи адміністратора, з посиланням на кожен список.
 * Показуються лише рядки, до яких у користувача є доступ; порожні — сірим, щоб було видно «усе розібрано».
 */
class AttentionQueue extends Widget
{
    protected static ?int $sort = 0;

    protected string $view = 'filament.widgets.attention-queue';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return list<array{label: string, hint: string, count: int|string, url: string, tone: string, icon: string}>
     */
    public function items(): array
    {
        $items = [];
        $add = function (bool $can, string $label, string $hint, int|string $count, string $url, string $icon, string $tone = 'warning') use (&$items) {
            if ($can) {
                $items[] = compact('label', 'hint', 'count', 'url', 'icon') + ['tone' => $count ? $tone : 'gray'];
            }
        };

        $servers = ServerResource::canViewAny();
        $add($servers, 'Нові сервери', 'чекають на перевірку', Server::where('status', ServerStatus::Pending)->count(),
            ServerResource::getUrl('index', ['tableFilters' => ['status' => ['value' => ServerStatus::Pending->value]]]), 'heroicon-o-server-stack');
        $add($servers, 'Зміни серверів', 'власники змінили опис чи поля', Server::whereNotNull('pending_changes')->count(),
            ServerResource::getUrl('index', ['tableFilters' => ['queue' => ['isActive' => true]]]), 'heroicon-o-pencil-square');
        $add(ReviewResource::canViewAny(), 'Відгуки', 'на модерації', Review::where('status', 'pending')->count(), ReviewResource::getUrl('index'), 'heroicon-o-chat-bubble-left-right');
        $add(ReportResource::canViewAny(), 'Скарги', 'відкриті', Report::where('status', 'open')->count(), ReportResource::getUrl('index'), 'heroicon-o-flag', 'danger');
        $add(VoteResource::canViewAny(), 'Підозрілі голоси', 'накрутка чи боти', Vote::where('status', 'suspicious')->count(), VoteResource::getUrl('index'), 'heroicon-o-hand-thumb-up');
        $add(TicketResource::canViewAny(), 'Тікети підтримки', 'без відповіді', Ticket::where('status', 'open')->count(), TicketResource::getUrl('index'), 'heroicon-o-lifebuoy');
        $add(Logs::canAccess(), 'Помилки сайту', 'за останню добу', app(LogReader::class)->errorsSince(now()->subDay()), Logs::getUrl(['level' => 'error']), 'heroicon-o-exclamation-triangle', 'danger');

        if (Updates::canAccess()) {
            try {
                $update = app(Updater::class)->hasUpdate();
            } catch (Throwable) {
                $update = false;
            }
            $add(true, 'Оновлення сайту', $update ? 'доступна нова версія' : 'остання версія', $update ? 'нове' : 0, Updates::getUrl(), 'heroicon-o-arrow-path-rounded-square', 'info');
        }

        return $items;
    }
}
