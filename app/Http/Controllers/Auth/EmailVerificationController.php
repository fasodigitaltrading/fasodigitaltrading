<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionOrder;
use App\Support\EmailDeliveryReadiness;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Throwable;

class EmailVerificationController extends Controller
{
    public function notice(Request $request, EmailDeliveryReadiness $readiness): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.verify-email', [
            'emailDeliveryStatus' => $readiness->status(),
            'emailCanSend' => $readiness->canSend(),
        ]);
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();
        if (!$user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $checkoutOrderId = $request->session()->pull('checkout.order_id');
        if ($checkoutOrderId !== null) {
            $order = SubscriptionOrder::whereKey($checkoutOrderId)
                ->where('user_id', $user->id)
                ->where('status', 'AWAITING_PAYMENT')
                ->first();
            if ($order) {
                return redirect()->route('subscriptions.orders.show', $order)
                    ->with('status', 'Adresse e-mail confirmée. Votre commande est prête; aucun paiement n’a été effectué.');
            }
        }

        return redirect()->intended(route('dashboard'))->with('status', 'Adresse e-mail confirmée. Votre espace est maintenant accessible.');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        $readiness = app(\App\Support\EmailDeliveryReadiness::class);
        if (!$readiness->canSend()) {
            Log::notice('Verification email resend blocked because SMTP is not live verified.', [
                'user_id' => $request->user()->id,
                'email_status' => $readiness->status(),
            ]);

            return back()->with('verification_email_failed', true);
        }

        $key = 'verification-resend:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['verification' => 'Limite atteinte : vous pourrez demander un nouvel e-mail dans une heure.']);
        }
        RateLimiter::hit($key, 3600);

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            Log::warning('Verification email delivery failed.', ['exception' => $exception::class]);

            return back()->with('verification_email_failed', true);
        }

        return back()->with('status', 'Le serveur SMTP a accepté la demande d’envoi. La réception dans votre boîte n’est pas confirmée; vérifiez aussi les courriers indésirables.');
    }
}
