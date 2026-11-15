<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $partner = Partner::withCount('referrals')
            ->where('user_id', $request->user()->id)->firstOrFail();
        $commissionTotals = $partner->commissions()->where('status', '!=', 'REVERSED')
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')->orderBy('currency')->get();
        $referrals = $partner->referrals()
            ->with('user:id,first_name,last_name')
            ->latest()->paginate(10, ['*'], 'referrals_page');
        $commissions = $partner->commissions()
            ->with(['user:id,first_name,last_name', 'payment.subscription'])
            ->latest()->paginate(10, ['*'], 'commissions_page');

        return view('partner.dashboard', [
            'partner' => $partner,
            'commissionTotals' => $commissionTotals,
            'referrals' => $referrals,
            'commissions' => $commissions,
            'referralUrl' => route('register', ['ref' => $partner->partner_code]),
        ]);
    }
}
