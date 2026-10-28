<x-layouts.app :title="__('messages.support.title').' | graj.club'">
    <x-cabinet-nav />
    <x-flash class="mb-6" />

    <h1 class="font-display text-3xl font-bold tracking-tight">{{ __('messages.support.title') }}</h1>
    <p class="mt-3 max-w-2xl text-moss">{{ __('messages.support.lead') }}</p>

    <div class="mt-10 grid gap-x-12 gap-y-12 lg:grid-cols-[1fr_22rem]">
        <section aria-labelledby="t-list">
            <h2 id="t-list" class="font-display text-lg font-medium">{{ __('messages.support.list') }}</h2>
            @if ($tickets->isEmpty())
                <p class="mt-3 text-sm text-moss">{{ __('messages.support.empty') }}</p>
            @else
                <ul class="mt-4 divide-y divide-seam border-y border-seam">
                    @foreach ($tickets as $ticket)
                        @php($unread = $ticket->conversation?->isUnreadFor(auth()->user()))
                        <li>
                            <a href="{{ route('cabinet.support.show', $ticket) }}" class="flex flex-wrap items-baseline justify-between gap-3 py-4 hover:bg-slab/60">
                                <span @class(['font-semibold' => $unread])>№{{ $ticket->id }} · {{ $ticket->subject }}
                                    <span class="block text-xs font-normal text-moss">{{ __("messages.support.categories.{$ticket->category}") }} · {{ $ticket->updated_at->diffForHumans() }}</span></span>
                                <span @class(['text-sm', 'text-signal' => $ticket->status === 'answered', 'text-moss' => $ticket->status !== 'answered'])>{{ __("messages.support.statuses.{$ticket->status}") }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-labelledby="t-new">
            <h2 id="t-new" class="font-display text-lg font-medium">{{ __('messages.support.new') }}</h2>
            <form method="POST" action="{{ route('cabinet.support.store') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="t-category" class="mb-1.5 block text-sm font-medium">{{ __('messages.support.category') }}</label>
                    <select id="t-category" name="category" required class="w-full rounded-md border border-seam bg-slab px-3 py-2.5 text-bone focus:border-amber focus:outline-none">
                        @foreach (\App\Models\Ticket::CATEGORIES as $category)
                            <option value="{{ $category }}" @selected(old('category') === $category)>{{ __("messages.support.categories.{$category}") }}</option>
                        @endforeach
                    </select>
                </div>
                <x-field name="subject" :label="__('messages.support.subject')" maxlength="120" required />
                <x-field name="body" :label="__('messages.support.body')" type="textarea" maxlength="2000" required />
                <div>
                    <label for="t-attachment" class="mb-1.5 block text-sm font-medium">{{ __('messages.attach') }}</label>
                    <input id="t-attachment" name="attachment" type="file" accept="image/*" class="block w-full text-sm text-moss file:mr-3 file:rounded-md file:border-0 file:bg-slab-2 file:px-3 file:py-2 file:text-bone">
                </div>
                <x-button class="w-full">{{ __('messages.support.submit') }}</x-button>
            </form>
        </section>
    </div>
</x-layouts.app>
