{{-- hreflang: та сама сторінка іншими мовами (SEO, ТЗ 4); x-default — основна мова --}}
@php($locales = \App\Support\Locales::all())
@if (count($locales) > 1)
    @foreach (array_keys($locales) as $code)
<link rel="alternate" hreflang="{{ $code }}" href="{{ \App\Support\Locales::urlFor($code) }}">
    @endforeach
<link rel="alternate" hreflang="x-default" href="{{ \App\Support\Locales::urlFor(\App\Support\Locales::default()) }}">
@endif
