<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use App\Models\ModerationLog;
use App\Support\HomePageData;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Рекламні банери: перегляд заброньованих, статистика, зняття з показу неприйнятних зображень.
 */
class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Платні послуги';

    protected static ?string $modelLabel = 'банер';

    protected static ?string $pluralModelLabel = 'Банери';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('billing.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['server', 'game', 'order.user']))
            ->columns([
                ImageColumn::make('image')->label('Банер')->getStateUsing(fn (Banner $record) => HomePageData::url($record->image))->imageHeight(40),
                TextColumn::make('placement')->label('Місце')->formatStateUsing(fn (string $state) => Banner::PLACEMENTS[$state][0] ?? $state)
                    ->description(fn (Banner $record) => $record->game?->name),
                TextColumn::make('server.name')->label('Сервер')->description(fn (Banner $record) => $record->order?->user?->email)->searchable(),
                TextColumn::make('starts_at')->label('Показ')->formatStateUsing(fn (Banner $record) => $record->starts_at->format('d.m.Y').' – '.$record->ends_at->copy()->subSecond()->format('d.m.Y')),
                TextColumn::make('impressions')->label('Покази')->numeric()->sortable(),
                TextColumn::make('clicks')->label('Кліки')->numeric()->sortable()->description(fn (Banner $record) => 'CTR '.$record->ctr().'%'),
                IconColumn::make('is_active')->label('Показується')->boolean(),
            ])
            ->filters([
                SelectFilter::make('placement')->label('Місце')->options(collect(Banner::PLACEMENTS)->map(fn ($p) => $p[0])),
                Filter::make('current')->label('Зараз у показі')->query(fn (Builder $query) => $query->where('starts_at', '<=', now())->where('ends_at', '>', now())),
            ])
            ->recordActions([
                Action::make('open')->label('Посилання')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(fn (Banner $record) => $record->url, shouldOpenInNewTab: true),
                Action::make('toggle')
                    ->label(fn (Banner $record) => $record->is_active ? 'Зняти з показу' : 'Повернути в показ')
                    ->icon(fn (Banner $record) => $record->is_active ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedEye)
                    ->color(fn (Banner $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Banner $record) {
                        $record->update(['is_active' => ! $record->is_active]);
                        ModerationLog::record($record, $record->is_active ? 'banner_show' : 'banner_hide');
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
        ];
    }
}
