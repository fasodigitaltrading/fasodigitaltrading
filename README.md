# FASO DIGITAL TRADING

## Identité visuelle

Le logo officiel FASO DIGITAL TRADING est servi depuis public/assets/faso-digital-trading-logo.jpeg et affiché dans l’en-tête, le pied de page et l’accueil. La palette associe bleu nuit, or, argent, rouge et vert, en cohérence avec le drapeau du Burkina Faso et le logo fourni.


Application Laravel pour le site public, les comptes, abonnements, licences MT5 et données de compte en lecture seule.

**Le cahier des charges annoncé manque encore dans le workspace.** La portée actuelle est provisoire et documentée dans [l'architecture](docs/architecture/README.md). Le dossier source de l'ancien projet NEXUS reste intact; aucune base, clé `.env` ou donnée de session n'a été importée.

## Développement local

Prérequis : PHP 8.3 ou plus récent avec `pdo_sqlite`, `mbstring`, `openssl`, `curl` et `zip`; Composer 2.

```powershell
Copy-Item .env.example .env
if (!(Test-Path database/black-wolf-local.sqlite)) { New-Item -ItemType File -Path database/black-wolf-local.sqlite }
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Avant d’utiliser l’API de licence, générez une clé HMAC distincte de `APP_KEY` et définissez `LICENSE_BINDING_KEY` dans `.env` :

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Conservez cette clé dans un gestionnaire de secrets et ne la faites pas tourner sans procédure de migration des liaisons existantes.

Créer le premier administrateur par saisie interactive :

```powershell
php artisan blackwolf:create-admin
```

Le seeder ajoute le catalogue publié sans écraser un catalogue existant. Il ne crée aucun utilisateur de démonstration, compte de trading, paiement ou résultat de performance.

Le formulaire de contact et l’inscription gardent la date et la version des consentements acceptés. Les nouvelles inscriptions doivent confirmer leur adresse e-mail avant d’ouvrir l’espace privé. Les pages légales sont accessibles depuis le pied de page. Les coordonnées de l’exploitant et les règles de conservation restent à renseigner avant une ouverture commerciale.

## Production

- PHP 8.3+ et Laravel 13 derrière un reverse proxy HTTPS.
- MySQL 8.4 LTS, sauvegardes chiffrées et stockage privé.
- `APP_DEBUG=false`, clé d'application unique, cookies de session HTTPS, proxies explicitement approuvés et secrets fournis par un gestionnaire de secrets.
- Définir `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true` et `SESSION_LIFETIME=60` en production. Les en-têtes de sécurité sont ajoutés par l’application; HSTS n’est émis que sur une requête HTTPS.
- Configurer un SMTP transactionnel réel pour permettre la récupération du mot de passe; les liens utilisent le domaine défini par `APP_URL`.
- Configurer `MAIL_MAILER=smtp`, `MAIL_SCHEME=smtp` (STARTTLS) ou `smtps`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` et le domaine HTTPS exact dans `APP_URL`.
- Les inscriptions publiques restent fermées tant que ces paramètres ne sont pas configurés et qu’un test SMTP vers `ADMIN_EMAIL` n’a pas été reçu par l’administrateur. Le bouton de test est accessible uniquement à l’administrateur principal après MFA. Après réception vérifiée, l’exploitant définit `MAIL_LIVE_VERIFIED=true` dans l’environnement secret du serveur. Une réponse SMTP acceptée ne prouve pas l’arrivée en boîte.
- Chaque inscription envoie la vérification signée à durée limitée, le message de bienvenue et une alerte à `ADMIN_EMAIL`; le renvoi est limité à trois par heure. Aucun secret SMTP ne doit être versionné. Les courriels sont envoyés de manière synchrone; aucun worker de queue n’est requis pour la vérification actuelle.
- `ADMIN_EMAIL` est l’unique identité administrateur autorisée. Créer le compte avec `php artisan blackwolf:create-admin`, puis enrôler l’application TOTP au premier accès. Les routes et politiques admin vérifient aussi cette adresse et le TOTP.
- Les tarifs enregistrés restent en USD; le cours quotidien de référence USD/XOF provient de la page officielle BCEAO. Un cours absent ou périmé bloque le paiement; aucun taux de remplacement fixe n’est appliqué aux nouvelles commandes.
- Chaque commande conserve le tarif USD, le montant XOF, le cours, sa date de publication et la source. Aucun montant envoyé par le navigateur n’est accepté.
- Le checkout PayDunya exige les trois clés marchandes, `PAYMENT_PROVIDER=paydunya`, une URL publique HTTPS joignable, des mentions légales renseignées et approuvées, un cours BCEAO admissible et une configuration de production sécurisée. `PAYDUNYA_LIVE_VERIFIED` est un indicateur d’exploitation et ne bloque pas la première facture live. Aucun abonnement ni licence ne s’active sans callback signé puis confirmation serveur PayDunya du montant et du statut.
- À défaut de PayDunya, le parcours de transfert manuel accepte les moyens configurés dans l’application et une preuve conservée en stockage privé. Le transfert n’est pas exécuté par la plateforme; l’administrateur vérifie les fonds réellement reçus avant de créer le paiement et activer l’abonnement. En production, le parcours exige HTTPS, cookies sécurisés, sessions chiffrées, `APP_DEBUG=false` et des clés distinctes et valides.
- Les moyens manuels nécessitent la validation du compte bénéficiaire et des conditions marchand par l’opérateur. USDT BEP20 n’a pas de vérification blockchain automatisée; l’administrateur doit contrôler le hash sur le réseau avant d’approuver.
- Le graphique d’accueil utilise le Web Component officiel TradingView et son flux de données intégré. Il dépend du chargement externe TradingView et de la disponibilité de ses marchés; aucune API TradingView privée ni extraction par scraping n’est utilisée.
- L'EA MQL5 doit autoriser le domaine HTTPS de validation de licence dans la liste WebRequest de MetaTrader.

## Vérifications

```powershell
php vendor/bin/phpunit
composer validate --strict
composer audit --locked
```

Les tests automatisés n'appellent pas les API de paiement, SMTP, TradingView ou MetaApi. Les fixtures locales valident les règles métier et le traitement des événements déjà normalisés; elles ne valident pas une connexion réelle. L'essai réel des fournisseurs nécessite leurs accès officiels et des comptes appartenant à l'exploitant.

## État actuel des intégrations

- Conversion USD/XOF : référence quotidienne BCEAO; parser et calcul testés avec des fixtures officielles, mais la connectivité externe en production reste à vérifier.
- Les envois SMTP, le checkout PayDunya et les synchronisations MetaApi restent désactivés en l'absence de configuration complète et d'un test réel attesté par l'exploitant.
- Les transferts manuels sont partiellement opérationnels dans le code local; l’abonnement n’est activé qu’après vérification admin MFA et contrôle exact du montant.
- L’identité administrateur autorisée est `fasodigitaltrading@gmail.com`. La base locale n’a pas encore de compte admin; utilisez la commande d’amorçage ci-dessus et choisissez le mot de passe dans votre terminal, puis enrôlez le MFA au premier accès.
- Le graphique TradingView est un composant navigateur externe; sa disponibilité et les droits de cotation n'ont pas été testés depuis le site en production.
- Il n'y a pas d'intégration de calendrier économique, de FASO DIGITAL AI ou de prévisions XAUUSD. Aucune cotation BRVM n'est importée; les demandes BRVM restent non exécutables tant qu'un contrat de données et un canal autorisé pour Coris Bourse ne sont pas fournis.
- Les commissions de 10 % sont calculées sur les paiements confirmés et les renouvellements, mais le versement des commissions aux partenaires n'est pas intégré.
- Le centre administrateur permet d’approuver une commission après paiement confirmé et délai de réserve, puis de rapprocher un versement réellement effectué avec sa référence. La plateforme ne transfère pas les commissions.
- Le centre administrateur comprend la gestion des utilisateurs (suppression logique), l’historique des paiements, abonnements, licences, comptes MT5 en lecture seule, partenaires, support, rapports, notifications internes et journaux d’audit/sécurité. L’accès demeure réservé à l’adresse d’administration autorisée et au MFA TOTP.
- Le flux PayDunya n'intègre pas le traitement des remboursements; ceux-ci doivent être rapprochés manuellement.
- Ce dépôt n'est pas déployé sur le site Bubble d'origine. Les validations présentes concernent uniquement l'environnement local.
