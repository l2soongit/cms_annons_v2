{{-- promo: на вільному місці показати запрошення купити рекламу (для місць, де порожнеча помітна) --}}
@props(['placement', 'game' => null, 'gap' => null, 'inset' => 0, 'max' => null, 'promo' => false])
@php
    use App\Models\Banner;

    // Один банер на місце: оплачений на сьогодні; показ рахується під час виводу
    $banner = Banner::live($placement, $game?->id)->latest('starts_at')->first();

    // Один сервер — один банер на сторінці. Шапка головніша (вона на кожній сторінці), далі — у порядку виводу:
    // вміст сторінки рендериться раніше за макет, тому шапку перевіряємо запитом, а решту — за вже показаними
    $shown = request()->attributes->get('banner_servers', []);
    // На сторінці голосування шапки немає — там вона банер не витісняє
    if ($banner && in_array('header', Banner::clashesWith($placement), true)) {
        $shown[] = Banner::live('header')->latest('starts_at')->value('server_id');
    }
    if ($banner?->server_id && in_array($banner->server_id, $shown, true)) {
        $banner = null;
    }
    if ($banner) {
        request()->attributes->set('banner_servers', [...request()->attributes->get('banner_servers', []), $banner->server_id]);
        $banner->increment('impressions');
    }
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
@elseif ($promo)
    <a href="{{ route('cabinet.ads') }}" {{ $attributes->merge(['class' => 'gj-place-free']) }}
       style="display:block;max-width:{{ $max ?? $w }}px;margin:{{ $gap ?? '0 auto' }};padding:18px 16px;border:1px dashed currentColor;border-radius:8px;opacity:.7;text-align:center;text-decoration:none;color:inherit;font:14px/1.45 system-ui,sans-serif">
        <strong style="display:block;font-size:15px">{{ __('billing.ads.free_title') }}</strong>
        <span style="display:block;margin-top:4px;font-size:12px">{{ __('billing.ads.free_text', ['w' => $w, 'h' => $h]) }}</span>
    </a>
@endif
