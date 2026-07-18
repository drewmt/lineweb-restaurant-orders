# SnapOrder by Lineweb changelog

## 1.0.0 - 2026-07-18

- Rebuilt order totals from server-side catalogue data and ignored browser-supplied prices.
- Added Stripe PaymentIntents, signed webhooks, idempotent order creation, and verified payment completion.
- Protected tracking details with opaque order tokens.
- Hardened admin order permissions, input validation, output escaping, and receipt access.
- Preserved complete delivery addresses and canonical cart details.
- Bundled Tailwind, Lucide, and QR runtime assets locally.
- Scoped frontend loading and PWA caching to SnapOrder surfaces.
- Added privacy-policy guidance, configurable order anonymization, and opt-in uninstall cleanup.
- Added unit and integration test foundations plus WordPress coding-standard tooling.
- Kept every included feature freely available, with no paid gates or upgrade prompts.
