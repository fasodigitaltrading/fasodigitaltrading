# FASO DIGITAL TRADING — audit et préparation

État du dépôt local au 15 novembre 2026. Ce document décrit le code et la base locale vérifiés; il ne décrit pas une publication sur le domaine Bubble.

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
- Les prix USD restent la référence commerciale. L’équivalence XOF des nouvelles commandes s’appuie sur le taux quotidien officiel BCEAO; son instantané, la date publiée et la source sont enregistrés sur la commande.
- Aucun cours absent, périmé ou invalide ne peut servir à démarrer un paiement. `php artisan blackwolf:refresh-fx` interroge la source officielle en environnement réel et affiche clairement l’échec si le cours utilisable n’est pas disponible. Le parseur est testé avec des fixtures, sans requête réseau implicite des tests.
- PayDunya est le prestataire prévu. Le checkout live requiert les clés marchandes, `PAYMENT_PROVIDER=paydunya`, une URL publique HTTPS joignable, les mentions légales renseignées et approuvées, et un cours BCEAO admissible. L’indicateur `PAYDUNYA_LIVE_VERIFIED` n’empêche pas la première facture; aucun abonnement ne s’active sans callback signé et confirmation serveur PayDunya. Un contrôle GET de confirmation avec un token aléatoire inexistant a répondu HTTP 200 / code PayDunya `1001` « Invalid Masterkey Specified »; aucune facture ni aucun paiement n’a été créé. Le runtime PHP local ne dispose pas de `curl.cainfo`/`openssl.cafile`, il faut aussi un bundle CA fiable sur l’hébergement.
- Les transferts manuels Orange Money, Moov Money, Wave, Sank et USDT BEP20 utilisent le montant enregistré sur la commande BCEAO. OTP, référence unique, reçu privé, empreinte SHA-256, contrôle de l’administrateur et confirmation exacte du montant sont implémentés. Ce flux ne se connecte pas aux portefeuilles et ne vérifie pas une transaction blockchain automatiquement.
- Une licence et un abonnement payant ne sont activés qu’après un événement de paiement confirmé ou une vérification manuelle explicite avec référence, inscrite dans l’audit.

## Autres services et limites réelles

| Fonction | État du dépôt local | Accès ou vérification restant |
|---|---|---|
| Centre administrateur | **OPÉRATIONNEL ET TESTÉ** localement; routes et rôles sont couverts par PHPUnit | Créer le compte ADMIN localement et enrôler le TOTP |
| E-mails de compte | **EN ATTENTE DE TEST RÉEL**; configuration présente, réception non attestée | Tester depuis le serveur de déploiement, confirmer la réception puis activer le marqueur de validation |
| Paiement PayDunya | **EN ERREUR ET BLOQUÉ**; l’API de production refuse la Master Key (`1001`); l’URL HTTPS publique et les informations légales approuvées manquent aussi | Reprendre la Master Key de la même application PayDunya en mode production, déployer Laravel avec HTTPS et CA système, renseigner/approuver les mentions légales, puis vérifier un paiement réel |
| Transferts manuels | **PARTIELLEMENT OPÉRATIONNELS**; contrôles et revue réelle implémentés, aucun portefeuille connecté | Vérifier les comptes bénéficiaires, les conditions marchands, les mentions légales et l’exploitation en production |
| Référence USD/XOF | **PARTIELLEMENT OPÉRATIONNELLE**; source BCEAO et calcul implémentés/testés, disponibilité réseau de production non confirmée | Autoriser l’accès HTTPS sortant à `www.bceao.int` et vérifier la commande de rafraîchissement sur l’hébergement |
| TradingView | **PARTIELLEMENT OPÉRATIONNEL** côté navigateur | Tester disponibilité, symboles, latence et droits de diffusion sur le domaine public |
| MT5 | **PARTIELLEMENT OPÉRATIONNEL** en lecture seule; les vues admin consultent uniquement les valeurs enregistrées | Jeton MetaApi lecteur, autorisation du compte, endpoint HTTPS et test réel sur compte autorisé |
| Licences EA | **RÈGLES SERVEUR OPÉRATIONNELLES ET TESTÉES** | Tester l’EA `.mq5` réel sur MetaTrader et l’API déployée en HTTPS |
| BRVM / Coris Bourse | **EN ATTENTE D’AUTORISATION**; demandes locales uniquement, aucun cours ni transfert automatique | Contrat de diffusion BRVM, flux officiel et canal ainsi qu’un mandat autorisés par Coris Bourse |
| Calendrier économique | **NON DÉVELOPPÉ/CONNECTÉ** | Fournisseur autorisé, accès et droits de rediffusion |
| BLACK WOLF AI / GOLD INTELLIGENCE / XAUUSD | **NON DÉVELOPPÉS/CONNECTÉS**; aucune prévision réelle n’est générée | Source de données, accès, spécification, validation et gouvernance du modèle |
| Hébergement Laravel Cloud | **CHOISI, NON DÉPLOYÉ**; dépôt Git local initialisé, sans dépôt distant ni compte Cloud relié | Créer le compte et connecter un dépôt privé; fournir le domaine public; intégrer le stockage objet privé, migrer SQLite vers MySQL et tester avant de basculer les paiements |

## Base de données et sauvegarde

La migration additive `034_complete_admin_console` est appliquée à la base SQLite locale. Avant application, la base a été copiée et vérifiée par SHA-256 dans `work/backups/black-wolf-before-admin-console-20261115-102028.sqlite`. Les effectifs relevés avant et après sont identiques : 1 utilisateur, 5 commandes, 0 paiement, 0 abonnement, 0 licence, 0 commission et 0 ticket/support message. La migration ajoute la suppression logique des utilisateurs, les colonnes de rapprochement des versements et les champs de classification/support; aucune donnée existante n’a été supprimée.

## Validation

La suite complète PHPUnit a été exécutée après les corrections de paiement : 132 tests et 897 assertions réussis. Elle utilise SQLite en mémoire, ne fait pas de débits ni d’appels MT5/BRVM/Coris et utilise des fixtures locales pour les API.

```powershell
php vendor/bin/phpunit
composer validate --strict
composer audit --locked
php artisan migrate:status
php artisan blackwolf:refresh-fx
```

Sur tout environnement partagé, produire et vérifier une sauvegarde restaurable avant d’appliquer une migration. Ne jamais utiliser `migrate:fresh` ou réinitialiser une base contenant des données client.
