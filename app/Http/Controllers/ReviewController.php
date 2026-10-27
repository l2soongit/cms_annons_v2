<?php

namespace App\Http\Controllers;

use App\Enums\ServerStatus;
use App\Models\Report;
use App\Models\Review;
use App\Models\Server;
use App\Notifications\NewReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function store(Request $request, Server $server): RedirectResponse
    {
        abort_unless($server->status === ServerStatus::Published, 404);
        abort_if($request->user()->isBanned(), 403);

        if ($request->user()->canManage($server)) {
            return back()->withErrors(['body' => __('reviews.own_server')]);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $body = strip_tags($data['body']);

        $review = Review::updateOrCreate(
            ['server_id' => $server->id, 'user_id' => $request->user()->id],
            ['rating' => $data['rating'], 'body' => $body, 'status' => $this->needsPremoderation($body) ? 'pending' : 'published'],
        );

        if ($review->wasRecentlyCreated && $review->status === 'published') {
            $server->owner?->notify(new NewReview($review));
        }

        return to_route('servers.show', $server)->withFragment('reviews')->with('status', __('reviews.saved'));
    }

    public function reply(Request $request, Review $review): RedirectResponse
    {
        abort_unless($request->user()->canManage($review->server), 403);

        $data = $request->validate(['owner_reply' => ['nullable', 'string', 'max:2000']]);

        $review->update(['owner_reply' => strip_tags((string) $data['owner_reply']) ?: null]);

        return back()->with('status', __('reviews.reply_saved'));
    }

    public function report(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(Report::REASONS)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->morphMany(Report::class, 'reportable')->firstOrCreate(
            ['user_id' => $request->user()->id, 'status' => 'open'],
            $data,
        );

        return back()->with('status', __('reviews.reported'));
    }

    /**
     * Посилання, стоп-слова та повторювані символи відправляють відгук на премодерацію.
     */
    private function needsPremoderation(string $body): bool
    {
        if (preg_match('#https?://|www\.|\.(ru|com|net|org|gg|ua)\b#i', $body)) {
            return true;
        }

        if (preg_match('/(.)\1{7,}/u', $body)) {
            return true;
        }

        return Str::contains(Str::lower($body), config('graj.reviews.stop_words'));
    }
}
