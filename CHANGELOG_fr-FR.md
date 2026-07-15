# 0.9.0-beta.1

Version préliminaire pour le développement interne, l'assurance qualité et les
tests sandbox/staging. Pas encore soumise au Shopware Store. L'API publique et
les espaces de noms peuvent encore changer avant la version `1.0.0`.

- Paiement Paystack pour Shopware 6 : carte, virement bancaire, USSD et mobile money.
- La vérification du paiement contrôle le statut, le montant et la devise avant qu'une commande ne soit marquée comme payée.
- Remboursements depuis la page de détail de la commande, y compris les remboursements partiels, avec une protection côté serveur contre le sur-remboursement.
- Autorisation d'administration dédiée "Remboursement Paystack" pouvant être attribuée à des rôles (dépend de l'autorisation d'édition des commandes).
- Gestion des webhooks pour `charge.success`, `refund.pending` et `refund.processed`, avec vérification de signature.
- Vérification du compte bancaire dans le compte client (résolution de compte via Paystack).
- Traitement correct des montants pour toutes les devises prises en charge par Paystack, y compris les devises à zéro et trois décimales (par ex. XOF, RWF, KWD). Le plugin ne crée pas de devises ni de langues dans la boutique.
- Interface du plugin traduite en anglais, allemand et français.
- Journalisation configurable, mode sandbox/live et montant minimum de remboursement.
- Supporte Shopware 6.6 et 6.7.
