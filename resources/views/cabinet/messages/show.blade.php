<x-layouts.cabinet :title="($other?->name ?? __('messages.title')).' | graj.club'" :heading="__('messages.title')" :subtitle="__('messages.lead')">
    <div class="c-card c-inbox has-chat">
        @include('cabinet.messages._threads', ['conversations' => $conversations, 'current' => $conversation])
        <section class="c-chat" aria-labelledby="chat-title">
            <div class="c-chat-head">
                <a href="{{ route('cabinet.messages') }}" class="c-icon-btn" aria-label="{{ __('messages.title') }}">‹</a>
                <span class="c-ava" style="width:34px;height:34px;font-size:13px" aria-hidden="true">{{ mb_strtoupper(mb_substr($other?->name ?? '?', 0, 1)) }}</span>
                <div>
                    <span id="chat-title">{{ $other?->name ?? __('messages.deleted_user') }}</span>
                    @if ($conversation->server)
                        <small>{{ __('messages.about', ['server' => '']) }}<a href="{{ route('servers.show', $conversation->server) }}" style="color:var(--c-amber)">{{ $conversation->server->name }}</a></small>
                    @endif
                </div>
            </div>
            <div class="c-chat-body">
                <livewire:messages.thread :conversation="$conversation" />
            </div>
        </section>
    </div>
</x-layouts.cabinet>
