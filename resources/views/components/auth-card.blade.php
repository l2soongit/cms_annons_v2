@props(['title', 'lead' => null])

<x-layouts.app :title="$title.' | graj.club'">
    <div class="mx-auto max-w-sm py-6">
        <h1 class="font-display text-2xl font-bold tracking-tight">{{ $title }}</h1>
        @if ($lead)
            <p class="mt-2 text-moss">{{ $lead }}</p>
        @endif

        @if (session('status'))
            <p class="mt-6 rounded-md border border-signal/40 bg-signal/10 px-4 py-3 text-sm" role="status">{{ session('status') }}</p>
        @endif

        <div class="mt-8">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="mt-8 border-t border-seam pt-6 text-sm text-moss">{{ $footer }}</div>
        @endisset
    </div>
</x-layouts.app>
