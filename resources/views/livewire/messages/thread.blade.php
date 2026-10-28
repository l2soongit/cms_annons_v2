@php
    $conversation = $this->conversation;
    $ticket = $conversation->ticket;
    $otherReadId = $conversation->lastReadId($other);
    $lastMine = $this->thread->where('user_id', $me->id)->where('is_support', false)->last();
    $blocked = $other && ! $ticket && $me->hasBlocked($other);
@endphp
<div>
    {{-- Опитування: нові повідомлення без перезавантаження (на хостингу немає WebSocket).
         Свої — праворуч, співрозмовника й підтримки — ліворуч, з аватаркою й підписом, хто написав --}}
    <ol wire:poll.{{ config('graj.messages.poll_seconds') }}s class="space-y-4" aria-live="polite" aria-label="{{ __('messages.title') }}">
        @foreach ($this->thread as $message)
            @php
                $fromSupport = (bool) $message->is_support;
                $mine = ! $fromSupport && $message->user_id === $me->id;
                $author = $mine ? __('messages.you') : ($fromSupport ? __('messages.support_name') : ($message->user?->name ?? __('messages.deleted_user')));
            @endphp
            <li wire:key="m-{{ $message->id }}" @class(['flex items-end gap-3', 'flex-row-reverse' => $mine])>
                <span aria-hidden="true" @class([
                    'grid size-9 shrink-0 place-items-center rounded-full font-display text-sm font-bold',
                    'bg-amber text-amber-ink' => $mine,
                    'bg-signal text-amber-ink' => $fromSupport,
                    'bg-slab-2 text-bone' => ! $mine && ! $fromSupport,
                ])>{{ $fromSupport ? 'G' : mb_strtoupper(mb_substr($mine ? $me->name : ($message->user?->name ?? '?'), 0, 1)) }}</span>
                <div @class([
                    'max-w-[80%] rounded-2xl border px-4 py-3 text-sm sm:max-w-[65%]',
                    'rounded-br-sm border-amber/40 bg-amber/10 text-bone' => $mine,
                    'rounded-bl-sm border-signal/40 bg-signal/10 text-bone' => $fromSupport,
                    'rounded-bl-sm border-seam bg-slab-2 text-bone' => ! $mine && ! $fromSupport,
                ])>
                    <p @class(['flex items-baseline gap-2 text-xs', 'justify-end' => $mine])>
                        <b @class(['font-semibold', 'text-amber' => $mine, 'text-signal' => $fromSupport, 'text-bone' => ! $mine && ! $fromSupport])>{{ $author }}</b>
                        <time class="text-moss" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('d.m H:i') }}</time>
                    </p>
                    @if ($message->body !== '')<p class="mt-1 whitespace-pre-line break-words">{{ $message->body }}</p>@endif
                    @if ($message->attachment)
                        <a href="{{ route('cabinet.messages.attachment', $message) }}" target="_blank" class="mt-2 block">
                            <img src="{{ route('cabinet.messages.attachment', $message) }}" alt="" class="max-h-64 rounded" loading="lazy">
                        </a>
                    @endif
                    @if ($mine && $lastMine?->is($message) && $otherReadId >= $message->id)
                        <p class="mt-1 text-right text-xs text-signal">{{ __('messages.read') }}</p>
                    @endif
                    @if (! $mine && ! $fromSupport)
                        <button type="button" wire:click="report({{ $message->id }})" wire:confirm="{{ __('messages.report') }}?" class="mt-1 text-xs text-moss hover:text-fade">{{ __('messages.report') }}</button>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    @if (session('status'))<p class="mt-4 text-sm text-signal" role="status">{{ session('status') }}</p>@endif
    @if ($ticket?->status === 'closed')<p class="mt-6 text-sm text-moss">{{ __('messages.support.closed_notice') }}</p>@endif
    @if ($blocked)<p class="mt-6 text-sm text-moss">{{ __('messages.blocked_notice') }}</p>@endif

    <form wire:submit="send" class="mt-6 space-y-3">
        <label for="msg-body" class="sr-only">{{ __('messages.placeholder') }}</label>
        <textarea id="msg-body" wire:model="body" rows="3" maxlength="2000" placeholder="{{ __('messages.placeholder') }}"
                  class="w-full rounded-md border border-seam bg-slab px-3 py-2.5 text-bone placeholder:text-moss/70 focus:border-amber focus:outline-none"></textarea>
        @error('body') <p class="text-sm text-fade" role="alert">{{ $message }}</p> @enderror
        @error('attachment') <p class="text-sm text-fade" role="alert">{{ $message }}</p> @enderror
        <div class="flex flex-wrap items-center gap-3">
            <label class="cursor-pointer text-sm text-moss hover:text-bone">
                <input type="file" wire:model="attachment" accept="image/*" class="sr-only">{{ __('messages.attach') }}@if ($attachment) · {{ $attachment->getClientOriginalName() }}@endif
            </label>
            @if ($other && ! $ticket)
                <button type="button" wire:click="toggleBlock" class="text-sm text-moss hover:text-fade">{{ $blocked ? __('messages.unblock') : __('messages.block') }}</button>
            @endif
            <x-button class="ml-auto" wire:loading.attr="disabled">{{ __('messages.send') }}</x-button>
        </div>
    </form>
</div>
