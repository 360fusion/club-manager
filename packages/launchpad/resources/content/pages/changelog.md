---
title: "Changelog"
description: "What is new in Launchpad, with a dated note each time something that matters changes."
updated: 2026-11-02
version: 2
noindex: false
---
## What's new

### 2 November 2026

- **Vue and Inertia option.** Every page can be drawn as a Vue page with plain props. Run `php artisan launchpad:install --vue` to turn it on.
- **New marketing site.** The default home, features, about and developer pages now describe Launchpad itself.
- **Sync to another service.** An optional, signed outbox tells a separate service about changes to people, teams and plans. It is off by default.
- **Easier moves.** Passwords made elsewhere with Argon2 are accepted, and old ids can be kept.
- **Tidier install.** The installer tidies `User.php` with the app's own Pint.

### Earlier

- Accounts, with sign-in by email, Google or Microsoft, and two-factor protection.
- Teams, with four roles and invitations.
- Billing with Stripe, a Filament admin and a Markdown help centre and FAQ.
