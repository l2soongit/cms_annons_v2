@props(['points', 'key', 'label', 'tone' => 'signal'])

@php($peak = max(1, collect($points)->max($key)))
@php($total = collect($points)->sum($key))

<figure {{ $attributes }}>
    <figcaption class="flex items-baseline justify-between text-sm text-moss">
        <span>{{ $label }}</span>
        @if ($key === 'votes')
            <span class="tabular-nums">{{ number_format($total, 0, ',', ' ') }}</span>
        @else
            <span class="tabular-nums">max {{ number_format($peak, 0, ',', ' ') }}</span>
        @endif
    </figcaption>
    <div class="mt-3 flex h-24 items-end gap-px border-b border-seam" role="img" aria-label="{{ $label }}">
        @foreach ($points as $point)
            <div @class(['flex-1 rounded-t-sm', 'bg-signal/70' => $tone === 'signal', 'bg-amber/80' => $tone === 'amber'])
                 style="height: {{ $point[$key] > 0 ? max(2, round($point[$key] / $peak * 100)) : 0 }}%"
                 title="{{ $point['label'] }}: {{ $point[$key] }}"></div>
        @endforeach
    </div>
    <div class="mt-1 flex justify-between text-xs text-moss tabular-nums" aria-hidden="true">
        <span>{{ $points->first()['label'] ?? '' }}</span>
        <span>{{ $points->last()['label'] ?? '' }}</span>
    </div>
</figure>
