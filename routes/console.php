<?php

use App\Models\User;
use App\Services\Fx\FixedUsdXofRateService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('blackwolf:health', function () {
    $this->info('FASO DIGITAL TRADING application is available.');
})->purpose('Check that the application boots.');

Artisan::command('blackwolf:refresh-fx', function (FixedUsdXofRateService $rates) {
    $quote = $rates->refresh();
    if (!$quote->canBeUsedForCheckout() || $quote->amountXof('1.00') !== 600) {
        $this->error('Le taux fixe USD/XOF est invalide; les paiements sont bloqués.');
        return 1;
    }

    $this->info('Taux fixe de paiement : 1 USD = 600 XOF. Aucune source de change externe n’est consultée.');
    return 0;
})->purpose('Vérifier le taux fixe USD/XOF utilisé par les nouvelles commandes.');

Artisan::command('blackwolf:create-admin', function () {
    $email = (string) config('admin.email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->fail('ADMIN_EMAIL must contain a valid email address.');
    }
    if (User::whereRaw('LOWER(TRIM(email)) = ?', [$email])->exists()) {
        $this->fail('A user with this email already exists.');
    }
    if (User::where('role', 'ADMIN')->exists()) {
        $this->fail('An administrator account already exists. Back up the database and review it before changing administrator access.');
    }

    $password = (string) $this->secret('Administrator password (minimum 16 characters)');
    if (mb_strlen($password) < 16) {
        $this->fail('The administrator password must contain at least 16 characters.');
    }

    $admin = User::create([
        'first_name' => 'FASO',
        'last_name' => 'Administrator',
        'email' => $email,
        'password' => $password,
        'role' => 'ADMIN',
        'status' => 'ACTIVE',
    ]);
    $admin->forceFill(['email_verified_at' => now()])->save();

    $this->info('Administrator account created. Enroll an authenticator app during first sign-in.');
})->purpose('Create the first administrator using ADMIN_EMAIL without a shared password.');
