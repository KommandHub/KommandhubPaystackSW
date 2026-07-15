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
