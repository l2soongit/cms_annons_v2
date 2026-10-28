<x-layouts.app :title="($other?->name ?? __('messages.title')).' | graj.club'">
    <x-cabinet-nav />
    <a href="{{ route('cabinet.messages') }}" class="text-sm text-moss hover:text-bone">{{ __('messages.title') }}</a>
    <h1 class="mt-2 font-display text-2xl font-bold tracking-tight">{{ $other?->name ?? __('messages.deleted_user') }}</h1>
    @if ($conversation->server)
        <p class="mt-1 text-sm text-moss">{{ __('messages.about', ['server' => '']) }}<a href="{{ route('servers.show', $conversation->server) }}" class="text-amber hover:underline">{{ $conversation->server->name }}</a></p>
    @endif

    <div class="mt-8 max-w-3xl">
        <livewire:messages.thread :conversation="$conversation" />
    </div>
</x-layouts.app>
