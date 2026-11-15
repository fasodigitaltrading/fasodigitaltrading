<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ReferralCodeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $userId = $request->user()->id;

        DB::transaction(function () use ($userId): void {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $partner = Partner::where('user_id', $userId)->lockForUpdate()->first();

            if ($partner && $partner->status !== 'ACTIVE') {
                abort(403, 'Le programme de parrainage n’est pas actif pour ce compte.');
            }

            if (!$partner) {
                $code = null;
                for ($attempt = 0; $attempt < 5; $attempt++) {
                    $candidate = Str::upper(Str::random(12));
                    if (!Partner::where('partner_code', $candidate)->exists()) {
                        $code = $candidate;
                        break;
                    }
                }

                if (!$code) {
                    throw new RuntimeException('A unique referral code could not be generated.');
                }

                $partner = new Partner([
                    'user_id' => $userId,
                    'partner_code' => $code,
                    'status' => 'ACTIVE',
                ]);
            }

            $partner->commission_rate = (string) config('referrals.default_commission_rate', '10.00');
            $partner->save();
        });

        return redirect()->route('investor.dashboard')->with('status', 'Votre code est prêt. La commission de 10 % est calculée sur chaque paiement confirmé des filleuls, y compris leurs renouvellements.');
    }
}
