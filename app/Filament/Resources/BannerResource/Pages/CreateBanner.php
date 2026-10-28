<?php

namespace App\Filament\Resources\BannerResource\Pages;

use App\Billing\Billing;
use App\Filament\Resources\BannerResource;
use App\Models\Banner;
use App\Models\ModerationLog;
use App\Models\Server;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Банер від адміністратора без оплати. Дати не можуть перетинатися з уже проданими на цьому місці,
 * а сервер не отримує банер, який на сторінці приховав би його ж інший банер.
 */
class CreateBanner extends CreateRecord
{
    protected static string $resource = BannerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['game_id'] = Banner::isPerGame($data['placement']) ? $data['game_id'] : null;
        // Як у куплених банерів: ends_at — початок дня після останнього дня показу
        $data['starts_at'] = Carbon::parse($data['starts_at'])->startOfDay();
        $data['ends_at'] = Carbon::parse($data['ends_at'])->startOfDay()->addDay();

        $billing = app(Billing::class);
        if ($billing->bannerTaken($data['placement'], $data['game_id'], $data['starts_at'], $data['ends_at'])) {
            throw ValidationException::withMessages(['data.starts_at' => 'На цьому місці ці дати вже зайняті іншим банером. Оберіть інші дати.']);
        }
        $server = $data['server_id'] ? Server::find($data['server_id']) : null;
        if ($server && ($clash = $billing->ownBannerClash($server, $data['placement'], $data['starts_at'], $data['ends_at']))) {
            throw ValidationException::withMessages(['data.server_id' => 'У цього сервера на ці дати вже є банер «'.Banner::PLACEMENTS[$clash->placement][0].'» (до '
                .$clash->ends_at->copy()->subSecond()->format('d.m').') — на сторінці показався б лише один із них.']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Banner $banner */
        $banner = $this->record;
        ModerationLog::record($banner, 'banner_create', changes: [
            'placement' => $banner->placement, 'server' => $banner->server?->name,
            'starts_at' => $banner->starts_at->toDateString(), 'ends_at' => $banner->ends_at->toDateString(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
