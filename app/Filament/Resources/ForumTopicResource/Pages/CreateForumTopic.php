<?php

namespace App\Filament\Resources\ForumTopicResource\Pages;

use App\Community\Moderation;
use App\Filament\Resources\ForumTopicResource;
use App\Models\ForumTopic;
use App\Models\ModerationLog;
use Filament\Resources\Pages\CreateRecord;

/**
 * Тема з адмінки: автор — поточний користувач. Модератор може опублікувати одразу (запис у журнал),
 * без права модерації тема йде в чергу, як і з сайту.
 */
class CreateForumTopic extends CreateRecord
{
    protected static string $resource = ForumTopicResource::class;

    private bool $publishNow = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->publishNow = ($data['status'] ?? null) === ForumTopic::PUBLISHED && auth()->user()?->can('community.moderate');
        $data = ForumTopicResource::prepare($data);
        $data['user_id'] = auth()->id();
        $data['status'] = ForumTopic::PENDING;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var ForumTopic $topic */
        $topic = $this->record;
        ModerationLog::record($topic, 'forum_topic_created');
        if ($this->publishNow) {
            Moderation::approve($topic);
        }
    }
}
