<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Rules\Turnstile;
use App\Voting\VoteRejected;
use App\Voting\VoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoteController extends Controller
{
    public function create(Request $request, Server $server, VoteService $votes): View
    {
        abort_unless($server->status === ServerStatus::Published, 404);

        $server->load(['game', 'media' => fn ($q) => $q->where('type', 'logo')]);

        // Відрив від сусіда в рейтингу — мотивує проголосувати
        $neighbor = $server->rank
            ? Server::published()->where('game_id', $server->game_id)->where('rank', $server->rank > 1 ? $server->rank - 1 : 2)->value('votes_month')
            : null;

        return view('servers.vote', [
            'server' => $server,
            'nextVoteAt' => $votes->nextVoteAt($server, $request->ip(), $request->user()),
            // Щойно проголосував: стан «Дякуємо» на цій самій сторінці
            'voted' => $request->session()->get('voted'),
            'neighbor' => $neighbor,
        ]);
    }

    public function store(Request $request, Server $server, VoteService $votes): RedirectResponse
    {
        $data = $request->validate([
            'nickname' => ['nullable', 'string', 'max:32', 'regex:/^[\pL\pN_.\-\[\] ]+$/u'],
            'fingerprint' => ['nullable', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            'cf-turnstile-response' => [new Turnstile],
        ]);

        try {
            $votes->cast($server, $request->ip(), $data['nickname'] ?? null, $request->user(), $data['fingerprint'] ?? null);
        } catch (VoteRejected $e) {
            return back()->withInput()->withErrors([
                'vote' => __("vote.rejected.{$e->reason}", [
                    'time' => $e->retryAt?->locale(app()->getLocale())->diffForHumans(),
                ]),
            ]);
        }

        return to_route('servers.vote', $server)->with('voted', ['nickname' => $data['nickname'] ?? null]);
    }
}
