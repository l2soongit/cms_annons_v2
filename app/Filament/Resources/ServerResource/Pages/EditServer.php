<?php

namespace App\Filament\Resources\ServerResource\Pages;

use App\Filament\Resources\ServerResource;
use App\Models\ModerationLog;
use Filament\Resources\Pages\EditRecord;

class EditServer extends EditRecord
{
    protected static string $resource = ServerResource::class;

    protected function afterSave(): void
    {
        $changes = $this->record->getChanges();
        unset($changes['updated_at']);

        if ($changes) {
            ModerationLog::record($this->record, 'edit', changes: ['after' => $changes]);
        }
    }
}
