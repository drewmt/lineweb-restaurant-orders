# Lineweb Restaurant Orders build sources

Lineweb Restaurant Orders ships its runtime assets locally. No remote code is downloaded or executed, except the documented Stripe.js payment-service script when Stripe is enabled.

## Reproduce the assets

Requirements: Node.js 20 or newer and npm.

```bash
npm ci
npm run build
```

The build performs two deterministic tasks:

- Tailwind CSS compiles `tailwind.input.css` to `assets/css/app.css`.
- `tools/copy-vendor-assets.mjs` copies the pinned Lucide and QRCode.js browser files and their licenses from `node_modules` to `assets/vendor`.

Exact package versions are recorded in `package-lock.json`. The release ZIP includes the asset source, lockfile, and copy script so every bundled file can be reproduced; it excludes `node_modules` and other installed development dependencies.

## Runtime dependencies

- Tailwind CSS 4.x — MIT License.
- Tailwind CLI 4.x — MIT License.
- Lucide 0.468.0 — ISC License.
- QRCode.js 1.0.0 — MIT License.
- Stripe.js v3 — loaded directly from `https://js.stripe.com/v3/` only when the administrator enables a fully configured Stripe checkout.

The original Lineweb Restaurant Orders source is copyright Andrew Matia / Lineweb and released under GPL-2.0-or-later. Third-party copyrights remain with their respective authors.
