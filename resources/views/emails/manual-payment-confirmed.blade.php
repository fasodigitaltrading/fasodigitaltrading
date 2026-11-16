<h1>Paiement confirmé</h1>
<p>Bonjour {{ $submission->user?->first_name }},</p>
<p>L’administrateur a vérifié votre transfert sur BNB Smart Chain. Votre commande est confirmée et votre abonnement est maintenant activé.</p>
<ul>
    <li>Formule : {{ $submission->order?->plan_name_snapshot }}</li>
    <li>Montant confirmé : {{ number_format((float) $submission->expected_amount, 2, ',', ' ') }} {{ $submission->currency }}</li>
    <li>Hash de transaction : {{ $submission->verified_transaction_hash ?: $submission->transaction_reference }}</li>
    <li>Durée de l’offre : {{ $submission->order?->duration_days_snapshot ?? 30 }} jours</li>
</ul>
<p>Connectez-vous à votre espace pour consulter votre abonnement et sa licence.</p>
<p>FASO DIGITAL TRADING</p>
