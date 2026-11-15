@extends('layouts.app')

@section('content')
<section class="page-hero"><div class="container"><span class="eyebrow">FASO DIGITAL TRADING</span><h1>Informations légales et confidentialité</h1><p>Version des conditions : {{ config('legal.terms_version') }} · Notice de contact : {{ config('legal.contact_notice_version') }}</p></div></section>
<section class="container" style="padding:30px 0 80px">
    @unless(config('legal.operator_name') && config('legal.operator_address') && config('legal.operator_email'))
        <div class="form-alert" role="alert"><strong>Informations exploitant à compléter.</strong> Le nom légal, l’adresse et le contact de l’exploitant ne sont pas configurés. Cette page doit être complétée et vérifiée avant commercialisation.</div>
    @endunless
    @unless(config('legal.disclosures_approved'))
        <div class="form-alert" role="alert"><strong>Conditions et notice à valider.</strong> Les règles de conservation, de remboursement et de règlement des litiges doivent être finalisées pour les marchés desservis avant d’activer les commandes.</div>
    @endunless
    <article class="card" id="mentions-legales">
        <h2>Mentions légales</h2>
        <p><strong>Éditeur du service :</strong> {{ config('legal.operator_name') ?: 'À renseigner par l’exploitant' }}</p>
        <p><strong>Adresse :</strong> {{ config('legal.operator_address') ?: 'À renseigner par l’exploitant' }}</p>
        <p><strong>Enregistrement :</strong> {{ config('legal.operator_registration') ?: 'À renseigner selon le statut et le pays de l’exploitant' }}</p>
        <p><strong>Contact :</strong> {{ config('legal.operator_email') ?: 'À configurer' }}</p>
    </article>

    <article class="card" id="conditions" style="margin-top:20px">
        <h2>Conditions d’utilisation — {{ config('legal.terms_version') }}</h2>
        <p>FASO DIGITAL TRADING fournit un site de consultation de formules, un espace client et, selon la configuration, des fonctions de gestion de licence et de lecture de compte. L’accès à une fonction dépend de son activation réelle.</p>
        <p>Le site ne garantit aucun résultat, ne fournit pas de prévision de rendement et ne constitue pas un conseil d’investissement. Le trading comporte un risque de perte totale ou partielle du capital. Chaque client reste responsable de ses décisions et de sa relation avec son broker.</p>
        <p>Les prix, durées et conditions doivent être confirmés avant toute commande. Le paiement en ligne n’est proposé que pour les offres compatibles avec un prestataire marchand réel et configuré. Une activation manuelle par l’administration ne change pas le statut du paiement et doit suivre une vérification du règlement.</p>
        <p>Si un transfert manuel est proposé, il est effectué hors de la plateforme vers le destinataire affiché. La transmission d’un reçu ne vaut pas confirmation du paiement : l’abonnement reste en attente jusqu’à la vérification de la réception réelle des fonds par l’administration. Aucun code PIN ou OTP ne doit être communiqué.</p>
        <p>Le programme de parrainage attribue une commission sur chaque paiement confirmé réalisé par un filleul, selon le taux affiché dans l’espace personnel. Les règles de validation, disponibilité et versement des commissions doivent être finalisées par l’exploitant avant commercialisation.</p>
        <p>Les règles de résiliation, remboursement, renouvellement et règlement des litiges doivent être complétées par l’exploitant selon les pays desservis avant ouverture commerciale.</p>
    </article>

    <article class="card" id="confidentialite" style="margin-top:20px">
        <h2>Notice de confidentialité — {{ config('legal.contact_notice_version') }}</h2>
        <p>Les formulaires et la création de compte enregistrent les coordonnées fournies et les éléments nécessaires au fonctionnement de l’espace. Lors d’une inscription, l’investisseur reçoit des messages de bienvenue et de vérification; une alerte limitée à son nom, son adresse e-mail, son pays et la date d’inscription est envoyée à l’adresse d’administration configurée.</p>
        <p>Lorsqu’un code de parrainage est utilisé, le lien entre le compte créé et le partenaire est conservé pour calculer les commissions issues des paiements confirmés. Les détails de paiement sont transmis au prestataire marchand uniquement si le paiement en ligne est activé.</p>
        <p>Pour un transfert manuel, le moyen choisi, le montant, la référence de transaction et le justificatif fourni sont enregistrés dans un stockage privé afin que l’administration puisse vérifier le règlement. La durée de conservation de ces justificatifs doit être définie par l’exploitant avant la mise en production.</p>
        <p>Le graphique de marché est fourni par un composant externe TradingView chargé dans le navigateur. Le navigateur communique alors directement avec TradingView pour afficher le graphique; des données techniques de connexion peuvent être traitées par ce fournisseur. Les cotations et délais affichés dépendent des marchés disponibles auprès de TradingView.</p>
        <p>Si un compte MT5 est effectivement relié, l’identifiant du fournisseur et un jeton dédié sont stockés; le jeton est chiffré dans la base. Les données de compte, positions et opérations ne sont collectées qu’à la suite d’une synchronisation réelle demandée sur un connecteur configuré.</p>
        <p>Les données servent à traiter les demandes et fournir les fonctions activées. Elles ne doivent pas être revendues. Les prestataires d’hébergement, de messagerie, de paiement ou de connexion MT5 ne sont sollicités que lorsque la fonction correspondante est configurée.</p>
        <p><strong>Durée de conservation, responsables de traitement, transferts éventuels et exercice des droits :</strong> ces modalités doivent être précisées par l’exploitant après choix de son pays, de son statut et de ses prestataires.</p>
        <p><strong>Contact confidentialité :</strong> {{ config('legal.privacy_email') ?: (config('legal.operator_email') ?: 'À configurer par l’exploitant') }}</p>
    </article>
</section>
@endsection
