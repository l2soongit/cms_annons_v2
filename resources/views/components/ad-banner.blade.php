@props(['placement', 'game' => null, 'gap' => null, 'inset' => 0, 'max' => null])
@php
    use App\Models\Banner;

    // Один банер на місце: оплачений на сьогодні; показ рахується під час виводу
    $banner = Banner::live($placement, $game?->id)->latest('starts_at')->first();
    $banner?->increment('impressions');
    [, $w, $h] = Banner::PLACEMENTS[$placement] ?? [null, 1200, 150];
@endphp
@if ($banner)
    {{-- Класи без «ad-»: блокувальники (EasyList: ##.ad-slot, ##.ad-header) ховали банер одразу після завантаження --}}
    <aside {{ $attributes->merge(['class' => 'gj-place gj-place-'.$placement]) }} aria-label="{{ __('billing.ads.sponsored') }}"
           style="position:relative;max-width:{{ $max ?? $w }}px;width:calc(100% - {{ $inset * 2 }}px);margin:{{ $gap ?? '0 auto' }};line-height:0">
        <a href="{{ route('ads.click', $banner) }}" rel="sponsored noopener" target="_blank" style="display:block;border-radius:8px;overflow:hidden">
            <img src="{{ \App\Support\HomePageData::url($banner->image) }}" alt="{{ $banner->alt }}" width="{{ $w }}" height="{{ $h }}"
                 loading="{{ $placement === 'header' ? 'eager' : 'lazy' }}" style="display:block;width:100%;height:auto;aspect-ratio:{{ $w }}/{{ $h }};object-fit:cover">
        </a>
        <span style="position:absolute;top:6px;right:6px;padding:1px 6px;border-radius:4px;background:#000a;color:#fff;font:600 10px/1.6 system-ui,sans-serif;letter-spacing:.04em;text-transform:uppercase">{{ __('billing.ads.sponsored') }}</span>
    </aside>
@endif
