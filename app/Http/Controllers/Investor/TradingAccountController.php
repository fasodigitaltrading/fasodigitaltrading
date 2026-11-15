<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\TradingAccount;
use App\Services\Mt5AccountSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class TradingAccountController extends Controller
{
    public function store(Request $request, Mt5AccountSyncService $accounts): RedirectResponse
    {
        abort_unless($request->isSecure() || app()->environment(['local', 'testing']), 426, 'HTTPS is required.');
        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:100'],
            'provider_account_id' => ['required', 'uuid'],
            'account_access_token' => ['required', 'string', 'max:5000'],
        ]);

        $license = License::where('user_id', $request->user()->id)
            ->where('license_key_hash', hash('sha256', $data['license_key']))->first();
        if (!$license) {
            throw ValidationException::withMessages(['license_key' => 'Licence invalide.']);
        }

        try {
            $accounts->attach($request->user(), $license, $data['provider_account_id'], $data['account_access_token']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            return back()->withErrors(['connection' => 'Connexion impossible. Vérifiez le token MetaApi en lecture seule et réessayez.']);
        }

        return redirect()->route('investor.dashboard')->with('status', 'Compte MT5 rattaché en lecture seule.');
    }

    public function sync(Request $request, TradingAccount $account, Mt5AccountSyncService $accounts): RedirectResponse
    {
        abort_unless($request->isSecure() || app()->environment(['local', 'testing']), 426, 'HTTPS is required.');
        abort_unless($account->user_id === $request->user()->id, 404);

        try {
            $accounts->sync($account);
        } catch (Throwable) {
            // Keep the last good balance and history; only mark connectivity stale.
            $account->forceFill(['connection_status' => 'ERROR'])->save();

            return back()->withErrors(['connection' => 'Actualisation impossible pour le moment. Réessayez ultérieurement.']);
        }

        return back()->with('status', 'Données du compte actualisées.');
    }
}
