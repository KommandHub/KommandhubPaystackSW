# 0.9.0-beta.2

Second pre-release. Still for internal development, QA and sandbox/staging
testing; not yet submitted to the Shopware Store.

- Debug logging is now resolved per sales channel. `enableDebugging` and the log levels are ordinary plugin settings, so a merchant can scope them to a single sales channel; previously they were only read from the global scope, so enabling debugging on one sales channel produced no output at all.
- Storefront bank-verification template blocks are namespaced, so they no longer collide with other plugins that extend the same account templates.
- Relicensed from MIT to the Apache License 2.0, adding an explicit patent grant, an explicit reservation of trademark rights, and a `NOTICE` file that carries attribution into forks.
- The plugin now states clearly that it is an independent, third-party integration and is not affiliated with or endorsed by Paystack. Added a trademark and branding policy (`TRADEMARKS.md`).
- Replaced the third-party logos used as the plugin and administration icons with KommandHub branding.
- Build tooling: `make prepare` now works for pre-release versions and on repeat runs, and the test container ships `shopware-cli` for plugin validation.

---

# 0.9.0-beta.1

Pre-release for internal development, QA and sandbox/staging testing. Not yet
submitted to the Shopware Store. The public API and namespaces may still
change before `1.0.0`.

- Paystack payment for Shopware 6: card, bank transfer, USSD and mobile money.
- Payment verification checks status, amount and currency before an order is marked paid.
- Refunds from the order detail page, including partial refunds, with a server-side over-refund guard.
- Dedicated "Paystack refund" admin permission that can be assigned to roles (depends on the order editor permission).
- Webhook handling for `charge.success`, `refund.pending` and `refund.processed`, with signature verification.
- Bank-account verification in the customer account (account resolution via Paystack).
- Correct amount handling for every currency Paystack supports, including zero- and three-decimal currencies (e.g. XOF, RWF, KWD). The plugin does not create currencies or languages in the shop.
- Plugin interface translated into English, German and French.
- Configurable logging, sandbox/live mode and a minimum refund amount.
- Supports Shopware 6.6 and 6.7.
