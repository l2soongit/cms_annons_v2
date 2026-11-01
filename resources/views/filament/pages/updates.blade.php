@php
    $u = $this->updater();
    $state = $u->state();
    $latest = $state['latest'] ?? null;
    $cron = \App\Update\Updater::cronAlive();
    $busy = $u->pending() || ! empty($state['queued']);
    $reasons = ['manual' => 'вручну', 'auto' => 'автоматично', 'upload' => 'з архіву', 'rollback' => 'відкат'];
@endphp
<x-filament-panels::page>
    <div @if ($busy) wire:poll.5s @endif class="space-y-6">
        @if ($u->isGitCheckout())
            <x-filament::section>
                <x-slot name="heading">Робоча копія git</x-slot>
                Тут код оновлюється через <code>git pull</code>. Оновлення з архівів працюють лише на сайті, встановленому з архіву.
            </x-filament::section>
        @endif

        @if ($busy)
            <x-filament::section icon="heroicon-o-arrow-path" icon-color="warning">
                <x-slot name="heading">Оновлення виконується</x-slot>
                @if ($u->pending())
                    Файли замінено на {{ $u->pending()['to'] }}, залишилися міграції й кеш.
                @else
                    У черзі — cron почне протягом хвилини.
                @endif
            </x-filament::section>
        @endif

        @if (! empty($state['error']))
            <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
                <x-slot name="heading">Остання спроба не вдалася{{ isset($state['error_at']) ? ' · '.date('d.m.Y H:i', $state['error_at']) : '' }}</x-slot>
                {{ $state['error'] }}
            </x-filament::section>
        @endif

        @if ($log)
            <x-filament::section>
                <x-slot name="heading">Завершення оновлення</x-slot>
                <ul class="text-sm">@foreach ($log as $line)<li>{{ $line }}</li>@endforeach</ul>
            </x-filament::section>
        @endif

        <div class="grid gap-6 md:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Версія</x-slot>
                <dl class="grid grid-cols-[auto,1fr] gap-x-6 gap-y-2 text-sm">
                    <dt class="text-gray-500">Встановлена</dt><dd class="font-mono">{{ $u->currentVersion() }}</dd>
                    <dt class="text-gray-500">Остання на GitHub</dt>
                    <dd class="font-mono">{{ $latest['tag'] ?? '—' }} @if ($latest && $u->hasUpdate()) <x-filament::badge color="success" class="ml-1 inline-flex">нова</x-filament::badge> @endif</dd>
                    <dt class="text-gray-500">Перевірено</dt><dd>{{ isset($state['last_check']) ? date('d.m.Y H:i', $state['last_check']) : 'ще ні' }}</dd>
                    <dt class="text-gray-500">Cron</dt>
                    <dd>@if ($cron) працює @else <span class="text-danger-600">не запускався останні 3 хв</span> — оновлення виконуватиметься в браузері @endif</dd>
                </dl>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Автооновлення</x-slot>
                <p class="text-sm text-gray-500">Раз на {{ config('graj.update.check_minutes') }} хв cron перевіряє GitHub і сам ставить новий реліз: резервна копія коду, заміна файлів, міграції. Потрібен робочий cron.</p>
                <div class="mt-4 flex items-center gap-3">
                    <x-filament::button wire:click="toggleAuto" :color="$u->autoEnabled() ? 'danger' : 'primary'" :disabled="$u->isGitCheckout()">
                        {{ $u->autoEnabled() ? 'Вимкнути' : 'Увімкнути' }}
                    </x-filament::button>
                    <span class="text-sm">{{ $u->autoEnabled() ? 'Увімкнено' : 'Вимкнено' }}@if ($u->autoEnabled() && ! $cron) <span class="text-danger-600">— cron не працює, автооновлення не спрацює</span>@endif</span>
                </div>
            </x-filament::section>
        </div>

        @if ($latest && $u->hasUpdate() && trim($latest['notes']) !== '')
            <x-filament::section>
                <x-slot name="heading">Що нового в {{ $latest['tag'] }}</x-slot>
                <div class="whitespace-pre-line text-sm">{{ $latest['notes'] }}</div>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">Історія</x-slot>
            @if ($u->history())
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-gray-500"><th class="py-2">Коли</th><th class="py-2">Версія</th><th class="py-2">Як</th><th class="py-2">Результат</th></tr></thead>
                    <tbody>
                        @foreach ($u->history() as $h)
                            <tr class="border-t border-gray-200 dark:border-white/10">
                                <td class="py-2">{{ date('d.m.Y H:i', $h['at']) }}</td>
                                <td class="py-2 font-mono">{{ $h['from'] }} → {{ $h['to'] }}</td>
                                <td class="py-2">{{ $reasons[$h['reason']] ?? $h['reason'] }}</td>
                                <td class="py-2">@if ($h['ok']) <span class="text-success-600">гаразд</span> @else <span class="text-danger-600">{{ $h['message'] }}</span> @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-sm text-gray-500">Оновлень ще не було.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
