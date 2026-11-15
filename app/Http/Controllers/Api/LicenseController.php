<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use RuntimeException;

class LicenseController extends Controller
{
    public function validateLicense(Request $request, LicenseService $service)
    {
        abort_unless($request->isSecure() || app()->environment(['local', 'testing']), 426, 'HTTPS is required.');

        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:100'],
            'account_id' => ['required', 'string', 'regex:/^[0-9]{1,20}$/'],
            'server' => ['required', 'string', 'max:150'],
        ]);

        if (strlen((string) config('license.binding_key')) < 32) {
            return response()->json(['valid' => false, 'status' => 'SERVICE_UNAVAILABLE'], 503);
        }

        try {
            return response()->json($service->validate($data['license_key'], $data['account_id'], $data['server']));
        } catch (RuntimeException) {
            return response()->json(['valid' => false, 'status' => 'SERVICE_UNAVAILABLE'], 503);
        }
    }
}
