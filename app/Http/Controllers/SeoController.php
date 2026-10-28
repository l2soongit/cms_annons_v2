<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Page;
use App\Models\Server;
use App\Support\GameUrl;
use App\Support\Locales;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * sitemap.xml з мовними версіями сторінок (hreflang) і robots.txt (ТЗ 4, SEO).
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('seo.sitemap', 3600, function () {
            $locales = array_keys(Locales::all());
            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

            foreach ($this->entries() as [$url, $lastmod, $priority]) {
                foreach ($locales as $locale) {
                    $out .= '<url><loc>'.e(Locales::localize($url, $locale)).'</loc>';
                    foreach ($locales as $alt) {
                        $out .= '<xhtml:link rel="alternate" hreflang="'.$alt.'" href="'.e(Locales::localize($url, $alt)).'"/>';
                    }
                    $out .= '<xhtml:link rel="alternate" hreflang="x-default" href="'.e($url).'"/>';
                    $out .= ($lastmod ? '<lastmod>'.$lastmod.'</lastmod>' : '').'<priority>'.$priority.'</priority></url>'."\n";
                }
            }

            return $out.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    public function robots(): Response
    {
        // Тестові й локальні копії сайту не індексуються зовсім
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /cabinet', 'Disallow: /admin', 'Disallow: /billing', 'Disallow: /api/', 'Disallow: /ad/', 'Disallow: /livewire/', 'Disallow: /*?*q=', '', 'Sitemap: '.route('seo.sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * Публічні сторінки основною мовою: адреса, дата зміни, пріоритет.
     *
     * @return iterable<array{0: string, 1: ?string, 2: string}>
     */
    private function entries(): iterable
    {
        yield [route('home'), null, '1.0'];

        $games = Game::active()->with('fields')->get();
        foreach ($games as $game) {
            yield [route('games.show', $game), null, '0.9'];

            // Сторінки фільтрів з ЧПУ (/lineage2/interlude), де є сервери
            foreach (GameUrl::pathFields($game) as $field) {
                $used = DB::table('server_field_values')->join('servers', 'servers.id', '=', 'server_field_values.server_id')
                    ->where('server_field_values.game_field_id', $field->id)->where('servers.status', 'published')->whereNull('servers.deleted_at')
                    ->distinct()->pluck('server_field_values.value')->all();
                foreach ($field->options as $option) {
                    if (in_array($option['value'], $used, true)) {
                        yield [GameUrl::to($game, [$field->key => $option['value']]), null, '0.8'];
                    }
                }
            }
        }

        foreach (Server::published()->whereIn('game_id', $games->pluck('id'))->orderByDesc('score')->cursor() as $server) {
            yield [route('servers.show', $server), $server->updated_at?->toAtomString(), '0.7'];
        }

        foreach (Page::where('is_published', true)->get() as $page) {
            yield [route('pages.show', $page), $page->updated_at?->toAtomString(), '0.3'];
        }

        yield [route('developers'), null, '0.4'];
    }
}
