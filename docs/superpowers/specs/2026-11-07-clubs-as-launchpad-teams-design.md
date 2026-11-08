# Clubs as Launchpad teams: club-manager's pages over Launchpad's back end

Status: agreed direction, two items to confirm (marked **Confirm**). 2026-11-07. Branch `spike/launchpad`.

## Why

club-manager wants Launchpad's team features (webhooks, files, team activity, feature flags per club; later the API and AI access) and wants
to keep getting Launchpad's improvements, without a second team model beside `Club`. The spike showed Launchpad installs cleanly but cannot be
used as it was: its migrations clash with club-manager's tables, and every team feature hangs off Launchpad's own `Workspace`.

## Decisions taken

- **Not** moving clubs into Launchpad's tables: `club_id` is in 59 tables and 855 lines, `club_user` is a member record, and club-manager has
  its own `memberships` table.
- **Not** using Launchpad's team pages for clubs. They come as a set (create, rename, re-slug, delete, transfer, leave, members and roles,
  invitations, custom roles) that would write to `clubs` around club-manager's rules, and they open files and member lists to every member.
- **Yes:** club-manager keeps its own pages, routes and permission checks (`EnsureUserCanAdministerClub`, the per-club permission matrix) and
  calls Launchpad's back end: webhook sending, file storage, the activity log and feature flags. Launchpad reads its teams from `clubs`.

What stays in sync: Launchpad's back-end code arrives with `composer update`. Launchpad's screens do not; a new Launchpad feature gets a
club-manager screen when it is wanted. Moving the superadmin area into HQ (where an app can add its own Filament screens) is a possible later,
separate project.

## Goals

- Webhooks, files, team activity and feature flags per club, behind club-manager's own pages and permission checks.
- One club, one row, one id; no copy of club or member data.
- Launchpad changes are general (useful to any existing app) and come with their own tests.

## Non-goals

- Launchpad's sign-in, billing, HQ, passkeys, site pages, team subdomains, SSO/SCIM, API and MCP (the API and MCP can follow later).
- An access list (Launchpad's memberships) for clubs: club-manager's checks decide who sees what, so Launchpad's `workspace` middleware and team
  pages are not used.
- Changes to `club_user`, `ClubRole` or club-manager's permission matrix.

## Anti-goals

- Two sources of truth for a club's name, slug, owners or members.
- Anyone gaining or losing access to a club page because of Launchpad.
- Launchpad gaining anything named after club-manager.

## Changes in Launchpad (general)

1. **Modules without their pages.** A module can be on with its routes and screens off: `launchpad.workspaces.pages` (default `true`). With it
   `false`, `routes/workspaces.php`, the team parts of `routes/billing.php` and `routes/sso.php`, the account menu's team items and the
   workspaces index are not registered, while the models, `Activity`, `Webhooks`, `Files` and `Flags` work. Tested with a test app that has
   pages off.
2. **The team table is a setting:** `launchpad.workspaces.table` (default `workspaces`), read by `Workspace::getTable()`. Raw
   `DB::table('workspaces')` in Launchpad goes through the model's table. Launchpad's own tables keep the column name `workspace_id` (then
   pointing at `clubs.id`).
3. **Feature flags do not take over the app's scope.** `Flags::register()` sets Pennant's default scope only if the app has not opted out
   (`launchpad.features_default_scope`, default `true`). The app asks for Launchpad's flags with the team (`Feature::for($club->asTeam())`):
   Launchpad's flags are defined for its `Workspace`, and Pennant passes the scope straight to them, so asking with a `Club` is not possible
   (storing by morph alias, considered earlier, would not change that and is dropped).
4. Already done: `Launchpad::ignoreMigrations()` and the `launchpad-migrations` publish tag (6b35a21).

Nothing about owners, members or roles changes in Launchpad: without its team pages, nothing in the used back end needs them. (`Workspace`'s
`owner()` and `memberships()` relations may exist and return nothing for a club; nothing club-manager calls uses them.)

## Changes in club-manager

### Configuration

- `Launchpad::ignoreMigrations()` in `AppServiceProvider::register()`.
- `config/launchpad.php`: `workspaces.table` = `clubs`, `workspaces.pages` = `false`, `features_default_scope` = `false`; modules on:
  `workspaces`, `webhooks`, `files`; every other module off.
- No Launchpad traits on `User` (the `memberships()` name clashes, and they are not needed).

### Migrations (published from Launchpad, adapted; columns and tables are only added)

- `clubs`: add what the used back end reads and is missing (the builder lists it by grepping `Workspace` attribute use in `Activity`,
  `Webhooks`, `Files`, `Flags`). Likely only `kind` (default `business`) and `timezone` (default `Europe/London`).
- New tables, `workspace_id` → `clubs.id`: `workspace_events`, `webhook_endpoints`, `webhook_deliveries`, `workspace_files`.
- Not created: anything else (users columns, Launchpad memberships, invitations, notifications, `features`, subscriptions, tokens, passkeys).

### Using it

- A `Club` → `Workspace` bridge: since both read `clubs`, `Workspace::find($club->id)` is the same row. A small helper (`$club->asTeam()`)
  returns it; Launchpad's services are called with that.
- Pages in club-manager's club admin, behind its own capability checks (new keys in `config/club_permissions.php`: `webhooks.manage`,
  `files.view`, `files.manage`, `activity.view`): webhook endpoints (list, add, send a test, see deliveries), files (upload, list, download,
  delete), team activity (the log, with filters). Vue pages in club-manager's style, calling its own controllers.
- Feature flags per club: a superadmin screen (club-manager's superadmin area) to switch a flag for one club, using Launchpad's `Flags`.

### What sends webhooks and goes in the activity log (**Confirm**: cut or add)

Each recorded once, where the change is made (a service or model event), with `Activity::record($team, ...)` and `Webhooks::dispatch($team,
...)`:

| Area | Events |
|---|---|
| Members | `member.added`, `member.removed`, `member.role_changed`, `member.status_changed` (club_user) |
| Meetings | `meeting.created`, `meeting.updated`, `meeting.cancelled`, `meeting.rsvp` |
| Events | `event.published`, `event.registration.created`, `event.registration.paid` |
| Money | `invoice.created`, `invoice.paid`, `donation.received` |
| Communication | `newsletter.sent`, `post.published` |
| Documents | `signature.completed` (the existing `SignatureCompleted` event) |

The activity log shows all of them; webhooks send all of them (an endpoint subscribes to the ones it wants, as Launchpad already supports).

### Feature flags (**Confirm**: in this round, or later)

In this round only if per-club flags are wanted soon; the Launchpad change (3) is needed either way before Launchpad's flags are switched on in
club-manager, so club-manager's own flags keep resolving as now.

## Rollout

1. Launchpad changes 1-3, each with tests, released.
2. club-manager on `spike/launchpad`: config, migrations, the bridge, team activity end to end (the members events first). Full suite and the
   PostgreSQL run green; club-manager's existing tests unchanged.
3. Webhooks (pages and the events above). Files: dropped (2026-11-08). club-manager's File manager already does more (folders, versions,
   crop, replace), so Launchpad's files module is off and its `workspace_files` table is not added.
4. Feature flags, if confirmed.
5. Deploy: migrations only add; turning the modules off is the rollback.

## Tests

- Launchpad: the suite with defaults; a test app with pages off (no team routes, services work) and teams in a table of another name; flags
  with the default scope left alone.
- club-manager: each recorded event writes one activity row and queues one webhook for subscribed endpoints; the capability checks on each new
  page (an ordinary member gets nothing; a super admin gets in as for other club admin pages); files stay private to the club; the existing
  suite unchanged.

## Calls made while writing this (check them)

- Super admins reach the new pages the way they reach other club admin pages (club-manager's own middleware already lets them in).
- `club_user` writes made with `DB::table` (`AnnualOfficerRosterService`) record their events by calling the same service as the Eloquent
  path; no nightly check is needed, since there is no copied access list.
- Files use a private disk and signed links, as in Launchpad; storage is club-manager's existing private disk.
- New permission keys default to owner and admin only, like club-manager's other admin capabilities.
