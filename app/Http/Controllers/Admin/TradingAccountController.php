<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TradingAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class TradingAccountController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['CONNECTED', 'DISCONNECTED', 'ERROR'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $accounts = TradingAccount::with(['user:id,first_name,last_name,email,status', 'broker:id,name', 'openPositions'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('connection_status', $status))
            ->when($filters['q'] ?? null, fn ($query, $needle) => $query->whereHas('user', fn ($user) => $user->where('email', 'like', '%'.$needle.'%')))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.trading-accounts.index', [
            'accounts' => $accounts,
            'filters' => $filters,
            'counts' => TradingAccount::query()->selectRaw('connection_status, COUNT(*) AS total')->groupBy('connection_status')->pluck('total', 'connection_status'),
        ]);
    }
}
