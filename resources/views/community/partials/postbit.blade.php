{{-- Картка автора зліва від повідомлення: аватар, нік, рівень, значки (команда / власник сервера), лічильники --}}
@php
    $lvl = \App\Community\Bonus::level($author);
    $isStaff = in_array($author->id, $staffIds, true);
    $isOwner = in_array($author->id, $ownerIds, true) || in_array($author->id, $serverOwnerIds, true);
@endphp
<div class="f-pbit">
  @include('community.partials.ava', ['user' => $author])
  @include('community.partials.name', ['user' => $author])
  <span class="f-utitle">{{ __('community.levels.'.$lvl['key']) }}</span>
  @if ($isStaff)<span class="f-badge staff">{{ __('community.roles.team') }}</span>@endif
  @if ($isOwner)<span class="f-badge owner">{{ __('community.roles.owner') }}</span>@endif
  <div class="f-pstats">
    <div>{{ __('community.forum.messages') }}<b class="num">{{ number_format($author->forum_posts_count, 0, ',', ' ') }}</b></div>
    <div>{{ __('community.forum.points') }}<b class="num">{{ number_format($author->bonus_earned, 0, ',', ' ') }}</b></div>
    <div>{{ __('community.forum.since') }}<b>{{ $author->created_at?->format('Y') }}</b></div>
  </div>
</div>
