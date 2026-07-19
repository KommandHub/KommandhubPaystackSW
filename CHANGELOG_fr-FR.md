# 0.9.0-beta.2

Deuxième version préliminaire. Toujours destinée au développement interne, à
l'assurance qualité et aux tests sandbox/staging ; pas encore soumise au
Shopware Store.

- La journalisation de débogage est désormais résolue par canal de vente. `enableDebugging` et les niveaux de journalisation sont des réglages de plugin ordinaires, configurables par canal de vente ; ils n'étaient auparavant lus qu'au niveau global, de sorte que l'activation sur un seul canal restait sans effet.
- Les blocs de template de la vérification bancaire côté storefront sont désormais préfixés et n'entrent plus en conflit avec d'autres plugins étendant les mêmes templates de compte.
- Passage de la licence MIT à la licence Apache 2.0 : concession de brevet explicite, réserve explicite des droits de marque et fichier `NOTICE` transmettant l'attribution aux forks.
- Le plugin indique désormais clairement qu'il s'agit d'une intégration indépendante et tierce, non affiliée à Paystack ni approuvée par Paystack. Ajout d'une politique de marque et d'image (`TRADEMARKS.md`).
- Les logos tiers utilisés comme icônes du plugin et de l'administration ont été remplacés par l'identité visuelle KommandHub.
- Outils de build : `make prepare` fonctionne désormais avec les versions préliminaires et lors d'exécutions répétées, et le conteneur de test embarque `shopware-cli` pour la validation du plugin.

---

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
