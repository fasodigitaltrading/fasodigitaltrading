# FASO DIGITAL TRADING — audit et préparation

État du dépôt local au 16 novembre 2026. Ce document décrit le code et la base locale vérifiés; il ne décrit pas une publication sur le domaine Bubble.

## Espace administrateur

Le centre d’administration est accessible par des routes protégées par authentification, statut actif, vérification d’adresse, version de mot de passe, rôle et MFA. Seule l’identité configurée dans `ADMIN_EMAIL` peut avoir le rôle administrateur authentifié.

Les sections couvertes sont les utilisateurs, paiements, abonnements, licences, comptes MT5 en lecture seule, partenaires, commissions, catalogue, brokers, support, demandes BRVM et contact, rapports, notifications internes, paramètres et journaux d’audit/sécurité. Les changements de compte, d’abonnement, de licence, de partenaire, de support et de commission sont journalisés. Les clés de licence et jetons MT5 ne sont pas rendus dans les vues. Les données financières et les historiques restent conservés lors d’une suppression de compte, qui est logique et réversible.

Les demandes de versement de commission sont des rapprochements manuels. Une référence est enregistrée seulement après que l’administrateur a confirmé avoir effectué le transfert externe; aucun transfert n’est déclenché par la plateforme. Les revenus et commissions sont affichés séparément par devise.

La base locale contient un compte investisseur et le compte ADMIN principal configuré. Le lien de réinitialisation a été accepté par le transport SMTP; la réception dans la boîte doit être confirmée par l’administrateur. L’application TOTP s’enrôle lors de la première connexion. Aucun mot de passe administrateur n’est stocké dans le dépôt.

## E-mails

- État observé dans l’environnement local : paramètres SMTP présents, test réel de transport requis; la réception n’est pas attestée.
- Les inscriptions restent fermées tant que le transport n’a pas accepté un vrai test et que l’administrateur n’en a pas confirmé la réception.
- L’inscription envoie un lien signé de confirmation, un message de bienvenue et une notification d’inscription à `ADMIN_EMAIL`. Une même adresse ne peut pas ouvrir plusieurs comptes, y compris lorsqu’un compte a été supprimé logiquement.
- Les paramètres SMTP et leur validation doivent être définis dans l’environnement secret du serveur. L’acceptation SMTP n’est pas une preuve d’arrivée en boîte.

## Catalogue, devises et paiements

- Le catalogue public expose uniquement STARTER, STANDARD, PRO et PREMIUM; les offres historiques restent archivées et inaccessibles aux nouvelles souscriptions.
- Les prix USD restent la référence commerciale. Tous les nouveaux montants en XOF utilisent le taux fixe convenu de 600 XOF par USD; le taux et le montant sont enregistrés sur la commande. Aucune API de change n’est appelée.
- La commande `php artisan blackwolf:refresh-fx` vérifie le taux fixe localement et n’effectue aucune requête réseau.
- Les nouveaux paiements utilisent Binance-Peg BSC-USD (USDT BEP20) sur BNB Smart Chain. Le montant USDT reprend la valeur numérique du prix USD; XOF est informatif. Le client envoie uniquement une capture JPG/PNG; une référence REQ interne est générée. L’administrateur vérifie manuellement sur BscScan le hash réel, le contrat accepté, le destinataire, le montant et au moins 15 confirmations. La capture est conservée dans un stockage privé et jointe à l’alerte SMTP si le transport est prêt. Aucun RPC ni API blockchain n’est configuré; aucune capture ne vaut confirmation.
- Après approbation, le processeur active l’abonnement et la licence; un courriel de confirmation est envoyé au client. Si SMTP échoue ou reste incomplet, l’état de l’e-mail est enregistré et l’administrateur peut réessayer sans annuler l’activation.
- Le stockage local est protégé et n’est pas considéré durable sur Laravel Cloud. L'adaptateur Flysystem S3 et l'AWS SDK sont installés. `ManualPaymentProofObjectStorage` parle à R2 sans envoyer d'ACL par objet ni checksum facultatif; la confidentialité doit être définie au niveau du bucket. `ManualPaymentReadiness` bloque les paiements production tant que bucket, région, identifiants et endpoint HTTPS manquent ou que la persistance privée n’est pas attestée. Le test réel d'écriture/lecture du bucket reste à faire en préproduction.
- Les nouveaux checkouts PayDunya et les paiements mobile money sont désactivés. Le connecteur PayDunya et ses paramètres historiques sont conservés uniquement pour rapprocher d’anciennes transactions; ils ne peuvent pas être sélectionnés pour de nouvelles commandes. Aucune clé PayDunya existante n’a été modifiée.
- Une licence et un abonnement payant ne sont activés qu’après un événement de paiement confirmé ou une vérification manuelle explicite avec référence, inscrite dans l’audit.

## Autres services et limites réelles

| Fonction | État du dépôt local | Accès ou vérification restant |
|---|---|---|
| Centre administrateur | **OPÉRATIONNEL ET TESTÉ** localement; routes et rôles sont couverts par PHPUnit | Créer le compte ADMIN localement et enrôler le TOTP |
| E-mails de compte | **EN ATTENTE DE TEST RÉEL**; configuration présente, réception non attestée | Tester depuis le serveur de déploiement, confirmer la réception puis activer le marqueur de validation |
| Paiement Binance-Peg BSC-USD | **PARTIELLEMENT OPÉRATIONNEL ET TESTÉ**; capture seule, pièce jointe mail, contrôle hash/contrat/destinataire/montant/confirmations, anti-doublon et activation admin MFA; aucune validation automatique | Configurer et tester SMTP; configurer un bucket R2 privé avec accès lecture/écriture, tester l'écriture/lecture/suppression et attester la persistance avant toute acceptation de capture en production; vérifier le portefeuille et l’exploitation réelle |
| PayDunya / paiements mobiles | **DÉSACTIVÉS POUR LES NOUVELLES COMMANDES**; paramètres et données PayDunya historiques conservés | Rapprocher toute facture PayDunya déjà émise avant d’envisager sa fermeture côté marchand |
| Conversion USD/XOF | **OPÉRATIONNELLE ET TESTÉE**; taux fixe de 600 XOF par USD, sans service externe | Confirmer le tarif affiché après déploiement |
| TradingView | **PARTIELLEMENT OPÉRATIONNEL** côté navigateur | Tester disponibilité, symboles, latence et droits de diffusion sur le domaine public |
| MT5 | **PARTIELLEMENT OPÉRATIONNEL** en lecture seule; les vues admin consultent uniquement les valeurs enregistrées | Jeton MetaApi lecteur, autorisation du compte, endpoint HTTPS et test réel sur compte autorisé |
| Licences EA | **RÈGLES SERVEUR OPÉRATIONNELLES ET TESTÉES** | Tester l’EA `.mq5` réel sur MetaTrader et l’API déployée en HTTPS |
| BRVM / Coris Bourse | **EN ATTENTE D’AUTORISATION**; demandes locales uniquement, aucun cours ni transfert automatique | Contrat de diffusion BRVM, flux officiel et canal ainsi qu’un mandat autorisés par Coris Bourse |
| Calendrier économique | **NON DÉVELOPPÉ/CONNECTÉ** | Fournisseur autorisé, accès et droits de rediffusion |
| BLACK WOLF AI / GOLD INTELLIGENCE / XAUUSD | **NON DÉVELOPPÉS/CONNECTÉS**; aucune prévision réelle n’est générée | Source de données, accès, spécification, validation et gouvernance du modèle |
| Hébergement Laravel Cloud | **CHOISI, NON DÉPLOYÉ**; dépôt local sur `main`, remote GitHub `origin` configuré, changements de travail non commités, Laravel Cloud non relié | Examiner et commiter les changements, relier Laravel Cloud au dépôt privé; fournir le domaine public; configurer le stockage objet privé, migrer SQLite vers MySQL et tester avant d'activer les paiements |

## Base de données et sauvegarde

La migration additive `034_complete_admin_console` est appliquée à la base SQLite locale. Avant application, la base a été copiée et vérifiée par SHA-256 dans `work/backups/black-wolf-before-admin-console-20261115-102028.sqlite`. Les effectifs relevés avant et après sont identiques : 1 utilisateur, 5 commandes, 0 paiement, 0 abonnement, 0 licence, 0 commission et 0 ticket/support message. La migration ajoute la suppression logique des utilisateurs, les colonnes de rapprochement des versements et les champs de classification/support; aucune donnée existante n’a été supprimée.

La migration additive `035_secure_crypto_payment_review` n'a pas été appliquée à la base SQLite locale; la suite de tests l'exécute sur une base SQLite en mémoire. Faire une sauvegarde restaurable et contrôler les migrations avant de l’appliquer à une base contenant des données.

## Validation

La suite complète PHPUnit a été exécutée après les corrections de paiement : 135 tests et 953 assertions réussis. Elle utilise SQLite en mémoire, ne fait pas de débits ni d’appels MT5/BRVM/Coris, SMTP ou blockchain.

```powershell
php vendor/bin/phpunit
composer validate --strict
composer audit --locked
php artisan migrate:status
php artisan blackwolf:refresh-fx
```

Sur tout environnement partagé, produire et vérifier une sauvegarde restaurable avant d’appliquer une migration. Ne jamais utiliser `migrate:fresh` ou réinitialiser une base contenant des données client.
