<?php

namespace App\Filament\Resources\GameResource\Pages;

use App\Filament\Resources\GameResource;
use App\Models\Game;
use Filament\Resources\Pages\EditRecord;

class EditGame extends EditRecord
{
    protected static string $resource = GameResource::class;

    /** Гра без налаштованих блоків показує всі — і перемикачі у формі мають бути увімкнені */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['page']['blocks'] = array_merge(array_fill_keys(array_keys(Game::BLOCKS), true), $data['page']['blocks'] ?? []);

        return $data;
    }
}
