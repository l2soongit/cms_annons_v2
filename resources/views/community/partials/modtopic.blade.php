{{-- Кнопки модератора для теми: схвалити / відхилити з причиною / приховати, закріпити, закрити --}}
<div class="f-modacts">
  @if (! $topic->isPublished())
    <form method="post" action="{{ route('community.mod.topic', [$topic, 'approve']) }}">@csrf<button class="f-btn hot sm">✓ {{ $topic->status === 'pending' ? __('community.mod.approve') : __('community.mod.restore') }}</button></form>
  @endif
  @if ($topic->status === 'pending')
    <details class="cm-report">
      <summary class="f-btn sm">{{ __('community.mod.reject') }}</summary>
      <form method="post" action="{{ route('community.mod.topic', [$topic, 'reject']) }}">
        @csrf
        <label for="rej-{{ $topic->id }}">{{ __('community.mod.reason') }}</label>
        <textarea id="rej-{{ $topic->id }}" name="reason" rows="2" maxlength="500" required></textarea>
        <button class="f-btn sm">{{ __('community.mod.reject') }}</button>
      </form>
    </details>
  @endif
  @if ($topic->isPublished())
    <form method="post" action="{{ route('community.mod.topic', [$topic, 'hide']) }}">@csrf<button class="f-btn sm">{{ __('community.mod.hide') }}</button></form>
    <form method="post" action="{{ route('community.mod.topic', [$topic, 'pin']) }}">@csrf<button class="f-btn sm">📌 {{ $topic->is_pinned ? __('community.mod.unpin') : __('community.mod.pin') }}</button></form>
    <form method="post" action="{{ route('community.mod.topic', [$topic, 'lock']) }}">@csrf<button class="f-btn sm">🔒 {{ $topic->is_locked ? __('community.mod.unlock') : __('community.mod.lock') }}</button></form>
  @endif
</div>
