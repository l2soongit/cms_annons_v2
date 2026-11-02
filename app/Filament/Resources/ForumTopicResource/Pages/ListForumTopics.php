<?php

namespace App\Filament\Resources\ForumTopicResource\Pages;

use App\Filament\Resources\ForumTopicResource;
use App\Models\ForumTopic;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/** Теми вкладками: черга модерації першою, далі опубліковані, новини, відхилені й приховані */
class ListForumTopics extends ListRecords
{
    protected static string $resource = ForumTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Нова тема чи новина')];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return ForumTopic::where('status', ForumTopic::PENDING)->exists() ? 'queue' : 'published';
    }

    public function getTabs(): array
    {
        // Filament передає аргументи за назвою — параметр має зватися саме $query
        $base = ForumTopicResource::getEloquentQuery();
        $count = fn (callable $scope) => $scope((clone $base))->count();

        $queue = fn (Builder $query) => $query->where('status', ForumTopic::PENDING);
        $published = fn (Builder $query) => $query->where('status', ForumTopic::PUBLISHED);
        $news = fn (Builder $query) => $query->whereIn('type', ['news', 'server_news']);
        $declined = fn (Builder $query) => $query->whereIn('status', [ForumTopic::REJECTED, ForumTopic::HIDDEN]);

        return [
            'queue' => Tab::make('На перевірці')->icon('heroicon-o-clock')
                ->badge($count($queue) ?: null)->badgeColor('warning')->modifyQueryUsing($queue),
            'published' => Tab::make('Опубліковані')->badge($count($published))->modifyQueryUsing($published),
            'news' => Tab::make('Новини')->icon('heroicon-o-megaphone')->badge($count($news))->modifyQueryUsing($news),
            'declined' => Tab::make('Відхилені й приховані')->badge($count($declined) ?: null)->modifyQueryUsing($declined),
            'all' => Tab::make('Усі'),
        ];
    }
}
