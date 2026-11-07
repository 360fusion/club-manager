# Club team activity on Launchpad's back end — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Clubs are Launchpad teams (one row in `clubs`), and member changes are recorded in Launchpad's team activity log and sent as webhooks, shown on a club admin page behind its own permission.

**Spec:** `docs/superpowers/specs/2026-11-07-clubs-as-launchpad-teams-design.md` (rollout step 2). Launchpad side done (launchpad commits 6b35a21..1bce89e).

## Global Constraints

- Only add tables and columns; nothing renamed or dropped. club-manager's existing suite passes unchanged (except tests this plan adds).
- Launchpad modules on: `workspaces`, `webhooks`, `files`; everything else off. `workspaces.table` = `clubs`, `workspaces.pages` = false, `features_default_scope` = false, `Launchpad::ignoreMigrations()`.
- Capability key `view_activity` (club-manager's snake_case convention; the design's `activity.view`), roles owner and admin.

## Review Focus

- A member added by every path (invitation accepted, admin adds, visitor registration, lodge claim, sign-up) records one `member.added` line.
- A role or status change records one line; a change of other pivot columns (rank, phone, committee role) records nothing.
- An ordinary member cannot open the activity page; a super admin can.
- Another club's lines never show.
- Existing tests that attach members still pass (the pivot model must not change what `attach`/`sync` store).

### Task 1: Install and configure

- [ ] `composer update 360fusion/launchpad` (path repo, copied).
- [ ] `config/launchpad.php` as in Global Constraints; webhooks `events` = the member events below.
- [ ] `Launchpad::ignoreMigrations()` in `AppServiceProvider::register()`.
- [ ] Publish Launchpad's migrations into a scratch dir; copy into `database/migrations` only: workspace events, webhook endpoints and deliveries (the webhooks half of `create_api_tokens_and_webhooks_tables`), workspace files. With `workspaces.table` = `clubs` their keys point at `clubs`.
- [ ] Add to `clubs` any column the back end reads that it lacks (grep `Workspace` attribute use in Launchpad's `Activity`, `Webhooks`, `Files`).
- [ ] Run the existing suite: green. Commit.

### Task 2: Bridge and member events

- [ ] Test `ClubTeamTest`: `$club->asTeam()` is a `Launchpad\Models\Workspace` with the club's id and name.
- [ ] Test `ClubMemberEventsTest`: attach → one `workspace_events` row `member.added` for that club, and `Webhooks::dispatch` queues one delivery to an endpoint subscribed to it; `updateExistingPivot` role → `member.role_changed`; status → `member.status_changed`; rank only → nothing; detach → `member.removed`; another club sees none.
- [ ] `App\Models\ClubUser extends Pivot` (table `club_user`, incrementing id), `->using(ClubUser::class)` on `Club::users()` and `User::clubs()`; `Club::asTeam(): Workspace`; an observer (or `booted()`) on `ClubUser` calling `Activity::record()` and `Webhooks::dispatch()`.
- [ ] Tests pass; existing suite green. Commit.

### Task 3: The activity page

- [ ] Test `ClubActivityPageTest`: owner sees the club's lines at `/{clubSlug}/admin/activity`; a plain member gets 403; a super admin gets in; another club's lines are absent.
- [ ] `view_activity` capability (owner, admin) and `route_map` `'activity' => 'view_activity'`; route `admin.activity.index`; `ActivityAdminController@index` paginating `WorkspaceEvent` for the club (25 a page); Vue `Admin/Activity/Index.vue` in `AdminLayout`; an entry in the admin menu.
- [ ] Tests pass; full suite on SQLite and Postgres. Commit.
