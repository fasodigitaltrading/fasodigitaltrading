<?php

namespace App\Http\Controllers\Investor;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrvmPurchaseController extends Controller
{
    public function index(Request $request): View
    {
        return view('investor.brvm-orders', [
            'requests' => $request->user()->brvmPurchaseRequests()->latest()->paginate(10),
            'officialQuotesUrl' => 'https://www.brvm.org/fr/flux-en-temps-reel',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->isSecure() || app()->environment(['local', 'testing']), 426, 'HTTPS is required.');

        $request->merge(['symbol' => strtoupper(trim((string) $request->input('symbol')))]);
        $data = $request->validate([
            'symbol' => ['required', 'string', 'max:12', 'regex:/^[A-Z0-9]{3,12}$/'],
            'security_name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'limit_price' => ['nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'acknowledged_non_executable' => ['accepted'],
        ]);

        $purchaseRequest = $request->user()->brvmPurchaseRequests()->create([
            'symbol' => $data['symbol'],
            'security_name_snapshot' => $data['security_name'] ?? null,
            'quantity' => $data['quantity'],
            'limit_price' => $data['limit_price'] ?? null,
            'status' => 'RECEIVED',
            'customer_acknowledged_at' => now(),
        ]);

        return redirect()->route('investor.brvm.index')->with(
            'status',
            'Demande enregistrée sous la référence BWT-BRVM-'.$purchaseRequest->id.'. Elle n’a pas encore été transmise à Coris Bourse et aucun achat n’a été exécuté.',
        );
    }
}
