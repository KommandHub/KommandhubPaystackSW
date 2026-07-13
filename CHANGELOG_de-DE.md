# 1.0.0

Erstveröffentlichung.

- Paystack-Zahlung für Shopware 6: Karte, Banküberweisung, USSD und Mobile Money.
- Zahlungsprüfung kontrolliert Status, Betrag und Währung, bevor eine Bestellung als bezahlt markiert wird.
- Rückerstattungen aus der Bestelldetailseite, inklusive Teilrückerstattungen, mit serverseitiger Absicherung gegen Überrückerstattung.
- Eigene Berechtigung „Paystack-Rückerstattung", die Rollen zugewiesen werden kann (abhängig von der Bestell-Editor-Berechtigung).
- Webhook-Verarbeitung für `charge.success`, `refund.pending` und `refund.processed` mit Signaturprüfung.
- Bankkontoprüfung im Kundenkonto (Kontoauflösung über Paystack).
- Unterstützung afrikanischer Währungen und Sprachen, einschließlich Währungen mit null und drei Dezimalstellen.
- Konfigurierbares Logging, Sandbox-/Live-Modus und ein Mindestrückerstattungsbetrag.
- Unterstützt Shopware 6.6 und 6.7.
