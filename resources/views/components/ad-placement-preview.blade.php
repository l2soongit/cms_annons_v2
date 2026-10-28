{{-- Скріншот сторінки сайту з позначеним місцем банера: покупець бачить, куди саме потрапить реклама.
     Знімки в public/images/ad-places — оновлюються разом із дизайном сторінок. Клік відкриває повний розмір --}}
@props(['placement', 'game' => null])
@php
    // Банер гри: у кожної гри свій стиль сторінки, тож і знімок свій (game-<slug>.webp), інакше — загальний
    $name = $placement === 'game' && $game && is_file(public_path("images/ad-places/game-{$game->slug}.webp")) ? "game-{$game->slug}" : $placement;
    $src = asset("images/ad-places/{$name}.webp");
    [$iw, $ih] = @getimagesize(public_path("images/ad-places/{$name}.webp")) ?: [1000, 540];
@endphp
<figure {{ $attributes->merge(['class' => 'shrink-0']) }}>
    <a href="{{ $src }}" target="_blank" rel="noopener" class="group relative block overflow-hidden rounded-lg border border-seam hover:border-amber">
        <img src="{{ $src }}" alt="{{ __("billing.ads.where.{$placement}") }}" width="{{ $iw }}" height="{{ $ih }}" loading="lazy" decoding="async"
             class="block h-auto w-full transition duration-200 group-hover:scale-[1.02]">
        <span class="absolute right-2 bottom-2 rounded bg-black/70 px-2 py-0.5 text-[11px] font-medium text-white opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100">{{ __('billing.ads.zoom') }}</span>
    </a>
    <figcaption class="mt-2 text-xs text-moss">{{ __("billing.ads.where.{$placement}") }}</figcaption>
</figure>
