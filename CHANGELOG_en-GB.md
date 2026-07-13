# 1.0.0

Initial release.

- Paystack payment for Shopware 6: card, bank transfer, USSD and mobile money.
- Payment verification checks status, amount and currency before an order is marked paid.
- Refunds from the order detail page, including partial refunds, with a server-side over-refund guard.
- Dedicated "Paystack refund" admin permission that can be assigned to roles (depends on the order editor permission).
- Webhook handling for `charge.success`, `refund.pending` and `refund.processed`, with signature verification.
- Bank-account verification in the customer account (account resolution via Paystack).
- African currency and language support, including zero- and three-decimal currencies.
- Configurable logging, sandbox/live mode and a minimum refund amount.
- Supports Shopware 6.6 and 6.7.
