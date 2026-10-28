<x-layouts.cabinet :title="'№'.$ticket->id.' · '.$ticket->subject.' | graj.club'" :heading="__('messages.support.title')" :subtitle="__('messages.support.lead')">
    <x-flash class="mb-6" />

    <div class="c-card c-inbox has-chat">
        @include('cabinet.support._tickets', ['tickets' => $tickets, 'current' => $ticket])
        <section class="c-chat" aria-labelledby="chat-title">
            <div class="c-chat-head">
                <a href="{{ route('cabinet.support') }}" class="c-icon-btn" aria-label="{{ __('messages.support.title') }}">‹</a>
                <div style="min-width:0">
                    <span id="chat-title">№{{ $ticket->id }} · {{ $ticket->subject }}</span>
                    <small>{{ __("messages.support.categories.{$ticket->category}") }} · {{ __("messages.support.statuses.{$ticket->status}") }}</small>
                </div>
                <span @class(['c-status', 'is-ok' => $ticket->status === 'answered', 'is-wait' => $ticket->status === 'open', 'is-bad' => $ticket->status === 'closed']) style="margin-left:auto">{{ __("messages.support.statuses.{$ticket->status}") }}</span>
            </div>
            <div class="c-chat-body">
                <livewire:messages.thread :conversation="$ticket->conversation" />
            </div>
        </section>
    </div>
</x-layouts.cabinet>
