{{-- Аватар: фото або перша літера на кольорі; куплена рамка — клас cm-frame-* --}}
@php
    $name = $user?->nickname ?: ($user?->name ?? '?');
    $palette = ['c1', 'c2', 'c3', 'c4', 'c5', 'c6'];
    $color = $palette[($user?->id ?? 0) % 6];
    $perks = (array) ($user?->perks ?? []);
    $frame = isset($perks['frame']) && (empty($perks['frame']['until']) || now()->lt($perks['frame']['until'])) ? $perks['frame']['value'] : null;
@endphp
<span class="cm-ava {{ $size ?? '' }} {{ $color }} @if ($frame) cm-frame-{{ preg_replace('/[^a-z0-9-]/', '', (string) $frame) }} @endif" aria-hidden="true">@if ($user?->avatar)<img src="{{ str_starts_with($user->avatar, 'http') ? $user->avatar : \App\Support\HomePageData::url($user->avatar) }}" alt="" loading="lazy">@else{{ mb_strtoupper(mb_substr($name, 0, 1)) }}@endif</span>
