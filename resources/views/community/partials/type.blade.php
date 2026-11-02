{{-- Позначка типу теми; для новини сервера — назва сервера --}}
@php
    $cls = ['news' => 'news', 'server_news' => 'srv', 'guide' => 'guide', 'question' => 'ask', 'clan' => 'clan'][$topic->type] ?? '';
@endphp
<span class="cm-chip {{ $cls }}">{{ __('community.types.'.$topic->type) }}@if (($withServer ?? false) && $topic->type === 'server_news' && $topic->server) · {{ $topic->server->name }}@endif</span>
