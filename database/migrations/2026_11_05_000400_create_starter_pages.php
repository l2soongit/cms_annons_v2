<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Стартові сторінки, на які ведуть посилання у футері. Створюються, лише якщо їх ще немає;
     * далі адміністратор редагує тексти в адмінці (Каталог → Сторінки).
     */
    public function up(): void
    {
        $pages = [
            'rules' => [
                ['uk' => 'Правила порталу', 'en' => 'Portal rules'],
                [
                    'uk' => '<h2>Для гравців</h2><ul><li>Голосувати за сервер можна раз на інтервал, указаний на сторінці голосування, з одного IP, акаунта й пристрою.</li><li>Голоси через VPN, проксі й дата-центри не зараховуються.</li><li>Відгук — один на сервер, без образ, реклами й посилань.</li></ul><h2>Для власників серверів</h2><ul><li>Додавайте лише сервери, якими керуєте; підтвердьте власність у кабінеті.</li><li>Накрутка голосів чи онлайну веде до анулювання голосів і блокування сервера.</li><li>Опис має відповідати серверу: рейти, хроніки, версія, режим.</li></ul><h2>Модерація</h2><p>Модератори перевіряють нові сервери й зміни назви чи адреси. Порушення розглядаються за скаргами; рішення приходить у сповіщеннях.</p>',
                    'en' => '<h2>For players</h2><ul><li>You can vote for a server once per the interval shown on the voting page, from one IP, account and device.</li><li>Votes through VPNs, proxies and data centres are not counted.</li><li>One review per server, without insults, ads or links.</li></ul><h2>For server owners</h2><ul><li>Add only servers you manage; verify ownership in your account.</li><li>Inflating votes or online leads to vote annulment and a server block.</li><li>The description must match the server: rates, chronicle, version, mode.</li></ul><h2>Moderation</h2><p>Moderators review new servers and changes of name or address. Violations are reviewed on reports; decisions arrive as notifications.</p>',
                ],
            ],
            'faq' => [
                ['uk' => 'Часті питання', 'en' => 'FAQ'],
                [
                    'uk' => '<h2>Як рахується місце в рейтингу?</h2><p>За голосами поточного місяця, бонусними балами, онлайном і аптаймом. Рейтинг оновлюється кожні 5 хвилин, 1-го числа починається новий місяць.</p><h2>Як отримати бонус за голос?</h2><p>Вкажіть свій нік під час голосування — сервер видасть бонус, якщо підключив vote-callback.</p><h2>Як додати свій сервер?</h2><p>Зареєструйтеся, натисніть «Додати сервер» і заповніть картку. Після перевірки модератором сервер з’явиться в рейтингу.</p><h2>Як просувати сервер?</h2><p>У кабінеті на сторінці сервера: VIP, виділення, підняття, анонс відкриття й банери оплачуються з балансу.</p>',
                    'en' => '<h2>How is the ranking calculated?</h2><p>By votes of the current month, bonus points, online and uptime. The ranking updates every 5 minutes; a new month starts on the 1st.</p><h2>How do I get a vote reward?</h2><p>Enter your nickname when voting — the server gives the reward if it has connected the vote-callback.</p><h2>How do I add my server?</h2><p>Sign up, press “Add server” and fill in the card. After moderation the server appears in the ranking.</p><h2>How do I promote a server?</h2><p>In your account on the server page: VIP, highlight, boost, opening announcement and banners are paid from your balance.</p>',
                ],
            ],
            'contacts' => [
                ['uk' => 'Контакти', 'en' => 'Contacts'],
                [
                    'uk' => '<p>Питання про оплату, модерацію чи технічні проблеми — створіть звернення в розділі «Підтримка» в кабінеті: відповідь прийде там і в сповіщеннях.</p><p>Для власників серверів — документація API й віджетів на сторінці «Для розробників».</p>',
                    'en' => '<p>Questions about payment, moderation or technical issues — open a request in the “Support” section of your account: the reply arrives there and in notifications.</p><p>Server owners — see the API and widget documentation on the “For developers” page.</p>',
                ],
            ],
        ];

        foreach ($pages as $slug => [$title, $body]) {
            if (DB::table('pages')->where('slug', $slug)->exists()) {
                continue;
            }
            DB::table('pages')->insert([
                'slug' => $slug,
                'title' => json_encode($title, JSON_UNESCAPED_UNICODE),
                'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
