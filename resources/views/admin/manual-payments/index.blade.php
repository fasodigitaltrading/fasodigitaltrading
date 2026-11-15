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
            @foreach(['PENDING' => 'À vérifier', 'APPROVED' => 'Confirmés', 'REJECTED' => 'Refusés'] as $value => $label)
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
                    <strong>{{ ['PENDING'=>'À VÉRIFIER','APPROVED'=>'CONFIRMÉ','REJECTED'=>'REFUSÉ'][$submission->status] ?? $submission->status }}</strong>
                </div>
                <dl class="order-details" style="margin-top:16px">
                    <div><dt>Moyen</dt><dd>{{ $methodLabels[$submission->method] ?? $submission->method }}</dd></div>
                    <div><dt>Destinataire</dt><dd><code>{{ $submission->recipient_snapshot }}</code></dd></div>
                    <div><dt>Montant attendu</dt><dd>{{ number_format((float) $submission->expected_amount, $submission->currency === 'XOF' ? 0 : 2, ',', ' ') }} {{ $submission->currency }}</dd></div>
                    <div><dt>Montant déclaré</dt><dd>{{ $submission->declared_amount !== null ? number_format((float) $submission->declared_amount, $submission->currency === 'XOF' ? 0 : 2, ',', ' ').' '.$submission->currency : 'Non renseigné' }}</dd></div>
                    <div><dt>Référence</dt><dd><code>{{ $submission->transaction_reference }}</code></dd></div>
                    <div><dt>Date du transfert déclarée</dt><dd>{{ $submission->transaction_occurred_at?->format('d/m/Y H:i') ?? 'Non renseignée' }}</dd></div>
                    <div><dt>Reçu le</dt><dd>{{ $submission->created_at->format('d/m/Y H:i:s') }}</dd></div>
                    <div><dt>Code de suivi</dt><dd>#{{ $submission->manual_payment_otp_id ?? '—' }} · le code n’est jamais affiché dans cette page</dd></div>
                    <div><dt>Notification e-mail</dt><dd>{{ ['SENT'=>'acceptée par le transport SMTP; réception à confirmer','FAILED'=>'échec; nouvelle tentative possible','BLOCKED'=>'en attente de configuration SMTP','SENDING'=>'envoi en cours','NOT_SENT'=>'non envoyée'][$submission->admin_email_status] ?? $submission->admin_email_status }}@if($submission->admin_email_last_attempt_at) · tentative {{ $submission->admin_email_last_attempt_at->format('d/m/Y H:i:s') }}@endif</dd></div>
                    <div><dt>Reconnaissance client</dt><dd>@if($submission->customer_acknowledged_at) Acceptée le {{ $submission->customer_acknowledged_at->format('d/m/Y H:i:s') }} · {{ $submission->customer_terms_version }} @else Non enregistrée @endif</dd></div>
                    <div><dt>Preuve</dt><dd><a class="text-link" href="{{ route('admin.manual-payments.proof', $submission) }}">Télécharger le justificatif privé</a></dd></div>
                    @if($submission->reviewed_at)<div><dt>Contrôle</dt><dd>{{ $submission->reviewer?->first_name }} · {{ $submission->reviewed_at->format('d/m/Y H:i') }} · {{ $submission->review_note }}</dd></div>@endif
                </dl>

                @if($submission->status === 'PENDING')
                    @if($submission->admin_email_status !== 'SENT' && $submission->otp?->code_ciphertext)
                        <form method="POST" action="{{ route('admin.manual-payments.notify', $submission) }}" style="margin-top:16px">
                            @csrf
                            <button class="button button-quiet" type="submit" @disabled(!$canNotify)>{{ $submission->admin_email_status === 'SENT' ? 'Renvoyer la notification administrateur' : 'Réessayer la notification administrateur' }}</button>
                            @unless($canNotify)<p class="field-help">Le renvoi restera désactivé jusqu’à la configuration et à la validation réelle du transport SMTP.</p>@endunless
                        </form>
                    @endif
                    <div class="grid" style="margin-top:18px;align-items:start">
                        <form method="POST" action="{{ route('admin.manual-payments.approve', $submission) }}" class="card">
                            @csrf
                            <h3>Confirmer la réception</h3>
                            <label for="verified-amount-{{ $submission->id }}">Montant réellement reçu ({{ $submission->currency }})</label>
                            <input id="verified-amount-{{ $submission->id }}" type="number" name="verified_amount" min="0" step="0.01" required>
                            <label style="display:flex;gap:8px;align-items:start;margin:12px 0"><input type="checkbox" name="confirm_funds" value="yes" required> J’ai vérifié que les fonds sont réellement arrivés et que le montant reçu est exact.</label>
                            <label for="approve-note-{{ $submission->id }}">Note d’audit</label>
                            <textarea id="approve-note-{{ $submission->id }}" name="review_note" minlength="10" maxlength="500" required></textarea>
                            <button class="button" type="submit" style="margin-top:10px" @disabled(!$canApprove)>Confirmer le paiement</button>
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
            </article>
        @empty
            <div class="plan-notice">Aucun justificatif ne correspond à ce filtre.</div>
        @endforelse
    </div>
    <div style="margin:18px 0">{{ $submissions->links() }}</div>
</div>
@endsection
