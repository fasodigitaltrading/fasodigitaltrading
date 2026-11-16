@extends('layouts.app')

@section('content')
<div class="container admin-dashboard">
    <p class="brand-accent">CENTRE DE CONTRÔLE · FASO DIGITAL TRADING</p>
    <h1>Vue d’ensemble</h1>
    <p class="muted">Les compteurs et montants reflètent les données conservées dans cette base au moment du chargement.</p>
    <div class="admin-quick-actions">
        <a class="button button-small" href="{{ route('admin.manual-payments.index') }}">Vérifier les preuves ({{ number_format($pendingManualPaymentCount) }})</a>
        <a class="button button-small button-quiet" href="{{ route('admin.subscriptions.index') }}">Contrôler abonnements</a>
        <a class="button button-small button-quiet" href="{{ route('admin.commissions.index') }}">Commissions en attente ({{ number_format($pendingCommissionCount) }})</a>
        <a class="button button-small button-quiet" href="{{ route('admin.brvm-orders.index') }}">Demandes BRVM ({{ number_format($openBrvmOrderRequestCount) }})</a>
    </div>
    <div class="admin-metric-grid">
        <div class="card"><p class="muted">Investisseurs</p><h2>{{ number_format($investorCount) }}</h2><small>{{ number_format($newUserCount) }} nouveaux sur 30 jours</small></div>
        <div class="card"><p class="muted">Abonnements actifs</p><h2>{{ number_format($activeSubscriptionCount) }}</h2><small>{{ number_format($expiredSubscriptionCount) }} expirés</small></div>
        <div class="card"><p class="muted">Paiements en attente</p><h2>{{ number_format($pendingPaymentCount) }}</h2><small>{{ number_format($pendingManualPaymentCount) }} preuves manuelles à revoir</small></div>
        <div class="card"><p class="muted">Licences actives</p><h2>{{ number_format($activeLicenseCount) }}</h2><small>{{ number_format($expiredLicenseCount) }} expirées</small></div>
        <div class="card"><p class="muted">Comptes de trading</p><h2>{{ number_format($tradingAccountCount) }}</h2><small>Consultation MT5 en lecture seule</small></div>
        <div class="card"><p class="muted">Partenaires actifs</p><h2>{{ number_format($activePartnerCount) }}</h2><small>Commissions à suivre : {{ number_format($pendingCommissionCount) }}</small></div>
        <div class="card"><p class="muted">Utilisateurs suspendus</p><h2>{{ number_format($suspendedUserCount) }}</h2><small>{{ number_format($unverifiedUserCount) }} e-mails non vérifiés</small></div>
        <div class="card"><p class="muted">Contacts à traiter</p><h2>{{ number_format($openContactRequestCount) }}</h2><small>{{ number_format($recentAuditCount) }} actions admin sur 7 jours</small></div>
    </div>
    <section class="card" style="margin-top:18px"><h2>Paiements réels enregistrés</h2><p class="muted">Totaux séparés par devise et statut; remboursements et paiements en attente ne sont pas assimilés à des revenus.</p><div class="table-wrap"><table><thead><tr><th>Devise</th><th>Statut</th><th>Nombre</th><th>Montant enregistré</th></tr></thead><tbody>@forelse($paymentTotals as $total)<tr><td>{{ $total->currency }}</td><td>{{ $total->status }}</td><td>{{ number_format($total->count) }}</td><td>{{ number_format((float)$total->total,2,',',' ') }} {{ $total->currency }}</td></tr>@empty<tr><td colspan="4">Aucun paiement enregistré.</td></tr>@endforelse</tbody></table></div></section>
    <section class="card admin-service-status" style="margin-top:18px">
        <h2>État des services externes</h2>
        @if($errors->has('smtp'))<div class="form-alert" role="alert">{{ $errors->first('smtp') }}</div>@endif
        @if(session('smtp_test_accepted'))<div class="success-alert" role="status">Le transport SMTP a accepté le test vers {{ config('admin.email') }}. Cela ne confirme pas la réception dans la boîte.</div>@endif
        <dl><div><dt>Paiement en ligne</dt><dd>{{ $paymentProviderStatus }}</dd></div><div><dt>Paiement manuel</dt><dd>{{ $manualPaymentApprovalStatus }}</dd></div><div><dt>Conversion USD/XOF</dt><dd>{{ $fxRateStatus }}</dd></div><div><dt>Remboursements</dt><dd>{{ $refundWorkflowStatus }}</dd></div><div><dt>E-mails / SMTP</dt><dd>{{ $mailServiceStatus }}</dd></div><div><dt>MT5 / MetaApi</dt><dd>{{ $mt5ConnectorStatus }}</dd></div><div><dt>Graphiques TradingView</dt><dd>{{ $tradingViewStatus }}</dd></div><div><dt>Calendrier économique</dt><dd>{{ $economicCalendarStatus }}</dd></div><div><dt>Cotations BRVM</dt><dd>{{ $brvmDataStatus }}</dd></div><div><dt>Canal Coris Bourse</dt><dd>{{ $corisChannelStatus }}</dd></div><div><dt>BLACK WOLF AI</dt><dd>{{ $blackWolfAiStatus }}</dd></div><div><dt>GOLD INTELLIGENCE / XAUUSD</dt><dd>{{ $goldIntelligenceStatus }}</dd></div></dl>
        <form method="POST" action="{{ route('admin.services.smtp-test') }}" style="margin-top:12px">@csrf<button class="button button-small" type="submit">Tester le transport SMTP vers {{ config('admin.email') }}</button></form>
    </section>
    <div class="grid" style="margin-top:18px">
        <section class="card"><h2>Inscriptions récentes</h2>@forelse($recentUsers as $user)<p>{{ $user->first_name }} {{ $user->last_name }}<br><small class="muted">{{ $user->email }} · {{ $user->created_at?->format('d/m/Y H:i') }}</small></p>@empty<p class="muted">Aucune inscription.</p>@endforelse<a class="text-link" href="{{ route('admin.users.index') }}">Gestion des utilisateurs →</a></section>
        <section class="card"><h2>Derniers paiements</h2>@forelse($recentPayments as $payment)<p>{{ $payment->status }} · {{ number_format((float)$payment->amount,2,',',' ') }} {{ $payment->currency }}<br><small class="muted">{{ $payment->user?->email ?? 'Compte indisponible' }} · {{ $payment->created_at?->format('d/m/Y H:i') }}</small></p>@empty<p class="muted">Aucun paiement.</p>@endforelse<a class="text-link" href="{{ route('admin.payments.index') }}">Journal complet →</a></section>
    </div>
</div>
@endsection
