@extends('layouts.app')

@section('content')
<div class="container">
    <p class="brand-accent">FASO DIGITAL TRADING · ADMINISTRATION</p>
    <h1>Vérification des paiements manuels</h1>
    <p class="muted">Ne validez un justificatif qu’après avoir confirmé la réception des fonds dans le portefeuille ou sur le réseau concerné. Un reçu seul ne prouve pas le paiement.</p>
    @if(session('status'))<div class="success-alert" role="status">{{ session('status') }}</div>@endif
    @if(session('email_status'))<div class="form-alert" role="alert">{{ session('email_status') }}</div>@endif
    @if($errors->any())<div class="form-alert" role="alert">{{ $errors->first() }}</div>@endif
    <div class="plan-notice" role="status">E-mail administrateur : {{ $emailReadinessStatus }}. « Transport accepté » signifie que le serveur SMTP a accepté l’envoi; cela ne confirme pas la réception dans la boîte.</div>
    @unless($canApprove)
        <div class="form-alert" role="alert">La configuration de sécurité du serveur doit être vérifiée avant de pouvoir confirmer un paiement et activer l’abonnement.</div>
    @endunless

    <form method="GET" class="card" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap;margin:20px 0">
        <label for="manual-payment-status">Statut</label>
        <select id="manual-payment-status" name="status" style="min-width:180px">
            <option value="">Tous</option>
            @foreach(['PENDING' => 'À vérifier', 'INFO_REQUIRED' => 'Information demandée', 'APPROVED' => 'Confirmés', 'REJECTED' => 'Refusés'] as $value => $label)
                <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="button button-small" type="submit">Filtrer</button>
    </form>

    <div style="display:grid;gap:18px">
        @forelse($submissions as $submission)
            <article class="card">
                <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap">
                    <div>
                        <h2 style="margin:0 0 6px">{{ $submission->user?->first_name }} {{ $submission->user?->last_name }}</h2>
                        <p class="muted" style="margin:0">{{ $submission->user?->email }} · Commande #{{ $submission->subscription_order_id }} · {{ $submission->order?->plan_name_snapshot }} · {{ number_format((float)($submission->order?->amount_usd ?? 0),2,',',' ') }} USD · taux {{ $submission->order?->fx_rate ?? 'absent' }} {{ $submission->order?->fx_provider ?? '' }}@if($submission->order?->fx_rate_as_of) · publié {{ $submission->order->fx_rate_as_of->format('d/m/Y') }}@endif</p>
                    </div>
                    <strong>{{ ['PENDING'=>'À VÉRIFIER','INFO_REQUIRED'=>'INFORMATION DEMANDÉE','APPROVED'=>'CONFIRMÉ','REJECTED'=>'REFUSÉ'][$submission->status] ?? $submission->status }}</strong>
                </div>
                <dl class="order-details" style="margin-top:16px">
                    <div><dt>Moyen</dt><dd>{{ $methodLabels[$submission->method] ?? $submission->method }}</dd></div>
                    <div><dt>Destinataire</dt><dd><code>{{ $submission->recipient_snapshot }}</code></dd></div>
                    <div><dt>Montant attendu</dt><dd>{{ number_format((float) $submission->expected_amount, $submission->currency === 'XOF' ? 0 : 2, ',', ' ') }} {{ $submission->currency }}</dd></div>
                    <div><dt>Référence de demande</dt><dd><code>{{ $submission->transaction_reference }}</code> · identifiant interne, pas un hash blockchain</dd></div>
                    <div><dt>Reçu le</dt><dd>{{ $submission->created_at->format('d/m/Y H:i:s') }} · délai cible informatif : 30 min
                        @if($submission->status === 'PENDING')<br><strong>Priorité de traitement</strong> · <span data-review-countdown data-deadline-ms="{{ $submission->created_at->copy()->addMinutes(30)->getTimestamp() * 1000 }}">Objectif indicatif {{ $submission->created_at->copy()->addMinutes(30)->format('H:i') }}</span>@endif
                    </dd></div>
                    @if($submission->verified_transaction_hash)<div><dt>Hash BSC vérifié</dt><dd><a href="{{ rtrim($crypto['explorer_url'] ?? 'https://bscscan.com', '/') }}/tx/{{ $submission->verified_transaction_hash }}" target="_blank" rel="noopener noreferrer"><code>{{ $submission->verified_transaction_hash }}</code></a></dd></div>@endif
                    @if($submission->verified_token_contract)<div><dt>Contrat vérifié</dt><dd><code>{{ $submission->verified_token_contract }}</code></dd></div>@endif
                    @if($submission->verified_confirmations)<div><dt>Confirmations vérifiées</dt><dd>{{ number_format($submission->verified_confirmations) }}</dd></div>@endif
                    <div><dt>Notification e-mail</dt><dd>{{ ['SENT'=>'acceptée par le transport SMTP; réception à confirmer','FAILED'=>'échec; nouvelle tentative possible','BLOCKED'=>'en attente de configuration SMTP','SENDING'=>'envoi en cours','NOT_SENT'=>'non envoyée'][$submission->admin_email_status] ?? $submission->admin_email_status }}@if($submission->admin_email_last_attempt_at) · tentative {{ $submission->admin_email_last_attempt_at->format('d/m/Y H:i:s') }}@endif</dd></div>
                    @if($submission->status === 'APPROVED')<div><dt>Courriel client</dt><dd>{{ ['SENT'=>'accepté par le transport SMTP; réception à confirmer','FAILED'=>'échec; nouvel envoi disponible','BLOCKED'=>'en attente de configuration SMTP','SENDING'=>'envoi en cours','NOT_SENT'=>'non envoyé'][$submission->customer_email_status] ?? $submission->customer_email_status }}</dd></div>@endif
                    <div><dt>Reconnaissance client</dt><dd>@if($submission->customer_acknowledged_at) Acceptée le {{ $submission->customer_acknowledged_at->format('d/m/Y H:i:s') }} · {{ $submission->customer_terms_version }} @else Non enregistrée @endif</dd></div>
                    <div><dt>Preuve</dt><dd><a class="text-link" href="{{ route('admin.manual-payments.proof', $submission) }}">Télécharger le justificatif privé</a></dd></div>
                    @if($submission->reviewed_at)<div><dt>Contrôle</dt><dd>{{ $submission->reviewer?->first_name }} · {{ $submission->reviewed_at->format('d/m/Y H:i') }} · {{ $submission->review_note }}</dd></div>@endif
                </dl>

                @if($submission->status === 'PENDING')
                    @if($submission->admin_email_status !== 'SENT')
                        <form method="POST" action="{{ route('admin.manual-payments.notify', $submission) }}" style="margin-top:16px">
                            @csrf
                            <button class="button button-quiet" type="submit" @disabled(!$canNotify)>{{ $submission->admin_email_status === 'SENT' ? 'Renvoyer la notification administrateur' : 'Réessayer la notification administrateur' }}</button>
                            @unless($canNotify)<p class="field-help">Le renvoi restera désactivé jusqu’à la configuration et à la validation réelle du transport SMTP.</p>@endunless
                        </form>
                    @endif
                    <div class="grid" style="margin-top:18px;align-items:start">
                        <form method="POST" action="{{ route('admin.manual-payments.approve', $submission) }}" class="card">
                            @csrf
                            <h3>Vérification et confirmation</h3>
                            @if($submission->method === 'usdt_bep20')
                                <p>Ouvrez le hash sur l’explorateur BNB Smart Chain. Vérifiez qu’il s’agit d’un transfert réussi du jeton exact, vers l’adresse exacte, pour le montant exact, avec au moins {{ $crypto['minimum_confirmations'] ?? 15 }} confirmations. Le reçu ne suffit pas.</p>
                                <p><a href="{{ rtrim($crypto['explorer_url'] ?? 'https://bscscan.com', '/') }}/address/{{ $submission->recipient_snapshot }}" target="_blank" rel="noopener noreferrer">Ouvrir le portefeuille sur BscScan</a></p>
                                <label for="verified-hash-{{ $submission->id }}">Hash BSC trouvé dans l’historique du portefeuille</label>
                                <input id="verified-hash-{{ $submission->id }}" type="text" name="verified_transaction_hash" pattern="0x[a-fA-F0-9]{64}" maxlength="66" required>
                                <label for="verified-contract-{{ $submission->id }}">Contrat observé dans le transfert</label>
                                <input id="verified-contract-{{ $submission->id }}" type="text" name="verified_token_contract" value="{{ $crypto['token_contract'] ?? '' }}" pattern="0x[a-fA-F0-9]{40}" maxlength="42" required>
                                <small class="field-help">Contrat accepté : {{ $crypto['symbol'] ?? 'BSC-USD' }}</small>
                                <label for="verified-recipient-{{ $submission->id }}">Adresse destinataire observée</label>
                                <input id="verified-recipient-{{ $submission->id }}" type="text" name="verified_recipient" value="{{ $submission->recipient_snapshot }}" pattern="0x[a-fA-F0-9]{40}" maxlength="42" required>
                                <label for="verified-confirmations-{{ $submission->id }}">Nombre de confirmations visibles</label>
                                <input id="verified-confirmations-{{ $submission->id }}" type="number" name="verified_confirmations" min="{{ $crypto['minimum_confirmations'] ?? 15 }}" max="999999" step="1" required>
                            @endif
                            <label for="verified-amount-{{ $submission->id }}">Montant réellement reçu ({{ $submission->currency }})</label>
                            <input id="verified-amount-{{ $submission->id }}" type="number" name="verified_amount" min="0" step="0.01" required>
                            @if($submission->method === 'usdt_bep20')
                                <label style="display:flex;gap:8px;align-items:start;margin:12px 0"><input type="checkbox" name="confirm_token_contract" value="yes" required>J’ai vérifié que le contrat correspond au jeton Binance-Peg BSC-USD configuré.</label>
                                <label style="display:flex;gap:8px;align-items:start;margin:12px 0"><input type="checkbox" name="confirm_recipient" value="yes" required>J’ai vérifié que l’événement de transfert crédite exactement l’adresse FASO DIGITAL TRADING ci-dessus.</label>
                            @endif
                            <label style="display:flex;gap:8px;align-items:start;margin:12px 0"><input type="checkbox" name="confirm_funds" value="yes" required>@if($submission->method === 'usdt_bep20') J’ai vérifié sur BNB Smart Chain que le hash correspond à une transaction USDT BEP20 réussie vers le destinataire affiché et que le montant reçu est exact.@else J’ai vérifié que les fonds sont réellement arrivés et que le montant reçu est exact.@endif</label>
                            <label for="approve-note-{{ $submission->id }}">Note d’audit</label>
                            <textarea id="approve-note-{{ $submission->id }}" name="review_note" minlength="10" maxlength="500" required>{{ old('review_note') }}</textarea>
                            <button class="button" type="submit" style="margin-top:10px" @disabled(!$canApprove)>Confirmer le paiement</button>
                        </form>
                        <form method="POST" action="{{ route('admin.manual-payments.request-info', $submission) }}" class="card">
                            @csrf
                            <h3>Demander une nouvelle capture</h3>
                            <label for="info-note-{{ $submission->id }}">Information manquante ou problème détecté</label>
                            <textarea id="info-note-{{ $submission->id }}" name="review_note" minlength="10" maxlength="500" required></textarea>
                            <button class="button button-quiet" type="submit" style="margin-top:10px">Demander une information</button>
                        </form>
                        <form method="POST" action="{{ route('admin.manual-payments.reject', $submission) }}" class="card">
                            @csrf
                            <h3>Refuser le justificatif</h3>
                            <label for="reject-note-{{ $submission->id }}">Motif à transmettre au client</label>
                            <textarea id="reject-note-{{ $submission->id }}" name="review_note" minlength="10" maxlength="500" required></textarea>
                            <button class="button button-quiet" type="submit" style="margin-top:10px">Refuser le justificatif</button>
                        </form>
                    </div>
                @endif
                @if($submission->status === 'APPROVED' && $submission->customer_email_status !== 'SENT')
                    <form method="POST" action="{{ route('admin.manual-payments.notify-customer', $submission) }}" style="margin-top:16px">
                        @csrf
                        <button class="button button-quiet" type="submit" @disabled(!$canNotify)>Réessayer le courriel de confirmation client</button>
                        @unless($canNotify)<p class="field-help">Le service SMTP n’est pas prêt; l’abonnement est déjà activé.</p>@endunless
                    </form>
                @endif
            </article>
        @empty
            <div class="plan-notice">Aucun justificatif ne correspond à ce filtre.</div>
        @endforelse
    </div>
    <div style="margin:18px 0">{{ $submissions->links() }}</div>
</div>
@endsection
@push('scripts')
    @if(file_exists(public_path('build/manifest.json')))
        @vite('resources/js/app.js')
    @endif
@endpush
