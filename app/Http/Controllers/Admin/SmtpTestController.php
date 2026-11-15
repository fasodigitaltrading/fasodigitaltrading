<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AdminSmtpTest;
use App\Support\EmailDeliveryReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SmtpTestController extends Controller
{
    public function send(Request $request, EmailDeliveryReadiness $readiness): RedirectResponse
    {
        $recipient = (string) config('admin.email');

        if (!$readiness->canAttemptTest()) {
            Log::notice('Administrator SMTP test blocked because configuration is incomplete.', [
                'actor_id' => $request->user()->id,
                'email_status' => $readiness->status(),
            ]);

            return back()->withErrors(['smtp' => 'Test impossible : complétez MAIL_HOST, MAIL_PORT, MAIL_FROM_ADDRESS et les identifiants SMTP dans l’environnement serveur. Aucun message n’a été envoyé.']);
        }

        try {
            Mail::to($recipient)->send(new AdminSmtpTest());
        } catch (Throwable $exception) {
            Log::warning('Administrator SMTP test failed.', [
                'actor_id' => $request->user()->id,
                'exception' => $exception::class,
            ]);

            return back()->withErrors(['smtp' => 'Échec du transport SMTP. Aucun message n’est déclaré envoyé. Vérifiez les paramètres et les journaux techniques.']);
        }

        Log::info('Administrator SMTP test accepted by configured mail transport.', [
            'actor_id' => $request->user()->id,
            'recipient' => $recipient,
        ]);

        return back()->with('smtp_test_accepted', true);
    }
}
