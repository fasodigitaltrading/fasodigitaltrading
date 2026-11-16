<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BrvmPurchaseRequest;
use App\Models\Commission;
use App\Models\ContactRequest;
use App\Models\License;
use App\Models\ManualPaymentSubmission;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TradingAccount;
use App\Models\User;
use App\Services\Fx\FixedUsdXofRateService;
use App\Services\Mt5ConnectorService;
use App\Services\ManualPaymentReadiness;
use App\Services\Payments\PayDunyaProvider;
use App\Services\SubscriptionCatalogAvailability;
use App\Support\EmailDeliveryReadiness;
use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller
{
    public function index(
        SubscriptionCatalogAvailability $availability,
        EmailDeliveryReadiness $emailReadiness,
        FixedUsdXofRateService $fxRates,
        Mt5ConnectorService $mt5Connector,
        ManualPaymentReadiness $manualPaymentReadiness,
    ) {
        $plans = SubscriptionPlan::where('status', true)->whereIn('slug', SubscriptionPlan::PUBLIC_CATALOGUE_SLUGS)
            ->orderBy('sort_order')->get()->filter(fn (SubscriptionPlan $plan) => $plan->matchesApprovedPublicTerms())->values();
        $checkoutReadiness = $availability->forPlans($plans);
        $payDunyaIssues = app(PayDunyaProvider::class)->readinessIssues();
        $providerStatus = $checkoutReadiness['providerName'] === ''
            ? 'aucun prestataire de paiement en ligne activé; nouvelles commandes en USDT BEP20 uniquement'
            : ($checkoutReadiness['providerName'] === 'paydunya' && $payDunyaIssues !== []
                ? 'bloqué — '.implode(' ; ', $payDunyaIssues)
                : ($checkoutReadiness['checkoutPlanIds'] !== []
                    ? 'prestataire en ligne configuré pour '.count($checkoutReadiness['checkoutPlanIds']).' offre(s); aucun paiement crypto ne s’active sans vérification admin'
                    : 'aucune offre payable : vérifiez le taux fixe USD/XOF et les tarifs publiés'));

        $fx = $fxRates->latest();
        $fxStatus = $fx && $fx->canBeUsedForCheckout()
            ? 'taux fixe appliqué : 1 USD = '.$fx->rate.' XOF'
            : 'configuration du taux fixe USD/XOF invalide; les nouveaux paiements sont bloqués';
        $mailStatus = $emailReadiness->status();
        $mt5Status = $mt5Connector->isConfigured()
            ? 'endpoint MetaApi configuré; aucun test de connexion MT5 par compte n’a été lancé depuis le tableau de bord'
            : 'en attente — endpoint MetaApi HTTPS non configuré';

        $activeSubscriptions = Subscription::where('status', 'ACTIVE')
            ->where(fn ($query) => $query->whereNull('admin_override')->orWhere('admin_override', '!=', 'DISABLED'))
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));

        return view('admin.dashboard', [
            'investorCount' => User::where('role', 'INVESTOR')->count(),
            'newUserCount' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'unverifiedUserCount' => User::whereNull('email_verified_at')->count(),
            'suspendedUserCount' => User::where('status', 'SUSPENDED')->count(),
            'activeSubscriptionCount' => (clone $activeSubscriptions)->count(),
            'expiredSubscriptionCount' => Subscription::where('status', 'EXPIRED')->count(),
            'tradingAccountCount' => TradingAccount::count(),
            'activeLicenseCount' => License::where('status', 'ACTIVE')->count(),
            'expiredLicenseCount' => License::where('status', 'EXPIRED')->count(),
            'activePartnerCount' => Partner::where('status', 'ACTIVE')->count(),
            'openContactRequestCount' => ContactRequest::where('status', 'NEW')->count(),
            'openBrvmOrderRequestCount' => BrvmPurchaseRequest::whereIn('status', ['RECEIVED', 'FORWARDED', 'ACCEPTED'])->count(),
            'pendingPaymentCount' => Payment::where('status', 'PENDING')->count(),
            'pendingManualPaymentCount' => ManualPaymentSubmission::where('status', 'PENDING')->count(),
            'pendingCommissionCount' => Commission::where('status', 'PENDING')->count(),
            'paymentTotals' => Payment::query()->selectRaw('currency, status, COUNT(*) AS count, SUM(amount) AS total')
                ->groupBy('currency', 'status')->orderBy('currency')->orderBy('status')->get(),
            'paymentProviderStatus' => $providerStatus,
            'manualPaymentApprovalStatus' => $manualPaymentReadiness->isReady()
                ? 'USDT BEP20 uniquement; la preuve et la transaction doivent être vérifiées sur la blockchain par l’administrateur avant activation'
                : 'USDT BEP20 uniquement; confirmation bloquée jusqu’à la configuration sécurisée et aux mentions légales requises',
            'fxRateStatus' => $fxStatus,
            'refundWorkflowStatus' => 'aucun remboursement crypto automatisé; les retours éventuels sont traités manuellement après vérification',
            'mailServiceStatus' => $mailStatus,
            'mt5ConnectorStatus' => $mt5Status,
            'brvmDataStatus' => 'en attente — contrat de diffusion et accès officiel absents; aucun cours n’est importé',
            'corisChannelStatus' => config('brvm.coris_manual_channel_enabled')
                ? 'canal manuel déclaré — aucune API ni transmission automatique'
                : 'en attente — canal autorisé absent; aucune transmission active',
            'tradingViewStatus' => 'widget navigateur intégré; aucune garantie de temps réel ni validation de flux côté serveur',
            'economicCalendarStatus' => 'en attente — aucune source de calendrier configurée',
            'blackWolfAiStatus' => 'non développé dans ce projet',
            'goldIntelligenceStatus' => 'non développé; aucun modèle de prévision XAUUSD actif',
            'recentUsers' => User::latest()->limit(5)->get(['id', 'first_name', 'last_name', 'email', 'created_at']),
            'recentPayments' => Payment::with('user:id,first_name,last_name,email')->latest()->limit(5)->get(),
            'recentAuditCount' => DB::table('audit_logs')->where('created_at', '>=', now()->subDays(7))->count(),
        ]);
    }
}
