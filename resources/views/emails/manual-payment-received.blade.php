<h1>Nouvelle transaction à vérifier</h1>
<p>Une demande de paiement manuel attend votre vérification. Le code de transaction associe le transfert à la commande; il ne prouve pas que les fonds sont reçus.</p>
<ul>
    <li>Code OTP de transaction : <strong>{{ $otpCode }}</strong></li>
    <li>Client : {{ $submission->user?->first_name }} {{ $submission->user?->last_name }} · {{ $submission->user?->email }}</li>
    <li>Commande : #{{ $submission->subscription_order_id }} · {{ $submission->order?->plan_name_snapshot }}</li>
    <li>Moyen : {{ config('payments.manual_methods.'.$submission->method.'.label', $submission->method) }}</li>
    <li>Montant attendu : {{ number_format((float) $submission->expected_amount, 2, ',', ' ') }} {{ $submission->currency }}</li>
    <li>Montant déclaré : {{ number_format((float) $submission->declared_amount, 2, ',', ' ') }} {{ $submission->currency }}</li>
    <li>Référence opérateur : {{ $submission->transaction_reference }}</li>
    <li>Date déclarée du transfert : {{ $submission->transaction_occurred_at?->format('d/m/Y H:i') }}</li>
</ul>
<p><a href="{{ route('admin.manual-payments.index') }}">Ouvrir la file des paiements privés</a></p>
<p>Vérifiez vous-même la réception dans le compte marchand ou sur le réseau concerné avant toute activation. Le justificatif n’est pas joint à cet e-mail et reste consultable dans l’administration MFA.</p>
