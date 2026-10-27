@props(['status'])

@php
    $tone = match ($status) {
        \App\Enums\ServerStatus::Published => 'border-signal/40 text-signal',
        \App\Enums\ServerStatus::Pending => 'border-amber/50 text-amber',
        \App\Enums\ServerStatus::Rejected, \App\Enums\ServerStatus::Blocked => 'border-fade/50 text-fade',
        default => 'border-seam text-moss',
    };
@endphp

<span {{ $attributes->class(['inline-block rounded border px-2 py-0.5 text-xs font-medium', $tone]) }}>{{ $status->label() }}</span>
