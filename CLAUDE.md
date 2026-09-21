# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer setup                                  # install, .env, key, migrate, npm build
composer dev                                    # runs the dev processes (`php artisan dev`, see `dev:list`)
npm run build                                   # Vite build; needed after any Vue/CSS change if `composer dev` isn't running
php artisan test --compact                      # full suite (in-memory SQLite, ~8s)
php artisan test --compact tests/Feature/SecurityAuditTest.php --filter=test_name
vendor/bin/pint --dirty --format agent          # required after touching PHP
composer analyse                                # PHPStan/Larastan, held at level 1 (higher levels report hundreds of model-type errors)
```

Herd serves the app at `club-manager.test`. The CLI PHP memory limit (128M) is low: tests that decode large images set `memory_limit` themselves.

## Architecture

Multi-tenant club manager (Masonic lodges and rowing clubs) on Laravel 13 / Inertia v3 / Vue 3 / Tailwind v4, with Livewire 4 for the accounting and committee screens.

**Tenancy and URLs.** A user belongs to clubs through the `club_user` pivot (`role`: owner/admin/treasurer/coach/member, `status`: active/pending/past). Club slugs sit at the top level of the URL space, so `App\Support\ReservedClubSlugs` keeps them from shadowing fixed paths. Areas:
- `/members/...`: the unified member area (dashboard, calendar, inbox, per-club pages at `/members/{slug}/...`). Queries come from `App\Support\MemberScope`, which limits every page to the user's active clubs (all of them, or one).
- `/{slug}/admin/...`: club admin. `/site/{clubSlug}`: public website. `/{slug}` and old `/clubs/...` URLs are redirects.

**Authorization is keyed on the URL shape, not per controller.** `EnsureUserCanAdministerClub` is appended to the web group and self-activates on any route with a `{clubSlug}`/`{slug}` segment followed by `/admin`. It maps the first segment after `/admin` to a capability via `config/club_permissions.php` (`route_map`), and each club can override roles per capability in `settings.permission_matrix`. Consequences:
- A new admin section must be added to `route_map`, otherwise it falls back to "any staff role".
- Club actions that live outside `/admin` (member approve/import/export, domain) need `->middleware('club.admin:manage_members')` (the alias takes a capability).
- Inside code, use `App\Support\ClubAccess` (`can`, `authorize`, `canAssignRole`); only owners may grant, change or remove `owner`.
- Livewire updates hit `/livewire/update`, not the page URL, so the admin middleware is registered as persistent and every public identifier (`clubSlug`, `meetingId`, ...) is `#[Locked]`. Look records up with `where('club_id', ...)`, never bare `find($id)` from client input, and load users through `$club->users()`.
- `tests/Feature/AdminRouteSweepTest.php` requests every admin route as a member, another club's admin and each staff role; it fails when a route is left unprotected or unmapped.

**Code layout.** `app/Models`, `app/Http/Controllers` and `resources/js/Pages` hold most of the app (Inertia pages, controllers often over 500 lines; `AccountingAdminController` and `Accounting/Index.vue` are very large). `app/Domains/ClubAccounting` is a second, Livewire-based layer (models on `club_acc_*` tables, services, Blade views in `resources/views/livewire`) for banking, reconciliation, subscriptions, members roster, charity, candidates and committee governance. Shared UI kit: `resources/js/Components/Ui`; dark mode is the `.dark` class and the members layout has an optional side nav (`Utils/navMode`).

**Cross-cutting helpers in `app/Support`** that new code should reuse:
- `UploadRules` / `ImageDownscaler`: every upload uses them (no SVG or HTML, 6000px max side, photos shrunk to 1920px). Rules on nested keys make `validated()` drop the sibling keys, so validate block files in a separate call.
- `RichTextSanitizer` and the `SanitizedHtml`/`SanitizedHtmlBlocks` casts sanitise on write for posts, pages and newsletters (those fields are rendered with `v-html`); block URL fields are also scheme-checked there.
- `Csv::safe()/line()` for any CSV output (formula injection, quoting).
- `Currencies` / `Club::currencyCode()`: each club keeps all its books in one currency (`settings.currency`, else its province's country, else GBP). Only currencies of countries that have a grand lodge or province are offered (registry in `Currencies`; add a country there to support a new one). The currency locks once the club has financial records (amounts are not converted). Never hard-code `£` or `'GBP'`: use `Currencies::format($amount, $club)` / `$club->currencyCode()` in PHP, `$cs` (symbol) in Vue templates, `currencySymbol()` in Vue scripts, and `$cs` in Livewire Blade views (a view composer supplies it from the component's `clubSlug`). Pages spanning several clubs must format per club on the server.
- **Events:** bookings live in `event_registrations` (one booking) and `event_attendees` (the booker plus each guest, each with their own ticket type and starter/main/dessert). Never write bookings directly: go through `App\Services\Events\EventRegistrationService` (capacity with a row lock, waitlist, guest limits, meal and ticket validation, public guest tokens). An organiser adds or edits a booking for a member or visitor with `EventRegistrationService::saveByOrganiser` (through `EventOrganiserBookingController`; member search is `admin.events.member_search`), which skips the member-facing closing date, audience and guest-limit checks but still validates meals and tiers and refuses a price change once something is paid. Member and public event payloads come from `EventPayload`; guest list, catering summary and CSV come from `GuestListBuilder`. The old `event_user` table is no longer written and can be dropped once the new pages are verified. Edit dishes and ticket types in place (by id), because attendees point at them.
- **Event payments:** a lodge sets up `ClubPaymentMethod`s once (bank transfer, card, pay later, on the night; config is encrypted) and switches them on per event with `EventPaymentMethod`, each with an optional discount or fee. The lodge's Payment options page lists every kind with a switch (`is_active`); an option can only be switched on once `hasCompleteDetails()`, and switching it on adds it to every upcoming event that has no row for it (`offerOnUpcomingEvents`), so new events and existing ones offer it by default and the event form is only for per-event discounts and fees. The event form keeps an unsaved draft in the browser (`useDraft`). `EventPricing` is the only place a price is worked out (whole pence; the booking screen, the stored booking and the advertised price all use it); fees are refused on card and bank. Never change a booking's paid status directly: use `EventPaymentService`, which writes the append-only `event_payment_log` (who, when, amount, reason) and posts the ledger entry. Reminders run from `app:send-event-payment-reminders` (needs the server scheduler). Card payments use each lodge's own Stripe account: `StripeGateway` is the only code that calls Stripe (mock it in tests), `EventOnlinePayment` creates the Checkout session (repricing to the online option so paying early keeps the discount) and handles `POST /webhooks/stripe/{club}` (signature-checked with that lodge's secret, tied to the booking's own session id, recorded once per Stripe payment id). Both Cashier packages' routes are disabled. A lodge can also connect Stripe to the platform instead of pasting keys (`EVENTS_PLATFORM_PAYMENTS`, `config/platform_payments.php`): `StripeConnectController` links an existing account (Standard OAuth) or creates an Express one, `StripeConnectGateway` is the only code calling Connect, and the card option's `config.stripe_mode` is `connect`. Payments are direct charges on the lodge's account with the platform commission (`PlatformFees`) as `application_fee_amount`, confirmed by the single signed webhook `POST /webhooks/stripe-connect` (`EventOnlinePayment::handleConnectWebhook`) and logged in `platform_payments`; a lodge must accept the terms (`ClubPlatformAccount::canTakePayments`) before it is offered. Hold-then-pay-out mode and payout statements are not built. Card payments stay hidden until `EVENTS_ONLINE_PAYMENTS=true` and the lodge has saved both Stripe keys. PayPal works the same way beside card (`PayPalGateway` is the only code calling PayPal; the payer is captured on return, `POST /webhooks/paypal/{club}` is the backup; it needs client id, secret and webhook id, and only shows in currencies PayPal supports). Event emails (booking, guest, reminder, receipt, refund, waiting-list place) are wording the superadmin edits under Email templates, and each lodge can override for itself at `/{club}/admin/email-templates` (`club_email_templates`, sanitised by `EmailTemplateSanitizer`, only the keys in `EmailTemplate::LODGE_EDITABLE`, and only that email's own placeholders so a guest email can never gain payment details): fill them with `App\Support\EmailTemplate`, send follow-up ones through `EventMailer` (it never lets a mail error undo a booking or payment), and add any new template with a migration, because the seeder only runs on an empty table.
- `EmailVerification::required()`: sign-up confirmation is only enforced once a real mail driver is set (`log`/`array` skip it); `REQUIRE_EMAIL_VERIFICATION` overrides.

**Files.** Media goes through Spatie MediaLibrary on the public disk. Accounting invoice/bill attachments are the exception: they are stored on the private `local` disk and served only by `admin.accounting.attachments.show` (needs `manage_billing`). Gateway secrets in club settings are encrypted and never sent back to the browser.

**Environment.** The "dev" site is a staging server (`APP_ENV=development`, `APP_DEBUG=false`); production password checks (`uncompromised`) only run when `APP_ENV=production`. `SESSION_SECURE_COOKIE` and `TRUSTED_PROXIES` are set per server.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
