# SnapOrder by Lineweb

SnapOrder by Lineweb is a self-hosted WordPress food menu and ordering plugin created by [Andrew Matia / Lineweb](https://www.lineweb.gr/).

Version 1.0.0 is the first public release and contains the complete planned free feature set. There is no Pro edition, feature gate, trial, commission, author telemetry, or upgrade prompt.

## Included

- Food products, categories, variants, extras, dietary labels, and allergens.
- Delivery, pickup, and optional table ordering.
- Server-authoritative totals with cash and optional Stripe payments.
- Order management, tracking, receipts, QR codes, aggregate reporting, and optional PWA support.
- Optional Twilio WhatsApp notifications and configurable privacy retention.

See [`readme.txt`](readme.txt) for installation, service disclosures, privacy behavior, and the full feature list.

## Requirements

- WordPress 6.2 or newer.
- PHP 7.4 or newer.
- Node.js 20 and Composer 2 only when developing or rebuilding assets.

## Development

```bash
composer install
npm ci
npm run build
composer test
composer lint
npm run test:js:syntax
```

The isolated WordPress integration environment uses `npm run wp-env -- start`, followed by `npm run test:php`.

Build the WordPress-ready archive with:

```bash
npm run build:release
```

Development and test files remain in GitHub for review. The release builder excludes them from the installable plugin ZIP.

## Security

Please do not publish exploitable security details in a public issue. Follow [`SECURITY.md`](SECURITY.md) for responsible reporting.

## Support and custom work

Use the WordPress.org support forum for the included plugin features. For tailored integrations or restaurant-specific workflows outside the included scope, visit [www.lineweb.gr](https://www.lineweb.gr/).

## Copyright and license

Copyright 2025-2026 Andrew Matia / Lineweb. SnapOrder is licensed under GPL-2.0-or-later; see [`LICENSE`](LICENSE). Third-party components retain their original copyrights and compatible licenses; see [`LICENSE.md`](LICENSE.md).
