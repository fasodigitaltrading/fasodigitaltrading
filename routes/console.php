<?php

use App\Models\User;
use App\Services\Fx\BceaoReferenceRateService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('blackwolf:health', function () {
    $this->info('FASO DIGITAL TRADING application is available.');
})->purpose('Check that the application boots.');

Artisan::command('blackwolf:refresh-fx', function (BceaoReferenceRateService $rates) {
    $quote = $rates->refresh();
    if (!$quote) {
        $this->error('Aucune référence USD/XOF BCEAO valide n’est disponible. Aucun nouveau paiement ne doit utiliser un taux de remplacement.');
        return 1;
    }

    $this->line('USD/XOF BCEAO : '.$quote->rate.' publié le '.$quote->asOf->format('d/m/Y').'.');
    $this->line('Source : '.$quote->sourceUrl);
    $this->line('Récupération : '.$quote->fetchedAt->format('d/m/Y H:i:s').' UTC.');
    if (!$quote->canBeUsedForCheckout()) {
        $this->warn('Référence périmée ou cache marqué comme ancien : elle n’est pas utilisable pour un nouveau paiement.');
        return 1;
    }

    $this->info('Référence admissible pour un nouveau récapitulatif de paiement.');
    return 0;
})->purpose('Actualiser et vérifier le cours de référence quotidien BCEAO USD/XOF.');

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
