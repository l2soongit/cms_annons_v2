<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Messaging\MessagingException;
use App\Messaging\Messenger;
use App\Models\Ticket;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Тікети підтримки (ТЗ 3.8): відповідь з історією листування, «взяти собі», закриття.
 */
class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'Модерація';

    protected static ?string $modelLabel = 'тікет';

    protected static ?string $pluralModelLabel = 'Тікети підтримки';

    protected static ?int $navigationSort = 6;

    private const CATEGORIES = ['payment' => 'Оплата', 'moderation' => 'Модерація', 'technical' => 'Технічне', 'other' => 'Інше'];

    private const STATUSES = ['open' => 'Чекає відповіді', 'answered' => 'Відповіли', 'closed' => 'Закрито'];

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('tickets.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Ticket::where('status', 'open')->count() ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'assignee', 'conversation.messages.user']))
            ->columns([
                TextColumn::make('id')->label('№'),
                TextColumn::make('subject')->label('Тема')->searchable()->wrap()
                    ->description(fn (Ticket $record) => self::CATEGORIES[$record->category] ?? $record->category),
                TextColumn::make('user.email')->label('Від кого')->searchable(),
                TextColumn::make('status')->label('Статус')->badge()->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => ['open' => 'warning', 'answered' => 'success'][$state] ?? 'gray'),
                TextColumn::make('assignee.name')->label('Відповідальний')->placeholder('—'),
                TextColumn::make('updated_at')->label('Оновлено')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES)->label('Статус'),
                SelectFilter::make('category')->options(self::CATEGORIES)->label('Категорія'),
            ])
            ->recordActions([
                Action::make('reply')->label('Відповісти')->icon(Heroicon::OutlinedChatBubbleLeftRight)->color('primary')
                    ->modalHeading(fn (Ticket $record) => "№{$record->id} · {$record->subject}")
                    ->modalContent(fn (Ticket $record) => view('filament.tickets.thread', ['ticket' => $record]))
                    ->schema([Textarea::make('body')->label('Відповідь')->required()->rows(5)->maxLength(2000)])
                    ->action(function (Ticket $record, array $data) {
                        try {
                            app(Messenger::class)->send($record->conversation, auth()->user(), $data['body'], asSupport: true);
                            $record->assignee_id ??= auth()->id();
                            $record->save();
                            Notification::make()->title('Відповідь надіслано')->success()->send();
                        } catch (MessagingException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('take')->label('Взяти собі')->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (Ticket $record) => $record->assignee_id !== auth()->id() && $record->status !== 'closed')
                    ->action(fn (Ticket $record) => $record->update(['assignee_id' => auth()->id()])),
                Action::make('close')->label('Закрити')->icon(Heroicon::OutlinedCheckCircle)->color('gray')
                    ->visible(fn (Ticket $record) => $record->status !== 'closed')
                    ->requiresConfirmation()
                    ->action(fn (Ticket $record) => app(Messenger::class)->closeTicket($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
        ];
    }
}
