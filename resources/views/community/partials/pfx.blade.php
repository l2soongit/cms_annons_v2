{{-- Префікс теми за типом (обговорення — без префікса) --}}
@php $cls = ['news' => 'news', 'server_news' => 'srv', 'guide' => 'guide', 'question' => 'ask', 'clan' => 'clan'][$topic->type] ?? null; @endphp
@if ($cls)<span class="f-pfx {{ $cls }}">{{ __('community.types.'.$topic->type) }}</span>@endif
