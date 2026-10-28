{{-- Перемикач мови: та сама сторінка іншою мовою. Стилі вбудовані — однаково в усіх макетах --}}
@php($locales = \App\Support\Locales::all())
@if (count($locales) > 1)
    {{-- Не <nav>: теми ігор стилізують посилання навігації в шапці --}}
    <div role="group" {{ $attributes->merge(['class' => 'locale-switcher']) }} aria-label="{{ __('ui.language') }}" style="display:inline-flex;align-items:center;gap:2px;margin:0;font:600 12px/1 system-ui,sans-serif;letter-spacing:.04em">
        @foreach ($locales as $code => $name)
            @php($current = $code === app()->getLocale())
            <a href="{{ \App\Support\Locales::urlFor($code) }}" hreflang="{{ $code }}" lang="{{ $code }}" title="{{ $name }}"
               @if ($current) aria-current="true" @endif
               style="display:inline-block;padding:6px 7px;border-radius:6px;background:none;text-transform:uppercase;color:inherit;opacity:{{ $current ? '1' : '.55' }};{{ $current ? 'box-shadow:inset 0 0 0 1px currentColor' : '' }}">{{ $code }}</a>
        @endforeach
    </div>
@endif
