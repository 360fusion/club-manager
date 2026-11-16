# Launchpad

Accounts, workspaces, Stripe billing, an admin and a default website for new Laravel builds. The plan and the full page list are in
[docs/spec.md](docs/spec.md).

What is in it:
- **Accounts:** sign-up, sign-in, password reset, email verification (with a grace period), 2FA, social login, security page, data export, account closure and purge.
- **Account area:** a side menu and header user menu; profile (name, email with re-verification, time zone), security with linked sign-in methods and signed-in devices, optional-email choices (`NotificationPreferences::wants()`), and a personal activity list. The footer is four link columns from config.
- **Workspaces:** members, invitations, four roles, and per-person permission ticks (manage billing, manage people; an app adds its own in `launchpad.workspaces.permissions`). A person without a permission sees a page in the normal layout that says why and links to their own workspace. Every route is classified.
- **API and webhooks:** per workspace, for its customers: API tokens (hashed, shown once, abilities, expiry, a page for each with its last 30 days of calls, replacing one with a grace period, `/api/v1` with `launchpad.api` middleware) and signed outbound webhooks with retries and an address guard. Managed by anyone who may manage the workspace; see [docs/api-and-webhooks.md](docs/api-and-webhooks.md) and, for JSON errors, per-plan rate limits and idempotency keys, [docs/api-conventions.md](docs/api-conventions.md), which also covers the OpenAPI description at `/api/v1/openapi.json` and the docs page at `/api/docs`.
- **For building an app:** a trait that keeps a model's rows to the current workspace, a Files page (private disk, type and size checks, quota, signed links), a Team activity log for team admins, feature flags (per plan, per percentage, per workspace, switched in HQ) and a Getting started card. See [docs/building-on-launchpad.md](docs/building-on-launchpad.md). The package ships guidelines and a skill for [Laravel Boost](https://laravel.com/docs/boost) (`resources/boost`), so an app that runs `boost:install` has its AI agents follow these conventions.
- **Languages:** every page, mail and Vue screen is translatable (the English sentence is the key); a build lists its languages, adds `lang/<locale>.json` and checks it with `launchpad:lang:missing`. See [docs/translations.md](docs/translations.md).
- **Going live:** `launchpad:doctor` checks the app key, URL, migrations, files disk, queue, cookies, mail and (with `--running`) that the scheduler and a queue worker are alive; a Forge recipe with the deploy script is in [docs/deploy.md](docs/deploy.md).
- **Team single sign-on:** a team's owner connects its company's OpenID Connect provider (Okta, Entra, Google Workspace), proves the email domain with a DNS record, and can require it; people join the team on first sign-in. See [docs/building-on-launchpad.md](docs/building-on-launchpad.md).
- **Billing:** plans, entitlements, promo codes (made in HQ, applied from `?promo=` links), Stripe checkout (also without an account first), plan changes and cancelling through Stripe's portal, the billing page, webhooks with duplicate protection, `launchpad:doctor`.
- **Admin (Filament 5):** users, workspaces, subscriptions, Stripe events, audit log, plan limits, contact inbox, help feedback, site settings, page review; staff on their own guard with a required authenticator step.
- **Email templates:** every mail has default wording in code and can be edited in HQ (Markdown, `{{variables}}`, preview, test send, reset); an app registers its own. See [docs/email-templates.md](docs/email-templates.md).
- **Website:** home, features, about, pricing, contact (spam-guarded, mailed, with a reference), nine legal page templates, cookie consent, a Markdown help centre (17 guides) and FAQ (10 answers) with search and feedback, robots.txt, sitemap.xml, llms.txt, security.txt. Pages are Markdown with `{{tokens}}`; an app overrides one file at a time (`launchpad:publish`) and `launchpad:status` says when a default it copied has changed. `help:build` checks every file and link.

The tests run on SQLite, MySQL 8.4 and Postgres 16 (`LAUNCHPAD_DB=mysql vendor/bin/phpunit`). Stripe itself is only exercised through a fake: see
[docs/stripe-setup.md](docs/stripe-setup.md) for the test-mode check. The legal text is a template, not legal advice: `launchpad:doctor` fails a
production site until each legal page is marked reviewed.

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test src tests config database
```

The country block list ships empty: a build chooses its own.

Optional and off by default: a signed token for a separate service (`docs/service-token.md`) and an outbox that tells such a service about changes to people, workspaces and plans.
