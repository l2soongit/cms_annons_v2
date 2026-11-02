@php
    $items = $this->items();
    $open = collect($items)->filter(fn ($i) => $i['count'])->count();
    $tones = [
        'warning' => 'background:#fef3c7;color:#92400e',
        'danger' => 'background:#fee2e2;color:#991b1b',
        'info' => 'background:#dbeafe;color:#1e40af',
        'gray' => 'background:rgba(127,127,127,.12);color:rgba(127,127,127,.9)',
    ];
@endphp
<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-bell-alert" :icon-color="$open ? 'warning' : 'success'">
        <x-slot name="heading">Потребує уваги</x-slot>
        <x-slot name="description">{{ $open ? 'Відкрийте рядок, щоб перейти до списку.' : 'Усе розібрано — черги порожні.' }}</x-slot>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px">
            @foreach ($items as $item)
                <a href="{{ $item['url'] }}" class="rounded-xl ring-1 ring-gray-950/5 hover:ring-primary-500 dark:ring-white/10"
                   style="display:flex;align-items:center;gap:12px;padding:12px 14px;{{ $item['count'] ? '' : 'opacity:.6' }}">
                    <x-filament::icon :icon="$item['icon']" class="h-6 w-6 text-gray-400" style="flex:none" />
                    <span style="flex:1;min-width:0">
                        <span class="text-sm font-semibold" style="display:block">{{ $item['label'] }}</span>
                        <span class="text-xs text-gray-500" style="display:block">{{ $item['hint'] }}</span>
                    </span>
                    <span class="text-sm font-bold" style="flex:none;min-width:30px;padding:2px 10px;border-radius:999px;text-align:center;{{ $tones[$item['tone']] }}">{{ $item['count'] ?: '0' }}</span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
