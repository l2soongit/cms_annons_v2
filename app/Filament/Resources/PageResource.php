<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Filament\Support\Localized;
use App\Models\Page;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Статичні сторінки (ТЗ 3.9): правила, FAQ, контакти — для кожної мови сайту.
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Каталог';

    protected static ?string $modelLabel = 'сторінка';

    protected static ?string $pluralModelLabel = 'Сторінки';

    protected static ?int $navigationSort = 8;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('slug')->label('Адреса')->required()->alphaDash()->maxLength(64)->unique(ignoreRecord: true)
                    ->prefix(url('/page').'/')->helperText('Правила — rules, FAQ — faq, контакти — contacts: на них ведуть посилання у футері.'),
                Toggle::make('is_published')->label('Опубліковано')->default(true),
                ...Localized::fields('title', 'Заголовок', fn (string $path) => TextInput::make($path)->maxLength(120), required: true),
            ]),
            Section::make('Текст')->schema(
                Localized::fields('body', 'Текст', fn (string $path) => RichEditor::make($path)
                    ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo']), required: true),
            ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Заголовок'),
                TextColumn::make('slug')->label('Адреса')->url(fn (Page $record) => route('pages.show', $record), shouldOpenInNewTab: true),
                IconColumn::make('is_published')->label('Опубліковано')->boolean(),
                TextColumn::make('updated_at')->label('Оновлено')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'create' => Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
