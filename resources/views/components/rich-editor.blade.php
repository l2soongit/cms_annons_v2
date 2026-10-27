@props(['name', 'label', 'value' => '', 'hint' => null])

{{-- Trix керує DOM сам, тому wire:ignore; значення передається в Livewire на кожну зміну --}}
<div>
    <label for="editor-{{ $name }}" class="mb-1.5 block text-sm font-medium">{{ $label }}</label>
    <div wire:ignore>
        <input id="input-{{ $name }}" type="hidden" value="{{ $value }}">
        <trix-editor id="editor-{{ $name }}" input="input-{{ $name }}"
                     x-data
                     x-on:trix-change="$wire.set('{{ $name }}', $event.target.value, false)"
                     x-on:trix-file-accept.prevent
                     @error($name) aria-invalid="true" @enderror
                     class="trix-content min-h-48 rounded-b-md border border-seam bg-slab px-3 py-2.5 text-bone focus:border-amber focus:outline-none"></trix-editor>
    </div>
    @if ($hint)
        <p class="mt-1.5 text-xs text-moss">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1.5 text-sm text-fade">{{ $message }}</p>
    @enderror
</div>
