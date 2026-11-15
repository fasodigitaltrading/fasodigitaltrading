<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->role !== 'ADMIN' || $request->user()->mfa_verified_at !== null, 403);
        return view('notifications.index', ['notifications' => $request->user()->notifications()->latest()->limit(50)->get()]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        abort_unless($request->user()->role !== 'ADMIN' || $request->user()->mfa_verified_at !== null, 403);
        $record = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();
        $url = (string) ($record->data['url'] ?? '');
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $path = parse_url($url, PHP_URL_PATH);
        if (($host !== null && strcasecmp($host, $request->getHost()) !== 0)
            || ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https'], true))
            || ($host === null && ($scheme !== null || !is_string($path) || !str_starts_with($url, '/') || str_starts_with($url, '//')))) {
            $url = route('notifications.index');
        }

        return redirect()->to($url !== '' ? $url : route('notifications.index'));
    }
}
