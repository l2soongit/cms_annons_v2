@php
    $reader = $this->reader();
    $files = $reader->files();
    $data = $file !== '' ? $reader->read($file, $level ?: null, trim($search), $limit) : ['entries' => [], 'counts' => []];
    $labels = ['emergency' => 'Аварія', 'alert' => 'Тривога', 'critical' => 'Критична', 'error' => 'Помилка', 'warning' => 'Попередження', 'notice' => 'Примітка', 'info' => 'Інфо', 'debug' => 'Налагодження'];
    $colors = ['emergency' => 'danger', 'alert' => 'danger', 'critical' => 'danger', 'error' => 'danger', 'warning' => 'warning', 'notice' => 'info', 'info' => 'info', 'debug' => 'gray'];
    $size = fn (int $b) => $b >= 1048576 ? number_format($b / 1048576, 1, ',', '').' МБ' : ($b >= 1024 ? round($b / 1024).' КБ' : $b.' Б');
    $total = array_sum($data['counts']);
@endphp
<x-filament-panels::page>
    @if (! $files)
        <x-filament::section>
            <x-slot name="heading">Логів поки немає</x-slot>
            Файли з’являться в <code>storage/logs</code>, щойно сайт запише першу помилку чи попередження.
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="flex flex-wrap items-end gap-4">
                <label class="grid gap-1 text-sm">
                    <span class="font-medium">Файл</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="file">
                            @foreach ($files as $f)
                                <option value="{{ $f['name'] }}">{{ $f['name'] }} · {{ $size($f['size']) }} · {{ date('d.m.Y H:i', $f['modified']) }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label class="grid flex-1 gap-1 text-sm" style="min-width: 220px">
                    <span class="font-medium">Пошук у повідомленнях</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="search" wire:model.live.debounce.400ms="search" placeholder="Наприклад: SQLSTATE, vote, 404" />
                    </x-filament::input.wrapper>
                </label>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::badge :color="$level === '' ? 'primary' : 'gray'" tag="button" wire:click="$set('level', '')">Усі · {{ $total }}</x-filament::badge>
                @foreach (\App\Support\LogReader::LEVELS as $lvl)
                    @if ($data['counts'][$lvl] ?? 0)
                        <x-filament::badge :color="$level === $lvl ? $colors[$lvl] : 'gray'" tag="button" wire:click="$set('level', '{{ $lvl }}')">{{ $labels[$lvl] }} · {{ $data['counts'][$lvl] }}</x-filament::badge>
                    @endif
                @endforeach
            </div>
            @if ($reader->truncated($file))
                <p class="mt-3 text-xs text-gray-500">Файл великий — показано записи з останніх {{ $size(\App\Support\LogReader::TAIL_BYTES) }}. Повністю його можна завантажити кнопкою вгорі.</p>
            @endif
        </x-filament::section>

        <div class="grid gap-2" wire:loading.class="opacity-60">
            @forelse ($data['entries'] as $entry)
                <details class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" wire:key="log-{{ $loop->index }}-{{ md5($entry['time'].$entry['message']) }}">
                    <summary class="flex cursor-pointer list-none items-start gap-3">
                        <x-filament::badge :color="$colors[$entry['level']] ?? 'gray'" class="shrink-0">{{ $labels[$entry['level']] ?? $entry['level'] }}</x-filament::badge>
                        <span class="shrink-0 font-mono text-xs text-gray-500" style="padding-top: 3px">{{ $entry['time'] }}</span>
                        <span class="min-w-0 text-sm" style="overflow-wrap: anywhere">{{ $entry['message'] }}</span>
                    </summary>
                    @if ($entry['details'] !== '')
                        <pre class="mt-3 max-h-96 overflow-auto rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-950" style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $entry['details'] }}</pre>
                    @endif
                </details>
            @empty
                <x-filament::section>
                    {{ $search !== '' || $level !== '' ? 'Нічого не знайдено — змініть пошук або рівень.' : 'У цьому файлі немає записів.' }}
                </x-filament::section>
            @endforelse

            @if (count($data['entries']) >= $limit && $limit < 1000)
                <div class="flex justify-center"><x-filament::button color="gray" wire:click="more">Показати ще</x-filament::button></div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
