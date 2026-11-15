<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\NewRegistrationAdminAlert;
use App\Mail\WelcomeUser;
use App\Models\Partner;
use App\Models\Referral;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionOrderCreator;
use App\Support\EmailDeliveryReadiness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegisterController extends Controller
{
    public function show(Request $request)
    {
        return view('auth.register', [
            'ref' => $request->query('ref'),
            'pack' => $request->query('pack'),
            'registrationAvailable' => app(EmailDeliveryReadiness::class)->canSend(),
        ]);
    }

    public function register(Request $request, SubscriptionOrderCreator $orders)
    {
        $normalizedEmail = mb_strtolower(trim((string) $request->input('email')));
        $request->merge(['email' => $normalizedEmail]);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($normalizedEmail): void {
                    if (User::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->exists()) {
                        $fail('Cette adresse e-mail est déjà associée à un compte.');
                    }
                },
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'country' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'terms' => ['accepted'],
            'ref' => ['nullable', 'string', 'max:32', Rule::exists('partners', 'partner_code')->where('status', 'ACTIVE')],
            'pack' => ['nullable', 'string', 'max:80', Rule::in(SubscriptionPlan::PUBLIC_CATALOGUE_SLUGS)],
        ]);

        if (!empty($data['pack'])) {
            $selectedPlan = SubscriptionPlan::where('slug', $data['pack'])->where('status', true)->first();
            if (!$selectedPlan || !$selectedPlan->matchesApprovedPublicTerms()) {
                throw ValidationException::withMessages([
                    'pack' => 'Cette formule ne peut pas être commandée en ligne pour le moment. Choisissez une formule disponible dans le catalogue.',
                ]);
            }
        }

        if (!app(EmailDeliveryReadiness::class)->canSend()) {
            throw ValidationException::withMessages([
                'email_service' => 'L’inscription n’est pas disponible pour le moment. La confirmation par e-mail doit d’abord être configurée et testée; aucun compte n’a été créé.',
            ]);
        }

        try {
            [$user, $checkoutOrderId] = DB::transaction(function () use ($data, $orders): array {
                $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'country' => $data['country'],
                'password' => Hash::make($data['password']),
                'role' => 'INVESTOR',
                'status' => 'ACTIVE',
                'terms_accepted_at' => now(),
                'terms_version' => config('legal.terms_version'),
                ]);

                if (!empty($data['ref'])) {
                $partner = Partner::where('partner_code', $data['ref'])
                    ->where('status', 'ACTIVE')->first();
                if ($partner) {
                    Referral::create(['partner_id' => $partner->id, 'user_id' => $user->id]);
                }
                }

                $checkoutOrder = null;
                if (!empty($data['pack'])) {
                    $plan = SubscriptionPlan::where('slug', $data['pack'])->where('status', true)
                        ->whereIn('slug', SubscriptionPlan::PUBLIC_CATALOGUE_SLUGS)->firstOrFail();
                    $checkoutOrder = $orders->create($user, $plan, (string) Str::uuid());
                }

                return [$user, $checkoutOrder?->id];
            });
        } catch (QueryException $exception) {
            if (User::whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])->exists()) {
                throw ValidationException::withMessages(['email' => 'Cette adresse e-mail est déjà associée à un compte.']);
            }

            throw $exception;
        }

        $emailReady = app(EmailDeliveryReadiness::class)->canSend();
        $verificationEmailFailed = !$emailReady;
        if ($emailReady) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (Throwable $exception) {
                $verificationEmailFailed = true;
                Log::warning('Registration verification email delivery failed.', ['exception' => $exception::class]);
            }
        }

        $welcomeEmailFailed = !$emailReady;
        if ($emailReady) {
            try {
                Mail::to($user->email)->send(new WelcomeUser($user));
            } catch (Throwable $exception) {
                $welcomeEmailFailed = true;
                Log::warning('Registration welcome email delivery failed.', ['exception' => $exception::class]);
            }
        }

        $adminEmailFailed = !$emailReady;
        if ($emailReady) {
            try {
                Mail::to((string) config('admin.email'))->send(new NewRegistrationAdminAlert($user));
            } catch (Throwable $exception) {
                $adminEmailFailed = true;
                Log::warning('Registration admin notification delivery failed.', ['exception' => $exception::class]);
            }
        }

        auth()->login($user);
        $request->session()->regenerate();
        $request->session()->put('auth.password_revision', (int) $user->password_revision);
        if ($checkoutOrderId !== null) {
            $request->session()->put('checkout.order_id', $checkoutOrderId);
        }

        return redirect()->route('verification.notice')
            ->with('verification_email_failed', $verificationEmailFailed)
            ->with('welcome_email_failed', $welcomeEmailFailed)
            ->with('admin_notification_failed', $adminEmailFailed);
    }
}
