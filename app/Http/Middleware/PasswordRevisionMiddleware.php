<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PasswordRevisionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $revision = (int) $user->password_revision;
        if (!$request->session()->has('auth.password_revision')) {
            // Preserve sessions created before this feature was deployed.
            $request->session()->put('auth.password_revision', $revision);
        } elseif ((int) $request->session()->get('auth.password_revision') !== $revision) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Votre mot de passe a changé. Connectez-vous à nouveau.',
            ]);
        }

        return $next($request);
    }
}
