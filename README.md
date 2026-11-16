# FASO DIGITAL TRADING

## Identité visuelle

Le logo officiel FASO DIGITAL TRADING est servi depuis public/assets/faso-digital-trading-logo.jpeg et affiché dans l’en-tête, le pied de page et l’accueil. La palette associe bleu nuit, or, argent, rouge et vert, en cohérence avec le drapeau du Burkina Faso et le logo fourni.


Application Laravel pour le site public, les comptes, abonnements, licences MT5 et données de compte en lecture seule.

**Le cahier des charges annoncé manque encore dans le workspace.** La portée actuelle est provisoire et documentée dans [l'architecture](docs/architecture/README.md). Le dossier source de l'ancien projet NEXUS reste intact; aucune base, clé `.env` ou donnée de session n'a été importée.

## Développement local

Prérequis : PHP 8.4.1 ou plus récent avec `pdo_sqlite`, `mbstring`, `openssl`, `curl` et `zip`; Composer 2; Node 20.19+ et pnpm pour le QR local et les ressources frontend.

```powershell
Copy-Item .env.example .env
if (!(Test-Path database/black-wolf-local.sqlite)) { New-Item -ItemType File -Path database/black-wolf-local.sqlite }
composer install
pnpm install --frozen-lockfile
pnpm build
php artisan key:generate
php artisan migrate --seed
.\scripts\serve-local.ps1
```

Le script lance le serveur PHP directement avec un dossier temporaire d’envoi accessible en écriture. Il règle aussi `APP_URL` pour ce seul processus afin que les liens envoyés par e-mail utilisent le port de l’aperçu. Si PHP n’est pas dans `PATH`, passez son chemin : `.\scripts\serve-local.ps1 -PhpExecutable 'C:\chemin\vers\php.exe'`.

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

- PHP 8.4.1+ et Laravel 13 derrière un reverse proxy HTTPS. La version minimale correspond aux dépendances verrouillées dans `composer.lock`.
- MySQL 8.4 LTS, sauvegardes chiffrées et stockage privé.
- `APP_DEBUG=false`, clé d'application unique, cookies de session HTTPS, proxies explicitement approuvés et secrets fournis par un gestionnaire de secrets.
- Définir `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true` et `SESSION_LIFETIME=60` en production. Les en-têtes de sécurité sont ajoutés par l’application; HSTS n’est émis que sur une requête HTTPS.
- Configurer un SMTP transactionnel réel pour permettre la récupération du mot de passe; les liens utilisent le domaine défini par `APP_URL`.
- Configurer `MAIL_MAILER=smtp`, `MAIL_SCHEME=smtp` (STARTTLS) ou `smtps`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` et le domaine HTTPS exact dans `APP_URL`.
- Les inscriptions publiques restent fermées tant que ces paramètres ne sont pas configurés et qu’un test SMTP vers `ADMIN_EMAIL` n’a pas été reçu par l’administrateur. Le bouton de test est accessible uniquement à l’administrateur principal après MFA. Après réception vérifiée, l’exploitant définit `MAIL_LIVE_VERIFIED=true` dans l’environnement secret du serveur. Une réponse SMTP acceptée ne prouve pas l’arrivée en boîte.
- Chaque inscription envoie la vérification signée à durée limitée, le message de bienvenue et une alerte à `ADMIN_EMAIL`; le renvoi est limité à trois par heure. Aucun secret SMTP ne doit être versionné. Les courriels sont envoyés de manière synchrone; aucun worker de queue n’est requis pour la vérification actuelle.
- `ADMIN_EMAIL` est l’unique identité administrateur autorisée. Créer le compte avec `php artisan blackwolf:create-admin`, puis enrôler l’application TOTP au premier accès. Les routes et politiques admin vérifient aussi cette adresse et le TOTP.
- Les tarifs restent en USD. L’équivalence XOF affichée utilise le taux fixe convenu de 1 USD = 600 XOF; aucun service de change BCEAO ou autre API n’est appelé. Le paiement crypto demandé reste égal au montant numérique du tarif en USDT.
- Chaque commande conserve le tarif USD et son équivalence XOF fixe, avec le taux et la date de calcul. Aucun montant envoyé par le navigateur n’est accepté.
- Les nouvelles souscriptions acceptent uniquement Binance-Peg BSC-USD (USDT BEP20) sur BNB Smart Chain, avec le contrat et l’adresse de réception déclarés dans `config/payments.php`. Le montant numérique du prix USD est le montant attendu en USDT; l’équivalence XOF au taux fixe de 600 XOF par USD reste informative.
- Après le transfert, le client envoie uniquement une capture JPG/PNG. Elle est stockée dans un disque privé, jointe au courriel administrateur lorsque SMTP est prêt, et vérifiée par empreinte SHA-256. La référence REQ est interne : le client ne saisit ni hash, ni montant, ni OTP. L’administrateur retrouve le hash réel sur BscScan et contrôle le contrat, le destinataire, le montant exact et au moins le nombre de confirmations configuré. Aucun RPC ou API blockchain n’est utilisé et aucune activation n’intervient avant approbation manuelle. Le client reçoit un courriel après confirmation.
- Aucun nouveau checkout PayDunya ni paiement mobile n’est activé. Le connecteur et les clés historiques PayDunya sont conservés pour le rapprochement des transactions déjà enregistrées; ils ne sélectionnent pas un moyen de paiement pour une nouvelle commande. En production, le parcours crypto exige HTTPS, cookies sécurisés, sessions chiffrées, `APP_DEBUG=false`, des clés distinctes, les mentions légales requises et un stockage privé persistant. L’acceptation reste bloquée tant que le bucket privé et sa persistance ne sont pas configurés et vérifiés.
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

- Conversion USD/XOF : taux fixe 600 XOF par USD appliqué côté serveur; aucun flux de change externe n’est utilisé.
- Les envois SMTP et les synchronisations MetaApi restent désactivés en l'absence de configuration complète et d'un test réel attesté par l'exploitant. Les nouveaux checkouts PayDunya et les paiements mobiles sont désactivés.
- Le dépôt des captures crypto, leur notification avec pièce jointe, le contrôle manuel par l’admin MFA, la protection contre le hash en doublon et le courriel client sont couverts par des tests locaux. Aucun appel RPC ou explorateur blockchain n’est configuré. L’adaptateur S3 est installé; en production, l’acceptation reste bloquée tant que le bucket privé durable n’est pas configuré et attesté.
- L’identité administrateur autorisée est `fasodigitaltrading@gmail.com`. Sur l’instance locale vérifiée, le compte existe, est actif et son MFA est configuré. Sur une base vierge seulement, la commande d’amorçage ci-dessus crée le premier compte et refuse les doublons.
- Le graphique TradingView est un composant navigateur externe; sa disponibilité et les droits de cotation n'ont pas été testés depuis le site en production.
- Il n'y a pas d'intégration de calendrier économique, de FASO DIGITAL AI ou de prévisions XAUUSD. Aucune cotation BRVM n'est importée; les demandes BRVM restent non exécutables tant qu'un contrat de données et un canal autorisé pour Coris Bourse ne sont pas fournis.
- Les commissions de 10 % sont calculées sur les paiements confirmés et les renouvellements, mais le versement des commissions aux partenaires n'est pas intégré.
- Le centre administrateur permet d’approuver une commission après paiement confirmé et délai de réserve, puis de rapprocher un versement réellement effectué avec sa référence. La plateforme ne transfère pas les commissions.
- Le centre administrateur comprend la gestion des utilisateurs (suppression logique), l’historique des paiements, abonnements, licences, comptes MT5 en lecture seule, partenaires, support, rapports, notifications internes et journaux d’audit/sécurité. L’accès demeure réservé à l’adresse d’administration autorisée et au MFA TOTP.
- Le flux PayDunya n'intègre pas le traitement des remboursements; ceux-ci doivent être rapprochés manuellement.
- Ce dépôt n'est pas déployé sur le site Bubble d'origine. Les validations présentes concernent uniquement l'environnement local.
