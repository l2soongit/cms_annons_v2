@php
    $rows = $this->rows();
    $locales = \App\Support\Locales::all();
    $default = \App\Support\Locales::default();
@endphp
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="description">
            Сірим — текст з файлів (для нової мови — англійський, поки немає перекладу). Свій текст замінює його на сайті й у листах; порожнє поле повертає текст з файлу.
            Заповнювачі на кшталт <code>:server</code> чи <code>:n</code> залишайте як є.
        </x-slot>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:12px">
            <label>
                <span style="display:block;font-size:12px;opacity:.7;margin-bottom:4px">Мова</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="locale">
                        @foreach ($locales as $code => $name)
                            <option value="{{ $code }}">{{ $name }} ({{ $code }}){{ $code === $default ? ' — основна' : '' }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label>
                <span style="display:block;font-size:12px;opacity:.7;margin-bottom:4px">Розділ</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="group">
                        @foreach (\App\Support\Translations::groups() as $g)
                            <option value="{{ $g }}">{{ $g }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label>
                <span style="display:block;font-size:12px;opacity:.7;margin-bottom:4px">Пошук за ключем або текстом</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="search" wire:model.live.debounce.400ms="search" placeholder="напр. кошик, vote, :server" />
                </x-filament::input.wrapper>
            </label>
        </div>
    </x-filament::section>

    <x-filament::section>
        @if (count($rows) === \App\Filament\Pages\Translations::LIMIT)
            <p style="font-size:12px;opacity:.7;margin-bottom:8px">Показано перші {{ \App\Filament\Pages\Translations::LIMIT }} рядків — звузьте пошуком.</p>
        @endif
        <div style="display:grid;gap:14px">
            @forelse ($rows as $row)
                <div wire:key="t-{{ $locale }}-{{ $row['field'] }}" style="display:grid;gap:6px;padding-bottom:14px;border-bottom:1px solid rgba(127,127,127,.2)">
                    <code style="font-size:11px;opacity:.6">{{ $row['key'] }}</code>
                    @if ($locale !== $default)
                        <div style="font-size:13px;opacity:.75">{{ $locales[$default] }}: {{ $row['source'] }}</div>
                    @endif
                    <div style="display:flex;gap:8px;align-items:flex-start">
                        <x-filament::input.wrapper style="flex:1">
                            <textarea wire:model="values.{{ $row['field'] }}" rows="2"
                                      placeholder="{{ $row['file'] ?? ($locale === $default ? $row['source'] : 'Немає перекладу — показується резервна мова') }}"
                                      style="width:100%;background:transparent;border:0;padding:8px 10px;font-size:14px;resize:vertical;outline:none"></textarea>
                        </x-filament::input.wrapper>
                        <x-filament::button size="sm" wire:click="saveRow('{{ $row['key'] }}')">Зберегти</x-filament::button>
                    </div>
                </div>
            @empty
                <p style="opacity:.7">Нічого не знайдено.</p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
