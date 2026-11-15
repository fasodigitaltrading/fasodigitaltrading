# Taux de référence USD/XOF de la BCEAO

## Source et fonctionnement

Le dollar reste la devise de référence des offres. Le serveur lit le cours quotidien publié par la BCEAO sur la page officielle configurée par `FX_BCEAO_REFERENCE_URL`. Aucune clé CurrencyAPI n’est requise; l’ancien adaptateur CurrencyAPI n’est pas utilisé.

Le parseur n’accepte que le domaine HTTPS `www.bceao.int`, extrait le taux de la ligne Dollar US et conserve le taux, la date de publication, la source et l’heure de récupération. La mise en cache réduit les requêtes; un cache périmé peut être affiché comme historique, mais il ne permet pas de créer un paiement.

Une commande conserve son taux et son montant XOF dans un instantané immuable. Les nouveaux paiements exigent une référence BCEAO récente et un récapitulatif non expiré. Le code refuse un taux absent ou invalide; il ne remplace jamais le cours par 600 XOF ou une conversion inventée.

La référence est quotidienne, pas une cotation intrajournalière universelle. Le montant XOF est arrondi à l’unité selon l’algorithme du service et verrouillé pour la durée du récapitulatif configurée (`FX_QUOTE_VALID_MINUTES`).

## Rafraîchir et contrôler

```powershell
php artisan blackwolf:refresh-fx
```

La commande contacte la source BCEAO en environnement réel, affiche la valeur, sa date, l’URL source et l’heure de récupération, puis renvoie un code d’erreur si aucune référence assez récente n’est disponible. En tests, la commande n’effectue aucune requête réseau implicite.

## Commandes et paiements manuels

Les paiements PayDunya et les transferts manuels utilisent le montant XOF enregistré sur la commande. Les soumissions manuelles contrôlent que le taux, la source officielle et le calcul correspondent à cet instantané; l’administrateur valide ensuite la réception réelle des fonds. Les anciennes commandes déjà enregistrées à `fixed-600` restent traitées comme des documents historiques et ne modifient pas le catalogue courant.
