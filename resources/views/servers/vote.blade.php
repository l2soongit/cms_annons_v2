<x-layouts.app :title="__('vote.title', ['server' => $server->name])">
    <div class="mx-auto max-w-md py-6">
        <a href="{{ route('servers.show', $server) }}" class="text-sm text-moss hover:text-bone">{{ $server->name }}</a>
        <h1 class="mt-2 font-display text-2xl font-bold tracking-tight">{{ __('vote.heading') }}</h1>
        <p class="mt-2 text-moss">{{ __('vote.lead', ['hours' => config('graj.voting.interval_hours')]) }}</p>

        @if ($nextVoteAt)
            <div class="mt-8 rounded-md border border-seam bg-slab px-5 py-4" role="status">
                <p class="font-medium">{{ __('vote.already') }}</p>
                <p class="mt-1 text-sm text-moss">{{ __('vote.next_at', ['time' => $nextVoteAt->locale(app()->getLocale())->diffForHumans()]) }}</p>
            </div>
        @else
            <form method="POST" action="{{ route('servers.vote.store', $server) }}" class="mt-8 space-y-5" data-fingerprint-form>
                @csrf
                <input type="hidden" name="fingerprint">

                <x-field name="nickname" :label="__('vote.nickname')" :hint="__('vote.nickname_hint')" maxlength="32" autocomplete="off" autofocus />

                <x-turnstile />

                @error('vote')
                    <p class="rounded-md border border-fade/40 bg-fade/10 px-4 py-3 text-sm" role="alert">{{ $message }}</p>
                @enderror

                <x-button class="w-full py-3 text-base">{{ __('vote.submit') }}</x-button>
            </form>
        @endif
    </div>
</x-layouts.app>
