<?php

namespace App\Filament\Resources;

use App\Billing\Money;
use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Журнал усіх рухів коштів (ТЗ 3.7): поповнення, списання, повернення, коригування. Лише перегляд.
 */
class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Платні послуги';

    protected static ?string $modelLabel = 'транзакція';

    protected static ?string $pluralModelLabel = 'Транзакції';

    protected static ?int $navigationSort = 5;

    private const TYPES = ['deposit' => 'Поповнення', 'charge' => 'Оплата послуги', 'refund' => 'Повернення', 'adjustment' => 'Коригування'];

    private const STATUSES = ['pending' => 'Очікує', 'succeeded' => 'Проведено', 'failed' => 'Не пройшла'];

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('billing.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'order.tariff']))
            ->columns([
                TextColumn::make('created_at')->label('Дата')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('user.email')->label('Користувач')->searchable(),
                TextColumn::make('type')->label('Операція')->formatStateUsing(fn (string $state) => self::TYPES[$state] ?? $state)
                    ->description(fn (Transaction $t) => $t->order?->tariff->name ?? ($t->payload['reason'] ?? null)),
                TextColumn::make('amount')->label('Сума')->formatStateUsing(fn ($state) => Money::format($state))
                    ->color(fn ($state) => $state > 0 ? 'success' : null),
                TextColumn::make('provider')->label('Платіжна система')->placeholder('баланс'),
                TextColumn::make('external_id')->label('ID у провайдера')->placeholder('—')->toggleable(isToggledHiddenByDefault: true)->copyable(),
                TextColumn::make('status')->label('Статус')->badge()->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => ['succeeded' => 'success', 'failed' => 'danger'][$state] ?? 'gray'),
            ])
            ->filters([
                SelectFilter::make('type')->options(self::TYPES)->label('Операція'),
                SelectFilter::make('status')->options(self::STATUSES)->label('Статус'),
                SelectFilter::make('provider')->options(['liqpay' => 'LiqPay', 'stripe' => 'Stripe', 'test' => 'Тестова'])->label('Платіжна система'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
        ];
    }
}
