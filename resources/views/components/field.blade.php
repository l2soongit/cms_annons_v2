@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null])

@php($id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name)))
@php($errorKey = str_replace(['[', ']'], ['.', ''], $name))

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}"
                  {{ $attributes->except(['class', 'id'])->merge(['rows' => 5]) }}
                  @error($errorKey) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
                  class="w-full rounded-md border border-seam bg-slab px-3 py-2.5 text-bone placeholder:text-moss/70 focus:border-amber focus:outline-none aria-invalid:border-fade">{{ old($errorKey, $value) }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
               @unless ($type === 'password') value="{{ old($errorKey, $value) }}" @endunless
               {{ $attributes->except(['class', 'id']) }}
               @error($errorKey) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
               class="w-full rounded-md border border-seam bg-slab px-3 py-2.5 text-bone placeholder:text-moss/70 focus:border-amber focus:outline-none aria-invalid:border-fade">
    @endif
    @if ($hint)
        <p class="mt-1.5 text-xs text-moss">{{ $hint }}</p>
    @endif
    @error($errorKey)
        <p id="{{ $id }}-error" class="mt-1.5 text-sm text-fade">{{ $message }}</p>
    @enderror
</div>
