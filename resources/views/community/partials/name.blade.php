{{-- Нік із купленим кольором і значком --}}
@php
    $perks = (array) ($user->perks ?? []);
    $active = fn (string $k) => isset($perks[$k]) && (empty($perks[$k]['until']) || now()->lt($perks[$k]['until'])) ? $perks[$k]['value'] : null;
    $color = $active('name_color');
    $badge = $active('badge');
@endphp
<b class="cm-name" @if ($color && preg_match('/^#[0-9a-f]{3,8}$/i', $color)) style="color: {{ $color }}" @endif>{{ $user->nickname ?: $user->name }}</b>@if ($badge)<span class="cm-badge" title="{{ __('community.perk_badge') }}">{{ $badge }}</span>@endif
