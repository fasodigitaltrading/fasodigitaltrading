<?php

namespace App\Http\Controllers;

use App\Models\ContactRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'intent' => ['required', Rule::in(['demo', 'quote', 'call', 'question'])],
            'package' => ['nullable', 'string', Rule::exists('subscription_plans', 'slug')->where('status', true)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'privacy_consent' => ['accepted'],
            'company' => ['prohibited'],
        ]);

        ContactRequest::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'intent' => $data['intent'],
            'package' => $data['package'] ?? null,
            'message' => $data['message'],
            'privacy_accepted_at' => now(),
            'privacy_notice_version' => config('legal.contact_notice_version'),
        ]);

        return back()->with('contact_status', 'Merci, votre demande a bien été enregistrée pour l’équipe FASO DIGITAL TRADING.');
    }
}
