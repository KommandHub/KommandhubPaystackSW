# 0.9.0-beta.1

Vorabversion für interne Entwicklung, QA und Sandbox-/Staging-Tests. Noch
nicht im Shopware Store eingereicht. Die öffentliche API und Namespaces können
sich vor `1.0.0` noch ändern.

- Paystack-Zahlung für Shopware 6: Karte, Banküberweisung, USSD und Mobile Money.
- Zahlungsprüfung kontrolliert Status, Betrag und Währung, bevor eine Bestellung als bezahlt markiert wird.
- Rückerstattungen aus der Bestelldetailseite, inklusive Teilrückerstattungen, mit serverseitiger Absicherung gegen Überrückerstattung.
- Eigene Berechtigung „Paystack-Rückerstattung", die Rollen zugewiesen werden kann (abhängig von der Bestell-Editor-Berechtigung).
- Webhook-Verarbeitung für `charge.success`, `refund.pending` und `refund.processed` mit Signaturprüfung.
- Bankkontoprüfung im Kundenkonto (Kontoauflösung über Paystack).
- Korrekte Betragsverarbeitung für alle von Paystack unterstützten Währungen, einschließlich Währungen mit null und drei Dezimalstellen (z. B. XOF, RWF, KWD). Das Plugin legt keine Währungen oder Sprachen im Shop an.
- Plugin-Oberfläche auf Englisch, Deutsch und Französisch verfügbar.
- Konfigurierbares Logging, Sandbox-/Live-Modus und ein Mindestrückerstattungsbetrag.
- Unterstützt Shopware 6.6 und 6.7.
