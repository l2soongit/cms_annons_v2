<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Livewire\Messages\Thread;
use App\Messaging\MessagingException;
use App\Messaging\Messenger;
use App\Models\Conversation;
use App\Models\Game;
use App\Models\Server;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewMessage;
use Database\Seeders\GameSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Повідомлення й тікети (ТЗ 3.8): діалог гравця з власником, прочитано, вкладення, чорний список, скарги,
 * ліміт нових діалогів, тікети з відповіддю підтримки.
 */
class MessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed([RoleSeeder::class, GameSeeder::class]);
    }

    private function server(?User $owner = null): Server
    {
        $name = 'Msg Server '.fake()->unique()->numberBetween(1, 99999);

        return Server::create([
            'game_id' => Game::where('slug', 'lineage2')->value('id'),
            'user_id' => ($owner ?? User::factory()->create())->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'host' => str($name)->slug().'.example.net',
            'status' => ServerStatus::Published,
        ]);
    }

    public function test_player_writes_owner_from_server_page_and_owner_replies(): void
    {
        Notification::fake();
        $server = $this->server();
        $player = User::factory()->create(['name' => 'Gamer']);

        $this->actingAs($player)->get(route('servers.show', $server))->assertOk()->assertSee('Написати власнику');
        $response = $this->actingAs($player)->post(route('servers.message', $server), ['body' => 'Коли вайп?']);

        $conversation = Conversation::first();
        $response->assertRedirect(route('cabinet.messages.show', $conversation));
        Notification::assertSentTo($server->owner, NewMessage::class);

        // Власник бачить непрочитаний діалог і відповідає; гравець бачить «Прочитано» під своїм повідомленням
        $this->actingAs($server->owner)->get(route('cabinet.messages'))->assertOk()->assertSee('Gamer')->assertSee('<span class="c-badge">1</span>', false);
        Livewire::actingAs($server->owner)->test(Thread::class, ['conversation' => $conversation])
            ->assertSee('Коли вайп?')
            ->set('body', 'У п’ятницю')
            ->call('send')
            ->assertHasNoErrors();

        $this->assertSame(0, app(Messenger::class)->unreadCount($server->owner));
        $this->assertSame(1, app(Messenger::class)->unreadCount($player));
        Livewire::actingAs($player)->test(Thread::class, ['conversation' => $conversation])->assertSee('У п’ятницю')->assertSee('Прочитано');

        // Повторне «Написати» продовжує той самий діалог
        app(Messenger::class)->start($player, $server, 'Ще питання');
        $this->assertSame(1, Conversation::count());
    }

    public function test_second_unread_message_does_not_send_another_notification(): void
    {
        Notification::fake();
        $server = $this->server();
        $player = User::factory()->create();

        $conversation = app(Messenger::class)->start($player, $server, 'Перше');
        app(Messenger::class)->send($conversation, $player, 'Друге');

        Notification::assertSentToTimes($server->owner, NewMessage::class, 1);
    }

    public function test_attachment_is_private_to_participants(): void
    {
        $server = $this->server();
        $player = User::factory()->create();
        $conversation = app(Messenger::class)->start($player, $server, 'Скрін', UploadedFile::fake()->image('shot.png', 400, 300));
        $message = $conversation->messages()->first();

        Storage::disk('local')->assertExists($message->attachment);
        $this->actingAs($server->owner)->get(route('cabinet.messages.attachment', $message))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('cabinet.messages.attachment', $message))->assertNotFound();
    }

    public function test_blacklist_stops_messages_and_reports_reach_moderators(): void
    {
        $server = $this->server();
        $spammer = User::factory()->create();
        $conversation = app(Messenger::class)->start($spammer, $server, 'Купи голоси дешево');

        Livewire::actingAs($server->owner)->test(Thread::class, ['conversation' => $conversation])
            ->call('report', $conversation->messages()->first()->id)
            ->call('toggleBlock')
            ->assertSee('Ви заблокували');

        $this->assertDatabaseHas('reports', ['reportable_type' => 'App\\Models\\Message', 'user_id' => $server->owner->id]);
        $this->expectException(MessagingException::class);
        app(Messenger::class)->send($conversation->fresh(), $spammer, 'Ще раз');
    }

    public function test_daily_limit_on_new_conversations(): void
    {
        config(['graj.messages.daily_new_conversations' => 2]);
        $player = User::factory()->create();

        app(Messenger::class)->start($player, $this->server(), 'Привіт 1');
        app(Messenger::class)->start($player, $this->server(), 'Привіт 2');

        $this->actingAs($player)->post(route('servers.message', $this->server()), ['body' => 'Привіт 3'])
            ->assertSessionHasErrors('body', null, 'message');
        $this->assertSame(2, Conversation::count());
    }

    public function test_outsider_cannot_open_foreign_conversation(): void
    {
        $conversation = app(Messenger::class)->start(User::factory()->create(), $this->server(), 'Приватне');

        $this->actingAs(User::factory()->create())->get(route('cabinet.messages.show', $conversation))->assertNotFound();
    }

    public function test_ticket_flow_with_support_reply(): void
    {
        Notification::fake();
        $player = User::factory()->create();
        $moderator = User::factory()->create()->assignRole('moderator');

        $this->actingAs($player)->post(route('cabinet.support.store'), [
            'category' => 'payment', 'subject' => 'Не зарахувалась оплата', 'body' => 'Оплатив 500 ₴, баланс 0',
        ])->assertRedirect();
        $ticket = Ticket::first();
        $this->assertSame('open', $ticket->status);

        $this->actingAs($moderator)->get('/admin/tickets')->assertOk()->assertSee('Не зарахувалась оплата');
        app(Messenger::class)->send($ticket->conversation, $moderator, 'Перевірили, зараховано');

        $this->assertSame('answered', $ticket->fresh()->status);
        Notification::assertSentTo($player, NewMessage::class, fn ($n) => $n->fromSupport);
        $this->actingAs($player)->get(route('cabinet.support.show', $ticket))->assertOk()->assertSee('Підтримка graj.club');

        app(Messenger::class)->closeTicket($ticket);
        app(Messenger::class)->send($ticket->conversation, $player, 'Дякую, ще питання');
        $this->assertSame('open', $ticket->fresh()->status);

        // Чужий тікет не відкривається; звичайний гравець не може відповідати в чужому тікеті
        $this->actingAs(User::factory()->create())->get(route('cabinet.support.show', $ticket))->assertNotFound();
        $this->expectException(MessagingException::class);
        app(Messenger::class)->send($ticket->conversation, User::factory()->create(), 'Влізу');
    }

    public function test_staff_author_sees_support_reply_on_the_left_with_support_name(): void
    {
        // Адмін сам створив тікет у кабінеті й відповів з адмінки — відповідь має бути від підтримки
        $admin = User::factory()->create(['name' => 'Orest'])->assignRole('admin');
        $ticket = app(Messenger::class)->openTicket($admin, 'technical', 'Перевірка тікетів', 'Питання від мене');
        $reply = app(Messenger::class)->send($ticket->conversation, $admin, 'Відповідь підтримки', asSupport: true);

        $this->assertTrue($reply->is_support);
        $this->assertSame('answered', $ticket->fresh()->status);

        $html = Livewire::actingAs($admin)->test(Thread::class, ['conversation' => $ticket->conversation])->html();
        // Своє повідомлення — праворуч з підписом «Ви», відповідь — ліворуч від «Підтримка graj.club»
        $this->assertMatchesRegularExpression('/flex-row-reverse.*?Ви.*?Питання від мене/s', $html);
        $this->assertMatchesRegularExpression('/<li[^>]*class="flex items-end gap-3"[^>]*>.*?Підтримка graj\.club.*?Відповідь підтримки/s', $html);

        // Звичайний гравець не може відповісти «від підтримки»
        $player = User::factory()->create();
        $own = app(Messenger::class)->openTicket($player, 'other', 'Моє звернення', 'Текст');
        $this->expectException(MessagingException::class);
        app(Messenger::class)->send($own->conversation, $player, 'Я підтримка', asSupport: true);
    }

    public function test_message_report_is_listed_for_moderators(): void
    {
        $server = $this->server();
        $conversation = app(Messenger::class)->start(User::factory()->create(), $server, 'Образливий текст');
        $conversation->messages()->first()->reports()->create(['user_id' => $server->owner->id, 'reason' => 'abuse']);

        $this->actingAs(User::factory()->create()->assignRole('moderator'))->get('/admin/reports')->assertOk()->assertSee('Образливий текст');
    }
}
