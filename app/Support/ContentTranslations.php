<?php

namespace App\Support;

use App\Models\Game;
use App\Models\GameField;
use App\Models\Page;
use App\Models\Tariff;

/**
 * Переклади стартового вмісту (ігри, сторінки, тарифи, поля й варіанти) російською й польською, а для DayZ і Rust —
 * ще й підзаголовок та опис усіма мовами. Заповнює лише порожні значення: тексти, змінені в адмінці, не чіпає.
 * Викликається міграцією (наявні бази) і DatabaseSeeder (нові установки).
 */
final class ContentTranslations
{
    /** гра => атрибут => мова => текст */
    private const GAMES = [
        'lineage2' => [
            'hero_title' => ['ru' => 'Lineage II', 'pl' => 'Lineage II'],
            'eyebrow' => ['ru' => 'Фэнтези MMORPG · с 2003 года', 'pl' => 'Fantasy MMORPG · od 2003 roku'],
            'description' => [
                'ru' => 'Осады замков, кланы и олимпиады. Выбери хроники и рейты — мы покажем, где сейчас больше всего игроков.',
                'pl' => 'Oblężenia zamków, klany i olimpiady. Wybierz kronikę i rate — pokażemy, gdzie jest teraz najwięcej graczy.',
            ],
        ],
        'gta' => [
            'eyebrow' => ['ru' => 'Ролевые и мультиплеерные серверы', 'pl' => 'Serwery RP i multiplayer'],
            'description' => [
                'ru' => 'Свой город, своя история: от серьёзного RP с фракциями до быстрого DM. Выбери платформу и режим — покажем, где жизнь кипит.',
                'pl' => 'Twoje miasto, twoja historia: od poważnego RP z frakcjami po szybki DM. Wybierz platformę i tryb — pokażemy, gdzie toczy się akcja.',
            ],
        ],
        'minecraft' => [
            'eyebrow' => ['ru' => 'Песочница · Java и Bedrock', 'pl' => 'Piaskownica · Java i Bedrock'],
            'description' => [
                'ru' => 'Выживание с друзьями, SkyBlock, анархия или мини-игры — выбери версию и режим, а мы покажем серверы, где уже копают.',
                'pl' => 'Przetrwanie ze znajomymi, SkyBlock, anarchia czy minigry — wybierz wersję i tryb, a pokażemy serwery, na których już kopią.',
            ],
        ],
        'dayz' => [
            'eyebrow' => ['uk' => 'Виживання · PC · 4 мапи', 'en' => 'Survival · PC · 4 maps', 'ru' => 'Выживание · PC · 4 карты', 'pl' => 'Przetrwanie · PC · 4 mapy'],
            'description' => [
                'uk' => 'Чорнорусь, Лівонія, острови Сахалу. Обери мапу й правила виживання — ми покажемо, де ще є вільне місце біля вогнища.',
                'en' => 'Chernarus, Livonia, the islands of Sakhal. Pick a map and survival rules — we will show where there is still room by the fire.',
                'ru' => 'Чернорусь, Ливония, острова Сахала. Выбери карту и правила выживания — мы покажем, где ещё есть место у костра.',
                'pl' => 'Czarnoruś, Liwonia, wyspy Sachalinu. Wybierz mapę i zasady przetrwania — pokażemy, gdzie jest jeszcze miejsce przy ognisku.',
            ],
        ],
        'rust' => [
            'eyebrow' => ['uk' => 'Виживання · вайп щочетверга', 'en' => 'Survival · Thursday wipes', 'ru' => 'Выживание · вайп по четвергам', 'pl' => 'Przetrwanie · wipe w czwartki'],
            'description' => [
                'uk' => 'Solo чи клан, ванілла чи x10 — обери, коли вайп і скільки вас. Покажемо сервери, де ще можна встигнути на свіжу мапу.',
                'en' => 'Solo or clan, vanilla or x10 — pick when the wipe is and how many of you there are. We will show servers where you can still catch a fresh map.',
                'ru' => 'Solo или клан, ванилла или x10 — выбери, когда вайп и сколько вас. Покажем серверы, где ещё можно успеть на свежую карту.',
                'pl' => 'Solo czy klan, vanilla czy x10 — wybierz, kiedy wipe i ilu was jest. Pokażemy serwery, na których zdążysz jeszcze na świeżą mapę.',
            ],
        ],
        'counter-strike' => [
            'eyebrow' => ['ru' => 'Тактический шутер · CS2 и CS 1.6', 'pl' => 'Taktyczna strzelanka · CS2 i CS 1.6'],
            'description' => [
                'ru' => 'Паблик, ретейк, DM или серф — выбери версию, режим и карту. Пинг, тикрейт и живой онлайн видно сразу.',
                'pl' => 'Public, retake, DM czy surf — wybierz wersję, tryb i mapę. Ping, tickrate i liczba graczy na żywo od razu widoczne.',
            ],
        ],
    ];

    /** сторінка => [заголовок, текст] за мовою */
    private const PAGES = [
        'rules' => [
            'ru' => ['Правила портала', '<h2>Для игроков</h2><ul><li>Голосовать за сервер можно раз в интервал, указанный на странице голосования, с одного IP, аккаунта и устройства.</li><li>Голоса через VPN, прокси и дата-центры не засчитываются.</li><li>Один отзыв на сервер, без оскорблений, рекламы и ссылок.</li></ul><h2>Для владельцев серверов</h2><ul><li>Добавляйте только серверы, которыми управляете; подтвердите владение в кабинете.</li><li>Накрутка голосов или онлайна ведёт к аннулированию голосов и блокировке сервера.</li><li>Описание должно соответствовать серверу: рейты, хроники, версия, режим.</li></ul><h2>Модерация</h2><p>Модераторы проверяют новые серверы и смену названия или адреса. Нарушения рассматриваются по жалобам; решения приходят уведомлениями.</p>'],
            'pl' => ['Regulamin portalu', '<h2>Dla graczy</h2><ul><li>Na serwer można głosować raz na okres podany na stronie głosowania, z jednego IP, konta i urządzenia.</li><li>Głosy przez VPN, proxy i centra danych nie są liczone.</li><li>Jedna opinia na serwer, bez obelg, reklam i linków.</li></ul><h2>Dla właścicieli serwerów</h2><ul><li>Dodawaj tylko serwery, którymi zarządzasz; potwierdź własność w panelu.</li><li>Sztuczne podbijanie głosów lub online skutkuje anulowaniem głosów i blokadą serwera.</li><li>Opis musi odpowiadać serwerowi: rate, kronika, wersja, tryb.</li></ul><h2>Moderacja</h2><p>Moderatorzy sprawdzają nowe serwery oraz zmiany nazwy lub adresu. Naruszenia są rozpatrywane na podstawie zgłoszeń; decyzje przychodzą w powiadomieniach.</p>'],
        ],
        'faq' => [
            'ru' => ['FAQ', '<h2>Как считается рейтинг?</h2><p>По голосам текущего месяца, бонусным баллам, онлайну и аптайму. Рейтинг обновляется каждые 5 минут; новый месяц начинается 1-го числа.</p><h2>Как получить награду за голос?</h2><p>Укажите ник при голосовании — сервер выдаст награду, если подключил vote-callback.</p><h2>Как добавить свой сервер?</h2><p>Зарегистрируйтесь, нажмите «Добавить сервер» и заполните карточку. После модерации сервер появится в рейтинге.</p><h2>Как продвинуть сервер?</h2><p>В кабинете на странице сервера: VIP, выделение, поднятие, анонс открытия и баннеры оплачиваются с баланса.</p>'],
            'pl' => ['FAQ', '<h2>Jak liczony jest ranking?</h2><p>Na podstawie głosów z bieżącego miesiąca, punktów bonusowych, liczby graczy online i uptime. Ranking aktualizuje się co 5 minut; nowy miesiąc zaczyna się 1. dnia.</p><h2>Jak dostać nagrodę za głos?</h2><p>Podaj nick przy głosowaniu — serwer wyda nagrodę, jeśli podłączył vote-callback.</p><h2>Jak dodać swój serwer?</h2><p>Zarejestruj się, kliknij „Dodaj serwer” i wypełnij kartę. Po moderacji serwer pojawi się w rankingu.</p><h2>Jak wypromować serwer?</h2><p>W panelu na stronie serwera: VIP, wyróżnienie, podbicie, zapowiedź otwarcia i banery opłacasz z salda.</p>'],
        ],
        'contacts' => [
            'ru' => ['Контакты', '<p>Вопросы об оплате, модерации или технических проблемах — создайте обращение в разделе «Поддержка» кабинета: ответ придёт туда и в уведомления.</p><p>Владельцам серверов — документация API и виджетов на странице «Для разработчиков».</p>'],
            'pl' => ['Kontakt', '<p>Pytania o płatności, moderację lub problemy techniczne — utwórz zgłoszenie w sekcji „Pomoc” w panelu: odpowiedź przyjdzie tam i w powiadomieniach.</p><p>Właściciele serwerów — dokumentacja API i widżetów na stronie „Dla deweloperów”.</p>'],
        ],
        'privacy' => [
            'ru' => ['Политика конфиденциальности', '<p>Эта страница описывает, какие данные хранит graj.club, зачем и как их удалить.</p><h2>Аккаунт</h2><ul><li>Email, ник, хеш пароля, язык интерфейса и настройки уведомлений.</li><li>Если вы подключили Telegram или Discord — ID чата или адрес вебхука (вебхук хранится в зашифрованном виде).</li><li>Вход через Google или Discord передаёт нам только email и имя.</li></ul><h2>Голосование</h2><ul><li>Чтобы засчитывать один голос за интервал, мы храним хеш IP-адреса и подсети, идентификатор устройства и введённый ник.</li><li>Хеши IP удаляются через 90 дней.</li><li>Для борьбы с накруткой IP может проверяться сервисом обнаружения VPN; форма защищена Cloudflare Turnstile.</li><li>Ник и время голоса передаются серверу, за который вы проголосовали, чтобы он выдал награду.</li></ul><h2>Платежи</h2><p>Данные карты вводятся на странице LiqPay или Stripe — мы их не видим и не храним. Мы сохраняем сумму, номер транзакции и историю услуг.</p><h2>Сообщения, отзывы, обращения</h2><p>Хранятся, пока существует аккаунт. Вложения видят только участники переписки и модераторы при жалобе.</p><h2>Cookies</h2><p>Только необходимые: сессия входа, защита форм (XSRF), «запомнить меня». Тема и закрытое уведомление о cookies хранятся в вашем браузере. Без рекламы и сторонней аналитики.</p><h2>Ваши права</h2><p>Удалить аккаунт можно в кабинете: «Профиль» → «Удалить аккаунт». Ваши серверы, отзывы и сообщения удаляются; голоса остаются в статистике без связи с вами. Другие запросы о данных — через «Поддержку» в кабинете.</p><h2>Кто ещё обрабатывает данные</h2><p>Хостинг сайта, Cloudflare (защита и капча), платёжные системы LiqPay и Stripe, сервис обнаружения VPN, Sentry (отчёты об ошибках), Telegram и Discord — только если вы подключили уведомления.</p>'],
            'pl' => ['Polityka prywatności', '<p>Ta strona opisuje, jakie dane przechowuje graj.club, w jakim celu i jak je usunąć.</p><h2>Konto</h2><ul><li>E-mail, nick, hash hasła, język interfejsu i ustawienia powiadomień.</li><li>Jeśli połączyłeś Telegram lub Discord — ID czatu lub adres webhooka (webhook jest przechowywany w postaci zaszyfrowanej).</li><li>Logowanie przez Google lub Discord przekazuje nam tylko e-mail i imię.</li></ul><h2>Głosowanie</h2><ul><li>Aby liczyć jeden głos na okres, przechowujemy hash adresu IP i podsieci, identyfikator urządzenia i wpisany nick.</li><li>Hashe IP są usuwane po 90 dniach.</li><li>W walce z oszustwami IP może być sprawdzany przez usługę wykrywania VPN; formularz chroni Cloudflare Turnstile.</li><li>Nick i czas głosu są przekazywane serwerowi, na który zagłosowałeś, aby mógł wydać nagrodę.</li></ul><h2>Płatności</h2><p>Dane karty wpisujesz na stronie LiqPay lub Stripe — nie widzimy ich i nie przechowujemy. Zapisujemy kwotę, numer transakcji i historię usług.</p><h2>Wiadomości, opinie, zgłoszenia</h2><p>Przechowywane, dopóki istnieje konto. Załączniki widzą tylko uczestnicy rozmowy i moderatorzy przy zgłoszeniu.</p><h2>Cookies</h2><p>Tylko niezbędne: sesja logowania, ochrona formularzy (XSRF), „zapamiętaj mnie”. Motyw i zamknięte powiadomienie o cookies są zapisywane w twojej przeglądarce. Bez reklam i analityki firm trzecich.</p><h2>Twoje prawa</h2><p>Konto możesz usunąć w panelu: „Profil” → „Usuń konto”. Twoje serwery, opinie i wiadomości są usuwane; głosy zostają w statystykach bez powiązania z tobą. Inne prośby dotyczące danych — przez „Pomoc” w panelu.</p><h2>Kto jeszcze przetwarza dane</h2><p>Hosting strony, Cloudflare (ochrona i captcha), systemy płatności LiqPay i Stripe, usługa wykrywania VPN, Sentry (raporty błędów), Telegram i Discord — tylko jeśli połączyłeś powiadomienia.</p>'],
        ],
    ];

    /** українська назва поля => [ru, pl] */
    private const FIELD_LABELS = [
        'Хроніки' => ['Хроники', 'Kronika'], 'Рейти' => ['Рейты', 'Rate'], 'Тип' => ['Тип', 'Typ'], 'Платформа' => ['Платформа', 'Platforma'],
        'Режим' => ['Режим', 'Tryb'], 'Видання' => ['Издание', 'Edycja'], 'Версія' => ['Версия', 'Wersja'], 'Ліцензія' => ['Лицензия', 'Licencja'],
        'Мапа' => ['Карта', 'Mapa'], 'Стиль' => ['Стиль', 'Styl'], 'Моди' => ['Моды', 'Mody'], 'Вайп' => ['Вайп', 'Wipe'], 'Група' => ['Группа', 'Grupa'],
        'Тикрейт' => ['Тикрейт', 'Tickrate'], 'Перспектива' => ['Перспектива', 'Perspektywa'], 'Розмір мапи' => ['Размер карты', 'Rozmiar mapy'],
    ];

    /** український підпис варіанта => [ru, pl] (латинські назви — Interlude, PvP, x100 — однакові всіма мовами) */
    private const OPTIONS = [
        'Гайдард' => ['Хардкор', 'Hardcore'], 'Мультипрофа' => ['Мультипрофа', 'Multiklasa'],
        'Виживання' => ['Выживание', 'Survival'], 'Анархія' => ['Анархия', 'Anarchia'], 'Міні-ігри' => ['Мини-игры', 'Minigry'], 'Творчий' => ['Творческий', 'Kreatywny'],
        'Ліцензія' => ['Лицензия', 'Licencja'], 'Піратка' => ['Пиратка', 'Piracka'],
        'Щотижня' => ['Еженедельно', 'Co tydzień'], 'Раз на 2 тижні' => ['Раз в 2 недели', 'Co 2 tygodnie'], 'Щомісяця' => ['Ежемесячно', 'Co miesiąc'],
        'Без ліміту' => ['Без лимита', 'Bez limitu'], 'Ванілла' => ['Ванилла', 'Vanilla'],
    ];

    /** Шаблони назв тарифів: українська регулярка => [en, ru, pl] з $1 — числом */
    private const TARIFFS = [
        '/^VIP на (\d+) днів$/u' => ['VIP for $1 days', 'VIP на $1 дней', 'VIP na $1 dni'],
        '/^Виділення на (\d+) днів$/u' => ['Highlight for $1 days', 'Выделение на $1 дней', 'Wyróżnienie na $1 dni'],
        '/^Підняття \+(\d+) балів$/u' => ['Boost +$1 points', 'Поднятие +$1 баллов', 'Podbicie +$1 punktów'],
        '/^Банер у шапці, (\d+) дн\.$/u' => ['Header banner, $1 days', 'Баннер в шапке, $1 дн.', 'Baner w nagłówku, $1 dn.'],
        '/^Банер у сайдбарі, (\d+) дн\.$/u' => ['Sidebar banner, $1 days', 'Баннер в сайдбаре, $1 дн.', 'Baner w pasku bocznym, $1 dn.'],
        '/^Банер на сторінці гри, (\d+) дн\.$/u' => ['Game page banner, $1 days', 'Баннер на странице игры, $1 дн.', 'Baner na stronie gry, $1 dn.'],
        '/^Банер у бічній колонці гри, (\d+) дн\.$/u' => ['Game side column banner, $1 days', 'Баннер в боковой колонке игры, $1 дн.', 'Baner w kolumnie bocznej gry, $1 dn.'],
        '/^Банер на сторінці голосування, (\d+) дн\.$/u' => ['Vote page banner, $1 days', 'Баннер на странице голосования, $1 дн.', 'Baner na stronie głosowania, $1 dn.'],
        '/^Анонс «Скоро відкриття»$/u' => ['“Opening soon” announcement', 'Анонс «Скоро открытие»', 'Zapowiedź „Wkrótce otwarcie”'],
    ];

    public static function fill(): void
    {
        foreach (Game::all() as $game) {
            foreach (self::GAMES[$game->slug] ?? [] as $attribute => $texts) {
                self::fillAttribute($game, $attribute, $texts);
            }
            // Назва гри — однакова всіма мовами (Lineage 2, Rust…)
            $name = $game->getTranslation('name', 'en', false) ?: $game->getTranslation('name', 'uk', false);
            self::fillAttribute($game, 'name', ['ru' => $name, 'pl' => $name]);
            $game->isDirty() && $game->saveQuietly();
        }

        foreach (Page::all() as $page) {
            foreach (self::PAGES[$page->slug] ?? [] as $locale => [$title, $body]) {
                self::fillAttribute($page, 'title', [$locale => $title]);
                self::fillAttribute($page, 'body', [$locale => $body]);
            }
            $page->isDirty() && $page->saveQuietly();
        }

        foreach (Tariff::all() as $tariff) {
            $uk = (string) $tariff->getTranslation('name', 'uk', false);
            foreach (self::TARIFFS as $pattern => [$en, $ru, $pl]) {
                if (preg_match($pattern, $uk)) {
                    $one = fn (string $t) => preg_replace('/\b1 days\b/', '1 day', preg_replace($pattern, $t, $uk));
                    self::fillAttribute($tariff, 'name', ['en' => $one($en), 'ru' => preg_replace($pattern, $ru, $uk), 'pl' => preg_replace($pattern, $pl, $uk)]);
                    // «1 days» у вже збережених англійських назвах
                    if (preg_match('/\b1 days\b/', (string) $tariff->getTranslation('name', 'en', false))) {
                        $tariff->setTranslation('name', 'en', preg_replace('/\b1 days\b/', '1 day', $tariff->getTranslation('name', 'en', false)));
                    }
                    break;
                }
            }
            $tariff->isDirty() && $tariff->saveQuietly();
        }

        foreach (GameField::all() as $field) {
            [$ru, $pl] = self::FIELD_LABELS[$field->getTranslation('label', 'uk', false)] ?? [null, null];
            if ($ru) {
                self::fillAttribute($field, 'label', ['ru' => $ru, 'pl' => $pl]);
            }
            // Варіанти списку: переклади — у labels[мова] (див. GameField::localize)
            $raw = json_decode((string) $field->getRawOriginal('options'), true);
            if (is_array($raw)) {
                $changed = false;
                foreach ($raw as &$option) {
                    [$oru, $opl] = self::OPTIONS[$option['label'] ?? ''] ?? [null, null];
                    foreach (array_filter(['ru' => $oru, 'pl' => $opl]) as $locale => $text) {
                        if (blank($option['labels'][$locale] ?? null)) {
                            $option['labels'][$locale] = $text;
                            $changed = true;
                        }
                    }
                }
                unset($option);
                if ($changed) {
                    $field->options = $raw;
                }
            }
            $field->isDirty() && $field->saveQuietly();
        }
    }

    /** @param  array<string, string>  $texts  мова => текст; записує лише туди, де порожньо */
    private static function fillAttribute($model, string $attribute, array $texts): void
    {
        foreach ($texts as $locale => $text) {
            if ($text !== '' && blank($model->getTranslation($attribute, $locale, false))) {
                $model->setTranslation($attribute, $locale, $text);
            }
        }
    }
}
