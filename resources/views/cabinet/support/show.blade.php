<x-layouts.app :title="'№'.$ticket->id.' · '.$ticket->subject.' | graj.club'">
    <x-cabinet-nav />
    <x-flash class="mb-6" />
    <a href="{{ route('cabinet.support') }}" class="text-sm text-moss hover:text-bone">{{ __('messages.support.title') }}</a>
    <h1 class="mt-2 font-display text-2xl font-bold tracking-tight">№{{ $ticket->id }} · {{ $ticket->subject }}</h1>
    <p class="mt-1 text-sm text-moss">{{ __("messages.support.categories.{$ticket->category}") }} · {{ __("messages.support.statuses.{$ticket->status}") }}</p>

    <div class="mt-8 max-w-3xl">
        <livewire:messages.thread :conversation="$ticket->conversation" />
    </div>
</x-layouts.app>
