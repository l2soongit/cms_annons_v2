<?php

namespace App\Filament\Resources\BonusRewardResource\Pages;

use App\Filament\Resources\BonusRewardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBonusRewards extends ListRecords
{
    protected static string $resource = BonusRewardResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
