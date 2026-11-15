<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\PaymentWebhookController;
Route::post('/robot/license/validate',[LicenseController::class,'validateLicense'])->middleware('throttle:30,1');
Route::post('/payments/webhooks/{providerName}',PaymentWebhookController::class)->middleware('throttle:120,1')->name('payments.webhook');
