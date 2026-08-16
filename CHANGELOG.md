# Lineweb Restaurant Orders changelog

## 1.0.2 - 2026-08-16

- Preserved the current menu page when redirecting customers to order tracking on sites using plain permalinks.

## 1.0.1 - 2026-08-15

- Added current desktop and mobile product screenshots, including the mobile menu homepage, to the public README.
- Adopted the distinctive `snaporder` prefix for options, metadata, hooks, post types, taxonomies, JavaScript globals, and CSS selectors.
- Added a one-time migration that preserves settings, products, categories, orders, banners, templates, shortcodes, metadata, and view statistics from 1.0.0.
- Replaced raw inline script and style output with properly enqueued frontend, PWA, QR, receipt, and admin assets.
- Updated the bundled Lucide icon library to 1.31.0.
- Restored the promotional banner content type and its secure admin fields.

## 1.0.0 - 2026-07-18

- Rebuilt order totals from server-side catalogue data and ignored browser-supplied prices.
- Added Stripe PaymentIntents, signed webhooks, idempotent order creation, and verified payment completion.
- Protected tracking details with opaque order tokens.
- Hardened admin order permissions, input validation, output escaping, and receipt access.
- Preserved complete delivery addresses and canonical cart details.
- Bundled Tailwind, Lucide, and QR runtime assets locally.
- Scoped frontend loading and PWA caching to Lineweb Restaurant Orders surfaces.
- Added privacy-policy guidance, configurable order anonymization, and opt-in uninstall cleanup.
- Added unit and integration test foundations plus WordPress coding-standard tooling.
- Kept every included feature freely available, with no paid gates or upgrade prompts.
