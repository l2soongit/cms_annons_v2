<?php

namespace App\Filament\Resources;

use App\Billing\Billing;
use App\Billing\Money;
use App\Filament\Resources\BannerResource\Pages;
use App\Filament\Support\ImageField;
use App\Models\Banner;
use App\Models\Game;
use App\Models\ModerationLog;
use App\Support\HomePageData;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Рекламні банери: перегляд заброньованих, статистика, зняття з показу неприйнятних зображень, видалення
 * (оплачений діючий банер скасовує замовлення, за бажанням — з поверненням коштів).
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
        return auth()->user()?->can('billing.manage') ?? false;
    }

    /** Банер без оплати: власна реклама порталу, партнер, домовленість поза сайтом */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Select::make('placement')->label('Місце')->required()->live()
                    ->options(collect(Banner::PLACEMENTS)->map(fn ($p) => "{$p[0]} ({$p[1]}×{$p[2]})")),
                Select::make('game_id')->label('Гра')
                    ->options(fn (Get $get) => Game::query()
                        ->when($get('placement') === 'game_side', fn ($q) => $q->whereIn('theme', Banner::SIDE_COLUMN_THEMES))
                        ->orderBy('sort')->pluck('name', 'id'))
                    ->visible(fn (Get $get) => Banner::isPerGame((string) $get('placement')))
                    ->required(fn (Get $get) => Banner::isPerGame((string) $get('placement'))),
                Select::make('server_id')->label('Сервер')->relationship('server', 'name')->searchable()->preload()
                    ->placeholder('Без сервера — реклама порталу чи партнера')
                    ->helperText('Якщо вказати, діє правило «один сервер — один банер на сторінці».'),
                TextInput::make('url')->label('Посилання')->url()->required()->maxLength(255),
                ImageField::make('image', 'banners', 'banner')->label('Зображення')->required()
                    ->helperText(fn (Get $get) => ($p = Banner::PLACEMENTS[$get('placement')] ?? null) ? "Розмір {$p[1]}×{$p[2]} px" : null),
                TextInput::make('alt')->label('Опис зображення (alt)')->maxLength(120),
                DatePicker::make('starts_at')->label('Перший день показу')->required()->default(today())->native(false)->displayFormat('d.m.Y'),
                DatePicker::make('ends_at')->label('Останній день показу')->required()->afterOrEqual('starts_at')->native(false)->displayFormat('d.m.Y'),
                Toggle::make('is_active')->label('Показувати')->default(true),
            ]),
        ]);
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
                // Без сервера опис не виводиться, тож позначка ручного банера — у заглушці
                TextColumn::make('server.name')->label('Сервер')->placeholder('Додано адміністратором, без сервера')->searchable()
                    ->description(fn (Banner $record) => $record->order_id ? $record->order?->user?->email : 'Додано адміністратором, без оплати'),
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
                Action::make('delete')->label('Видалити')->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Видалити банер?')
                    ->modalDescription(fn (Banner $record) => self::hasActiveOrder($record)
                        ? 'Банер оплачено до '.$record->ends_at->copy()->subSecond()->format('d.m.Y').' ('.Money::format($record->order->amount).'). Замовлення буде скасовано, дати звільняться для інших.'
                        : 'Банер і його зображення буде видалено назавжди. Статистика показів зникне разом із ним.')
                    ->schema(fn (Banner $record) => self::hasActiveOrder($record)
                        // Ще не почався — повертаємо за замовчуванням; уже йде — на розсуд адміністратора
                        ? [Toggle::make('refund')->label('Повернути '.Money::format($record->order->amount).' на баланс покупця')->default($record->starts_at->isFuture())]
                        : [])
                    ->action(function (Banner $record, array $data) {
                        $refund = (bool) ($data['refund'] ?? false);
                        if (self::hasActiveOrder($record)) {
                            app(Billing::class)->cancel($record->order, $refund);
                        }

                        ModerationLog::record($record, 'banner_delete', changes: [
                            'placement' => $record->placement, 'server' => $record->server?->name, 'refund' => $refund,
                            'starts_at' => $record->starts_at->toDateString(), 'ends_at' => $record->ends_at->toDateString(),
                        ]);
                        // Те саме зображення могло лишитися в іншого банера (повторна покупка з тим самим файлом)
                        if (! Banner::where('image', $record->image)->whereKeyNot($record->getKey())->exists()) {
                            Storage::disk('public')->delete($record->image);
                        }
                        $record->delete();

                        Notification::make()->title($refund ? 'Банер видалено, кошти повернено' : 'Банер видалено')->success()->send();
                    }),
            ]);
    }

    /** Банер куплено, і замовлення ще діє — видалення його скасовує */
    private static function hasActiveOrder(Banner $record): bool
    {
        return $record->order?->status === 'active';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
        ];
    }
}
