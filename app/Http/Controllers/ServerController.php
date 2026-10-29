<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Models\Report;
use App\Models\Server;
use App\Servers\ServerHistory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServerController extends Controller
{
    private const SIMILAR = 4;

    public function show(Request $request, Server $server, ServerHistory $history): View
    {
        abort_unless($server->status === ServerStatus::Published, 404);

        $server->load(['game', 'fieldValues.field', 'media']);

        $reviews = $server->reviews()
            ->where('status', 'published')
            ->with('user:id,name')
            ->latest()
            ->paginate(10, pageName: 'reviews');

        $myReview = $request->user()
            ? $server->reviews()->where('user_id', $request->user()->id)->first()
            : null;

        $rating = $server->reviews()->where('status', 'published')
            ->selectRaw('AVG(rating) as avg, COUNT(*) as total')
            ->first();

        // Розподіл оцінок 5★…1★ для блоку відгуків
        $stars = $server->reviews()->where('status', 'published')
            ->groupBy('rating')->selectRaw('rating, COUNT(*) as n')->pluck('n', 'rating')
            ->map(fn ($n) => (int) $n)->all();

        // Графіки всіх періодів одразу: перемикання без перезавантаження сторінки
        $charts = collect(ServerHistory::PERIODS)->keys()
            ->mapWithKeys(fn (string $period) => [$period => $history->points($server, $period)->values()])
            ->all();

        $peak = DB::table('server_stats_hourly')->where('server_id', $server->id)
            ->where('hour', '>=', now()->subDay())
            ->orderByDesc('players_max')->first(['hour', 'players_max']);

        return view('servers.show', [
            'server' => $server,
            'reviews' => $reviews,
            'myReview' => $myReview,
            'rating' => $rating,
            'stars' => $stars,
            'charts' => $charts,
            'peak' => $peak ? ['players' => (int) $peak->players_max, 'at' => Carbon::parse($peak->hour)] : null,
            'dayVotes' => $server->votes()->where('status', 'counted')->where('created_at', '>=', now()->subDay())->count(),
            'neighbors' => $this->neighbors($server),
            'similar' => $this->similar($server),
        ]);
    }

    /** Сусіди в рейтингу гри: скільки голосів до вищого місця й відрив від нижчого */
    private function neighbors(Server $server): array
    {
        if (! $server->rank) {
            return ['above' => null, 'below' => null];
        }

        $votes = fn (int $rank) => Server::published()->where('game_id', $server->game_id)->where('rank', $rank)->value('votes_month');

        return [
            'above' => $server->rank > 1 ? $votes($server->rank - 1) : null,
            'below' => $votes($server->rank + 1),
        ];
    }

    /**
     * Схожі сервери: та сама гра й те саме значення першого фільтра (хроніки, платформа, версія…),
     * решту місць добирає рейтинг гри.
     *
     * @return Collection<int, Server>
     */
    private function similar(Server $server): Collection
    {
        $base = fn () => Server::ranked()->where('game_id', $server->game_id)->whereKeyNot($server->id)
            ->with(['fieldValues.field', 'media' => fn ($q) => $q->where('type', 'logo')]);

        $first = $server->fieldValues->sortBy(fn ($v) => $v->field?->sort)->first();
        $similar = $first
            ? $base()->whereHas('fieldValues', fn ($q) => $q->where('game_field_id', $first->game_field_id)->where('value', $first->value))->limit(self::SIMILAR)->get()
            : new Collection;

        if ($similar->count() < self::SIMILAR) {
            $similar = $similar->concat($base()->whereKeyNot($similar->modelKeys())->limit(self::SIMILAR - $similar->count())->get());
        }

        return $similar;
    }

    public function report(Request $request, Server $server): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(Report::REASONS)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $server->morphMany(Report::class, 'reportable')->firstOrCreate(
            ['user_id' => $request->user()->id, 'status' => 'open'],
            $data,
        );

        return back()->with('status', __('reviews.reported'));
    }
}
