<?php

namespace App\Filament\Resources\ForumPostResource\Pages;

use App\Filament\Resources\ForumPostResource;
use App\Models\ForumPost;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListForumPosts extends ListRecords
{
    protected static string $resource = ForumPostResource::class;

    public function getDefaultActiveTab(): string|int|null
    {
        return ForumPost::where('status', 'pending')->exists() ? 'queue' : 'all';
    }

    public function getTabs(): array
    {
        // Filament передає аргументи за назвою — параметр має зватися саме $query
        return [
            'queue' => Tab::make('На перевірці')->icon('heroicon-o-clock')
                ->badge(ForumPost::where('status', 'pending')->count() ?: null)->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),
            'all' => Tab::make('Опубліковані')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'published')),
            'hidden' => Tab::make('Приховані')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'hidden')),
        ];
    }
}
