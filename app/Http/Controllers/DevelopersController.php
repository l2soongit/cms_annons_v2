<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Support\OpenApi;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Документація для власників серверів (ТЗ 7.2–7.3): API, vote-callback, віджети, OpenAPI.
 */
class DevelopersController extends Controller
{
    public function index(): View
    {
        return view('developers', [
            'spec' => OpenApi::spec(),
            // Приклад віджетів — на справжньому сервері з рейтингу
            'sample' => Server::ranked()->whereNotNull('rank')->first(),
            'interval' => (int) config('graj.voting.interval_hours'),
        ]);
    }

    public function openapi(): JsonResponse
    {
        return response()->json(OpenApi::spec(), 200, ['Access-Control-Allow-Origin' => '*'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
