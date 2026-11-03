{{-- Нік із купленим кольором і значком --}}
@php
    use App\Community\Perks;
    $color = Perks::active($user, 'name_color');
    $badge = Perks::active($user, 'badge');
    $fx = Perks::nameEffect($user);
@endphp
<b class="cm-name {{ $fx }}" @if (! $fx && $color && preg_match('/^#[0-9a-f]{3,8}$/i', $color)) style="color: {{ $color }}" @endif>{{ $user->nickname ?: $user->name }}</b>@if ($badge)<span class="cm-badge" title="{{ __('community.perk_badge') }}">{{ $badge }}</span>@endif
