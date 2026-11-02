<?php

namespace App\Filament\Resources\ForumTopicResource\Pages;

use App\Filament\Resources\ForumTopicResource;
use App\Models\ForumTopic;
use App\Models\ModerationLog;
use App\Support\PageCache;
use Filament\Resources\Pages\EditRecord;

class EditForumTopic extends EditRecord
{
    protected static string $resource = ForumTopicResource::class;

    public function getTitle(): string
    {
        return $this->record instanceof ForumTopic ? 'Тема: '.$this->record->title : 'Тема';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ForumTopicResource::prepare($data);
    }

    protected function afterSave(): void
    {
        /** @var ForumTopic $topic */
        $topic = $this->record;
        ModerationLog::record($topic, 'forum_topic_edited');
        $topic->category->refreshCounters();
        PageCache::flush();
    }
}
