<div class="mx-auto max-w-3xl">
    <a href="{{ route('cabinet') }}" class="text-sm text-moss hover:text-bone">{{ __('cabinet.title') }}</a>
    <h1 class="mt-2 font-display text-3xl font-bold tracking-tight">
        {{ $server ? __('cabinet.form.edit_title', ['name' => $server->name]) : __('cabinet.form.create_title') }}
    </h1>
    @if ($server?->pending_changes)
        <p class="mt-3 rounded-md border border-amber/40 bg-amber/10 px-4 py-3 text-sm">{{ __('cabinet.form.pending_notice') }}</p>
    @endif

    <form wire:submit="save" class="mt-10 space-y-12">
        <section class="space-y-5" aria-labelledby="s-game">
            <h2 id="s-game" class="font-display text-lg font-medium">{{ __('cabinet.form.section_game') }}</h2>

            <fieldset>
                <legend class="mb-2 text-sm font-medium">{{ __('cabinet.form.game') }}</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->games as $g)
                        <label @class([
                            'cursor-pointer rounded-md border px-4 py-2 text-sm transition',
                            'border-amber bg-amber/10 text-bone' => $game_id === $g->id,
                            'border-seam text-moss hover:text-bone' => $game_id !== $g->id,
                        ])>
                            <input type="radio" wire:model.live="game_id" value="{{ $g->id }}" class="sr-only">{{ $g->name }}
                        </label>
                    @endforeach
                </div>
                @error('game_id') <p class="mt-1.5 text-sm text-fade">{{ $message }}</p> @enderror
            </fieldset>

            @if ($this->game)
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ($this->game->fields as $field)
                        <div wire:key="field-{{ $field->id }}">
                            <label for="field-{{ $field->key }}" class="mb-1.5 block text-sm font-medium">
                                {{ $field->label }} @unless ($field->is_required)<span class="font-normal text-moss">({{ __('cabinet.form.optional') }})</span>@endunless
                            </label>
                            @if ($field->type === 'select')
                                <select id="field-{{ $field->key }}" wire:model="fields.{{ $field->key }}"
                                        class="w-full rounded-md border border-seam bg-slab px-3 py-2.5 text-bone focus:border-amber focus:outline-none">
                                    <option value="">—</option>
                                    @foreach ($field->options as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            @elseif ($field->type === 'multiselect')
                                <div id="field-{{ $field->key }}" class="flex flex-wrap gap-2">
                                    @foreach ($field->options as $option)
                                        <label class="inline-flex items-center gap-2 rounded-md border border-seam bg-slab px-3 py-2 text-sm">
                                            <input type="checkbox" wire:model="fields.{{ $field->key }}" value="{{ $option['value'] }}" class="accent-amber">{{ $option['label'] }}
                                        </label>
                                    @endforeach
                                </div>
                            @elseif ($field->type === 'boolean')
                                <label class="inline-flex items-center gap-2 py-2.5 text-sm">
                                    <input id="field-{{ $field->key }}" type="checkbox" wire:model="fields.{{ $field->key }}" class="accent-amber">{{ __('ui.yes') }}
                                </label>
                            @else
                                <input id="field-{{ $field->key }}" wire:model="fields.{{ $field->key }}" type="{{ $field->type === 'number' ? 'number' : 'text' }}"
                                       class="w-full rounded-md border border-seam bg-slab px-3 py-2.5 text-bone focus:border-amber focus:outline-none">
                            @endif
                            @error("fields.{$field->key}") <p class="mt-1.5 text-sm text-fade">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="space-y-5" aria-labelledby="s-main">
            <h2 id="s-main" class="font-display text-lg font-medium">{{ __('cabinet.form.section_main') }}</h2>
            <x-field name="name" wire:model="name" :label="__('cabinet.form.name')" maxlength="64" />
            <div class="grid gap-5 sm:grid-cols-[1fr_9rem]">
                <x-field name="host" wire:model="host" :label="__('cabinet.form.host')" :hint="__('cabinet.form.host_hint')" placeholder="play.example.com" />
                <x-field name="port" wire:model="port" type="number" :label="__('cabinet.form.port')" min="1" max="65535" />
            </div>
            <x-field name="opens_at" wire:model="opens_at" type="date" :label="__('cabinet.form.opens_at')" :hint="__('cabinet.form.opens_at_hint')" />
            <p class="text-xs text-moss">{{ __('cabinet.form.moderated_hint') }}</p>
        </section>

        <section class="space-y-5" aria-labelledby="s-desc">
            <h2 id="s-desc" class="font-display text-lg font-medium">{{ __('cabinet.form.section_description') }}</h2>
            @foreach (\App\Support\Locales::all() as $code => $language)
                <x-rich-editor :name="'descriptions.'.$code" :value="$descriptions[$code] ?? ''" :label="__('cabinet.form.description_in', ['language' => $language])"
                               :hint="$code === \App\Support\Locales::default() ? __('cabinet.form.description_hint') : null" />
            @endforeach

            <div class="grid gap-5 sm:grid-cols-[1fr_14rem]">
                <fieldset>
                    <legend class="mb-2 text-sm font-medium">{{ __('cabinet.form.languages') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Livewire\Cabinet\ServerForm::LANGUAGES as $code => $langName)
                            <label class="cursor-pointer">
                                <input type="checkbox" wire:model="languages" value="{{ $code }}" class="peer sr-only">
                                <span class="block rounded-md border border-seam px-3 py-1.5 text-sm text-moss peer-checked:border-amber peer-checked:bg-amber/10 peer-checked:text-bone peer-focus-visible:outline-2 peer-focus-visible:outline-amber">{{ $langName }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('languages') <p class="mt-1.5 text-sm text-fade">{{ $message }}</p> @enderror
                </fieldset>
                <div>
                    <label for="country" class="mb-1.5 block text-sm font-medium">{{ __('cabinet.form.country') }}</label>
                    <select id="country" wire:model="country" class="w-full rounded-md border border-seam bg-slab px-3 py-2.5 text-bone focus:border-amber focus:outline-none">
                        <option value="">—</option>
                        @foreach (\App\Livewire\Cabinet\ServerForm::COUNTRIES as $code => $countryName)
                            <option value="{{ $code }}">{{ $countryName }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-moss">{{ __('cabinet.form.country_hint') }}</p>
                </div>
            </div>
        </section>

        <section class="space-y-5" aria-labelledby="s-links">
            <h2 id="s-links" class="font-display text-lg font-medium">{{ __('cabinet.form.section_links') }}</h2>
            <x-field name="website" wire:model="website" type="url" :label="__('cabinet.form.website')" placeholder="https://" />
            <x-field name="discord" wire:model="discord" type="url" :label="__('cabinet.form.discord')" placeholder="https://discord.gg/…" />
            <x-field name="youtube" wire:model="youtube" type="url" :label="__('cabinet.form.youtube')" placeholder="https://youtube.com/…" />
        </section>

        <section class="space-y-5" aria-labelledby="s-media">
            <h2 id="s-media" class="font-display text-lg font-medium">{{ __('cabinet.form.section_media') }}</h2>

            @if ($server && $server->media->isNotEmpty())
                <ul class="flex flex-wrap gap-3" aria-label="{{ __('cabinet.form.media_current') }}">
                    @foreach ($server->media as $item)
                        <li wire:key="media-{{ $item->id }}" class="relative">
                            <img src="{{ Storage::disk('public')->url($item->path) }}" alt="{{ __('cabinet.form.media_'.$item->type) }}" class="h-20 w-28 rounded-md object-cover">
                            <span class="absolute left-1 top-1 rounded bg-night/80 px-1.5 text-xs">{{ __('cabinet.form.media_'.$item->type) }}</span>
                            <button type="button" wire:click="deleteMedia({{ $item->id }})" wire:confirm="{{ __('cabinet.form.media_delete_confirm') }}"
                                    class="absolute right-1 top-1 rounded bg-night/80 px-1.5 text-xs text-fade hover:bg-fade hover:text-night"
                                    aria-label="{{ __('cabinet.form.media_delete') }}">✕</button>
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="logo" class="mb-1.5 block text-sm font-medium">{{ __('cabinet.form.logo') }}</label>
                    <input id="logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-moss file:mr-3 file:rounded-md file:border-0 file:bg-slab-2 file:px-3 file:py-2 file:text-bone">
                    <p class="mt-1.5 text-xs text-moss">{{ __('cabinet.form.logo_hint') }}</p>
                    @error('logo') <p class="mt-1.5 text-sm text-fade">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="banner" class="mb-1.5 block text-sm font-medium">{{ __('cabinet.form.banner') }}</label>
                    <input id="banner" type="file" wire:model="banner" accept="image/png,image/jpeg,image/webp,image/gif" class="block w-full text-sm text-moss file:mr-3 file:rounded-md file:border-0 file:bg-slab-2 file:px-3 file:py-2 file:text-bone">
                    @error('banner') <p class="mt-1.5 text-sm text-fade">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label for="screenshots" class="mb-1.5 block text-sm font-medium">{{ __('cabinet.form.screenshots') }}</label>
                <input id="screenshots" type="file" wire:model="screenshots" multiple accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-moss file:mr-3 file:rounded-md file:border-0 file:bg-slab-2 file:px-3 file:py-2 file:text-bone">
                <p class="mt-1.5 text-xs text-moss">{{ __('cabinet.form.screenshots_hint', ['n' => config('graj.servers.max_screenshots')]) }}</p>
                @error('screenshots') <p class="mt-1.5 text-sm text-fade">{{ $message }}</p> @enderror
                @error('screenshots.*') <p class="mt-1.5 text-sm text-fade">{{ $message }}</p> @enderror
            </div>
            <div wire:loading wire:target="logo,banner,screenshots" class="text-sm text-moss">{{ __('cabinet.form.uploading') }}</div>
        </section>

        <div class="flex items-center gap-4 border-t border-seam pt-6">
            <x-button wire:loading.attr="disabled">{{ $server ? __('cabinet.form.save') : __('cabinet.form.submit') }}</x-button>
            <a href="{{ $server ? route('cabinet.servers.show', $server) : route('cabinet') }}" class="text-sm text-moss hover:text-bone">{{ __('cabinet.form.cancel') }}</a>
        </div>
    </form>
</div>
