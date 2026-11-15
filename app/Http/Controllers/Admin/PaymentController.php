<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['PENDING', 'PAID', 'FAILED', 'CANCELLED', 'REFUNDED'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $payments = Payment::with([
            'user:id,first_name,last_name,email',
            'subscription:id,plan_name_snapshot,status',
            'subscriptionOrder:id,plan_name_snapshot',
        ])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, $needle): void {
                $query->where(function ($builder) use ($needle): void {
                    $builder->where('transaction_id', 'like', '%'.$needle.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$needle.'%'));
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'filters' => $filters,
            'totals' => Payment::query()->selectRaw('currency, status, COUNT(*) AS count, SUM(amount) AS total')
                ->groupBy('currency', 'status')->orderBy('currency')->orderBy('status')->get(),
        ]);
    }
}
