<?php

namespace App\Analytics;

use Illuminate\Http\Request;

/**
 * Хто відкрив сторінку — без cookies і без збереження IP:
 * анонімний ідентифікатор на добу, пристрій, браузер, ОС, мова, країна (з заголовка CDN), джерело переходу й UTM.
 */
final class Visitor
{
    private const SEARCH = ['google.', 'bing.com', 'yandex.', 'ya.ru', 'duckduckgo.com', 'yahoo.', 'ecosia.org', 'baidu.com', 'search.brave.com', 'startpage.com', 'qwant.com', 'meta.ua', 'seznam.cz', 'naver.com'];

    private const SOCIAL = ['facebook.com', 'fb.com', 'instagram.com', 't.co', 'twitter.com', 'x.com', 'vk.com', 'youtube.com', 'youtu.be', 'reddit.com', 'tiktok.com', 'linkedin.com', 'pinterest.', 'twitch.tv', 'threads.net', 'ok.ru', 'steamcommunity.com'];

    private const MESSENGER = ['t.me', 'telegram.', 'discord.com', 'discord.gg', 'discordapp.com', 'whatsapp.com', 'viber.com', 'messenger.com', 'web.skype.com'];

    // Порядок важливий: Edge і Opera містять «Chrome», Chrome містить «Safari»
    private const BROWSERS = ['YaBrowser' => 'Yandex', 'Edg' => 'Edge', 'OPR' => 'Opera', 'Opera' => 'Opera', 'SamsungBrowser' => 'Samsung', 'Vivaldi' => 'Vivaldi', 'Brave' => 'Brave', 'Firefox' => 'Firefox', 'FxiOS' => 'Firefox', 'CriOS' => 'Chrome', 'Chrome' => 'Chrome', 'Safari' => 'Safari', 'MSIE' => 'IE', 'Trident' => 'IE'];

    private const SYSTEMS = ['Windows' => 'Windows', 'iPhone' => 'iOS', 'iPad' => 'iPadOS', 'Android' => 'Android', 'CrOS' => 'ChromeOS', 'Mac OS X' => 'macOS', 'Macintosh' => 'macOS', 'Linux' => 'Linux'];

    // Відомі роботи — окремим рядком у звіті «Роботи», у відвідувачів не потрапляють
    private const BOTS = ['Googlebot' => 'Google', 'bingbot' => 'Bing', 'YandexBot' => 'Yandex', 'YandexImages' => 'Yandex', 'DuckDuckBot' => 'DuckDuckGo', 'Baiduspider' => 'Baidu', 'Applebot' => 'Apple', 'AhrefsBot' => 'Ahrefs', 'SemrushBot' => 'Semrush', 'MJ12bot' => 'Majestic', 'DotBot' => 'Moz', 'PetalBot' => 'Petal', 'GPTBot' => 'OpenAI', 'OAI-SearchBot' => 'OpenAI', 'ChatGPT-User' => 'OpenAI', 'ClaudeBot' => 'Anthropic', 'Claude-User' => 'Anthropic', 'PerplexityBot' => 'Perplexity', 'Bytespider' => 'ByteDance', 'facebookexternalhit' => 'Facebook', 'Twitterbot' => 'Twitter', 'TelegramBot' => 'Telegram', 'Discordbot' => 'Discord', 'Slackbot' => 'Slack', 'WhatsApp' => 'WhatsApp'];

    private const BOT_PATTERN = '/bot\b|crawl|spider|slurp|preview|fetch|scan|monitor|curl|wget|python|go-http|java\/|okhttp|axios|node-fetch|headless|lighthouse|pagespeed|phantom|scrapy|httpclient|uptime/i';

    public function __construct(
        public readonly string $id,
        public readonly string $device,
        public readonly ?string $browser,
        public readonly ?string $os,
        public readonly ?string $lang,
        public readonly ?string $country,
        public readonly string $source,
        public readonly ?string $referrer,
        public readonly bool $entry,
        public readonly ?string $utmSource,
        public readonly ?string $utmMedium,
        public readonly ?string $utmCampaign,
    ) {}

    public static function from(Request $request, ?string $referer = null): self
    {
        $ua = (string) $request->userAgent();
        $bot = self::bot($ua);

        // Сіль змінюється щодня: той самий браузер завтра — новий відвідувач, тож історію людини не зібрати
        $id = substr(hash('sha256', config('app.key').'|'.now()->toDateString().'|'.$request->ip().'|'.$ua), 0, 16);

        $referer ??= $request->headers->get('referer');
        $host = $referer ? strtolower((string) parse_url($referer, PHP_URL_HOST)) : null;
        $host = $host ? preg_replace('/^(www|m|l|lm|mobile)\./', '', $host) : null;
        $own = preg_replace('/^www\./', '', strtolower($request->getHost()));
        $internal = $host !== null && ($host === $own || str_ends_with($host, '.'.$own));

        $utm = fn (string $key) => ($v = trim((string) $request->query($key))) !== '' ? mb_substr(strip_tags($v), 0, 64) : null;
        $utmSource = $utm('utm_source') ?? $utm('ref');

        $source = match (true) {
            $internal => 'internal',
            $utmSource !== null => 'campaign',
            $host === null || $host === '' => 'direct',
            self::matches($host, self::SEARCH) => 'search',
            self::matches($host, self::SOCIAL) => 'social',
            self::matches($host, self::MESSENGER) => 'messenger',
            default => 'referral',
        };

        return new self(
            id: $id,
            device: $bot ? 'bot' : self::device($ua),
            browser: $bot ?? self::first($ua, self::BROWSERS),
            os: $bot ? null : self::first($ua, self::SYSTEMS),
            lang: self::lang((string) $request->headers->get('accept-language')),
            country: self::country($request),
            source: $source,
            referrer: $internal || ! $host ? null : mb_substr($host, 0, 128),
            // Перегляд із зовнішнього джерела або без Referer (закладка, введена адреса) починає новий візит
            entry: ! $internal,
            utmSource: $utmSource,
            utmMedium: $utm('utm_medium'),
            utmCampaign: $utm('utm_campaign'),
        );
    }

    public function isBot(): bool
    {
        return $this->device === 'bot';
    }

    /** Назва відомого робота, «Інший робот» для решти автоматичних клієнтів, null — людина */
    public static function bot(string $ua): ?string
    {
        if ($ua === '') {
            return 'Без User-Agent';
        }
        foreach (self::BOTS as $needle => $name) {
            if (stripos($ua, $needle) !== false) {
                return $name;
            }
        }

        return preg_match(self::BOT_PATTERN, $ua) ? 'Інший робот' : null;
    }

    private static function device(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet|PlayBook|Silk|Kindle|(Android(?!.*Mobile))/i', $ua) => 'tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android|Windows Phone|Opera Mini/i', $ua) => 'mobile',
            default => 'desktop',
        };
    }

    /** @param  array<string, string>  $map */
    private static function first(string $ua, array $map): ?string
    {
        foreach ($map as $needle => $name) {
            if (str_contains($ua, $needle)) {
                return $name;
            }
        }

        return null;
    }

    /** @param  list<string>  $list */
    private static function matches(string $host, array $list): bool
    {
        foreach ($list as $item) {
            if (str_ends_with($item, '.') ? str_starts_with($host, $item) || str_contains($host, '.'.$item) : ($host === $item || str_ends_with($host, '.'.$item))) {
                return true;
            }
        }

        return false;
    }

    private static function lang(string $header): ?string
    {
        return preg_match('/^\s*([a-z]{2,3})/i', $header, $m) ? strtolower($m[1]) : null;
    }

    /** Країна — лише якщо її передає CDN чи хостинг (Cloudflare, GeoIP модуль веб-сервера); баз GeoIP сайт не тримає */
    private static function country(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'X-Country-Code', 'X-Geo-Country', 'CloudFront-Viewer-Country', 'X-Appengine-Country'] as $header) {
            $value = strtoupper(trim((string) $request->headers->get($header)));
            if (preg_match('/^[A-Z]{2}$/', $value) && ! in_array($value, ['XX', 'T1', 'ZZ'], true)) {
                return $value;
            }
        }
        $server = strtoupper((string) ($request->server('GEOIP_COUNTRY_CODE') ?? $request->server('HTTP_GEOIP_COUNTRY_CODE') ?? ''));

        return preg_match('/^[A-Z]{2}$/', $server) ? $server : null;
    }
}
