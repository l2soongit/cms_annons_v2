<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\Pages;
use App\Models\ModerationLog;
use App\Models\Report;
use App\Models\Review;
use App\Models\Server;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Модерація';

    protected static ?string $modelLabel = 'скарга';

    protected static ?string $pluralModelLabel = 'Скарги';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        return Report::where('status', 'open')->count() ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reportable_type')->label('Об’єкт')
                    ->formatStateUsing(fn (string $state, Report $r) => match ($r->reportable::class ?? null) {
                        Server::class => 'Сервер: '.$r->reportable->name,
                        Review::class => 'Відгук на '.$r->reportable->server?->name,
                        default => $state,
                    })
                    ->url(fn (Report $r) => match ($r->reportable::class ?? null) {
                        Server::class => route('servers.show', $r->reportable),
                        Review::class => $r->reportable->server ? route('servers.show', $r->reportable->server).'#reviews' : null,
                        default => null,
                    }, shouldOpenInNewTab: true),
                TextColumn::make('reason')->label('Причина')->formatStateUsing(fn ($state) => __("reviews.reasons.{$state}")),
                TextColumn::make('comment')->label('Коментар')->limit(60)->wrap(),
                TextColumn::make('user.name')->label('Від кого'),
                TextColumn::make('status')->label('Статус')->badge()
                    ->color(fn (string $state) => ['open' => 'warning', 'resolved' => 'success', 'dismissed' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('created_at')->label('Дата')->since(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['open' => 'Відкриті', 'resolved' => 'Вирішені', 'dismissed' => 'Відхилені'])->default('open'),
            ])
            ->recordActions([
                Action::make('resolve')->label('Вирішено')->color('success')->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (Report $r) => $r->status === 'open')
                    ->action(fn (Report $r) => self::close($r, 'resolved')),
                Action::make('dismiss')->label('Відхилити')->icon(Heroicon::OutlinedXMark)
                    ->visible(fn (Report $r) => $r->status === 'open')
                    ->action(fn (Report $r) => self::close($r, 'dismissed')),
            ]);
    }

    private static function close(Report $report, string $status): void
    {
        $report->update(['status' => $status]);
        ModerationLog::record($report, "report_{$status}");
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListReports::route('/')];
    }
}
