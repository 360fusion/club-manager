---
name: launchpad-development
description: Build features on Launchpad, the base of this app (teams, billing, API tokens, webhooks, files, feature flags, onboarding, admin). Use when adding a team-owned model, a team page or API route, an upload, a feature flag, a webhook event or a Getting started step.
---

# Launchpad development

## When to use this skill

Use it when adding anything that belongs to a team (a "workspace" in code, a "team" in anything people read), anything a customer calls through the API, or anything staff switch from the admin.

## A team-owned model

1. Migration: `$table->belongsToWorkspace();` (a `workspace_id` that cascades on delete).
2. Model: `use Launchpad\Workspaces\Concerns\BelongsToWorkspace;`. Every query is then narrowed to the current team and a new row is given it. With no current team (a job, a command, the admin) nothing is narrowed and a new row needs its `workspace_id` set; a queued job is given the team id and sets `app(CurrentWorkspace::class)->workspace`. Say `Model::withoutGlobalScope(Launchpad\Workspaces\WorkspaceScope::class)` when you mean every team's rows.

## A team page

Routes go in `/w/{slug}/...` behind `workspace` (add `:manage-members`, `:manage-billing`, `:manage-workspace` or an app permission from `launchpad.workspaces.permissions` to require it). Draw pages with `Launchpad\Http\Ui::render('area.page', $data, fn () => $propsForVue)` so Blade and Inertia both work, and add the person's link to the account menu only if it belongs there.

## A customer API route

```php
Route::prefix('api/v1')->middleware(['launchpad.json', 'throttle:launchpad-api', 'launchpad.api:write', 'launchpad.idempotent'])->group(function () {
    Route::post('projects', fn (Request $r) => Project::create($r->validate(['name' => 'required|max:120'])));   // BelongsToWorkspace gives it the token's team
});
```

Errors are JSON, the rate is the plan's `api_rate` limit, and a client may retry a write with an `Idempotency-Key` (see `docs/api-conventions.md`). A token's page shows its last 30 days of calls and replaces it with a grace period (`ApiToken::rotate($token, $graceMinutes)`). Tokens are Laravel Sanctum's, made for one team on the team's API page; abilities are listed in `launchpad.api.abilities`. Do not use `auth:sanctum` for these routes: it also accepts tokens with no team.

## Limits, flags, files, webhooks, steps

- Plan limits and features: `Entitlements::allows()` and `Entitlements::within()`; the numbers live in `launchpad.billing.plans.*.limits` and staff can override them in the admin.
- Feature flags: list in `launchpad.features` (`on`, `plans`, `rollout`), then `Feature::active('key')`, `@feature('key')`, or `feature:key` middleware (with `features_default_scope` off: `Feature::for($workspace)->active('key')`). Run `php artisan pennant:purge key` after changing a flag's rules.
- Uploads: `Files::store()` and `Files::rules()`; `WorkspaceFile::signedUrl()` for a link that works without signing in.
- Webhooks: add the event to `launchpad.webhooks.events`, then `Webhooks::dispatch($workspace, 'event.key', $payload)`.
- Usage quotas: list a meter in `launchpad.metering.meters`, give plans a limit of the same key, and put `meter:key` after `workspace` or `launchpad.api` on the route (or `Usage::record()`/`within()` by hand).
- Team activity: Launchpad logs people, roles, invitations, tokens, webhooks, files and plan changes by itself; add your own with `Activity::record($workspace, 'project.created', 'created the project Apollo')` (a sentence that follows the person's name).
- Getting started card: `Onboarding::step(...)`; show the card on a dashboard with `<x-launchpad::onboarding />`.

## Wording

Wrap every visible sentence in `__()` with the whole English sentence as the key (`__('Invite a teammate to your team')`), and say "team", never "workspace". A sentence with a link or emphasis uses `Launchpad\Support\Trans::html()` (Vue: `$t()`, `<Trans k="...">`). Check with `php artisan launchpad:lang:missing <locale> --scan=app --scan=resources/views`; details in `docs/translations.md`.

## Tests

Use `Launchpad\Testing\AssertsRoutesAreClassified` and call `assertEveryRouteIsClassified()` so a route nobody classified is caught. Stripe is faked with `Launchpad\Testing\FakeGateway`.
