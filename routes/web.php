<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Investor\DashboardController as InvestorDashboard;
use App\Http\Controllers\Partner\DashboardController as PartnerDashboard;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\SubscriptionCheckoutController;
use App\Http\Controllers\SubscriptionOrderController;
use App\Http\Controllers\Admin\SmtpTestController;
use App\Http\Controllers\PublicSubscriptionController;
use App\Http\Controllers\PublicFxRateController;
use App\Http\Controllers\ManualPaymentSubmissionController;
use App\Http\Controllers\Admin\ManualPaymentController as AdminManualPaymentController;
use App\Http\Controllers\Admin\BrokerController;
use App\Http\Controllers\Investor\TradingAccountController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\ContactRequestController;
use App\Http\Controllers\Admin\ContactRequestController as AdminContactRequestController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Investor\ReferralCodeController;
use App\Http\Controllers\Investor\BrvmPurchaseController;
use App\Http\Controllers\Admin\BrvmOrderRequestController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\TradingAccountController as AdminTradingAccountController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Models\Broker;
use App\Models\SubscriptionPlan;
use App\Models\SiteSetting;
use App\Services\SubscriptionCatalogAvailability;
use App\Services\ManualPaymentReadiness;
Route::get('/', function (SubscriptionCatalogAvailability $availability, ManualPaymentReadiness $manualPayments) {
 $plans = SubscriptionPlan::where('status', true)->whereIn('slug', SubscriptionPlan::PUBLIC_CATALOGUE_SLUGS)
  ->orderBy('sort_order')->get()->filter(fn (SubscriptionPlan $plan) => $plan->matchesApprovedPublicTerms())->values();
 return view('public.home', [
 'headline' => SiteSetting::read('home_headline', 'Une plateforme claire pour gérer votre offre de trading.'),
 'description' => SiteSetting::read('home_description', 'Consultez les formules, échangez avec notre équipe et retrouvez vos informations dans un espace personnel.'),
 'plans' => $plans,
 ...$availability->forPlans($plans),
 'manualPaymentReady' => $manualPayments->isReady(),
 'selectedPackage' => request()->query('pack'),
 ]);
})->name('home');
Route::get('/subscriptions', [PublicSubscriptionController::class, 'index'])->name('subscriptions');
Route::get('/market/usd-xof', PublicFxRateController::class)->middleware('throttle:120,1')->name('fx.usd-xof');
Route::get('/brokers', fn()=>view('public.brokers', ['brokers' => Broker::where('status', true)->orderBy('sort_order')->orderBy('name')->get()]))->name('brokers');
Route::view('/legal', 'public.legal')->name('legal');
Route::post('/contact', [ContactRequestController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::middleware('guest')->group(function(){
 Route::get('/forgot-password',[PasswordResetController::class,'requestForm'])->name('password.request');
 Route::post('/forgot-password',[PasswordResetController::class,'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
 Route::get('/reset-password/{token}',[PasswordResetController::class,'resetForm'])->name('password.reset');
 Route::post('/reset-password',[PasswordResetController::class,'reset'])->middleware('throttle:5,1')->name('password.update');
 Route::get('/login',[LoginController::class,'show'])->name('login');
 Route::post('/login',[LoginController::class,'login'])->name('login.store');
 Route::get('/portail/investisseur',[LoginController::class,'show'])->defaults('portal','investisseur')->name('portal.investor.login');
 Route::post('/portail/investisseur',[LoginController::class,'login'])->defaults('portal','investisseur')->name('portal.investor.authenticate');
 Route::get('/portail/administrateur',[LoginController::class,'show'])->defaults('portal','administrateur')->name('portal.admin.login');
 Route::post('/portail/administrateur',[LoginController::class,'login'])->defaults('portal','administrateur')->name('portal.admin.authenticate');
 Route::get('/login/mfa',[LoginController::class,'showMfaChallenge'])->name('mfa.challenge');
 Route::post('/login/mfa',[LoginController::class,'verifyMfa'])->middleware('throttle:10,1')->name('mfa.verify');
 Route::get('/login/mfa/setup',[LoginController::class,'showMfaSetup'])->name('mfa.setup');
 Route::post('/login/mfa/setup',[LoginController::class,'verifyMfaSetup'])->middleware('throttle:10,1')->name('mfa.setup.verify');
 Route::get('/register',[RegisterController::class,'show'])->name('register');
 Route::post('/register',[RegisterController::class,'register'])->middleware('throttle:5,1')->name('register.store');
});
Route::post('/logout',[LoginController::class,'logout'])->middleware('auth')->name('logout');
Route::middleware(['auth','active','password.current'])->group(function(){
 Route::get('/email/verify',[EmailVerificationController::class,'notice'])->name('verification.notice');
 Route::get('/email/verify/{id}/{hash}',[EmailVerificationController::class,'verify'])->middleware(['signed','throttle:6,1'])->name('verification.verify');
 Route::post('/email/verification-notification',[EmailVerificationController::class,'resend'])->middleware('throttle:6,1')->name('verification.send');
 Route::post('/subscriptions/{plan}/prepare-before-verification',[SubscriptionOrderController::class,'prepareBeforeEmailVerification'])->middleware(['role:INVESTOR','throttle:5,1'])->name('subscriptions.orders.prepare');
});
Route::middleware(['auth','active','password.current','verified'])->group(function(){
 Route::get('/support',[SupportController::class,'index'])->name('support.index');
 Route::post('/support',[SupportController::class,'store'])->middleware('throttle:5,1')->name('support.store');
 Route::get('/support/{ticket}',[SupportController::class,'show'])->name('support.show');
 Route::post('/support/{ticket}/reply',[SupportController::class,'reply'])->middleware('throttle:10,1')->name('support.reply');
 Route::get('/notifications',[NotificationsController::class,'index'])->name('notifications.index');
 Route::post('/notifications/{notification}/read',[NotificationsController::class,'read'])->name('notifications.read');
 Route::middleware('role:INVESTOR')->group(function(){
  Route::post('/subscriptions/{plan}/orders',[SubscriptionOrderController::class,'store'])->middleware('throttle:5,1')->name('subscriptions.orders.store');
  Route::get('/subscriptions/orders/{order}',[SubscriptionOrderController::class,'show'])->name('subscriptions.orders.show');
  Route::post('/subscriptions/orders/{order}/refresh-quote',[SubscriptionOrderController::class,'refreshQuote'])->middleware('throttle:5,1')->name('subscriptions.orders.refresh');
  Route::post('/subscriptions/orders/{order}/manual-payment',[ManualPaymentSubmissionController::class,'store'])->middleware('throttle:5,1')->name('subscriptions.orders.manual-payment');
  Route::post('/subscriptions/orders/{order}/checkout',[SubscriptionOrderController::class,'checkout'])->middleware('throttle:5,1')->name('subscriptions.orders.checkout');
 });
 Route::post('/subscriptions/{plan}/checkout',[SubscriptionCheckoutController::class,'__invoke'])->middleware('throttle:5,1')->name('subscriptions.checkout');
 Route::get('/dashboard',function(){return match(auth()->user()->role){'ADMIN'=>redirect()->route('admin.dashboard'),'PARTNER'=>redirect()->route('partner.dashboard'),default=>redirect()->route('investor.dashboard')};})->name('dashboard');
 Route::get('/investor/dashboard',[InvestorDashboard::class,'index'])->middleware('role:INVESTOR')->name('investor.dashboard');
 Route::get('/investor/brvm',[BrvmPurchaseController::class,'index'])->middleware('role:INVESTOR')->name('investor.brvm.index');
 Route::post('/investor/brvm/orders',[BrvmPurchaseController::class,'store'])->middleware(['role:INVESTOR','throttle:5,1'])->name('investor.brvm.orders.store');
 Route::post('/investor/referral-code',[ReferralCodeController::class,'store'])->middleware(['role:INVESTOR','throttle:5,1'])->name('investor.referral-code.store');
 Route::post('/investor/accounts',[TradingAccountController::class,'store'])->middleware(['role:INVESTOR','throttle:5,1'])->name('investor.accounts.store');
 Route::post('/investor/accounts/{account}/sync',[TradingAccountController::class,'sync'])->middleware(['role:INVESTOR','throttle:15,1'])->name('investor.accounts.sync');
 Route::get('/partner/dashboard',[PartnerDashboard::class,'index'])->middleware('role:PARTNER,INVESTOR')->name('partner.dashboard');
 Route::get('/admin/dashboard',[AdminDashboard::class,'index'])->middleware('role:ADMIN')->name('admin.dashboard');
 Route::post('/admin/services/smtp-test',[SmtpTestController::class,'send'])->middleware(['role:ADMIN','throttle:1,5'])->name('admin.services.smtp-test');
 Route::middleware('role:ADMIN')->prefix('admin/brokers')->name('admin.brokers.')->group(function(){
  Route::get('/',[BrokerController::class,'index'])->name('index');
  Route::post('/',[BrokerController::class,'store'])->name('store');
  Route::put('/{broker}',[BrokerController::class,'update'])->name('update');
 });
 Route::middleware('role:ADMIN')->prefix('admin')->name('admin.')->group(function(){
  Route::get('/users',[UserManagementController::class,'index'])->name('users.index');
  Route::get('/users/{userId}',[UserManagementController::class,'show'])->whereNumber('userId')->name('users.show');
  Route::put('/users/{user}',[UserManagementController::class,'update'])->name('users.update');
  Route::delete('/users/{user}',[UserManagementController::class,'destroy'])->name('users.destroy');
  Route::post('/users/{userId}/restore',[UserManagementController::class,'restore'])->whereNumber('userId')->name('users.restore');
  Route::get('/payments',[PaymentController::class,'index'])->name('payments.index');
  Route::get('/partners',[PartnerController::class,'index'])->name('partners.index');
  Route::put('/partners/{partner}',[PartnerController::class,'update'])->name('partners.update');
  Route::get('/licenses',[LicenseController::class,'index'])->name('licenses.index');
  Route::post('/licenses/{license}/control',[LicenseController::class,'control'])->name('licenses.control');
  Route::get('/trading-accounts',[AdminTradingAccountController::class,'index'])->name('trading-accounts.index');
  Route::get('/reports',[ReportController::class,'index'])->name('reports.index');
  Route::get('/logs',[AuditLogController::class,'index'])->name('logs.index');
  Route::get('/notifications',[AdminNotificationController::class,'index'])->name('notifications.index');
  Route::get('/plans',[SubscriptionPlanController::class,'index'])->name('plans.index');
  Route::put('/plans/{plan}',[SubscriptionPlanController::class,'update'])->name('plans.update');
  Route::get('/subscriptions',[AdminSubscriptionController::class,'index'])->name('subscriptions.index');
  Route::post('/subscriptions/{subscription}/control',[AdminSubscriptionController::class,'control'])->name('subscriptions.control');
  Route::get('/manual-payments',[AdminManualPaymentController::class,'index'])->name('manual-payments.index');
  Route::get('/manual-payments/{submission}/proof',[AdminManualPaymentController::class,'proof'])->name('manual-payments.proof');
  Route::post('/manual-payments/{submission}/approve',[AdminManualPaymentController::class,'approve'])->middleware('throttle:10,1')->name('manual-payments.approve');
  Route::post('/manual-payments/{submission}/request-info',[AdminManualPaymentController::class,'requestMoreInfo'])->middleware('throttle:10,1')->name('manual-payments.request-info');
  Route::post('/manual-payments/{submission}/reject',[AdminManualPaymentController::class,'reject'])->middleware('throttle:10,1')->name('manual-payments.reject');
  Route::post('/manual-payments/{submission}/notify',[AdminManualPaymentController::class,'notify'])->middleware('throttle:1,5')->name('manual-payments.notify');
  Route::post('/manual-payments/{submission}/notify-customer',[AdminManualPaymentController::class,'notifyCustomer'])->middleware('throttle:1,5')->name('manual-payments.notify-customer');
  Route::get('/commissions',[CommissionController::class,'index'])->name('commissions.index');
  Route::post('/commissions/{commission}/control',[CommissionController::class,'control'])->name('commissions.control');
  Route::get('/settings',[SiteSettingsController::class,'index'])->name('settings.index');
  Route::put('/settings',[SiteSettingsController::class,'update'])->name('settings.update');
  Route::get('/support',[SupportController::class,'index'])->name('support.index');
  Route::put('/support/{ticket}',[SupportController::class,'adminUpdate'])->name('support.update');
  Route::post('/support/{ticket}/internal-note',[SupportController::class,'internalNote'])->name('support.internal-note');
  Route::post('/support/{ticket}/close',[SupportController::class,'close'])->name('support.close');
  Route::get('/contact-requests',[AdminContactRequestController::class,'index'])->name('contact-requests.index');
  Route::put('/contact-requests/{contactRequest}',[AdminContactRequestController::class,'update'])->name('contact-requests.update');
  Route::get('/brvm-orders',[BrvmOrderRequestController::class,'index'])->name('brvm-orders.index');
  Route::put('/brvm-orders/{brvmPurchaseRequest}',[BrvmOrderRequestController::class,'update'])->name('brvm-orders.update');
 });
});
