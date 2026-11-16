# Conversion fixe USD/XOF

Les tarifs de catalogue restent en USD. L’équivalence XOF affichée pour les nouvelles commandes applique le taux fixe convenu : **1 USD = 600 XOF**. Les nouveaux paiements sont uniquement en USDT BEP20, pour le même montant numérique que le prix USD. Aucun cours BCEAO, CurrencyAPI ou autre taux externe n’est consulté.

Le serveur calcule le montant en XOF à partir du tarif USD approuvé, enregistre le taux `600.0000`, le fournisseur `fixed-600` et le montant arrondi à l’unité dans l’instantané de commande. Le navigateur ne peut pas imposer un montant ou un taux.

Les commandes historiques conservent leur taux, source et montant enregistrés. Une ancienne commande non payée au taux BCEAO doit être actualisée avant paiement; une commande déjà payée et son historique ne sont pas réécrits.

La durée du récapitulatif est définie par `FX_QUOTE_VALID_MINUTES`. `php artisan blackwolf:refresh-fx` vérifie le taux fixe localement et n’effectue aucune requête réseau.
