# Préparer l’envoi des e-mails avec Brevo SMTP

Le projet utilise déjà Laravel SMTP. Cette configuration choisit le relais SMTP Brevo; elle ne crée pas de compte Brevo, ne prouve pas la propriété d’une adresse et n’envoie aucun message tant que les paramètres secrets ne sont pas configurés.

## Étapes à réaliser par le titulaire du compte

1. Créer et vérifier un compte Brevo contrôlé par l’équipe Faso Digital Trading.
2. Activer l’envoi transactionnel si Brevo le demande.
3. Ajouter une adresse expéditrice vérifiée. Pour une meilleure délivrabilité, authentifier le domaine d’envoi et publier les enregistrements DNS demandés par Brevo (notamment DKIM et SPF). Il faut avoir accès à la gestion DNS du domaine.
4. Dans les paramètres SMTP Brevo, récupérer le **login SMTP** et générer une **clé SMTP**. La clé SMTP est distincte d’une clé API.
5. Ajouter les valeurs au gestionnaire de secrets de l’hébergement de production, ou au fichier `.env` local non versionné :

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=<login SMTP Brevo>
MAIL_PASSWORD=<clé SMTP Brevo>
MAIL_FROM_ADDRESS=<adresse expéditrice vérifiée>
MAIL_FROM_NAME="FASO DIGITAL TRADING"
MAIL_EHLO_DOMAIN=<domaine contrôlé par l’équipe, si disponible>
MAIL_LIVE_VERIFIED=false
```

Ne pas mettre de secret dans Git, dans un ticket ou dans cette conversation. Le fichier `.env.example` ne contient que les paramètres non secrets et des champs à compléter.

## Validation réelle

Après configuration, vider/reconstruire le cache de configuration Laravel selon la procédure de déploiement, puis utiliser le test SMTP réservé à l’administrateur. Il envoie un message de contrôle à l’adresse administrateur configurée. Vérifier sa réception réelle, y compris dans les indésirables. Le serveur qui accepte le message n’est pas une preuve de réception.

Garder `MAIL_LIVE_VERIFIED=false` tant que le test n’a pas été reçu et vérifié par l’équipe. Ce drapeau ne doit être activé que par l’exploitant après cette vérification. Tant que la configuration ou cette vérification manque, les inscriptions restent suspendues volontairement; aucune adresse ne sera présentée comme confirmée sans envoi réel.

## Références officielles

- [Configurer l’envoi transactionnel via le SMTP Brevo](https://help.brevo.com/hc/en-us/articles/7924908994450-Send-transactional-emails-using-Brevo-SMTP)
- [Dépanner le SMTP Brevo : identifiants et adresses expéditrices](https://help.brevo.com/hc/en-us/articles/115000188150-Troubleshooting-Issues-with-Brevo-SMTP)
