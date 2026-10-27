<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Models\Report;
use App\Models\Server;
use App\Servers\ServerHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServerController extends Controller
{
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

        $period = array_key_exists($request->query('period'), ServerHistory::PERIODS) ? $request->query('period') : 'week';
        $history = $history->points($server, $period);

        return view('servers.show', compact('server', 'reviews', 'myReview', 'rating', 'history', 'period'));
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
