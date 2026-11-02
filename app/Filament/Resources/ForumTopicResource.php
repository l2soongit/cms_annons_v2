<?php

namespace App\Filament\Resources;

use App\Community\AutoFilter;
use App\Community\Moderation;
use App\Filament\Resources\ForumTopicResource\Pages;
use App\Filament\Support\ImageField;
use App\Models\ForumCategory;
use App\Models\ForumTopic;
use App\Models\ModerationLog;
use App\Models\Server;
use App\Support\HtmlSanitizer;
use App\Support\Locales;
use App\Support\PageCache;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Теми й новини спільноти: черга модерації, схвалення з журналом, закріплення, головна новина, перенесення.
 * Кожна тема користувача проходить перевірку; тема, створена модератором тут, публікується одразу (з записом у журнал).
 */
class ForumTopicResource extends Resource
{
    protected static ?string $model = ForumTopic::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Спільнота';

    protected static ?string $modelLabel = 'тема';

    protected static ?string $pluralModelLabel = 'Теми й новини';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->can('community.moderate') || $user->can('community.manage') || $user->can('community.news'));
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('community.manage') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) ForumTopic::where('status', ForumTopic::PENDING)->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** Новини сайту видно тим, хто має право на новини; решта — модераторам */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['category', 'user', 'server']);
        $user = auth()->user();
        if ($user && ! $user->can('community.moderate') && ! $user->can('community.manage')) {
            $query->where('type', 'news');
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Section::make('Тема')->columnSpan(['lg' => 2])->columns(2)->schema([
                    Select::make('category_id')->label('Розділ')->required()->searchable()->preload()
                        ->options(fn () => ForumCategory::orderBy('sort')->get()->mapWithKeys(fn (ForumCategory $c) => [$c->id => $c->name])),
                    Select::make('type')->label('Тип')->required()->default('discussion')->live()
                        ->options(fn () => collect(ForumTopic::TYPES)->filter(fn ($label, $key) => $key !== 'news' || auth()->user()?->can('community.news'))->all()),
                    TextInput::make('title')->label('Заголовок')->required()->maxLength(160)->columnSpanFull(),
                    Textarea::make('excerpt')->label('Короткий опис')->rows(2)->maxLength(300)->columnSpanFull()
                        ->helperText('Показується в стрічці й у картці новини. Порожньо — початок тексту.'),
                    RichEditor::make('body')->label('Текст')->required()->columnSpanFull()
                        ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo']),
                    KeyValue::make('meta')->label('Деталі набору в клан')->keyLabel('Поле')->valueLabel('Значення')->columnSpanFull()
                        ->visible(fn (Get $get) => $get('type') === 'clan')
                        ->helperText('Напр. «Рівень» → «76+», «Прайм» → «20:00–00:00», «Мова» → «UA».'),
                ]),
                Grid::make(1)->columnSpan(1)->schema([
                    Section::make('Публікація')->schema([
                        Select::make('status')->label('Статус')->required()->default(ForumTopic::PUBLISHED)
                            ->options([ForumTopic::PUBLISHED => 'Опублікувати одразу', ForumTopic::PENDING => 'Зберегти в черзі перевірки'])
                            ->visible(fn (string $operation) => $operation === 'create' && self::canModerate())
                            ->helperText('Статус наявної теми змінюється кнопками в списку: схвалити, відхилити, приховати.'),
                        Select::make('locale')->label('Мова')->options(Locales::all())->default(Locales::default())->required(),
                        Select::make('server_id')->label('Сервер')->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Server::where('name', 'like', "%{$search}%")->limit(20)->pluck('name', 'id'))
                            ->getOptionLabelUsing(fn ($value) => Server::find($value)?->name)
                            ->required(fn (Get $get) => $get('type') === 'server_news')
                            ->helperText('Для новини сервера: вона з’явиться на сторінці сервера.'),
                        ImageField::make('cover', 'forum', 'cover')->label('Обкладинка'),
                    ]),
                    Section::make('Відображення')->schema([
                        Toggle::make('is_featured')->label('Головна новина зверху')
                            ->visible(fn () => auth()->user()?->can('community.manage') || auth()->user()?->can('community.news')),
                        Toggle::make('is_pinned')->label('Закріпити в розділі'),
                        DateTimePicker::make('pinned_until')->label('Закріплено до')->helperText('Порожньо — без строку.'),
                        Toggle::make('is_locked')->label('Закрити для відповідей'),
                    ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Тема')->searchable()->wrap()->limit(90)
                    ->description(fn (ForumTopic $t) => $t->category?->name.' · '.(ForumTopic::TYPES[$t->type] ?? $t->type)),
                TextColumn::make('user.name')->label('Автор')->searchable(),
                TextColumn::make('server.name')->label('Сервер')->toggleable(),
                TextColumn::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn (string $state) => ForumTopic::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => [ForumTopic::PUBLISHED => 'success', ForumTopic::PENDING => 'warning', ForumTopic::REJECTED => 'danger'][$state] ?? 'gray'),
                TextColumn::make('flag_reason')->label('Автофільтр')->color('danger')->placeholder('—')->toggleable(),
                IconColumn::make('is_featured')->label('Головна')->boolean()->trueIcon(Heroicon::Star)->falseIcon(null)->toggleable(),
                IconColumn::make('is_pinned')->label('📌')->boolean()->falseIcon(null)->toggleable(),
                TextColumn::make('posts_count')->label('Відповіді')->numeric()->sortable(),
                TextColumn::make('created_at')->label('Створено')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Тип')->options(ForumTopic::TYPES),
                SelectFilter::make('category_id')->label('Розділ')
                    ->options(fn () => ForumCategory::orderBy('sort')->get()->mapWithKeys(fn (ForumCategory $c) => [$c->id => $c->name])),
            ])
            ->recordActions([
                Action::make('approve')->label('Схвалити')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (ForumTopic $t) => $t->status !== ForumTopic::PUBLISHED && self::canModerate())
                    ->requiresConfirmation()->modalDescription(fn (ForumTopic $t) => self::preview($t))
                    ->action(function (ForumTopic $t) {
                        Moderation::approve($t);
                        Notification::make()->title('Тему опубліковано, автора сповіщено')->success()->send();
                    }),
                Action::make('reject')->label('Відхилити')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible(fn (ForumTopic $t) => $t->status === ForumTopic::PENDING && self::canModerate())
                    ->modalDescription(fn (ForumTopic $t) => self::preview($t))
                    ->schema([self::reasonField()])
                    ->action(function (ForumTopic $t, array $data) {
                        Moderation::reject($t, (string) $data['reason']);
                        Notification::make()->title('Відхилено, автора сповіщено')->send();
                    }),
                ActionGroup::make([
                    Action::make('open')->label('Відкрити на сайті')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (ForumTopic $t) => route('community.topic', $t), shouldOpenInNewTab: true),
                    EditAction::make(),
                    Action::make('hide')->label('Приховати')->icon(Heroicon::OutlinedEyeSlash)->color('danger')
                        ->visible(fn (ForumTopic $t) => $t->status === ForumTopic::PUBLISHED && self::canModerate())
                        ->schema([self::reasonField(false)])
                        ->action(fn (ForumTopic $t, array $data) => Moderation::hide($t, $data['reason'] ?: null)),
                    Action::make('pin')->label(fn (ForumTopic $t) => $t->is_pinned ? 'Відкріпити' : 'Закріпити')->icon(Heroicon::OutlinedMapPin)
                        ->visible(fn () => self::canModerate())
                        ->action(fn (ForumTopic $t) => self::toggle($t, 'is_pinned')),
                    Action::make('lock')->label(fn (ForumTopic $t) => $t->is_locked ? 'Відкрити для відповідей' : 'Закрити для відповідей')
                        ->icon(Heroicon::OutlinedLockClosed)->visible(fn () => self::canModerate())
                        ->action(fn (ForumTopic $t) => self::toggle($t, 'is_locked')),
                    Action::make('feature')->label(fn (ForumTopic $t) => $t->is_featured ? 'Прибрати з головної' : 'Зробити головною новиною')
                        ->icon(Heroicon::OutlinedStar)
                        ->visible(fn (ForumTopic $t) => $t->isPublished() && (auth()->user()?->can('community.manage') || auth()->user()?->can('community.news')))
                        ->action(fn (ForumTopic $t) => self::toggle($t, 'is_featured')),
                    Action::make('move')->label('Перенести в розділ')->icon(Heroicon::OutlinedArrowsRightLeft)
                        ->visible(fn () => self::canModerate())
                        ->schema([Select::make('category_id')->label('Розділ')->required()
                            ->options(fn () => ForumCategory::orderBy('sort')->get()->mapWithKeys(fn (ForumCategory $c) => [$c->id => $c->name]))])
                        ->action(function (ForumTopic $t, array $data) {
                            $old = $t->category;
                            $t->update(['category_id' => $data['category_id']]);
                            ModerationLog::record($t, 'forum_topic_moved', null, ['from' => $old->id, 'to' => (int) $data['category_id']]);
                            $old->refreshCounters();
                            $t->refresh()->category->refreshCounters();
                            PageCache::flush();
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('approveMany')->label('Схвалити')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn () => self::canModerate())->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->whereInstanceOf(ForumTopic::class)
                        ->filter(fn (ForumTopic $t) => ! $t->isPublished())->each(fn (ForumTopic $t) => Moderation::approve($t))),
                BulkAction::make('hideMany')->label('Приховати')->color('danger')->visible(fn () => self::canModerate())->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->whereInstanceOf(ForumTopic::class)
                        ->filter(fn (ForumTopic $t) => $t->isPublished())->each(fn (ForumTopic $t) => Moderation::hide($t))),
            ]);
    }

    private static function canModerate(): bool
    {
        return auth()->user()?->can('community.moderate') ?? false;
    }

    private static function reasonField(bool $required = true): Textarea
    {
        return Textarea::make('reason')->label('Причина (побачить автор)')->rows(3)->maxLength(500)->required($required);
    }

    private static function toggle(ForumTopic $topic, string $field): void
    {
        $topic->update([$field => ! $topic->{$field}]);
        ModerationLog::record($topic, 'forum_topic_'.$field, null, [$field => $topic->{$field}]);
        PageCache::flush();
    }

    /** Текст теми у вікні модерації */
    private static function preview(ForumTopic $topic): HtmlString
    {
        $flag = $topic->flag_reason ? '<p style="color:#dc2626;font-weight:600">Автофільтр: '.e($topic->flag_reason).'</p>' : '';

        return new HtmlString($flag.'<div style="max-height:320px;overflow:auto;text-align:left;font-size:14px">'
            .'<p><b>'.e($topic->title).'</b> · '.e($topic->category?->name).' · '.e(ForumTopic::TYPES[$topic->type] ?? $topic->type).'</p>'
            .HtmlSanitizer::clean($topic->body).'</div>');
    }

    /** Дані форми адмінки → тема: чистий HTML, короткий опис і автофільтр */
    public static function prepare(array $data): array
    {
        $data['body'] = HtmlSanitizer::clean((string) ($data['body'] ?? ''));
        $data['excerpt'] = filled($data['excerpt'] ?? null) ? $data['excerpt'] : AutoFilter::excerpt($data['body']);
        if (($data['type'] ?? null) !== 'clan') {
            $data['meta'] = null;
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListForumTopics::route('/'),
            'create' => Pages\CreateForumTopic::route('/create'),
            'edit' => Pages\EditForumTopic::route('/{record}/edit'),
        ];
    }
}
