# Préparation au déploiement sur Laravel Cloud

État au 15 novembre 2026 : le projet est préparé localement, mais il n'est pas publié ni prêt à recevoir des paiements en production.

## Répertoire et dépôt

- Racine Laravel et racine Git : `outputs/black-wolf-trading` dans le workspace.
- Branche locale : `main`.
- Le dépôt Git est initialisé, sans commit ni remote.
- Aucun dépôt GitHub ne peut être identifié depuis ce clone local. L'absence de remote signifie qu'aucun dépôt distant n'y est relié; elle ne permet pas de savoir si un dépôt privé existe dans le compte GitHub du propriétaire.
- Connecter Laravel Cloud au dossier du projet à la racine du dépôt. Ne pas sélectionner le dossier parent du workspace.

## Configuration Laravel Cloud

Laravel Cloud détecte l'application Laravel et gère son runtime PHP, les domaines HTTPS et les ressources attachées depuis son tableau de bord. Aucun fichier `cloud.yaml`, Procfile ou pipeline de déploiement propre à Cloud n'est requis.

Paramètres initiaux à utiliser dans un environnement de préproduction :

- Runtime PHP 8.3 ou supérieur (le projet exige `^8.3`).
- Ajouter une base Laravel MySQL dans la même région que l'application. Cloud injecte les variables `DB_*`; ne pas copier la configuration SQLite locale.
- Ajouter un bucket d'objets **privé**. Choisir le nom de disque `s3`; Cloud injecte les identifiants `AWS_*` et configure `FILESYSTEM_DISK` si le bucket est défini par défaut.
- Garder `SESSION_DRIVER=database` et `CACHE_STORE=database` au départ.
- Configurer les secrets SMTP, PayDunya et les autres clés dans Cloud Secrets, jamais dans GitHub ou un fichier du dépôt.
- Utiliser l'URL HTTPS `*.laravel.cloud` pour les premiers tests de callback avant d'enregistrer un domaine personnalisé.

La configuration `config/filesystems.php` définit les disques Laravel local/public et le disque S3 attendu par Cloud. L'adaptateur `league/flysystem-aws-s3-v3` n'est toutefois pas encore dans `composer.json` / `composer.lock`, et les justificatifs de paiement sont encore écrits sur le disque local. Il faut installer l'adaptateur, adapter et tester `ManualPaymentProofStorage` pour utiliser le disque S3 privé avant d'accepter des justificatifs en production. Le système de fichiers local de Cloud est éphémère et peut différer entre déploiements et réplicas.

La compilation frontend nécessite aussi Node, Vite et un `package-lock.json`; le manifeste de verrouillage npm est absent. Générer et valider ce fichier avant de configurer une commande reproductible `npm ci && npm run build` dans Cloud. Ne pas utiliser `npm ci` tant que le lockfile n'existe pas.

## Base de données et commandes de déploiement

- La base SQLite locale `database/black-wolf-local.sqlite` est une donnée utilisateur et reste exclue de Git. Elle n'est ni supprimée ni convertie ici.
- SQLite n'est pas pris en charge en production sur Cloud. Avant la mise en ligne, faire une sauvegarde vérifiée, créer MySQL, transférer les données avec contrôle des volumes et des relations, puis sauvegarder les justificatifs existants dans le bucket privé et vérifier leurs SHA-256.
- La commande de déploiement `php artisan migrate --force` est à laisser désactivée jusqu'à ce que le transfert ait été répété et validé en préproduction. N'utiliser aucune migration destructive et conserver une sauvegarde restaurable.
- Commande de build à finaliser après ajout du lockfile frontend et de l'adaptateur S3 : `composer install --no-dev --prefer-dist --optimize-autoloader && npm ci && npm run build && php artisan optimize`.
- Aucun déploiement, import de base, migration ou paiement réel n'a été lancé dans cette préparation.

## Vérifications avant connexion GitHub

Le `.gitignore` exclut `.env`, les fichiers `.env.*` sauf le modèle `.env.example`, la base locale, les clés/certificats, les identifiants Composer/npm/AWS, les journaux, les justificatifs privés et les sauvegardes. Le `.dockerignore` applique également ces exclusions au contexte d'image Docker.

`composer.lock` est présent. `package-lock.json` manque. Le dépôt local n'a encore aucun commit; avant le premier push, vérifier une dernière fois les fichiers proposés par Git et ne jamais forcer l'ajout de `.env`, de base locale, de sauvegarde ou de justificatif.

## Étapes utilisateur dans Laravel Cloud

1. Créer/ouvrir le compte Laravel Cloud et connecter le fournisseur GitHub.
2. Créer un dépôt **privé** à partir de la racine Laravel du projet, ou relier ce dépôt local à un dépôt privé après revue du premier commit.
3. Dans Cloud, créer une application depuis la branche `main`, sélectionner PHP 8.3+ et une région adaptée.
4. Créer une préproduction avec MySQL et un bucket privé; ne pas activer les paiements.
5. Renseigner les secrets dans Cloud. Ne pas coller les clés dans une conversation, un ticket ou un fichier commité.
6. Finaliser le stockage S3 et le lockfile npm, faire passer la CI et tester les flux en préproduction.
7. Après sauvegarde et validation du transfert de données, demander une validation explicite avant le premier déploiement de production.
