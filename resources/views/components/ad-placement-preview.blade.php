{{-- Скріншот сторінки сайту з позначеним місцем банера мовою сторінки: покупець бачить, куди саме потрапить реклама.
     Знімки в public/images/ad-places (і /<мова>/) — оновлюються разом із дизайном сторінок. Клік відкриває повний розмір --}}
@props(['placement', 'game' => null])
@php($shot = \App\Models\Banner::shot($placement, $game))
<figure {{ $attributes->merge(['class' => 'shrink-0']) }}>
    <a href="{{ $shot['url'] }}" target="_blank" rel="noopener" class="group relative block overflow-hidden rounded-lg border border-seam hover:border-amber">
        <img src="{{ $shot['url'] }}" alt="{{ __("billing.ads.where.{$placement}") }}" width="{{ $shot['width'] }}" height="{{ $shot['height'] }}" loading="lazy" decoding="async"
             class="block h-auto w-full transition duration-200 group-hover:scale-[1.02]">
        <span class="absolute right-2 bottom-2 rounded bg-black/70 px-2 py-0.5 text-[11px] font-medium text-white opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100">{{ __('billing.ads.zoom') }}</span>
    </a>
    <figcaption class="mt-2 text-xs text-moss">{{ __("billing.ads.where.{$placement}") }}</figcaption>
</figure>
