# Préparation au déploiement sur Laravel Cloud

État au 16 novembre 2026 : le projet est préparé localement, mais il n'est pas déployé ni prêt à recevoir des paiements en production.

## Répertoire et dépôt

- Racine Laravel et racine Git : `outputs/black-wolf-trading` dans le workspace.
- Branche : `main`.
- Le remote `origin` pointe vers `https://github.com/fasodigitaltrading/fasodigitaltrading.git`.
- Laravel Cloud n'est pas encore relié au dépôt et aucun déploiement n'a été lancé.
- Connecter Laravel Cloud au dossier du projet à la racine du dépôt. Ne pas sélectionner le dossier parent du workspace.

## Configuration Laravel Cloud

Laravel Cloud détecte l'application Laravel et gère son runtime PHP, les domaines HTTPS et les ressources attachées depuis son tableau de bord. Aucun fichier `cloud.yaml`, Procfile ou pipeline de déploiement propre à Cloud n'est requis. Le `Dockerfile` présent lance `php artisan serve`; il n'est pas la configuration de production Cloud et ne doit pas servir de conteneur de production.

Paramètres initiaux à utiliser dans un environnement de préproduction :

- Runtime PHP 8.4 ou supérieur : les versions verrouillées de Symfony dans `composer.lock` exigent PHP 8.4.1 minimum, même si `composer.json` accepte encore `^8.3`.
- Ajouter une base Laravel MySQL dans la même région que l'application. Cloud injecte les variables de connexion `DB_*`; définir explicitement `DB_CONNECTION=mysql`, car le modèle local `.env.example` utilise SQLite. Ne pas copier la configuration SQLite locale.
- Ajouter un bucket Laravel Object Storage **privé** et lui donner le nom de disque `s3`. Cloud s'appuie sur Cloudflare R2 et injecte le bucket, la région, l'endpoint HTTPS et les identifiants `AWS_*`. La configuration applicative utilise ces valeurs. Pour les captures crypto, définir `PAYMENTS_MANUAL_PROOF_DISK=s3` et `PAYMENTS_MANUAL_PROOF_STORAGE_PERSISTENT=true` après avoir vérifié que le bucket est privé et que la clé d'accès a les droits lecture/écriture.
- Garder `SESSION_DRIVER=database` et `CACHE_STORE=database` au départ.
- Configurer les secrets SMTP et les autres clés historiques dans Cloud Secrets, jamais dans GitHub ou un fichier du dépôt. Les nouvelles commandes sont limitées au paiement USDT BEP20 et à une vérification manuelle; aucun secret PayDunya ne doit activer un nouveau checkout.
- Utiliser l'URL HTTPS `*.laravel.cloud` pour les premiers tests de callback avant d'enregistrer un domaine personnalisé.

La configuration `config/filesystems.php` définit les paramètres d'un disque `s3`. `ManualPaymentProofObjectStorage` utilise l'AWS SDK directement pour les captures, relit leur contenu pour vérifier le SHA-256 et les joint au mail sans URL publique. Les transferts n'envoient ni ACL par objet ni checksum facultatif incompatible : l'accès privé vient du niveau du bucket R2. L'adaptateur `league/flysystem-aws-s3-v3` et l'AWS SDK sont verrouillés dans Composer. `ManualPaymentReadiness` exige l'adaptateur, le bucket, la région, une clé et un secret, un endpoint HTTPS ainsi qu'une attestation de stockage persistant. Le véritable test d'écriture, de lecture et de suppression d'un fichier de test reste à faire en préproduction. Le stockage local de Cloud est éphémère.

La compilation frontend utilise Node, Vite et `qrcode` pour générer localement un QR de l'adresse fixe. `pnpm-lock.yaml` est présent; le build `pnpm build` a été vérifié. `public/build` est généré et ignoré par Git, donc Laravel Cloud doit exécuter le build à chaque déploiement.

## Base de données et commandes de déploiement

- La base SQLite locale `database/black-wolf-local.sqlite` est une donnée utilisateur et reste exclue de Git. Elle n'est ni supprimée ni convertie ici.
- SQLite n'est pas pris en charge en production sur Cloud. Avant la mise en ligne, faire une sauvegarde vérifiée, créer MySQL, transférer les données avec contrôle des volumes et des relations, puis sauvegarder les justificatifs existants dans le bucket privé et vérifier leurs SHA-256.
- La commande de déploiement `php artisan migrate --force` est à laisser désactivée jusqu'à ce que le transfert ait été répété et validé en préproduction. La migration `035_secure_crypto_payment_review` est additive et non appliquée à la base locale. N'utiliser aucune migration destructive et conserver une sauvegarde restaurable.
- Commande de build : `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader && npm exec --yes --package=pnpm@11.25.0 -- pnpm install --frozen-lockfile && npm exec --yes --package=pnpm@11.25.0 -- pnpm build && php artisan optimize`. Le runtime PHP de Cloud inclut Node.js et npm, mais la version de pnpm est explicitement épinglée au niveau du projet et lors du build.
- Aucun déploiement, import de base, migration ou paiement réel n'a été lancé dans cette préparation.

## Vérifications de code et CI

Le `.gitignore` exclut `.env`, les fichiers `.env.*` sauf le modèle `.env.example`, la base locale, les clés/certificats, les identifiants Composer/npm/AWS, les journaux, les justificatifs privés et les sauvegardes. Le `.dockerignore` applique également ces exclusions au contexte d'image Docker.

`composer.lock` et `pnpm-lock.yaml` sont présents. Vérifications locales du 16 novembre 2026 : PHPUnit passe (135 tests, 953 assertions); `pnpm install --frozen-lockfile`, le build frontend et `pnpm audit --audit-level=high` passent. Composer CLI n'est pas disponible localement. Le premier run GitHub Actions s'est arrêté avant les tests : il utilisait PHP 8.3, alors que des dépendances verrouillées dans `composer.lock` exigent PHP 8.4.1 minimum. Le workflow est corrigé pour utiliser PHP 8.4; il faut attendre la réussite du prochain run avant de considérer les contrôles CI comme validés. L'analyse du dépôt n'a trouvé aucun secret; `.env` et la base SQLite locale sont ignorés. Ne jamais forcer leur ajout.

## Étapes utilisateur dans Laravel Cloud

1. Créer/ouvrir le compte Laravel Cloud et connecter le fournisseur GitHub.
2. Le dépôt GitHub existant est `fasodigitaltrading/fasodigitaltrading`; vérifier son réglage de visibilité avant d'y placer du code privé. Aucun secret ne doit être ajouté au dépôt.
3. Dans Cloud, créer une application depuis la branche `main`, sélectionner PHP 8.4 ou supérieur et une région adaptée.
4. Créer une préproduction avec MySQL et un bucket privé; ne pas activer les paiements.
5. Renseigner les secrets dans Cloud. Ne pas coller les clés dans une conversation, un ticket ou un fichier commité.
6. Tester le bucket S3 privé, les e-mails et les flux crypto en préproduction.
7. Après sauvegarde et validation du transfert de données, demander une validation explicite avant le premier déploiement de production.

## Références techniques vérifiées

- [Images de base et outils Laravel Cloud](https://laravel.com/cloud/docs/base-images) : le runtime PHP inclut Node.js et npm pour compiler les assets.
- [Laravel Object Storage](https://laravel.com/cloud/docs/resources/object-storage) : stockage S3 compatible Cloudflare R2, bucket privé et variables `AWS_*`.
- [Compatibilité S3 de Cloudflare R2](https://developers.cloudflare.com/r2/api/s3/api/) : les ACL d'objet ne sont pas prises en charge; l'accès doit être défini par le bucket.

