<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteSettingsController extends Controller
{
    private const ALLOWED = ['home_headline', 'home_description'];

    public function index(): View
    {
        return view('admin.settings', ['settings' => collect(self::ALLOWED)->mapWithKeys(fn ($key) => [$key => SiteSetting::read($key, '')])]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'home_headline' => ['required', 'string', 'max:200'],
            'home_description' => ['required', 'string', 'max:1000'],
        ]);

        foreach (self::ALLOWED as $key) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => trim($data[$key])]);
        }

        return redirect()->route('admin.settings.index')->with('status', 'Contenu enregistré.');
    }
}
