@props(['variant' => 'primary', 'href' => null])

@php
    $classes = match ($variant) {
        'primary' => 'bg-amber text-amber-ink hover:brightness-110',
        'secondary' => 'border border-seam hover:border-amber hover:text-amber',
        'danger' => 'border border-fade/50 text-fade hover:bg-fade/10',
        'ghost' => 'text-moss hover:text-bone',
    };
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['inline-flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-medium transition', $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit'])->class(['inline-flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60', $classes]) }}>{{ $slot }}</button>
@endif
