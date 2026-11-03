{{-- Картка автора зліва від повідомлення: аватар, нік, рівень, значки (команда / власник сервера), лічильники --}}
@php
    $lvl = \App\Community\Bonus::level($author);
    $isStaff = in_array($author->id, $staffIds, true);
    $isOwner = in_array($author->id, $ownerIds, true) || in_array($author->id, $serverOwnerIds, true);
    $customTitle = \App\Community\Perks::active($author, 'title');
    $bg = \App\Community\Perks::cardBackground($author);
    $badges = \App\Community\Achievements::for($author, $authorStats[$author->id] ?? []);
@endphp
<div @class(['f-pbit', 'has-bg' => $bg]) @if ($bg) style="--pbit-bg: url('{{ $bg }}')" @endif>
  @include('community.partials.ava', ['user' => $author])
  @include('community.partials.name', ['user' => $author])
  @if ($customTitle)<span class="f-ctitle">{{ $customTitle }}</span>@endif
  <span class="f-utitle">{{ __('community.levels.'.$lvl['key']) }}</span>
  @if ($badges)<span class="f-ach">@foreach ($badges as $b)<span title="{{ __('community.ach.'.$b['key']) }}" aria-label="{{ __('community.ach.'.$b['key']) }}">{{ $b['icon'] }}</span>@endforeach</span>@endif
  @if ($isStaff)<span class="f-badge staff">{{ __('community.roles.team') }}</span>@endif
  @if ($isOwner)<span class="f-badge owner">{{ __('community.roles.owner') }}</span>@endif
  <div class="f-pstats">
    <div>{{ __('community.forum.messages') }}<b class="num">{{ number_format($author->forum_posts_count, 0, ',', ' ') }}</b></div>
    <div>{{ __('community.forum.points') }}<b class="num">{{ number_format($author->bonus_earned, 0, ',', ' ') }}</b></div>
    <div>{{ __('community.forum.since') }}<b>{{ $author->created_at?->format('Y') }}</b></div>
  </div>
</div>
