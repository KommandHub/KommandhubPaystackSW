# 1.0.0

Sortie initiale.

- Paiement Paystack pour Shopware 6 : carte, virement bancaire, USSD et mobile money.
- La vérification du paiement contrôle le statut, le montant et la devise avant qu'une commande ne soit marquée comme payée.
- Remboursements depuis la page de détail de la commande, y compris les remboursements partiels, avec une protection côté serveur contre le sur-remboursement.
- Autorisation d'administration dédiée "Remboursement Paystack" pouvant être attribuée à des rôles (dépend de l'autorisation d'édition des commandes).
- Gestion des webhooks pour `charge.success`, `refund.pending` et `refund.processed`, avec vérification de signature.
- Vérification du compte bancaire dans le compte client (résolution de compte via Paystack).
- Prise en charge des devises et langues africaines, y compris les devises à zéro et trois décimales.
- Journalisation configurable, mode sandbox/live et montant minimum de remboursement.
- Supporte Shopware 6.6 et 6.7.
