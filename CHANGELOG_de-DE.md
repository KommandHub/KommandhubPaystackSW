# 0.9.0-beta.2

Zweite Vorabversion. Weiterhin für interne Entwicklung, QA und Sandbox-/
Staging-Tests; noch nicht im Shopware Store eingereicht.

- Die Debug-Protokollierung wird jetzt pro Verkaufskanal ausgewertet. `enableDebugging` und die Log-Level sind normale Plugin-Einstellungen und lassen sich je Verkaufskanal setzen; bisher wurden sie nur global gelesen, sodass die Aktivierung für einen einzelnen Verkaufskanal wirkungslos blieb.
- Die Template-Blöcke der Bankverifizierung im Storefront sind jetzt eindeutig benannt und kollidieren nicht mehr mit anderen Plugins, die dieselben Konto-Templates erweitern.
- Lizenzwechsel von MIT zur Apache License 2.0: mit ausdrücklicher Patentlizenz, ausdrücklichem Markenvorbehalt und einer `NOTICE`-Datei, die die Urheberangaben in Forks weiterträgt.
- Das Plugin weist nun deutlich darauf hin, dass es eine unabhängige Drittanbieter-Integration ist und nicht mit Paystack verbunden oder von Paystack unterstützt wird. Ergänzt um eine Marken- und Branding-Richtlinie (`TRADEMARKS.md`).
- Die als Plugin- und Administrations-Icon verwendeten fremden Logos wurden durch KommandHub-Branding ersetzt.
- Build-Werkzeuge: `make prepare` funktioniert jetzt auch mit Vorabversionen und bei wiederholten Läufen, und der Testcontainer enthält `shopware-cli` für die Plugin-Validierung.

---

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
