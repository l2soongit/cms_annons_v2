{{-- Аватар: фото або перша літера на кольорі; куплена рамка — клас cm-frame-* --}}
@php
    $name = $user?->nickname ?: ($user?->name ?? '?');
    $palette = ['c1', 'c2', 'c3', 'c4', 'c5', 'c6'];
    $color = $palette[($user?->id ?? 0) % 6];
    $frame = \App\Community\Perks::active($user, 'frame');
    // Анімований аватар — лише поки діє «плюшка» з магазину балів
    $avatar = $user?->avatar && (! str_ends_with(strtolower($user->avatar), '.gif') || \App\Community\Perks::has($user, 'gif_avatar')) ? $user->avatar : null;
@endphp
<span class="cm-ava {{ $size ?? '' }} {{ $color }} @if ($frame) cm-frame-{{ preg_replace('/[^a-z0-9-]/', '', (string) $frame) }} @endif" aria-hidden="true">@if ($avatar)<img src="{{ str_starts_with($avatar, 'http') ? $avatar : \App\Support\HomePageData::url($avatar) }}" alt="" loading="lazy">@else{{ mb_strtoupper(mb_substr($name, 0, 1)) }}@endif</span>
