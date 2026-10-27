<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\ModerationLog;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Модерація';

    protected static ?string $modelLabel = 'відгук';

    protected static ?string $pluralModelLabel = 'Відгуки';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return (string) (Review::where('status', 'pending')->count()) ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('server.name')->label('Сервер')->searchable(),
                TextColumn::make('user.name')->label('Автор')->searchable(),
                TextColumn::make('rating')->label('Оцінка'),
                TextColumn::make('body')->label('Текст')->limit(80)->wrap(),
                TextColumn::make('status')->label('Статус')->badge()
                    ->color(fn (string $state) => ['published' => 'success', 'pending' => 'warning', 'hidden' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('created_at')->label('Дата')->since(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'На перевірці', 'published' => 'Опубліковано', 'hidden' => 'Приховано'])->default('pending'),
            ])
            ->recordActions([
                Action::make('publish')->label('Опублікувати')->color('success')->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (Review $r) => $r->status !== 'published')
                    ->action(fn (Review $r) => self::setStatus($r, 'published')),
                Action::make('hide')->label('Приховати')->color('danger')->icon(Heroicon::OutlinedEyeSlash)
                    ->visible(fn (Review $r) => $r->status !== 'hidden')
                    ->action(fn (Review $r) => self::setStatus($r, 'hidden')),
            ])
            ->toolbarActions([
                BulkAction::make('publishMany')->label('Опублікувати')->action(fn (Collection $records) => self::setStatusMany($records, 'published')),
                BulkAction::make('hideMany')->label('Приховати')->color('danger')->action(fn (Collection $records) => self::setStatusMany($records, 'hidden')),
            ]);
    }

    private static function setStatusMany(Collection $records, string $status): void
    {
        $records->whereInstanceOf(Review::class)->each(fn (Review $review) => self::setStatus($review, $status));
    }

    private static function setStatus(Review $review, string $status): void
    {
        $review->update(['status' => $status]);
        ModerationLog::record($review, "review_{$status}");
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListReviews::route('/')];
    }
}
