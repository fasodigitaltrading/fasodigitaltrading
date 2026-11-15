<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => SubscriptionPlan::whereIn('slug', SubscriptionPlan::PUBLIC_CATALOGUE_SLUGS)
                ->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'list_price' => ['required', 'numeric', 'gte:price', 'max:99999999.99'],
            'capital_required' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'max_mt5_accounts' => ['required', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'status' => ['sometimes', 'boolean'],
            'features_text' => ['nullable', 'string', 'max:3000'],
        ]);

        if (strtoupper($data['currency']) === 'XOF') {
            if ((float) $data['price'] < 1) {
                throw ValidationException::withMessages([
                    'price' => 'Le prix d’une offre XOF doit être supérieur à zéro.',
                ]);
            }

            foreach (['price', 'list_price', 'capital_required'] as $amountField) {
                if (preg_match('/^\d+(?:\.0{1,2})?$/', (string) $data[$amountField]) !== 1) {
                    throw ValidationException::withMessages([
                        $amountField => 'Les montants XOF doivent être des nombres entiers.',
                    ]);
                }
            }
        }

        $approvedTerms = SubscriptionPlan::PUBLIC_CATALOGUE_TERMS[$plan->slug] ?? null;
        if ($approvedTerms) {
            if (strtoupper($data['currency']) !== 'USD'
                || $data['name'] !== $approvedTerms['name']
                || number_format((float) $data['capital_required'], 2, '.', '') !== $approvedTerms['capital']
                || (int) $data['duration_days'] !== 30) {
                throw ValidationException::withMessages([
                    'price' => 'Les noms, capitaux et durées de référence des quatre packs ne peuvent pas être modifiés ici.',
                ]);
            }
            $priceChanged = (float) $plan->price !== (float) $data['price']
                || (float) $plan->list_price !== (float) $data['list_price'];
            $priceChangeReason = null;
            if ($priceChanged) {
                $request->validate([
                    'price_change_confirmed' => ['required', Rule::in(['yes'])],
                    'price_change_reason' => ['required', 'string', 'min:10', 'max:500'],
                ]);
                $priceChangeReason = trim((string) $request->input('price_change_reason'));
            }
            $data['status'] = $request->boolean('status');
        } else {
            // Retained legacy offers must stay out of new public subscriptions.
            $data['status'] = false;
            $priceChanged = false;
            $priceChangeReason = null;
        }
        $data['features'] = array_values(array_filter(array_map(
            static fn (string $feature): string => trim($feature),
            preg_split('/\R/u', (string) ($data['features_text'] ?? implode("\n", $plan->features ?? []))) ?: [],
        ), static fn (string $feature): bool => $feature !== ''));
        unset($data['features_text']);
        $fields = ['name', 'description', 'price', 'list_price', 'currency', 'capital_required', 'duration_days', 'max_mt5_accounts', 'sort_order', 'status', 'features'];
        $before = $plan->only($fields);

        DB::transaction(function () use ($plan, $data, $before, $request, $priceChanged, $priceChangeReason) {
            $plan->update($data);
            DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id,
                'action' => $priceChanged ? 'subscription_plan.price_updated' : 'subscription_plan.updated',
                'entity_type' => SubscriptionPlan::class,
                'entity_id' => $plan->id,
                'old_values' => json_encode($before, JSON_THROW_ON_ERROR),
                'new_values' => json_encode($plan->only(array_keys($before)) + ['price_change_reason' => $priceChangeReason], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.plans.index')->with('status', 'Offre mise à jour. Les abonnements déjà créés conservent leurs conditions enregistrées.');
    }
}
