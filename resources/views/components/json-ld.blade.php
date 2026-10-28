@props(['data'])
{{-- Структуровані дані schema.org (ТЗ 4, SEO); JSON_HEX_TAG не дає закрити тег скрипта з даних --}}
<script type="application/ld+json">{!! json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
