# Clubs as Launchpad teams

Status: draft for review, 2026-11-07. Branch `spike/launchpad`.

## Why

club-manager wants Launchpad's team features (webhooks, files, team activity, feature flags per club, later the API and its AI access) without a
second team model beside `Club`. The spike showed Launchpad installs cleanly but cannot be used: its migrations clash with club-manager's tables,
and every team feature hangs off Launchpad's own `Workspace`.

Moving clubs into Launchpad's tables (`clubs` → `workspaces`, `club_user` → `memberships`) was looked at and rejected:

- `club_id` is in 59 tables and 855 lines of app code.
- `club_user` is a member record (rank, committee role, member number, status, contact and dietary details, invitation history), not only access.
- club-manager has its own `Membership` model and `memberships` table (members' subscriptions with plans).

## The idea

**A club is a Launchpad team: one row, one id.** Launchpad's `Workspace` model reads the `clubs` table. Launchpad's access list (who may use a
team, with which Launchpad role) lives in its own table, renamed so it does not clash, and is filled from `club_user`. Everything else in
club-manager stays as it is.

## Goals

- Webhooks, files, team activity and feature flags per club, working off `clubs`.
- No copy of club data. The only thing kept in step is who can access what.
- Launchpad stays a general starter: the changes it needs are small, configurable and useful to any existing app.

## Non-goals

- Launchpad's sign-in (club-manager keeps Fortify), billing (club-manager keeps its Stripe and Paddle set-up, with `Club` as the customer),
  HQ admin (the superadmin area stays), passkeys and site pages. Those modules stay off.
- Team subdomains: clubs already have their own `custom_domain` handling (`IdentifyTenantByDomain`); Launchpad's subdomain mode stays off.
- Changing `club_user`, `ClubRole` or how club-manager checks club access (`EnsureUserCanAdministerClub`).

## Anti-goals (a failure even if it works)

- Two sources of truth for a club's name, slug, owner or members.
- A club member losing or gaining access to club-manager's own pages because of Launchpad.
- Launchpad gaining anything named after club-manager.

## Changes in Launchpad (small, general)

1. **Configurable table names** for its team and access-list models: `launchpad.workspaces.table` (default `workspaces`) and
   `launchpad.workspaces.members_table` (default `memberships`). `Workspace::getTable()` and `Membership::getTable()` read them; every query
   goes through the models, and any raw `DB::table('workspaces'|'memberships')` in Launchpad is changed to use them. Foreign-key columns stay
   `workspace_id` in Launchpad's own tables (they then point at `clubs.id`).
2. **The owner column:** Launchpad's `Workspace` uses `owner_id`. Either a setting for the column name (`launchpad.workspaces.owner_column`) or
   club-manager adds an `owner_id` kept equal to `clubs.user_id`. Builder's call; the setting is preferred if it stays a one-line change in the
   relation.
3. Already done: `Launchpad::ignoreMigrations()` and `vendor:publish --tag=launchpad-migrations` (commit 6b35a21).

These are separate Launchpad commits with their own tests (a test app whose teams live in a table called something else).

## Changes in club-manager

### Configuration

- `Launchpad::ignoreMigrations()` in `AppServiceProvider::register()`.
- `config/launchpad.php`: `workspaces.table` = `clubs`, `workspaces.members_table` = `club_access` (name to confirm), modules on: `workspaces`,
  `webhooks`, `files`; off: `auth`, `social`, `billing`, `admin`, `site`, `help`, `contact`, `sso`, `scim`, `passkeys`, `login_links`, `mcp`,
  `api` (the API and MCP can follow later), `moderation`, `blog`, `roadmap`, `waitlist`, `sync`.
- User model: add Launchpad's `HasWorkspaces` trait (and only what the chosen modules need). It must not clash with club-manager's own user
  relations; the builder checks method names.

### Migrations (published from Launchpad, then adapted)

- Add to `clubs` the team columns Launchpad reads that it lacks: `kind` (default `business`), `timezone` (default `Europe/London`),
  `role_permissions` (json, null), `suspended_at`, `suspended_reason`, `onboarding` columns if the workspaces module needs them. `name`, `slug`,
  `stripe_id` and the other Cashier columns exist already.
- Create the access list as `club_access` (Launchpad's `memberships` shape: `workspace_id` → `clubs.id`, `user_id`, `role`, `permissions`, ...).
- Create, with `workspace_id` pointing at `clubs.id`, the tables of the modules that are on: `workspace_events`, `invitations` (Launchpad's
  invitation table is needed by the workspaces module even if club-manager's own invitations stay the ones people use), `webhook_endpoints`,
  `webhook_deliveries`, `workspace_files`, `custom_roles` if used.
- Leave out everything else: users columns, `notifications`, `features` (club-manager has Pennant's already), `subscriptions`,
  `personal_access_tokens`, `passkeys`, `stripe_events`.
- `users.current_workspace_id`: Launchpad remembers the team a person is working in. Add it (nullable, → `clubs.id`).

### Keeping access in step

`club_user` stays the source. A `ClubAccess` sync (one class) writes the person's row in `club_access` whenever their `club_user` row is
created, changes role or status, or is removed. Because `Club::users()` is a `BelongsToMany`, its changes fire no model events, so either:
(a) a `ClubUser` pivot model is added with `->using(ClubUser::class)` and the sync runs from its events, or (b) the places that change members
call the sync. (a) is preferred: nothing can be missed. Plus a command `club-access:sync` that rebuilds the whole table (run once to fill it,
and nightly with `--check` to report any drift).

Rows that do not give access (status not active, the visitor role) have no `club_access` row.

### Role mapping (decision needed)

Launchpad's roles are owner, admin, manager, member, plus per-person permissions (`manage-billing`, `manage-members`, and an app's own keys).
Proposed mapping, to confirm:

| Club role | Launchpad role | Notes |
|---|---|---|
| owner | owner | the club's `user_id` |
| admin | admin | |
| secretary | manager | |
| treasurer | manager | plus any money-related permission club-manager defines |
| coach, examiner | member | |
| member | member | |
| visitor | none | no Launchpad access |

### Where the pages come from (decision needed)

- **(i) Launchpad's Vue pages, restyled:** install Launchpad's Vue stubs for the webhooks, files and team activity pages and link them from
  club-manager's club admin menu. Their address is `/w/{club-slug}/...` (Launchpad's), while the rest of the club admin is club-manager's own
  routes. Quickest.
- **(ii) club-manager's own pages over Launchpad's back end:** use Launchpad's models and services (webhook delivery, file storage, the activity
  log, flags) from club-manager's controllers and Vue pages under its own routes. More work, one consistent admin.

### Feature flags per club

Launchpad's flags (Pennant, scoped to a team) then scope to a club. club-manager already uses Pennant; the builder checks its existing scope (it
may already scope to users or clubs) so the two do not collide in the shared `features` table.

## Rollout

1. Launchpad's configurable table names (and owner column), released.
2. club-manager on the spike branch: config, migrations, the access sync and its backfill, one module (team activity) end to end; full test
   suite and the PostgreSQL run green.
3. Webhooks and files, with the chosen pages.
4. Deploy: migrations add columns and tables only (nothing is renamed or dropped), then run `club-access:sync`. Rolling back is turning the
   modules off; the new tables and columns can stay.

## Tests

- Launchpad: its suite with the default names, and a test app with renamed tables.
- club-manager: the access sync for every way a member changes (added, role changed, status changed, removed, club deleted); the backfill and
  `--check`; a member reaching a Launchpad page for their club and a non-member not; nothing in club-manager's own access changing (its existing
  suite, unchanged, must pass).

## Left to the builder

The exact column list Launchpad's enabled modules read from a team (a grep of `Workspace` attribute use), the table name for the access list if
`club_access` collides, the timezone default, and the shape of the backfill command.
