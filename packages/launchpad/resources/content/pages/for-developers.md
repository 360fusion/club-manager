---
title: "For developers"
description: "How to add Launchpad to a Laravel project, what you can override, and how updates work."
updated: 2026-11-02
version: 1
noindex: false
---
# For developers

Launchpad is a Composer package. You add it to a fresh Laravel app, run one command, and the app has accounts, teams, billing, an admin and a default website.

You need PHP 8.3 or newer, Composer and a database. SQLite is fine to start with, and MySQL 8 and Postgres 16 are tested.

## Install

```bash
composer create-project laravel/laravel my-site
cd my-site
composer require 360fusion/launchpad
php artisan launchpad:install --migrate
php artisan launchpad:make-admin you@example.com --name="Your Name"
```

If the package is not on Packagist for you, add its repository to `composer.json` first with `composer config repositories.launchpad vcs <your repository address>`.

`launchpad:install` is safe to run twice. It:

1. Publishes `config/launchpad.php`, where you set the site name, the plans and which pages are on.
2. Adds the traits and interfaces to `app/Models/User.php`, or prints the lines to add.
3. Removes Laravel's welcome route, which would hide the home page.
4. Copies the error pages into `resources/views/errors`.
5. Appends the Launchpad keys to `.env.example`.
6. Adds a smoke test to `tests/Feature`.

Add `--vue` to draw the pages with Vue and Inertia instead of Blade.

## Make it yours

Everything is overridable, one piece at a time.

- **Settings:** `config/launchpad.php` holds the site name, accent colour, header links, plans, limits and the pages that are on.
- **Words:** sign in at `/admin` and open Site settings to set the company name, support email and address. Pages fill them in wherever they use a placeholder.
- **Look:** run `php artisan vendor:publish --tag=launchpad-views` and the copies in `resources/views/vendor/launchpad` win over the package's.
- **Home page:** add `Route::get('/', ...)` to `routes/web.php` and yours replaces the default.
- **New pages:** add a Markdown file to `resources/launchpad/pages/` and switch it on in `launchpad.site.pages`.
- **Plans:** replace `launchpad.billing.plans` with yours and set the Stripe price ids in `.env`. Then run `php artisan launchpad:doctor --remote`.

## Change a page

```bash
php artisan launchpad:publish privacy
```

The copy lands in `resources/launchpad/pages/privacy.md`. Edit it there. The package's own file is left alone, and the copy remembers which default it came from.

Help guides and FAQ answers work the same way with `--kind=article` or `--kind=faq`.

## How updates work

You never hold a copy of a page until you ask for one, so `composer update` carries every fix to the pages you have not changed. For the ones you have:

```bash
composer update
php artisan launchpad:status
php artisan launchpad:status --diff
php artisan help:build
php artisan test
```

`launchpad:status` says which of your files are copies and whether their default has changed since. `--diff` shows what changed, so you can bring across what you want. `help:build` checks every page, guide and link.

Launchpad uses semantic versions. A minor version never removes a setting, a route name or a column.

## Before you go live

- Follow `docs/stripe-setup.md` in the package to set up your Stripe products and webhook, then run `php artisan launchpad:doctor --remote` against your test account.
- Have the legal pages read, edit them if needed, and mark each as reviewed in the admin. A production site fails `launchpad:doctor` until you have.
- Set up a real mailer, a queue worker and the scheduler, which purges closed accounts each day.

Stripe is exercised in Launchpad's own tests through a fake, so run the test-mode check with your own account before you take payments.

Back to [features](/features) or the [help centre](/help).
