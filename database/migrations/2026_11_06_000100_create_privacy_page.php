<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Стартова політика конфіденційності (ТЗ 4, GDPR) — описує, що сайт справді зберігає.
     * Створюється, лише якщо сторінки ще немає; остаточний текст варто узгодити з юристом.
     */
    public function up(): void
    {
        if (DB::table('pages')->where('slug', 'privacy')->exists()) {
            return;
        }

        $uk = '<p>Тут описано, які дані зберігає graj.club, навіщо і як їх видалити.</p>'
            .'<h2>Акаунт</h2><ul><li>Email, нік, хеш пароля, мова інтерфейсу й налаштування сповіщень.</li><li>Якщо ви підключили Telegram чи Discord — ідентифікатор чату або адреса вебхука (вебхук зберігається зашифрованим).</li><li>Вхід через Google чи Discord передає нам лише ваш email та ім’я.</li></ul>'
            .'<h2>Голосування</h2><ul><li>Щоб один голос рахувався раз на інтервал, ми зберігаємо хеш IP-адреси й підмережі, ідентифікатор пристрою і нік, який ви вказали.</li><li>Хеші IP видаляються через 90 днів.</li><li>Для захисту від накрутки IP може перевірятися сервісом виявлення VPN; форма захищена капчею Cloudflare Turnstile.</li><li>Нік і час голосу передаються серверу, за який ви голосуєте, щоб видати бонус.</li></ul>'
            .'<h2>Оплата</h2><p>Карткові дані вводяться на сторінці LiqPay або Stripe — ми їх не бачимо й не зберігаємо. У нас лишаються сума, номер транзакції та історія послуг.</p>'
            .'<h2>Повідомлення, відгуки, тікети</h2><p>Зберігаються, доки існує акаунт. Вкладення доступні лише учасникам діалогу й модераторам за скаргою.</p>'
            .'<h2>Cookies</h2><p>Лише необхідні: сесія входу, захист форм (XSRF), «запам’ятати мене». Тема оформлення й закрите повідомлення про cookies зберігаються у вашому браузері. Реклами й сторонньої аналітики немає.</p>'
            .'<h2>Ваші права</h2><p>Видалити акаунт можна в кабінеті: «Профіль» → «Видалення акаунта». Ваші сервери, відгуки й повідомлення видаляються, голоси лишаються в статистиці без прив’язки до вас. Інші запити щодо даних — через «Підтримку» в кабінеті.</p>'
            .'<h2>Хто ще обробляє дані</h2><p>Хостинг сайту, Cloudflare (захист і капча), платіжні системи LiqPay і Stripe, сервіс перевірки VPN, Sentry (звіти про помилки), Telegram і Discord — лише якщо ви підключили сповіщення.</p>';

        $en = '<p>This page describes what data graj.club stores, why, and how to delete it.</p>'
            .'<h2>Account</h2><ul><li>Email, nickname, password hash, interface language and notification settings.</li><li>If you connected Telegram or Discord — the chat ID or webhook URL (the webhook is stored encrypted).</li><li>Signing in with Google or Discord gives us only your email and name.</li></ul>'
            .'<h2>Voting</h2><ul><li>To count one vote per interval we store a hash of your IP address and subnet, a device identifier and the nickname you entered.</li><li>IP hashes are deleted after 90 days.</li><li>To fight cheating, your IP may be checked by a VPN detection service; the form is protected by Cloudflare Turnstile.</li><li>Your nickname and vote time are sent to the server you voted for so it can give you a reward.</li></ul>'
            .'<h2>Payments</h2><p>Card details are entered on the LiqPay or Stripe page — we never see or store them. We keep the amount, the transaction number and your service history.</p>'
            .'<h2>Messages, reviews, tickets</h2><p>Kept while your account exists. Attachments are visible only to conversation participants and to moderators when reported.</p>'
            .'<h2>Cookies</h2><p>Essential only: sign-in session, form protection (XSRF), “remember me”. Your theme and the dismissed cookie notice are stored in your browser. No ads or third-party analytics.</p>'
            .'<h2>Your rights</h2><p>You can delete your account in your account settings: “Profile” → “Delete account”. Your servers, reviews and messages are deleted; votes stay in statistics without a link to you. Other data requests — via “Support” in your account.</p>'
            .'<h2>Who else processes data</h2><p>The site hosting, Cloudflare (protection and captcha), the LiqPay and Stripe payment systems, a VPN detection service, Sentry (error reports), Telegram and Discord — only if you connected notifications.</p>';

        DB::table('pages')->insert([
            'slug' => 'privacy',
            'title' => json_encode(['uk' => 'Політика конфіденційності', 'en' => 'Privacy policy'], JSON_UNESCAPED_UNICODE),
            'body' => json_encode(['uk' => $uk, 'en' => $en], JSON_UNESCAPED_UNICODE),
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        //
    }
};
