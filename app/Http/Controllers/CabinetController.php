<?php

namespace App\Http\Controllers;

use App\Billing\Billing;
use App\Jobs\DeliverVoteCallback;
use App\Models\Server;
use App\Models\ServerInvitation;
use App\Models\Tariff;
use App\Models\Vote;
use App\Rules\PublicAddress;
use App\Servers\OwnershipVerifier;
use App\Support\PublicHost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CabinetController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $servers = Server::with('game')
            ->where('user_id', $user->id)
            ->orWhereHas('coOwners', fn ($q) => $q->whereKey($user->id))
            ->latest()
            ->get();

        return view('cabinet.index', compact('servers'));
    }

    public function show(Request $request, Server $server, OwnershipVerifier $verifier, Billing $billing): View
    {
        $this->authorizeServer($request, $server);

        $server->load(['game', 'media', 'owner', 'coOwners']);
        $invitations = ServerInvitation::where('server_id', $server->id)->latest()->get();

        $stats = DB::table('server_stats_hourly')
            ->where('server_id', $server->id)
            ->where('hour', '>=', now()->subDays(7))
            ->orderBy('hour')
            ->get(['hour', 'players_avg', 'votes']);

        $recentVotes = $server->votes()->latest('created_at')->limit(10)->get();

        // Просування: тарифи гри (і загальні), активні й оплачені наперед послуги сервера
        $tariffs = Tariff::where('is_active', true)
            ->where(fn ($q) => $q->where('game_id', $server->game_id)->orWhereNull('game_id'))
            ->orderBy('service')->orderBy('price')->get()->groupBy('service');
        $activeOrders = $server->orders()->with('tariff')->where('status', 'active')->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderBy('ends_at')->get();

        return view('cabinet.server', [
            'server' => $server,
            'verifyCode' => $verifier->code($server),
            'stats' => $stats,
            'recentVotes' => $recentVotes,
            'invitations' => $invitations,
            'isMainOwner' => $server->user_id === $request->user()->id,
            'tariffs' => $tariffs,
            'activeOrders' => $activeOrders,
            'vipTaken' => $billing->vipTaken($server),
        ]);
    }

    public function verify(Request $request, Server $server, OwnershipVerifier $verifier): RedirectResponse
    {
        $this->authorizeServer($request, $server);

        $method = $verifier->verify($server);

        return back()->with($method ? 'status' : 'error', $method
            ? __('cabinet.verify.success', ['method' => __("cabinet.verify.method_{$method}")])
            : __('cabinet.verify.failed'));
    }

    public function updateCallback(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server);

        $data = $request->validate([
            'callback_url' => ['nullable', 'url:http,https', 'max:255', new PublicAddress(url: true)],
        ]);

        $server->forceFill(['callback_url' => $data['callback_url'] ?: null])->save();

        return back()->with('status', __('cabinet.callback.saved'));
    }

    public function testCallback(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server);

        if (! PublicHost::isPublicUrl($server->callback_url)) {
            return back()->with('error', __('cabinet.callback.not_set'));
        }

        $test = new Vote(['server_id' => $server->id, 'nickname' => 'graj_test']);
        $test->id = 0;
        $test->created_at = now();
        $body = json_encode(DeliverVoteCallback::payload($test, $request->ip()) + ['test' => true]);

        try {
            $response = Http::timeout(10)->withoutRedirecting()
                ->withHeaders(['X-Graj-Signature' => hash_hmac('sha256', $body, $server->callback_secret)])
                ->withBody($body, 'application/json')
                ->post($server->callback_url);

            return back()->with($response->successful() ? 'status' : 'error', __('cabinet.callback.test_result', ['code' => $response->status()]));
        } catch (\Throwable $e) {
            return back()->with('error', __('cabinet.callback.test_failed'));
        }
    }

    public function regenerateKey(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServer($request, $server);

        $server->forceFill(['callback_secret' => Str::random(40)])->save();

        return back()->with('status', __('cabinet.api.regenerated'));
    }

    private function authorizeServer(Request $request, Server $server): void
    {
        abort_unless($request->user()->canManage($server), 403);
    }
}
