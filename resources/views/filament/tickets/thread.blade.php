{{-- Історія тікета в модальному вікні відповіді --}}
<div style="display:grid;gap:10px;max-height:24rem;overflow-y:auto">
    @foreach ($ticket->conversation?->messages ?? [] as $message)
        @php($fromAuthor = ! $message->is_support)
        <div style="padding:10px 12px;border-radius:8px;{{ $fromAuthor ? 'background:rgba(127,127,127,.12)' : 'background:rgba(16,185,129,.12);margin-left:24px' }}">
            <div style="font-size:12px;opacity:.7">{{ $fromAuthor ? ($message->user?->name ?? '—') : 'Підтримка · '.($message->user?->name ?? '—') }} · {{ $message->created_at->format('d.m.Y H:i') }}</div>
            @if ($message->body !== '')<div style="margin-top:4px;white-space:pre-line">{{ $message->body }}</div>@endif
            @if ($message->attachment)<a href="{{ route('cabinet.messages.attachment', $message) }}" target="_blank" style="font-size:12px;text-decoration:underline">Вкладення</a>@endif
        </div>
    @endforeach
</div>
