# Club Webhooks and Events Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Club admins add webhook endpoints for their club, and the design's events (meetings, events, money, communication, documents) go to the club's activity log and its webhooks.

**Architecture:** One helper, `App\Support\ClubTeamEvents::record()`, writes an activity line and dispatches the webhook for a club (what `ClubUser::record()` does today, moved out). Model observers call it where each change is saved, so every writer is covered (no raw query-builder writes of these columns exist: checked). A club admin page, `/{clubSlug}/admin/webhooks`, manages endpoints with Launchpad's `Webhooks` helpers and models.

**Tech Stack:** Laravel 13, Inertia + Vue, Launchpad (`Launchpad\Webhooks\Webhooks`, `Launchpad\Models\WebhookEndpoint`, `WebhookDelivery`, `Launchpad\Workspaces\Activity`).

**Spec:** `docs/superpowers/specs/2026-11-07-clubs-as-launchpad-teams-design.md` (Rollout step 3; "What sends webhooks and goes in the activity log").

## Global Constraints

- Events, exactly: `meeting.created`, `meeting.updated`, `meeting.cancelled`, `meeting.rsvp`, `event.published`, `event.registration.created`, `event.registration.paid`, `invoice.created`, `invoice.paid`, `donation.received`, `newsletter.sent`, `post.published`, `signature.completed`.
- Each is recorded once per change, with `Activity::record($team, ...)` and `Webhooks::dispatch($team, ...)`.
- Every event key is listed in `config/launchpad.php` `webhooks.events` with a label, so endpoints can subscribe to it.
- New page behind club-manager's own check: capability `manage_webhooks` (owner, admin), `route_map` `'webhooks' => 'manage_webhooks'`. A plain member gets 403; a super admin gets in.
- Payloads carry ids and the few fields a receiver needs. Never bank details, tokens, consent IPs or notes.
- Don't migrate the shared `club_manager` database. Tests run on SQLite, and on Postgres against a throwaway database.

## Review Focus

- A model saved with no change to a watched column (a meeting's menu text, a post's excerpt) sends nothing. Task 2 and Task 3 tests save unrelated columns and assert no new line.
- Something created already in its end state (a post created `published`, an invoice created `paid`, a contribution created `paid`) records the end event once, not twice. Task 3 tests.
- A club's endpoint URL pointing at a private address is refused, as Launchpad does (`Webhooks::urlProblem`). Task 1 test.
- Another club's endpoint can't be toggled, tested or deleted by changing the id in the URL (404). Task 1 test.
- A change made by a queued job or command (no signed-in person) still records, as "The system". Task 2 test runs one change with no user signed in.

---

### Task 1: The webhooks page

**Files:**
- Create: `app/Http/Controllers/WebhookAdminController.php`, `resources/js/Pages/Admin/Webhooks/Index.vue`, `tests/Feature/Launchpad/ClubWebhooksPageTest.php`
- Modify: `config/club_permissions.php` (capability and route map), `routes/club.php` (next to the activity route), `resources/js/Layouts/AdminLayout.vue` (menu entry and `currentActiveTab` `'webhooks'`)

**Interfaces:**
- Produces: routes `admin.webhooks.index` (GET), `admin.webhooks.store` (POST), `admin.webhooks.toggle` (POST `/{id}/toggle`), `admin.webhooks.test` (POST `/{id}/test`), `admin.webhooks.destroy` (DELETE `/{id}`), all under `/{clubSlug}/admin/webhooks`.

- [ ] **Step 1: Write `ClubWebhooksPageTest`**
  - `test_an_owner_adds_an_endpoint_and_sees_the_secret_once`: POST url `https://hooks.example.com/x`, events `['member.added']`. Redirects to the index, with `new_secret` in the session starting `whsec_`. One `WebhookEndpoint` exists with `workspace_id` = the club id.
  - `test_a_private_address_is_refused`: url `http://127.0.0.1/x` gives a validation error on `url`, and no endpoint is created.
  - `test_an_unknown_event_is_refused`: events `['nope']` gives a validation error.
  - `test_another_clubs_endpoint_is_not_found`: toggle, test and destroy on the other club's endpoint id all return 404.
  - `test_send_test_queues_a_ping`: `Queue::fake()`, then POST test; `DeliverWebhook` is pushed once and a delivery with event `ping` exists.
  - `test_a_plain_member_is_refused` (403), `test_a_treasurer_is_refused` (403), `test_a_super_admin_gets_in` (200).
  - Set the resolver so the example host resolves publicly, the way Launchpad's own webhook tests do.
- [ ] **Step 2: Run it.** `php artisan test --filter ClubWebhooksPageTest` fails (route missing).
- [ ] **Step 3: Implement.**
  - The controller mirrors `Launchpad\Http\Controllers\Workspaces\WebhookController`: the same validation, the 10-endpoint cap, `Webhooks::newSecret()`, `Webhooks::queue($endpoint, 'ping', ...)`, and the last 20 deliveries.
  - It differs in four ways: it takes `string $clubSlug`, uses `$this->findClub()`, uses `workspace_id = $club->id`, and returns `Inertia::render('Admin/Webhooks/Index', ['club', 'endpoints', 'deliveries', 'events', 'newSecret'])`.
  - Write the Vue page in the style of `Admin/Activity/Index.vue`:
    - an add form with url, description and event checkboxes
    - the secret, shown once
    - an endpoint list with on/off, Send test and Delete (with `confirm()`)
    - a recent deliveries table
- [ ] **Step 4: Run it, then `AdminRouteSweepTest`.** Both pass. Run `npx vite build` and check it builds.
- [ ] **Step 5: Commit.** "The club webhooks page"

### Task 2: One helper for recording, and the meetings events

**Files:**
- Create: `app/Support/ClubTeamEvents.php`, `app/Observers/MeetingTeamEvents.php`, `app/Observers/MeetingRsvpTeamEvents.php`, `tests/Feature/Launchpad/ClubMeetingEventsTest.php`
- Modify: `app/Models/ClubUser.php` (its `record()` calls the helper), `app/Providers/AppServiceProvider.php` (`boot()`: register the observers), `config/launchpad.php` (event labels for all 13 events)

**Interfaces:**
- Produces: `ClubTeamEvents::record(int $clubId, string $event, string $summary, array $payload): void`. It does nothing when the club is missing; it calls `Activity::record(Workspace::find($clubId), $event, $summary)` and `Webhooks::dispatch(...)`.

- [ ] **Step 1: Write `ClubMeetingEventsTest`.** A helper, `lines()`, returns the club's `WorkspaceEvent` actions in order.
  - Create a meeting: `['meeting.created']`.
  - Change `venue`: `meeting.updated` is added. Then change `festive_board_menu`: nothing new.
  - Set `status` to `cancelled`: `meeting.cancelled`, and not `meeting.updated` as well.
  - Create an RSVP: `meeting.rsvp`. Then change its `attendance_status`: `meeting.rsvp` again. Then change `payment_status`: nothing new.
  - The summary names the meeting title.
  - With no one signed in, `actor_name` is `The system`.
  - `ClubMemberEventsTest` still passes.
- [ ] **Step 2: Run it.** It fails.
- [ ] **Step 3: Implement.**
  - `meeting.updated` fires when any of `title`, `meeting_date`, `starts_at` or `venue` changes and the status didn't change to `cancelled`.
  - `meeting.rsvp` fires on create, or when `attendance_status` changes. Its payload is `meeting_id`, `user_id` and `attendance_status`.
  - The meeting payload is `meeting_id`, `title`, `meeting_date`, `starts_at`, `venue` and `status`.
  - The RSVP's club comes from `meeting.club_id`.
- [ ] **Step 4: Run** `--filter 'ClubMeetingEventsTest|ClubMemberEventsTest'`. They pass.
- [ ] **Step 5: Commit.** "Meeting changes go to club activity and webhooks"

### Task 3: Events, money, communication and documents

**Files:**
- Create: `app/Observers/EventTeamEvents.php`, `EventRegistrationTeamEvents.php`, `InvoiceTeamEvents.php`, `DonationContributionTeamEvents.php`, `NewsletterTeamEvents.php`, `PostTeamEvents.php`, `app/Listeners/RecordSignatureCompleted.php`, `tests/Feature/Launchpad/ClubOtherEventsTest.php`
- Modify: `app/Providers/AppServiceProvider.php` (observers and the listener; check how the existing `SignatureCompleted` listeners are registered and do the same)

When each event fires (on create, or when the column changes to the value):

| Event | Model and condition | Payload |
|---|---|---|
| `event.published` | Event `status` leaves `draft` (or created not draft) | event_id, title, starts_at |
| `event.registration.created` | EventRegistration created | registration_id, event_id, contact_name, total |
| `event.registration.paid` | EventRegistration `payment_status` becomes `paid` | registration_id, event_id, amount_paid |
| `invoice.created` / `invoice.paid` | Invoice created / `status` becomes `paid` | invoice_id, invoice_number, title, amount, user_id |
| `donation.received` | DonationContribution `payment_status` becomes `paid` (club from `donation.club_id`) | donation_id, contribution_id, donor_name, amount |
| `newsletter.sent` | Newsletter `status` becomes `sent` | newsletter_id, subject |
| `post.published` | Post `status` becomes `published` | post_id, title, slug |
| `signature.completed` | the `SignatureCompleted` event | signature_request_id, purpose, signer_name |

- [ ] **Step 1: Write `ClubOtherEventsTest`.** Each row gets one test:
  - the triggering change records exactly that action once
  - a save of an unrelated column records nothing
  - created already in the end state records it once (post `published`, invoice `paid`, contribution `paid`)
  - one endpoint subscribed to `invoice.paid` with `Queue::fake()` gets one `DeliverWebhook`
- [ ] **Step 2: Run it.** It fails.
- [ ] **Step 3: Implement the observers and the listener** with `ClubTeamEvents::record`.
- [ ] **Step 4: Run the Launchpad folder tests, then the full suite on SQLite and on Postgres** (throwaway database, then dropped). Both are green apart from the existing skips.
- [ ] **Step 5: Commit.** "Events, money, newsletters, posts and signatures go to club activity and webhooks"
