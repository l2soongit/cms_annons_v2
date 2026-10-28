<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\HtmlSanitizer;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Page $page): View
    {
        abort_unless($page->is_published, 404);

        return view('page', ['page' => $page, 'body' => HtmlSanitizer::clean((string) $page->body)]);
    }
}
