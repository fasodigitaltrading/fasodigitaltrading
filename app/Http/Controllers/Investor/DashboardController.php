<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Partner;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\TradingAccount;
use App\Services\Mt5ConnectorService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, Mt5ConnectorService $mt5Connector)
    {
        $accounts = TradingAccount::with([
            'openPositions' => fn ($query) => $query->orderByDesc('opened_at'),
            'positionSnapshots' => fn ($query) => $query->where('is_open', false)->orderByDesc('captured_at')->limit(10),
            'trades' => fn ($query) => $query->orderByDesc('closed_at')->limit(20),
            'performanceSnapshots' => fn ($query) => $query->orderByDesc('captured_at')->limit(12),
        ])
            ->where('user_id', $request->user()->id)->orderByDesc('last_sync_at')->get();
        $licenses = License::with('subscription.plan')->withCount('accountBindings')
            ->where('user_id', $request->user()->id)->orderByDesc('created_at')->get();
        $subscriptions = Subscription::with(['plan', 'latestPayment'])
            ->where('user_id', $request->user()->id)->orderByDesc('created_at')->limit(10)->get();
        $orders = SubscriptionOrder::with('latestPayment')
            ->where('user_id', $request->user()->id)->orderByDesc('created_at')->limit(5)->get();
        $mt5ConnectorReady = $mt5Connector->isConfigured();
        $canAttachAccount = $mt5ConnectorReady && $licenses->contains(fn ($license) =>
            $license->status === 'ACTIVE'
            && (!$license->expires_at || $license->expires_at->isFuture())
            && $license->account_bindings_count < $license->max_accounts
        );
        $activeSubscriptionCount = $subscriptions->filter(fn ($subscription) => $subscription->isOperationallyActive())->count();
        $activeLicenseCount = $licenses->filter(fn ($license) =>
            $license->status === 'ACTIVE' && (!$license->expires_at || $license->expires_at->isFuture())
        )->count();
        $openPositionCount = $accounts->sum(fn ($account) => $account->openPositions->count());
        $partner = Partner::withCount('referrals')->where('user_id', $request->user()->id)->first();
        $referrals = $partner
            ? $partner->referrals()->with('user:id,first_name,last_name')->orderByDesc('created_at')->paginate(10, ['*'], 'referrals_page')
            : null;
        $commissionTotals = $partner
            ? $partner->commissions()->where('status', '!=', 'REVERSED')->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->orderBy('currency')->get()
            : collect();
        $commissions = $partner
            ? $partner->commissions()->with(['user:id,first_name,last_name', 'payment.subscription'])
                ->orderByDesc('created_at')->paginate(10, ['*'], 'commissions_page')
            : null;
        $charts = [];
        foreach ($accounts as $account) {
            $snapshots = $account->performanceSnapshots->sortBy('captured_at')->values();
            $values = $snapshots->map(fn ($snapshot) => (float) $snapshot->equity);
            $min = $values->min() ?? 0;
            $max = $values->max() ?? 0;
            $span = max(0.01, $max - $min);
            $charts[$account->id] = $values->map(function ($value, $index) use ($values, $min, $span) {
                $x = $values->count() > 1 ? ($index / ($values->count() - 1)) * 100 : 50;
                $y = 40 - (($value - $min) / $span) * 36;

                return number_format($x, 1, '.', '').','.number_format($y, 1, '.', '');
            })->implode(' ');
        }

        return view('investor.dashboard', compact(
            'accounts', 'charts', 'licenses', 'subscriptions', 'orders', 'partner', 'referrals',
            'commissionTotals', 'commissions', 'mt5ConnectorReady', 'canAttachAccount',
            'activeSubscriptionCount', 'activeLicenseCount', 'openPositionCount',
        ));
    }
}
