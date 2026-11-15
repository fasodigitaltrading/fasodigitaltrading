<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminTotpService;
use App\Support\PrimaryAdministrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function show(Request $request)
    {
        $portal = $request->route('portal');
        abort_if($portal !== null && !in_array($portal, ['investisseur', 'administrateur'], true), 404);

        return view('auth.login', ['portal' => $portal]);
    }

    public function login(Request $request, AdminTotpService $totp)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => 'Trop de tentatives.']);
        }

        $user = User::where('email', $credentials['email'])->first();
        if (!$user || !Hash::check($credentials['password'], $user->password) || $user->status !== 'ACTIVE') {
            RateLimiter::hit($key, 60);
            return back()->withErrors(['email' => 'Identifiants invalides.']);
        }

        $portal = $request->route('portal');
        if (($portal === 'administrateur' && $user->role !== 'ADMIN')
            || ($portal === 'investisseur' && $user->role === 'ADMIN')) {
            return back()->withErrors(['email' => 'Ces identifiants ne donnent pas accès à ce portail.']);
        }

        RateLimiter::clear($key);

        if ($user->role === 'ADMIN') {
            if (!PrimaryAdministrator::matches($user)) {
                return back()->withErrors(['email' => 'Identifiants invalides.']);
            }

            if (!$user->admin_totp_enabled_at || !$user->admin_totp_secret) {
                if (!$user->admin_totp_secret) {
                    $user->forceFill(['admin_totp_secret' => $totp->generateSecret()])->save();
                }
                $request->session()->put('auth.mfa_enrollment_user_id', $user->id);

                return redirect()->route('mfa.setup');
            }

            $request->session()->put('auth.mfa_user_id', $user->id);

            return redirect()->route('mfa.challenge');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->put('auth.password_revision', (int) $user->password_revision);

        if (!$user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function showMfaSetup(Request $request)
    {
        $userId = $request->session()->get('auth.mfa_enrollment_user_id');
        $user = $userId ? User::find($userId) : null;
        abort_unless(PrimaryAdministrator::matches($user) && $user->admin_totp_secret, 403);

        return view('auth.mfa-setup', [
            'secret' => $user->admin_totp_secret,
            'provisioningUri' => app(AdminTotpService::class)->provisioningUri($user->email, $user->admin_totp_secret),
        ]);
    }

    public function verifyMfaSetup(Request $request, AdminTotpService $totp)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $userId = $request->session()->get('auth.mfa_enrollment_user_id');
        $user = $userId ? User::find($userId) : null;
        $key = 'mfa-enroll:'.($userId ?? 'guest').'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Trop de tentatives. Reconnectez-vous.']);
        }

        if (!PrimaryAdministrator::matches($user) || !$user->admin_totp_secret
            || !$totp->verifyAndConsume($user, $data['code'])) {
            RateLimiter::hit($key, 600);
            return back()->withErrors(['code' => 'Code invalide. Vérifie l’heure de ton téléphone et réessaie.']);
        }

        RateLimiter::clear($key);
        $user->forceFill(['admin_totp_enabled_at' => now(), 'mfa_verified_at' => now()])->save();
        Auth::login($user);
        $request->session()->forget('auth.mfa_enrollment_user_id');
        $request->session()->regenerate();
        $request->session()->put('auth.password_revision', (int) $user->password_revision);

        if (!$user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function showMfaChallenge(Request $request)
    {
        $user = User::find($request->session()->get('auth.mfa_user_id'));
        abort_unless(PrimaryAdministrator::matches($user) && $request->session()->has('auth.mfa_user_id'), 403);

        return view('auth.mfa-challenge');
    }

    public function verifyMfa(Request $request, AdminTotpService $totp)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $id = $request->session()->get('auth.mfa_user_id');
        $user = $id ? User::find($id) : null;
        $key = 'mfa:'.($id ?? 'guest').'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Trop de tentatives. Reconnectez-vous.']);
        }

        if (!PrimaryAdministrator::matches($user)
            || !$user->admin_totp_enabled_at || !$user->admin_totp_secret
            || !$totp->verifyAndConsume($user, $data['code'])) {
            RateLimiter::hit($key, 600);
            return back()->withErrors(['code' => 'Code invalide ou expiré.']);
        }

        RateLimiter::clear($key);
        $user->forceFill(['mfa_verified_at' => now()])->save();
        Auth::login($user);
        $request->session()->forget('auth.mfa_user_id');
        $request->session()->regenerate();
        $request->session()->put('auth.password_revision', (int) $user->password_revision);

        if (!$user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
