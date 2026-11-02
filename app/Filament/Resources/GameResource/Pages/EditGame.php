<?php

namespace App\Filament\Resources\GameResource\Pages;

use App\Filament\Resources\GameResource;
use App\Models\Game;
use Filament\Resources\Pages\EditRecord;

class EditGame extends EditRecord
{
    protected static string $resource = GameResource::class;

    public function getTitle(): string
    {
        $game = $this->getRecord();

        return $game instanceof Game ? 'Гра: '.$game->name : parent::getTitle();
    }

    /** Гра без налаштованих блоків показує всі — і перемикачі у формі мають бути увімкнені */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['page']['blocks'] = array_merge(array_fill_keys(array_keys(Game::BLOCKS), true), $data['page']['blocks'] ?? []);

        return $data;
    }
}
