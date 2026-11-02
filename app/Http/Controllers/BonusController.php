<?php

namespace App\Http\Controllers;

use App\Community\Bonus;
use App\Models\BonusReward;
use App\Models\BonusTransaction;
use App\Models\ForumTopic;
use App\Support\PageCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Кабінет → Бонуси: баланс балів, рівень, за що нараховуються, магазин нагород, історія.
 */
class BonusController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $owner = $user->servers()->exists();

        return view('cabinet.bonus', [
            'user' => $user,
            'level' => Bonus::level($user),
            'rewards' => BonusReward::where('is_active', true)->orderBy('sort')->get()
                ->filter(fn (BonusReward $r) => $r->audience === 'all' || ($r->audience === 'owners') === $owner),
            'history' => BonusTransaction::where('user_id', $user->id)->latest('id')->paginate(20),
            'clanTopics' => ForumTopic::published()->where('user_id', $user->id)->where('type', 'clan')->latest('id')->get(['id', 'title']),
            'earn' => collect(['topic', 'server_news', 'best_answer', 'like', 'daily_vote', 'streak_7', 'owner_reply'])
                ->mapWithKeys(fn ($k) => [$k => (int) config("graj.community.bonus.{$k}", 0)])->filter(),
        ]);
    }

    public function buy(Request $request, BonusReward $reward): RedirectResponse
    {
        $user = $request->user();
        abort_unless($reward->is_active && Bonus::enabled(), 404);
        $owner = $user->servers()->exists();
        abort_unless($reward->audience === 'all' || ($reward->audience === 'owners') === $owner, 403);

        $topic = null;
        if ($reward->kind === 'pin_clan') {
            $data = $request->validate(['topic_id' => ['required', 'integer']]);
            $topic = ForumTopic::published()->where('user_id', $user->id)->where('type', 'clan')->findOrFail($data['topic_id']);
        }

        if (! Bonus::spend($user, $reward->cost, 'reward', $reward, $reward->name)) {
            return back()->withErrors(['reward' => __('community.bonus.not_enough')]);
        }

        $until = $reward->duration_days ? now()->addDays($reward->duration_days) : null;
        if ($topic) {
            $from = $topic->pinned_until?->isFuture() ? $topic->pinned_until : now();
            $topic->forceFill(['pinned_until' => $from->copy()->addDays($reward->duration_days ?: 3)])->save();
            PageCache::flush();
        } else {
            $key = $reward->kind === 'service_discount' ? 'discount' : $reward->kind;
            $perks = (array) $user->fresh()->perks;
            $perks[$key] = ['value' => (string) $reward->value, 'until' => $until?->toIso8601String()];
            $user->forceFill(['perks' => $perks])->save();
        }

        return back()->with('status', __('community.bonus.bought', ['name' => $reward->name]));
    }
}
