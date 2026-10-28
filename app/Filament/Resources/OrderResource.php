<?php

namespace App\Filament\Resources;

use App\Billing\Billing;
use App\Billing\Money;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Tariff;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Куплені послуги: перегляд, скасування (з поверненням коштів на баланс або без).
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Платні послуги';

    protected static ?string $modelLabel = 'замовлення';

    protected static ?string $pluralModelLabel = 'Замовлення';

    protected static ?int $navigationSort = 3;

    private const STATUSES = ['active' => 'Діє', 'expired' => 'Завершено', 'cancelled' => 'Скасовано', 'refunded' => 'Повернено'];

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['tariff', 'server', 'user']))
            ->columns([
                TextColumn::make('id')->label('№'),
                TextColumn::make('tariff.name')->label('Послуга'),
                TextColumn::make('server.name')->label('Сервер')->searchable(),
                TextColumn::make('user.email')->label('Покупець')->searchable(),
                TextColumn::make('amount')->label('Сума')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('ends_at')->label('Діє до')->dateTime('d.m.Y H:i'),
                IconColumn::make('auto_renew')->label('Авто')->boolean(),
                TextColumn::make('status')->label('Статус')->badge()->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => ['active' => 'success', 'refunded' => 'warning', 'cancelled' => 'danger'][$state] ?? 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES)->label('Статус'),
                SelectFilter::make('service')->label('Послуга')->options(Tariff::SERVICES)
                    ->query(fn (Builder $query, array $data) => $data['value'] ? $query->whereHas('tariff', fn ($t) => $t->where('service', $data['value'])) : $query),
            ])
            ->recordActions([
                Action::make('receipt')->label('Квитанція')->icon(Heroicon::OutlinedDocumentText)
                    ->url(fn (Order $o) => route('cabinet.orders.receipt', $o), shouldOpenInNewTab: true),
                Action::make('cancel')->label('Скасувати')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->visible(fn (Order $o) => $o->status === 'active')
                    ->schema([Toggle::make('refund')->label('Повернути кошти на баланс покупця')->default(true)])
                    ->action(function (Order $o, array $data) {
                        app(Billing::class)->cancel($o, (bool) $data['refund']);
                        Notification::make()->title($data['refund'] ? 'Скасовано, кошти повернено' : 'Скасовано')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
        ];
    }
}
