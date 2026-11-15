@extends('layouts.app')
@section('content')
<div class="container investor-dashboard">
    <section class="investor-welcome">
        <div>
            <p class="eyebrow">FASO DIGITAL TRADING · ESPACE INVESTISSEUR</p>
            <h1>Bonjour {{ auth()->user()->first_name }}</h1>
            <p class="section-copy">Retrouvez vos formules, vos licences et les dernières données réellement reçues de vos comptes.</p>
        </div>
        <div class="investor-welcome-actions">
            <a class="button" href="{{ route('subscriptions') }}">Voir les formules</a>
            <a class="button button-quiet" href="{{ route('support.index') }}">Contacter le support</a>
        </div>
    </section>

    @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif

    <section class="investor-stats" aria-label="Résumé de votre espace">
        <article class="investor-stat"><span>Abonnements actifs</span><strong>{{ number_format($activeSubscriptionCount) }}</strong><small>selon leur statut et leur période</small></article>
        <article class="investor-stat"><span>Licences valides</span><strong>{{ number_format($activeLicenseCount) }}</strong><small>pour vos robots MT5</small></article>
        <article class="investor-stat"><span>Comptes rattachés</span><strong>{{ number_format($accounts->count()) }}</strong><small>lecture seule</small></article>
        <article class="investor-stat"><span>Positions ouvertes</span><strong>{{ number_format($openPositionCount) }}</strong><small>d’après la dernière synchronisation</small></article>
    </section>

    <nav class="investor-shortcuts" aria-label="Accès rapide à mon espace">
        <a href="#abonnements">Abonnements</a><a href="#licences">Licences</a><a href="#mt5">Comptes MT5</a><a href="#parrainage">Parrainage</a>
    </nav>

    <section class="investor-section" id="abonnements">
        <div class="investor-section-heading"><div><p class="eyebrow">OFFRES ET COMMANDES</p><h2>Mes abonnements</h2><p class="muted">Les paiements n’activent une formule qu’après confirmation réelle du prestataire.</p></div><a class="text-link" href="{{ route('subscriptions') }}">Parcourir les formules <span aria-hidden="true">↗</span></a></div>
        @if($subscriptions->isEmpty() && $orders->isEmpty())
            <div class="empty-state"><p>Aucun abonnement ni commande enregistrés.</p><a class="button button-small" href="{{ route('subscriptions') }}">Découvrir les formules</a></div>
        @endif
        @if($subscriptions->isNotEmpty())
            <div class="investor-subscription-grid">
                @foreach($subscriptions as $subscription)
                    @php
                        $subscriptionLabel = $subscription->admin_override === 'DISABLED'
                            ? 'Suspendu par l’administration'
                            : ($subscription->isOperationallyActive() ? 'Actif' : match ($subscription->status) {
                                'PENDING' => 'En attente', 'CANCELLED' => 'Annulé', 'EXPIRED' => 'Expiré',
                                default => $subscription->starts_at?->isFuture() ? 'À venir' : 'Expiré',
                            });
                    @endphp
                    <article class="card investor-subscription-card">
                        <div class="investor-card-top"><h3>{{ $subscription->plan_name_snapshot ?? $subscription->plan?->name ?? 'Formule' }}</h3><span class="state-pill {{ $subscription->isOperationallyActive() ? 'state-good' : 'state-muted' }}">{{ $subscriptionLabel }}</span></div>
                        <p class="muted">{{ number_format((float) ($subscription->plan_price_snapshot ?? $subscription->plan?->price ?? 0), 2, ',', ' ') }} {{ $subscription->plan_currency_snapshot ?? $subscription->plan?->currency ?? 'USD' }}</p>
                        <dl class="investor-detail-list">
                            <div><dt>Début</dt><dd>{{ $subscription->starts_at?->format('d/m/Y') ?? 'À confirmer' }}</dd></div>
                            <div><dt>Fin</dt><dd>{{ $subscription->ends_at?->format('d/m/Y') ?? 'Non définie' }}</dd></div>
                            <div><dt>Dernier paiement</dt><dd>{{ $subscription->latestPayment ? number_format((float) $subscription->latestPayment->amount, 0, ',', ' ').' '.$subscription->latestPayment->currency : 'Aucun' }}</dd></div>
                        </dl>
                    </article>
                @endforeach
            </div>
        @endif
        @if($orders->isNotEmpty())
            <h3 class="investor-subheading">Commandes récentes</h3>
            <div class="investor-order-list">
                @foreach($orders as $order)
                    <article class="investor-order-row">
                        <div><strong>{{ $order->plan_name_snapshot }}</strong><small>Créée le {{ $order->created_at?->format('d/m/Y à H:i') }}</small></div>
                        <div><span class="state-pill {{ $order->status === 'PAID' ? 'state-good' : 'state-muted' }}">{{ ['AWAITING_PAYMENT'=>'En attente de paiement','PAYMENT_FAILED'=>'Paiement à reprendre','PAID'=>'Payée','CANCELLED'=>'Annulée','EXPIRED'=>'Expirée'][$order->status] ?? $order->status }}</span><small>{{ number_format((float) $order->amount_usd, 2, ',', ' ') }} USD @if(!is_null($order->amount_xof)) · {{ number_format((int) $order->amount_xof, 0, ',', ' ') }} XOF @else · équivalence XOF indisponible @endif</small></div>
                        @if(in_array($order->status, ['AWAITING_PAYMENT', 'PAYMENT_FAILED'], true))<a class="text-link" href="{{ route('subscriptions.orders.show', $order) }}">Voir le récapitulatif</a>@endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card investor-referral-card" id="parrainage" style="margin:20px 0">
        <p class="brand-accent">PROGRAMME DE PARRAINAGE</p>
        <h2>Partagez votre lien et suivez vos commissions.</h2>
        @if($partner)
            <p>Votre code : <strong>{{ $partner->partner_code }}</strong> · Commission : <strong>{{ number_format((float) $partner->commission_rate, 2) }} %</strong></p>
            <p><a class="text-link" href="{{ route('register', ['ref' => $partner->partner_code]) }}">{{ route('register', ['ref' => $partner->partner_code]) }}</a></p>
            <p class="muted">Chaque paiement d’abonnement confirmé, y compris un renouvellement, crée une commission. Les commissions restent en attente pendant le délai de contrôle configuré.</p>
            <h3>Filleuls · {{ $partner->referrals_count }}</h3>
            @if($referrals?->isEmpty())
                <p class="muted">Aucune inscription parrainée pour le moment.</p>
            @else
                <div style="overflow-x:auto"><table style="width:100%;text-align:left;border-collapse:collapse"><thead><tr><th>Nom</th><th>Inscription</th></tr></thead><tbody>
                    @foreach($referrals as $referral)<tr><td>{{ $referral->user?->first_name }} {{ $referral->user?->last_name }}</td><td>{{ $referral->created_at?->format('d/m/Y') }}</td></tr>@endforeach
                </tbody></table></div>
                {{ $referrals->links() }}
            @endif
            <h3>Commissions</h3>
            @forelse($commissionTotals as $total)<p>Total {{ $total->currency }} : <strong>{{ number_format((float) $total->total, 2, '.', ',') }}</strong></p>@empty<p class="muted">Aucune commission comptabilisée.</p>@endforelse
            @if($commissions?->isNotEmpty())
                <div style="overflow-x:auto"><table style="width:100%;text-align:left;border-collapse:collapse"><thead><tr><th>Filleul</th><th>Abonnement</th><th>Base</th><th>Commission</th><th>État</th><th>Disponible le</th></tr></thead><tbody>
                    @foreach($commissions as $commission)
                        <tr><td>{{ $commission->user?->first_name }} {{ $commission->user?->last_name }}</td><td>{{ $commission->payment?->subscription?->plan_name_snapshot ?? $commission->payment?->subscription?->plan?->name ?? 'Paiement' }}</td><td>{{ number_format((float) ($commission->payment?->amount ?? 0), 2, ',', ' ') }} {{ $commission->currency }}</td><td>{{ number_format((float) $commission->amount, 2, ',', ' ') }} {{ $commission->currency }} · {{ number_format((float) $commission->percentage, 2, ',', ' ') }} %</td><td>{{ ['PENDING'=>'En attente','APPROVED'=>'Approuvée','PAID'=>'Payée','REVERSED'=>'Annulée'][$commission->status] ?? $commission->status }}</td><td>{{ $commission->available_at?->format('d/m/Y') ?? '—' }}</td></tr>
                    @endforeach
                </tbody></table></div>
                {{ $commissions->links() }}
            @endif
        @else
            <p class="muted">Créez un code personnel. Le taux appliqué sera de 10 % sur les paiements réellement confirmés des personnes inscrites avec votre lien.</p>
            <form method="POST" action="{{ route('investor.referral-code.store') }}">@csrf<button class="button" type="submit">Créer mon code de parrainage</button></form>
        @endif
    </section>

    <section class="investor-section" id="licences">
        <div class="investor-section-heading"><div><p class="eyebrow">ACCÈS AU ROBOT</p><h2>Mes licences</h2></div></div>
        @forelse($licenses as $license)
            <article class="card investor-license-card">
                <div class="investor-card-top"><h3>{{ $license->subscription?->plan_name_snapshot ?? $license->subscription?->plan?->name ?? 'Licence FASO DIGITAL TRADING' }}</h3><span class="state-pill {{ $license->status === 'ACTIVE' && (!$license->expires_at || $license->expires_at->isFuture()) ? 'state-good' : 'state-muted' }}">{{ $license->status === 'ACTIVE' && (!$license->expires_at || $license->expires_at->isFuture()) ? 'Valide' : (['EXPIRED'=>'Expirée','SUSPENDED'=>'Suspendue','REVOKED'=>'Révoquée'][$license->status] ?? $license->status) }}</span></div>
                <p class="muted">Expiration : {{ $license->expires_at?->format('d/m/Y H:i') ?? 'sans expiration définie' }} · Comptes autorisés : {{ $license->max_accounts }} · Comptes rattachés : {{ $license->account_bindings_count }}</p>
                @if($license->status === 'ACTIVE' && (!$license->expires_at || $license->expires_at->isFuture()) && $license->revealKey())
                    <label for="license-{{ $license->id }}">Clé de licence — à saisir dans le robot MT5</label><input id="license-{{ $license->id }}" value="{{ $license->revealKey() }}" readonly autocomplete="off">
                @endif
            </article>
        @empty
            <div class="empty-state">Aucune licence n’est associée à votre compte. Les licences sont créées après validation d’un abonnement.</div>
        @endforelse
    </section>

    <section class="investor-section" id="mt5">
        <div class="investor-section-heading"><div><p class="eyebrow">LECTURE SEULE</p><h2>Comptes MT5</h2></div></div>
        <div class="card investor-mt5-setup">
            @if(!$mt5ConnectorReady)
                <h3>Connexion MT5 en attente de configuration</h3>
                <p class="muted">Le connecteur MetaApi n’est pas encore disponible. Aucun jeton ne sera demandé ni enregistré tant que la connexion réelle ne sera pas configurée.</p>
            @elseif(!$canAttachAccount)
                <h3>Votre compte n’est pas encore prêt à être rattaché</h3>
                <p class="muted">Il faut une licence active avec un emplacement de compte disponible pour continuer.</p>
            @else
                <h3>Rattacher un compte en lecture seule</h3>
                <p class="muted">La plateforme lira les informations depuis MetaApi. Utilisez un jeton dédié à ce compte et autorisé en lecture seule. Le mot de passe principal MT5 n’est jamais demandé; le jeton est chiffré avant stockage.</p>
                <form method="POST" action="{{ route('investor.accounts.store') }}">@csrf
                    <label for="license-key">Clé de licence FASO DIGITAL TRADING</label><input id="license-key" name="license_key" required maxlength="100" autocomplete="off">
                    <label for="metaapi-account-id">Identifiant du compte MetaApi</label><input id="metaapi-account-id" name="provider_account_id" required maxlength="64" autocomplete="off">
                    <label for="metaapi-reader-token">Token MetaApi reader limité à ce compte</label><input id="metaapi-reader-token" type="password" name="account_access_token" required maxlength="5000" autocomplete="new-password">
                    <button class="button button-small" type="submit">Connecter en lecture seule</button>
                </form>
            @endif
        </div>
    </section>

    <section class="investor-section" id="comptes-rattaches">
    <div class="investor-section-heading"><div><p class="eyebrow">SUIVI DU COMPTE</p><h2>Comptes rattachés</h2></div></div>
    @forelse($accounts as $account)
        <section class="card" style="margin-top:16px">
            <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:center">
                <div><h2>{{ $account->account_number_masked }}</h2><p class="muted">{{ $account->server ?? 'Serveur indisponible' }} · {{ $account->currency }} · {{ ['CONNECTED'=>'Connecté en lecture seule','ERROR'=>'Erreur de synchronisation','DISCONNECTED'=>'Déconnecté'][$account->connection_status] ?? 'État en attente' }}</p></div>
                <form method="POST" action="{{ route('investor.accounts.sync', $account) }}">@csrf<button class="btn" type="submit">Actualiser</button></form>
            </div>
            <div class="grid">
                <div><p class="muted">Balance</p><h3>{{ !is_null($account->balance) ? number_format((float) $account->balance, 2).' '.$account->currency : '—' }}</h3></div>
                <div><p class="muted">Equity</p><h3>{{ !is_null($account->equity) ? number_format((float) $account->equity, 2).' '.$account->currency : '—' }}</h3></div>
                <div><p class="muted">Positions ouvertes</p><h3>{{ $account->openPositions->count() }}</h3></div>
                <div><p class="muted">Dernière synchronisation</p><h3>{{ $account->last_sync_at?->format('d/m/Y H:i') ?? '—' }}</h3></div>
            </div>

            <h3>Positions ouvertes</h3>
            @if($account->openPositions->isEmpty())
                <p class="muted">Aucune position ouverte.</p>
            @else
                <div style="overflow-x:auto"><table style="width:100%;text-align:left;border-collapse:collapse"><thead><tr><th>Symbole</th><th>Sens</th><th>Volume</th><th>Entrée</th><th>Prix courant</th><th>P/L flottant</th></tr></thead><tbody>
                    @foreach($account->openPositions as $position)<tr><td>{{ $position->symbol }}</td><td>{{ $position->type }}</td><td>{{ $position->volume }}</td><td>{{ $position->entry_price ?? '—' }}</td><td>{{ $position->current_price ?? '—' }}</td><td>{{ number_format((float) $position->floating_profit_loss, 2) }} {{ $account->currency }}</td></tr>@endforeach
                </tbody></table></div>
            @endif

            <h3 style="margin-top:24px">Dernières positions clôturées</h3>
            @if($account->positionSnapshots->isEmpty())
                <p class="muted">Aucune position clôturée enregistrée.</p>
            @else
                <div style="overflow-x:auto"><table style="width:100%;text-align:left;border-collapse:collapse"><thead><tr><th>Symbole</th><th>Sens</th><th>Volume</th><th>Dernier P/L flottant observé</th><th>Capture</th></tr></thead><tbody>
                    @foreach($account->positionSnapshots as $position)<tr><td>{{ $position->symbol }}</td><td>{{ $position->type }}</td><td>{{ $position->volume }}</td><td>{{ number_format((float) $position->floating_profit_loss, 2) }} {{ $account->currency }}</td><td>{{ $position->captured_at?->format('d/m/Y H:i') }}</td></tr>@endforeach
                </tbody></table></div>
            @endif

            <h3 style="margin-top:24px">Évolution récente du capital</h3>
            @if($account->performanceSnapshots->count() > 1)
                <svg viewBox="0 0 100 44" role="img" aria-label="Évolution récente de l’equity" style="width:100%;height:100px;background:#071121;border-radius:8px"><polyline points="{{ $charts[$account->id] }}" fill="none" stroke="#e5ad16" stroke-width="1.5" vector-effect="non-scaling-stroke" /></svg>
            @endif
            <div class="grid">
                @foreach($account->performanceSnapshots->sortBy('captured_at') as $snapshot)
                    <div><span class="muted">{{ $snapshot->captured_at->format('d/m H:i') }}</span><br>{{ number_format((float) $snapshot->equity, 2) }} {{ $account->currency }}<br><small class="muted">Drawdown {{ $snapshot->drawdown }} %</small></div>
                @endforeach
            </div>

            <h3 style="margin-top:24px">Historique récent des trades</h3>
            @if($account->trades->isEmpty())
                <p class="muted">Aucun trade historique sur la période chargée.</p>
            @else
                <div style="overflow-x:auto"><table style="width:100%;text-align:left;border-collapse:collapse"><thead><tr><th>Symbole</th><th>Sens</th><th>Volume</th><th>Entrée</th><th>Sortie</th><th>P/L réalisé</th></tr></thead><tbody>
                    @foreach($account->trades as $trade)<tr><td>{{ $trade->symbol }}</td><td>{{ $trade->type }}</td><td>{{ $trade->volume }}</td><td>{{ $trade->entry_price ?? '—' }}</td><td>{{ $trade->exit_price ?? 'Ouvert' }}</td><td>{{ number_format((float) $trade->profit_loss, 2) }} {{ $account->currency }}</td></tr>@endforeach
                </tbody></table></div>
            @endif
        </section>
    @empty
        <p class="muted">Aucun compte n’est encore rattaché.</p>
    @endforelse
    </section>
</div>
@endsection
