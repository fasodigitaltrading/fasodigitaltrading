<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\EmailDeliveryReadiness;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    public function requestForm(EmailDeliveryReadiness $readiness): View
    {
        return view('auth.password-email', [
            'emailTransportAvailable' => $readiness->canAttemptTest(),
        ]);
    }

    public function sendResetLink(Request $request, EmailDeliveryReadiness $readiness): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        if (!$readiness->canAttemptTest()) {
            Log::notice('Password reset blocked because SMTP configuration is incomplete.');

            return back()->with('email_service_unavailable', true);
        }

        try {
            Password::sendResetLink($data);
        } catch (Throwable $exception) {
            Log::warning('Password reset email delivery failed.', ['exception' => $exception::class]);
        }

        // The response is the same for registered and unknown addresses.
        return back()->with('status', 'Si un compte correspond à cette adresse, vérifiez votre boîte de réception. Si aucun message n’arrive, contactez le support.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.password-reset', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
                'password_revision' => ((int) $user->password_revision) + 1,
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');
        }

        return back()->withErrors([
            'email' => 'Ce lien de réinitialisation est invalide ou a expiré. Demandez-en un nouveau.',
        ])->onlyInput('email');
    }
}
