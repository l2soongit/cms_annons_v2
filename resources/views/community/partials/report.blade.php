{{-- Скарга на тему чи відповідь: розкривна форма без діалогів браузера --}}
<details class="cm-report">
  <summary>{{ __('community.report.button') }}</summary>
  <form method="post" action="{{ route('community.report') }}">
    @csrf
    <input type="hidden" name="target" value="{{ $target }}"><input type="hidden" name="id" value="{{ $id }}">
    <label for="rep-{{ $target }}-{{ $id }}">{{ __('community.report.reason') }}</label>
    <select id="rep-{{ $target }}-{{ $id }}" name="reason" required>
      @foreach (\App\Models\Report::REASONS as $reason)<option value="{{ $reason }}">{{ __('reviews.reasons.'.$reason) }}</option>@endforeach
    </select>
    <label class="sr" for="rep-c-{{ $target }}-{{ $id }}">{{ __('community.report.comment') }}</label>
    <textarea id="rep-c-{{ $target }}-{{ $id }}" name="comment" rows="2" maxlength="1000" placeholder="{{ __('community.report.comment') }}"></textarea>
    <button class="btn btn-ghost btn-sm">{{ __('community.report.send') }}</button>
  </form>
</details>
