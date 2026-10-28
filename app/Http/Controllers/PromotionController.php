<?php

namespace App\Http\Controllers;

use App\Billing\Billing;
use App\Billing\BillingException;
use App\Models\Order;
use App\Models\Server;
use App\Models\Tariff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Купівля платних послуг для сервера з балансу та керування автопродовженням.
 */
class PromotionController extends Controller
{
    public function store(Request $request, Server $server, Billing $billing): RedirectResponse
    {
        abort_unless($request->user()->canManage($server), 403);

        $data = $request->validate([
            'tariff_id' => ['required', 'integer'],
            'promo_code' => ['nullable', 'string', 'max:32'],
            'auto_renew' => ['nullable', 'boolean'],
        ]);

        $tariff = Tariff::findOrFail($data['tariff_id']);

        try {
            $order = $billing->purchase($request->user(), $server, $tariff, $data['promo_code'] ?? null, (bool) ($data['auto_renew'] ?? false));
        } catch (BillingException $e) {
            return redirect()->to(route('cabinet.servers.show', $server).'#s-promo')->withInput()->withErrors(['tariff_id' => $e->getMessage()], 'promo');
        }

        return redirect()->to(route('cabinet.servers.show', $server).'#s-promo')->with('status', __('billing.promo.bought', [
            'service' => $tariff->name,
            'date' => $order->ends_at->translatedFormat('j F Y, H:i'),
        ]));
    }

    public function autoRenew(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id && $order->status === 'active', 403);
        $order->loadMissing('tariff');
        abort_if(in_array($order->tariff->service, ['boost', 'coming_soon'], true), 422);

        $order->update(['auto_renew' => ! $order->auto_renew]);

        return back()->with('status', __($order->auto_renew ? 'billing.promo.auto_renew_on' : 'billing.promo.auto_renew_off'));
    }
}
