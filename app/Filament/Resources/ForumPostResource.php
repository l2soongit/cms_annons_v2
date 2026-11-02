<?php

namespace App\Filament\Resources;

use App\Community\Moderation;
use App\Filament\Resources\ForumPostResource\Pages;
use App\Models\ForumPost;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/** Відповіді в темах і коментарі під новинами: перевірка відповідей новачків і з позначками автофільтра */
class ForumPostResource extends Resource
{
    protected static ?string $model = ForumPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'Спільнота';

    protected static ?string $modelLabel = 'відповідь';

    protected static ?string $pluralModelLabel = 'Відповіді';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('community.moderate') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) ForumPost::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['topic', 'user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('body')->label('Текст')->formatStateUsing(fn (string $state) => strip_tags($state))->limit(140)->wrap()->searchable()
                    ->description(fn (ForumPost $p) => 'У темі: '.$p->topic?->title),
                TextColumn::make('user.name')->label('Автор')->searchable(),
                TextColumn::make('status')->label('Статус')->badge()
                    ->formatStateUsing(fn (string $state) => ForumPost::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => ['published' => 'success', 'pending' => 'warning'][$state] ?? 'gray'),
                TextColumn::make('flag_reason')->label('Причина перевірки')->color('danger')->placeholder('—'),
                TextColumn::make('score')->label('▲')->sortable(),
                TextColumn::make('created_at')->label('Дата')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('approve')->label('Опублікувати')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->visible(fn (ForumPost $p) => $p->status !== 'published')
                    ->action(fn (ForumPost $p) => Moderation::approvePost($p)),
                Action::make('hide')->label('Приховати')->icon(Heroicon::OutlinedEyeSlash)->color('danger')
                    ->visible(fn (ForumPost $p) => $p->status !== 'hidden')
                    ->schema([Textarea::make('reason')->label('Причина (для журналу)')->rows(2)])
                    ->action(fn (ForumPost $p, array $data) => Moderation::hidePost($p, $data['reason'] ?: null)),
                Action::make('open')->label('У темі')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (ForumPost $p) => $p->topic ? route('community.topic', $p->topic).'#post-'.$p->id : null, shouldOpenInNewTab: true),
                DeleteAction::make()->visible(fn () => auth()->user()?->can('community.manage'))
                    ->after(function (ForumPost $p) {
                        $p->topic?->refreshCounters();
                        $p->topic?->category->refreshCounters();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('approveMany')->label('Опублікувати')->color('success')
                    ->action(fn (Collection $records) => $records->whereInstanceOf(ForumPost::class)
                        ->filter(fn (ForumPost $p) => $p->status !== 'published')->each(fn (ForumPost $p) => Moderation::approvePost($p))),
                BulkAction::make('hideMany')->label('Приховати')->color('danger')->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->whereInstanceOf(ForumPost::class)
                        ->filter(fn (ForumPost $p) => $p->status !== 'hidden')->each(fn (ForumPost $p) => Moderation::hidePost($p))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListForumPosts::route('/')];
    }
}
