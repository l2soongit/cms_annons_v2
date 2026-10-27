<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Пошук серверів за назвою або адресою. На віртуальному хостингу без Meilisearch — LIKE по MySQL;
 * таблиця має індекси, а обсяг (тисячі серверів) для цього прийнятний.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $q = trim(mb_substr((string) $request->query('q'), 0, 64));

        $servers = collect();
        if (mb_strlen($q) >= 2) {
            $like = '%'.addcslashes($q, '%_\\').'%';

            $servers = Server::published()
                ->with(['game', 'fieldValues.field'])
                ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('host', 'like', $like))
                ->orderByDesc('score')
                ->limit(50)
                ->get();
        }

        return view('search', compact('q', 'servers'));
    }
}
