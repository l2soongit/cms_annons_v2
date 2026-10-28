@php
    use App\Billing\Money;

    $months = $this->months();
    $services = $this->services();
@endphp
<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Виручка за послугами — {{ now()->translatedFormat('F Y') }}</x-slot>
        <x-slot name="description">На балансах користувачів зараз {{ Money::format($this->balances()) }}</x-slot>
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500"><th class="py-2">Послуга</th><th class="py-2 text-right">Замовлень</th><th class="py-2 text-right">Сума</th></tr></thead>
            <tbody>
                @foreach ($services as $row)
                    <tr class="border-t border-gray-200 dark:border-white/10"><td class="py-2">{{ $row['label'] }}</td><td class="py-2 text-right tabular-nums">{{ $row['count'] }}</td><td class="py-2 text-right tabular-nums">{{ Money::format($row['total']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Рух коштів за 12 місяців</x-slot>
        <x-slot name="description">Поповнення — гроші від платіжних систем; списання — оплата послуг з балансу.</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[36rem] text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-2">Місяць</th><th class="py-2 text-right">Поповнення</th><th class="py-2 text-right">Списано за послуги</th><th class="py-2 text-right">Повернення</th><th class="py-2 text-right">Коригування</th></tr></thead>
                <tbody>
                    @foreach ($months as $m)
                        <tr class="border-t border-gray-200 dark:border-white/10">
                            <td class="py-2">{{ \Illuminate\Support\Str::ucfirst($m['label']) }}</td>
                            <td class="py-2 text-right tabular-nums">{{ Money::format($m['deposit']) }}</td>
                            <td class="py-2 text-right tabular-nums">{{ Money::format($m['charge']) }}</td>
                            <td class="py-2 text-right tabular-nums">{{ Money::format($m['refund']) }}</td>
                            <td class="py-2 text-right tabular-nums">{{ Money::format($m['adjustment']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
