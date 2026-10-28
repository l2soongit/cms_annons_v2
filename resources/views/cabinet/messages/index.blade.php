<x-layouts.cabinet :heading="__('messages.title')" :subtitle="__('messages.lead')">
    <x-flash class="mb-6" />

    <div class="c-card c-inbox">
        @include('cabinet.messages._threads', ['conversations' => $conversations])
        <div class="c-chat"><div class="c-chat-empty">{{ __('messages.pick') }}</div></div>
    </div>
    @if ($conversations->hasPages())<div style="margin-top:16px">{{ $conversations->links() }}</div>@endif
</x-layouts.cabinet>
