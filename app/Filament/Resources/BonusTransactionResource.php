<?php

namespace App\Filament\Resources;

use App\Community\Bonus;
use App\Filament\Resources\BonusTransactionResource\Pages;
use App\Models\BonusTransaction;
use App\Models\ModerationLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Історія бонусних балів і ручне нарахування чи списання */
class BonusTransactionResource extends Resource
{
    protected static ?string $model = BonusTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Спільнота';

    protected static ?string $modelLabel = 'рух балів';

    protected static ?string $pluralModelLabel = 'Бонусні бали';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('community.bonus') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'author']))
            ->columns([
                TextColumn::make('user.name')->label('Користувач')->searchable()
                    ->description(fn (BonusTransaction $t) => 'Баланс: '.$t->user?->bonus_balance.' б.'),
                TextColumn::make('amount')->label('Бали')->sortable()
                    ->formatStateUsing(fn (int $state) => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state) => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('reason')->label('Причина')->formatStateUsing(fn (string $state) => Bonus::REASONS[$state] ?? $state)
                    ->description(fn (BonusTransaction $t) => $t->note),
                TextColumn::make('author.name')->label('Хто нарахував')->placeholder('Автоматично'),
                TextColumn::make('created_at')->label('Коли')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('reason')->label('Причина')->options(Bonus::REASONS),
            ])
            ->headerActions([
                Action::make('adjust')->label('Нарахувати чи списати')->icon(Heroicon::OutlinedPlusCircle)
                    ->schema([
                        Select::make('user_id')->label('Користувач')->required()->searchable()
                            ->getSearchResultsUsing(fn (string $search) => User::where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
                                ->limit(20)->get()->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->email}) — {$u->bonus_balance} б."]))
                            ->getOptionLabelUsing(fn ($value) => User::find($value)?->name),
                        TextInput::make('amount')->label('Бали')->numeric()->required()->notIn([0])
                            ->helperText('Додатне число — нарахувати, від’ємне — списати.'),
                        TextInput::make('note')->label('Коментар (побачить користувач)')->required()->maxLength(200),
                    ])
                    ->action(function (array $data) {
                        $user = User::findOrFail($data['user_id']);
                        $tx = Bonus::adjust($user, (int) $data['amount'], (string) $data['note'], auth()->user());
                        ModerationLog::record($tx, 'bonus_adjust', $data['note'], ['user_id' => $user->id, 'amount' => (int) $data['amount']]);
                        Notification::make()->title('Готово: баланс '.$user->fresh()?->bonus_balance.' б.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBonusTransactions::route('/')];
    }
}
