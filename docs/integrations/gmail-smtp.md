# Utiliser Gmail SMTP pour les e-mails transactionnels

Cette option réutilise le compte `fasodigitaltrading@gmail.com` et ne demande pas de domaine personnalisé. Elle est adaptée à un faible volume de messages transactionnels; elle ne remplace pas un service d’e-mail transactionnel lorsque le nombre d’inscriptions augmente.

## Configuration à faire par le titulaire du compte

1. Activer la validation en deux étapes sur le compte Google.
2. Ouvrir la section officielle « Mots de passe des applications » et créer un mot de passe pour le serveur FASO DIGITAL TRADING. Google peut masquer cette option selon les règles de sécurité du compte, la protection avancée ou la politique d’un compte d’organisation.
3. Ajouter les réglages suivants dans les secrets de l’hébergement de production ou dans le `.env` local non versionné :

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=fasodigitaltrading@gmail.com
MAIL_PASSWORD=<mot de passe d’application Google>
MAIL_FROM_ADDRESS=fasodigitaltrading@gmail.com
MAIL_FROM_NAME="FASO DIGITAL TRADING"
MAIL_LIVE_VERIFIED=false
```

Ne jamais utiliser le mot de passe principal Google. Ne pas placer le mot de passe d’application dans Git, un ticket ou cette conversation. Google indique que les mots de passe d’application nécessitent la validation en deux étapes et qu’ils sont révoqués si le mot de passe Google principal est modifié.

## Test d’envoi

Après configuration, actualiser le cache Laravel selon la procédure de déploiement, puis lancer le test SMTP depuis l’espace administrateur. Confirmer la réception du message dans `fasodigitaltrading@gmail.com`, y compris les indésirables. Garder `MAIL_LIVE_VERIFIED=false` tant que la réception réelle n’a pas été vérifiée par l’équipe. L’acceptation SMTP seule ne prouve pas la réception.

Gmail limite l’envoi des comptes grand public; la documentation mentionne un blocage temporaire si l’on dépasse 500 e-mails envoyés par jour ou 500 destinataires sur un message. Ce choix devra être remplacé par un service transactionnel si le volume augmente.

## Références officielles Google

- [Paramètres SMTP Gmail](https://support.google.com/mail/answer/7104828)
- [Mots de passe des applications](https://support.google.com/accounts/answer/185833)
- [Limites d’envoi Gmail](https://support.google.com/mail/answer/22839)
