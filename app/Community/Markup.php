<?php

namespace App\Community;

use App\Support\HtmlSanitizer;
use Illuminate\Support\Str;

/**
 * Текст дописів із сайту: простий Markdown (**жирний**, *курсив*, > цитата, списки, ## заголовок, посилання)
 * → безпечний HTML. Сирий HTML автора вирізається, посилання отримують nofollow.
 */
final class Markup
{
    public static function toHtml(string $source): string
    {
        $html = Str::markdown($source, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]);

        return HtmlSanitizer::clean($html);
    }

    /** Текст для поля редагування: збережений Markdown або видимий текст старого HTML */
    public static function source(?string $source, string $html): string
    {
        return $source ?? HtmlSanitizer::text($html);
    }

    /**
     * Заголовки ## отримують id для змісту статті.
     *
     * @return array{0: string, 1: list<array{id: string, text: string}>}
     */
    public static function withToc(string $html): array
    {
        $toc = [];
        $html = preg_replace_callback('~<h2>(.*?)</h2>~su', function (array $m) use (&$toc) {
            $text = trim(strip_tags($m[1]));
            $id = 's-'.(count($toc) + 1);
            $toc[] = ['id' => $id, 'text' => $text];

            return '<h2 id="'.$id.'">'.$m[1].'</h2>';
        }, $html) ?? $html;

        return [$html, $toc];
    }
}
