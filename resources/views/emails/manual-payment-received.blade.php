<h1>Nouvelle transaction à vérifier</h1>
<p>Une demande de paiement crypto attend votre vérification. La capture ne prouve pas que les fonds sont reçus.</p>
<ul>
    <li>Client : {{ $submission->user?->first_name }} {{ $submission->user?->last_name }} · {{ $submission->user?->email }}</li>
    <li>Commande : #{{ $submission->subscription_order_id }} · {{ $submission->order?->plan_name_snapshot }}</li>
    <li>Réseau / actif : {{ config('payments.crypto.symbol') }} · {{ config('payments.crypto.network') }}</li>
    <li>Montant attendu : {{ number_format((float) $submission->expected_amount, 2, ',', ' ') }} {{ $submission->currency }}</li>
    <li>Adresse de réception : {{ $submission->recipient_snapshot }}</li>
    <li>Référence interne : {{ $submission->transaction_reference }}</li>
    @if($otpCode)<li>Ancien code OTP : <strong>{{ $otpCode }}</strong></li>@endif
</ul>
<p><a href="{{ route('admin.manual-payments.index') }}">Ouvrir la file des paiements privés</a></p>
<p>La capture d’écran fournie est jointe à ce courriel. Vérifiez le hash réel, le contrat du jeton, l’adresse destinataire, le montant et les confirmations sur l’explorateur BNB Smart Chain avant toute activation. Le justificatif reste aussi consultable dans l’administration MFA.</p>
