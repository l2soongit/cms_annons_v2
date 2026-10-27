<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\ModerationLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Користувачі';

    protected static ?string $modelLabel = 'користувач';

    protected static ?string $pluralModelLabel = 'Користувачі';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label('Нік')->required(),
                TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                Select::make('roles')->label('Ролі')->multiple()->relationship('roles', 'name')->preload()
                    ->visible(fn () => auth()->user()->hasRole('admin')),
                DateTimePicker::make('banned_until')->label('Заблокований до'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Нік')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('roles.name')->label('Ролі')->badge(),
                TextColumn::make('servers_count')->counts('servers')->label('Серверів'),
                TextColumn::make('banned_until')->label('Бан до')->dateTime('d.m.Y H:i'),
                TextColumn::make('created_at')->label('Реєстрація')->since(),
            ])
            ->filters([
                SelectFilter::make('roles')->relationship('roles', 'name')->label('Роль'),
            ])
            ->recordActions([
                Action::make('ban')->label('Заблокувати')->color('danger')->icon(Heroicon::OutlinedNoSymbol)
                    ->visible(fn (User $u) => ! $u->isBanned() && ! $u->is(auth()->user()))
                    ->schema([
                        Select::make('days')->label('Термін')->required()->options([1 => '1 день', 7 => '7 днів', 30 => '30 днів', 36500 => 'Назавжди']),
                        TextInput::make('reason')->label('Причина')->required(),
                    ])
                    ->action(function (User $u, array $data) {
                        $u->update(['banned_until' => now()->addDays((int) $data['days'])]);
                        ModerationLog::record($u, 'ban', $data['reason'], ['days' => (int) $data['days']]);
                    }),
                Action::make('unban')->label('Розблокувати')->visible(fn (User $u) => $u->isBanned())
                    ->action(function (User $u) {
                        $u->update(['banned_until' => null]);
                        ModerationLog::record($u, 'unban');
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
