<?php

namespace App\Filament\Resources;

use App\Enums\ServerStatus;
use App\Filament\Resources\ServerResource\Pages;
use App\Models\Server;
use App\Monitoring\ServerMonitor;
use App\Servers\ServerModeration;
use App\Support\Locales;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'Модерація';

    protected static ?string $modelLabel = 'сервер';

    protected static ?string $pluralModelLabel = 'Сервери';

    protected static ?string $recordTitleAttribute = 'name';

    /** Глобальний пошук (Ctrl+K): за назвою й адресою */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'host'];
    }

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('servers.moderate') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Server::where('status', ServerStatus::Pending)->orWhereNotNull('pending_changes')->count();

        return (string) ($count) ?: null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->columnSpanFull()->schema([
                Section::make('Сервер')->columnSpan(2)->columns(2)->schema([
                    TextInput::make('name')->label('Назва')->required(),
                    Select::make('game_id')->relationship('game', 'slug')->label('Гра')->required(),
                    TextInput::make('slug')->label('Адреса сторінки')->required()->unique(ignoreRecord: true)->prefix('/server/'),
                    DatePicker::make('opens_at')->label('Дата відкриття'),
                ]),
                Section::make('Модерація')->columnSpan(1)->schema([
                    Select::make('status')->label('Статус')->required()
                        ->options(collect(ServerStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                    TextInput::make('bonus_points')->label('Бонусні бали')->numeric()->helperText('Ручне коригування; платні підняття рахуються окремо.'),
                ]),
            ]),
            Section::make('Підключення й посилання')->columns(4)->columnSpanFull()->schema([
                TextInput::make('host')->label('Адреса (IP або домен)')->required()->columnSpan(2),
                TextInput::make('port')->label('Порт')->numeric(),
                TextInput::make('website')->label('Сайт')->url(),
                TextInput::make('discord')->label('Discord')->url()->columnSpan(2),
            ]),
            Section::make('Опис')->columnSpanFull()->schema([
                Tabs::make('description')->contained(false)->tabs(collect(Locales::all())->map(
                    fn (string $name, string $locale) => Tab::make($name)->schema([
                        Textarea::make("description.{$locale}")->hiddenLabel()->rows(6),
                    ])
                )->values()->all()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            // Групи за статусом: спершу те, що чекає на перевірку
            ->groups([
                Group::make('status')->label('Статус')->collapsible()
                    ->getTitleFromRecordUsing(fn (Server $s) => $s->status->label())
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'published' THEN 1 WHEN 'hidden' THEN 2 WHEN 'draft' THEN 3 WHEN 'rejected' THEN 4 WHEN 'blocked' THEN 5 ELSE 6 END")),
                Group::make('game.slug')->label('Гра')->collapsible(),
            ])
            ->defaultGroup('status')
            ->columns([
                TextColumn::make('name')->label('Назва')->searchable()
                    ->description(fn (Server $s) => $s->address()),
                TextColumn::make('game.name')->label('Гра')->toggleable(),
                TextColumn::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn (ServerStatus $state) => $state->label())
                    ->color(fn (ServerStatus $state) => match ($state) {
                        ServerStatus::Published => 'success',
                        ServerStatus::Pending => 'warning',
                        ServerStatus::Rejected, ServerStatus::Blocked => 'danger',
                        default => 'gray',
                    }),
                // Позначки лише там, де вони є: порожня клітинка замість червоного хрестика
                IconColumn::make('pending_changes')->label('Зміни')->boolean()->falseIcon('')
                    ->trueIcon(Heroicon::OutlinedPencilSquare)->trueColor('warning')->tooltip('Власник змінив дані — чекає перевірки')
                    ->state(fn (Server $s) => (bool) $s->pending_changes),
                IconColumn::make('verified_at')->label('Підтв.')->boolean()->falseIcon('')->tooltip('Власник підтвердив володіння')
                    ->state(fn (Server $s) => (bool) $s->verified_at),
                IconColumn::make('suspicious_online')->label('Підозр. онлайн')->boolean()
                    ->trueColor('danger')->falseIcon('')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('votes_month')->label('Голоси')->sortable()->numeric(),
                TextColumn::make('rank')->label('Місце')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('owner.email')->label('Власник')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Додано')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')
                    ->options(collect(ServerStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                SelectFilter::make('game')->relationship('game', 'slug')->label('Гра'),
                Filter::make('queue')->label('Черга модерації')
                    ->query(fn (Builder $query) => $query->where(fn ($q) => $q->where('status', ServerStatus::Pending)->orWhereNotNull('pending_changes'))),
                Filter::make('suspicious')->label('Підозрілий онлайн')->query(fn (Builder $query) => $query->where('suspicious_online', true)),
            ])
            ->recordActions([
                Action::make('approve')->label('Схвалити')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (Server $s) => $s->status === ServerStatus::Pending || $s->pending_changes)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Server $s) => self::changesHtml($s))
                    ->action(function (Server $s) {
                        app(ServerModeration::class)->approve($s);
                        Notification::make()->title('Схвалено')->success()->send();
                    }),
                Action::make('reject')->label('Відхилити')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible(fn (Server $s) => $s->status === ServerStatus::Pending || $s->pending_changes)
                    ->modalDescription(fn (Server $s) => self::changesHtml($s))
                    ->schema(self::reasonFields())
                    ->action(function (Server $s, array $data) {
                        app(ServerModeration::class)->reject($s, self::reasonText($data));
                        Notification::make()->title('Відхилено, власника сповіщено')->send();
                    }),
                ActionGroup::make([
                    Action::make('warn')->label('Попередити')->icon(Heroicon::OutlinedExclamationTriangle)->color('warning')
                        ->visible(fn (Server $s) => $s->status === ServerStatus::Published)
                        ->schema(self::reasonFields())
                        ->action(function (Server $s, array $data) {
                            app(ServerModeration::class)->warn($s, self::reasonText($data));
                            Notification::make()->title('Попередження надіслано власнику')->send();
                        }),
                    Action::make('block')->label('Заблокувати')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                        ->visible(fn (Server $s) => $s->status === ServerStatus::Published)
                        ->schema([
                            Select::make('days')->label('Термін')->required()->default(0)
                                ->options([1 => '1 день', 7 => '7 днів', 30 => '30 днів', 0 => 'Назавжди']),
                            ...self::reasonFields(),
                        ])
                        ->action(fn (Server $s, array $data) => app(ServerModeration::class)->block($s, self::reasonText($data), (int) $data['days'] ?: null)),
                    Action::make('unblock')->label('Розблокувати')->icon(Heroicon::OutlinedLockOpen)->color('success')
                        ->visible(fn (Server $s) => $s->status === ServerStatus::Blocked)
                        ->requiresConfirmation()
                        ->modalDescription(fn (Server $s) => $s->blocked_until ? 'Заблоковано до '.$s->blocked_until->format('d.m.Y H:i') : 'Заблоковано назавжди')
                        ->action(fn (Server $s) => app(ServerModeration::class)->unblock($s)),
                    Action::make('recheck')->label('Перевірити онлайн')->icon(Heroicon::OutlinedArrowPath)
                        ->action(function (Server $s) {
                            $r = app(ServerMonitor::class)->check($s->load('game'));
                            Notification::make()->title($r->online ? "Онлайн: {$r->players}/{$r->maxPlayers}" : 'Офлайн')->send();
                        }),
                    Action::make('open')->label('На сайті')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Server $s) => route('servers.show', $s), shouldOpenInNewTab: true)
                        ->visible(fn (Server $s) => $s->status === ServerStatus::Published),
                    EditAction::make()->label('Змінити'),
                ])->tooltip('Дії')->icon(Heroicon::OutlinedEllipsisVertical)->color('gray'),
            ])
            ->toolbarActions([
                BulkAction::make('approveMany')->label('Схвалити вибрані')->color('success')->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->whereInstanceOf(Server::class)->each(fn (Server $s) => app(ServerModeration::class)->approve($s))),
            ]);
    }

    /**
     * Причина з довідника + коментар; власник бачить їх у кабінеті та листі.
     */
    public static function reasonFields(): array
    {
        return [
            Select::make('reason')->label('Причина')->required()->options(array_combine(self::REASONS, self::REASONS)),
            Textarea::make('comment')->label('Коментар для власника')->rows(3)
                ->requiredIf('reason', 'Інше'),
        ];
    }

    private const REASONS = [
        'Неповний або неінформативний опис',
        'Сервер недоступний за вказаною адресою',
        'Дублікат існуючого сервера',
        'Накрутка онлайну або голосів',
        'Заборонений вміст',
        'Інше',
    ];

    private static function reasonText(array $data): string
    {
        return trim($data['reason'].(filled($data['comment'] ?? null) ? ': '.$data['comment'] : ''));
    }

    private static function changesHtml(Server $server): ?HtmlString
    {
        if (! $server->pending_changes) {
            return null;
        }

        $diff = app(ServerModeration::class)->diff($server);
        $rows = collect($diff['after'])->map(fn ($after, $key) => sprintf(
            '<tr><td style="padding:2px 8px">%s</td><td style="padding:2px 8px;text-decoration:line-through;opacity:.6">%s</td><td style="padding:2px 8px"><b>%s</b></td></tr>',
            e($key), e((string) ($diff['before'][$key] ?? '')), e((string) $after),
        ))->implode('');

        return new HtmlString('<p>Зміни ключових полів:</p><table>'.$rows.'</table>');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServers::route('/'),
            'edit' => Pages\EditServer::route('/{record}/edit'),
        ];
    }
}
