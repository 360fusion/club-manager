# Launchpad: team back end without its pages — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an existing app use Launchpad's team back end (activity log, webhooks, files, flags) on its own team table, without Launchpad's team pages and without Launchpad changing the app's Pennant default scope.

**Architecture:** Three settings in `config/launchpad.php`, read where the behaviour lives: the `Workspace` model's table, `TeamAddresses::group()` plus the team-wide routes in `routes/workspaces.php`, and `Flags::register()`. Defaults keep today's behaviour exactly.

**Tech Stack:** Laravel 13, Orchestra Testbench, PHPUnit, Laravel Pennant.

**Repo:** `/Users/ben/Herd/launchpad` (the code changes go there; this plan sits beside the design it implements).

**Spec:** `docs/superpowers/specs/2026-11-07-clubs-as-launchpad-teams-design.md`, section "Changes in Launchpad". Change 3 is narrowed: storing flags by morph alias is dropped, because Launchpad's flags are defined for `?Workspace` and Pennant passes the scope straight to the resolver, so an app must ask with the `Workspace` (`$club->asTeam()`) anyway; only the default-scope switch remains.

## Global Constraints

- Nothing in Launchpad's code, tests, docs or commit messages names another product (Launchpad is a general starter).
- Defaults unchanged: `workspaces.table` = `workspaces`, `workspaces.pages` = `true`, `features.set_default_scope` = `true`; the full suite passes untouched.
- `config/launchpad.php`, `routes/workspaces.php` and `src/LaunchpadServiceProvider.php` carry another session's uncommitted edits: stage only this plan's lines (build the staged file from `git show HEAD:<file>` plus this plan's edits), and run the suite on the staged tree before committing.
- Each task is one commit, ending with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

- A signed file link (`files.signed`) made by the back end still opens with pages off → Task 2 test asserts `Route::has('files.signed')` and a GET of a signed link returns the file.
- The account menu shows no team items with pages off (they key off `Route::has`) → Task 2 test on `AccountMenu::items()`.
- A model with `BelongsToWorkspace` still scopes and fills `workspace_id` when the team table is renamed → Task 1 test creates a scoped row.
- Account closure with teams on a renamed table finds the person's owned teams (`AccountClosure` used `Schema::hasTable('workspaces')`) → Task 1 test closes an owner's account.
- With the default scope left alone, Pennant's own default (the signed-in user) applies → Task 3 test.

---

### Task 1: The team table is a setting

**Files:**
- Modify: `config/launchpad.php` (the `workspaces` block): add `'table' => env('LAUNCHPAD_TEAM_TABLE', 'workspaces'),` with a one-line comment ("the table teams are in: an app with teams of its own points this at it")
- Modify: `src/Models/Workspace.php`: `public function getTable(): string` returning `config('launchpad.workspaces.table') ?: parent::getTable()`
- Modify: `src/Accounts/AccountClosure.php:42,88`: `Schema::hasTable((new Workspace)->getTable())`
- Modify: `src/LaunchpadServiceProvider.php`: the `belongsToWorkspace` Blueprint macro constrains to `(new Workspace)->getTable()`
- Test: `tests/Feature/TeamTableTest.php`

**Interfaces:**
- Produces: `Workspace::getTable(): string` (config-driven); config key `launchpad.workspaces.table`.

- [ ] **Step 1: Write the failing test** `TeamTableTest`: `defineEnvironment` sets `launchpad.workspaces.table` = `teams`; `migrate()` runs the package migrations then `Schema::rename('workspaces', 'teams')`. Tests:
  - `test_teams_live_in_the_configured_table`: `app(Workspaces::class)->create($owner, 'Acme')` → `DB::table('teams')->where('name', 'Acme')->exists()` true; `Workspace::first()->getTable() === 'teams'`.
  - `test_the_back_end_works_on_it`: `Activity::record($team, 'x.y', 'did a thing', 'Test')` writes a `workspace_events` row with that `workspace_id`; a `WorkspaceFile` (a `BelongsToWorkspace` model) created with `app(CurrentWorkspace::class)->set($team, $membership)` gets `workspace_id` = `$team->id` and is not visible from a second team.
  - `test_closing_an_owners_account_finds_their_teams`: `app(AccountClosure::class)->close($owner, null)` behaves as it does today for an owner (the existing closure test's expectation for owned teams; read `tests/Feature/*Closure*` for it).
- [ ] **Step 2:** Run `vendor/bin/phpunit --filter TeamTableTest` → FAIL (rows go to `workspaces`, which no longer exists).
- [ ] **Step 3:** Implement the four modifications above.
- [ ] **Step 4:** Run `vendor/bin/phpunit --filter TeamTableTest` → PASS; then `vendor/bin/phpunit` → all pass.
- [ ] **Step 5:** Add one paragraph to `docs/install.md` ("Adding Launchpad to an app that already has its own tables"): teams can live in the app's own table with `LAUNCHPAD_TEAM_TABLE`; the columns Launchpad needs on it (`name`, `slug`, `kind`, `owner_id`, `timezone`, plus those of the modules on). Commit.

### Task 2: The workspaces module without its pages

**Files:**
- Modify: `config/launchpad.php` (the `workspaces` block): `'pages' => (bool) env('LAUNCHPAD_TEAM_PAGES', true),` comment: "false: the team back end (activity, webhooks, files, flags) without Launchpad's team pages, for an app with pages of its own"
- Modify: `src/Workspaces/TeamAddresses.php`: `group()` registers nothing when `config('launchpad.workspaces.pages') === false` (covers the team groups in `routes/workspaces.php`, `routes/billing.php`, `routes/sso.php`)
- Modify: `routes/workspaces.php`: wrap the team-wide routes (`invitations.show`, `invitations.accept`, `workspaces.index`, `workspaces.store`, `workspaces.switch`, and the `w/{slug}` subdomain redirect) in `if (config('launchpad.workspaces.pages', true))`; leave `files.signed` outside it
- Test: `tests/Feature/TeamPagesOffTest.php`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: config key `launchpad.workspaces.pages`; `TeamAddresses::group(Closure): void` is a no-op when it is `false`.

- [ ] **Step 1: Write the failing test** `TeamPagesOffTest` (`defineEnvironment` sets `launchpad.workspaces.pages` = `false`):
  - `test_no_team_pages_are_registered`: for each of `workspace.members`, `workspace.settings`, `workspace.webhooks`, `workspace.files`, `workspace.activity`, `workspace.api`, `workspace.billing`, `workspace.sso`, `workspaces.index`, `workspaces.store`, `invitations.show`: `assertFalse(Route::has($name))`; `assertTrue(Route::has('files.signed'))`.
  - `test_the_back_end_still_works`: with a team made by `Workspaces::create`: `Activity::record(...)` writes a row; `Webhooks::dispatch($team, 'member.joined', [...])` queues a delivery for a subscribed endpoint (`Queue::fake()`, assert `DeliverWebhook` pushed); `Files` stores an upload for the team and `WorkspaceFile::signedUrl()` (or the method `Files` uses) opens with a GET returning 200.
  - `test_the_account_menu_has_no_team_items`: `AccountMenu::items($user)` labels contain none of `Team`, `Files`, `Plan and billing`, `API`, `Webhooks`, `Team activity`.
- [ ] **Step 2:** Run `vendor/bin/phpunit --filter TeamPagesOffTest` → FAIL (routes registered).
- [ ] **Step 3:** Implement the three modifications above.
- [ ] **Step 4:** Run `vendor/bin/phpunit --filter "TeamPagesOffTest|WorkspaceTest"` → PASS (the route-classification test still passes); then `vendor/bin/phpunit` → all pass.
- [ ] **Step 5:** Add a short section to `docs/building-on-launchpad.md` ("The team back end without its pages"): `LAUNCHPAD_TEAM_PAGES=false`; what keeps working (`Activity::record`, `Webhooks::dispatch`, `Files`, `Flags`, `BelongsToWorkspace`); the app calls them with a `Workspace` for its team and builds its own pages and permission checks. Commit.

### Task 3: Flags leave the app's default scope alone when asked

**Files:**
- Modify: `config/launchpad.php`: a `features` setting `'set_default_scope' => true,` (in the existing `features` block if there is one, else a new one) with a comment: "false: Launchpad does not make the current team Pennant's default scope; the app asks with Feature::for(\$team)"
- Modify: `src/Features/Flags.php`, `register()`: `Feature::resolveScopeUsing(...)` only when `config('launchpad.features.set_default_scope', true)`
- Test: `tests/Feature/FlagsScopeTest.php`

**Interfaces:**
- Produces: config key `launchpad.features.set_default_scope`.

- [ ] **Step 1: Write the failing test** `FlagsScopeTest`, with one Launchpad flag in config (`launchpad.features` flags so `Flags::all() !== []`, same shape as the existing flags tests) and `set_default_scope` = `false` in `defineEnvironment`; in the test, `Feature::define('who', fn ($scope) => $scope?->getKey())`, `actingAs($user)`:
  - `test_pennants_own_default_scope_applies`: `Feature::value('who') === $user->getKey()`.
  - `test_a_team_flag_still_answers_for_a_team`: `Feature::for($team)->active('<the Launchpad flag key>')` returns that flag's configured answer.
- [ ] **Step 2:** Run `vendor/bin/phpunit --filter FlagsScopeTest` → FAIL (`who` gets the current workspace, null).
- [ ] **Step 3:** Implement the modification above.
- [ ] **Step 4:** Run `vendor/bin/phpunit --filter "FlagsScopeTest|Flag"` → PASS; then `vendor/bin/phpunit` → all pass.
- [ ] **Step 5:** One line in the flags part of `docs/building-on-launchpad.md` about `set_default_scope`. Commit.
