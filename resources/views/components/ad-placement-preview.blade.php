{{-- Схема сторінки, де стоїть рекламне місце: покупець бачить, куди потрапить банер. Кольори — з теми сайту --}}
@props(['placement'])
@php
    $muted = 'fill:var(--color-seam)';
    $soft = 'fill:var(--color-slab-2)';
    $spot = 'fill:var(--color-amber)';
@endphp
<figure {{ $attributes->merge(['class' => 'shrink-0']) }}>
    <svg viewBox="0 0 200 130" width="200" height="130" role="img" aria-label="{{ __("billing.ads.where.{$placement}") }}"
         style="display:block;width:100%;max-width:200px;height:auto;border:1px solid var(--color-seam);border-radius:6px;background:var(--color-slab)">
        {{-- Вікно браузера й навігація --}}
        <rect x="0" y="0" width="200" height="10" style="{{ $soft }}"/>
        <circle cx="6" cy="5" r="1.6" style="{{ $muted }}"/><circle cx="11" cy="5" r="1.6" style="{{ $muted }}"/><circle cx="16" cy="5" r="1.6" style="{{ $muted }}"/>
        <rect x="8" y="14" width="26" height="5" rx="1.5" style="{{ $muted }}"/>
        <rect x="120" y="14" width="72" height="5" rx="1.5" style="{{ $muted }}"/>

        {{-- Шапка є на всіх сторінках --}}
        <rect x="8" y="23" width="184" height="12" rx="2" style="{{ $placement === 'header' ? $spot : $muted }}" opacity="{{ $placement === 'header' ? 1 : .45 }}"/>

        @if ($placement === 'sidebar')
            {{-- Сторінка сервера: назва, опис зліва, бічна колонка справа --}}
            <rect x="8" y="40" width="20" height="20" rx="3" style="{{ $muted }}"/>
            <rect x="32" y="42" width="60" height="6" rx="1.5" style="{{ $muted }}"/>
            <rect x="32" y="52" width="40" height="4" rx="1" style="{{ $soft }}"/>
            @foreach ([66, 76, 86, 96, 106, 116] as $y)
                <rect x="8" y="{{ $y }}" width="{{ $loop->even ? 112 : 124 }}" height="5" rx="1" style="{{ $soft }}"/>
            @endforeach
            <rect x="142" y="40" width="50" height="42" rx="2" style="{{ $spot }}"/>
            <rect x="142" y="86" width="50" height="36" rx="2" style="{{ $soft }}"/>
        @else
            {{-- Сторінка гри (для шапки — вона ж як приклад): блок гри, банер гри, список серверів --}}
            <rect x="8" y="39" width="184" height="24" rx="3" style="{{ $soft }}"/>
            <rect x="14" y="45" width="58" height="7" rx="1.5" style="{{ $muted }}"/>
            <rect x="14" y="55" width="40" height="3" rx="1" style="{{ $muted }}"/>
            <rect x="8" y="67" width="{{ $placement === 'game_side' ? 128 : 184 }}" height="10" rx="2"
                  style="{{ $placement === 'game' ? $spot : $muted }}" opacity="{{ $placement === 'game' ? 1 : .45 }}"/>
            @foreach ([81, 92, 103, 114] as $y)
                <rect x="8" y="{{ $y }}" width="{{ $placement === 'game_side' ? 128 : 184 }}" height="8" rx="1.5" style="{{ $soft }}"/>
            @endforeach
            @if ($placement === 'game_side')
                <rect x="142" y="67" width="50" height="34" rx="2" style="{{ $spot }}"/>
                <rect x="142" y="105" width="50" height="17" rx="2" style="{{ $soft }}"/>
            @endif
        @endif
    </svg>
    <figcaption class="mt-1.5 max-w-[200px] text-xs text-moss">{{ __("billing.ads.where.{$placement}") }}</figcaption>
</figure>
