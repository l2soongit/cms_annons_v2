@props(['action'])

<form method="POST" action="{{ $action }}" class="mt-3 space-y-3">
    @csrf
    <select name="reason" required class="w-full rounded-md border border-seam bg-slab px-3 py-2 text-bone" aria-label="{{ __('reviews.report_reason') }}">
        @foreach (\App\Models\Report::REASONS as $reason)
            <option value="{{ $reason }}">{{ __("reviews.reasons.{$reason}") }}</option>
        @endforeach
    </select>
    <textarea name="comment" rows="2" maxlength="1000" placeholder="{{ __('reviews.report_comment') }}" class="w-full rounded-md border border-seam bg-slab px-3 py-2 text-bone" aria-label="{{ __('reviews.report_comment') }}"></textarea>
    <x-button variant="secondary">{{ __('reviews.send_report') }}</x-button>
</form>
