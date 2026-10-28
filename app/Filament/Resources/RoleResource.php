<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * Ролі як набори дозволів (RBAC): нову роль адмін створює тут, без зміни коду.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Користувачі';

    protected static ?string $modelLabel = 'роль';

    protected static ?string $pluralModelLabel = 'Ролі';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('roles.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('name')->label('Назва (латиницею)')->required()->maxLength(64)->alphaDash()
                    ->unique(ignoreRecord: true)
                    // Базові ролі використовує код (реєстрація, додавання сервера) — назву не змінюємо
                    ->disabled(fn (?Role $record) => in_array($record?->name, Permissions::CORE_ROLES, true))
                    ->helperText('Напр. «support» або «senior-moderator».'),
                CheckboxList::make('permissions')->label('Дозволи')->relationship('permissions', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Permission $record) => Permissions::LIST[$record->name] ?? $record->name)
                    ->columns(2)->bulkToggleable()
                    ->disabled(fn (?Role $record) => $record?->name === 'admin')
                    ->helperText(fn (?Role $record) => $record?->name === 'admin'
                        ? 'Адміністратор завжди має всі дозволи.'
                        : 'Без «Вхід в адмін-панель» решта дозволів не відкриє адмінку.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('name')->label('Роль')->weight('bold'),
                TextColumn::make('permissions_count')->counts('permissions')->label('Дозволів')
                    ->formatStateUsing(fn ($state, Role $record) => $record->name === 'admin' ? 'усі' : $state),
                TextColumn::make('users_count')->counts('users')->label('Користувачів'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (Role $record) => ! in_array($record->name, Permissions::CORE_ROLES, true)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
