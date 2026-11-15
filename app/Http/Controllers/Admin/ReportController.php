<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\License;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class ReportController extends Controller
{
    public function index(): View
    {
        $monthStart = CarbonImmutable::now()->startOfMonth()->subMonths(11);
        $monthlyPayments = Payment::where('status', 'PAID')->whereNotNull('paid_at')->where('paid_at', '>=', $monthStart)
            ->get(['amount', 'currency', 'paid_at']);
        $currencies = $monthlyPayments->pluck('currency')->unique()->sort()->values();
        $months = collect(range(0, 11))->map(fn (int $offset) => $monthStart->addMonths($offset));
        $monthlyTotals = $currencies->mapWithKeys(function (string $currency) use ($months, $monthlyPayments): array {
            $forCurrency = $monthlyPayments->where('currency', $currency);
            return [$currency => $months->mapWithKeys(function (CarbonImmutable $month) use ($forCurrency): array {
                $key = $month->format('Y-m');
                return [$key => [
                    'label' => $month->translatedFormat('M Y'),
                    'total' => (float) $forCurrency->filter(fn (Payment $payment) => $payment->paid_at?->format('Y-m') === $key)->sum('amount'),
                ]];
            })->all()];
        });

        return view('admin.reports.index', [
            'periodLabel' => $monthStart->format('m/Y').' — '.CarbonImmutable::now()->format('m/Y'),
            'monthlyTotals' => $monthlyTotals,
            'paymentTotals' => Payment::query()->selectRaw('currency, status, COUNT(*) AS count, SUM(amount) AS total')
                ->groupBy('currency', 'status')->orderBy('currency')->orderBy('status')->get(),
            'commissionTotals' => Commission::query()->selectRaw('currency, status, COUNT(*) AS count, SUM(amount) AS total')
                ->groupBy('currency', 'status')->orderBy('currency')->orderBy('status')->get(),
            'subscriptionTotals' => Subscription::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->orderBy('status')->get(),
            'planTotals' => Subscription::query()->selectRaw('plan_name_snapshot, COUNT(*) AS total')->groupBy('plan_name_snapshot')->orderBy('plan_name_snapshot')->get(),
            'counts' => [
                'users' => User::count(),
                'partners' => Partner::count(),
                'active_partners' => Partner::where('status', 'ACTIVE')->count(),
                'licenses' => License::count(),
                'active_licenses' => License::where('status', 'ACTIVE')->count(),
            ],
        ]);
    }
}
