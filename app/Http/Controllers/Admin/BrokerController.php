<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Broker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BrokerController extends Controller
{
    public function index(): View
    {
        return view('admin.brokers.index', ['brokers' => Broker::orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Broker::create($this->validated($request));

        return redirect()->route('admin.brokers.index')->with('status', 'Broker créé.');
    }

    public function update(Request $request, Broker $broker): RedirectResponse
    {
        $broker->update($this->validated($request, $broker));

        return redirect()->route('admin.brokers.index')->with('status', 'Broker mis à jour.');
    }

    private function validated(Request $request, ?Broker $broker = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('brokers', 'slug')->ignore($broker?->id)],
            'description' => ['nullable', 'string', 'max:10000'],
            'logo' => ['nullable', 'url', 'max:2048'],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'affiliate_url' => ['nullable', 'url', 'max:2048'],
            'mt5_supported' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ]);

        foreach (['logo', 'website_url', 'affiliate_url'] as $field) {
            if (!empty($data[$field]) && strtolower((string) parse_url($data[$field], PHP_URL_SCHEME)) !== 'https') {
                throw ValidationException::withMessages([$field => 'Les liens doivent utiliser HTTPS.']);
            }
        }

        $data['mt5_supported'] = $request->boolean('mt5_supported');
        $data['status'] = $request->boolean('status');

        return $data;
    }
}
