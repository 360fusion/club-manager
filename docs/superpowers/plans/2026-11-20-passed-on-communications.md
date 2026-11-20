# Passed-on Communications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Secretary passes on summonses and notices from sister and reciprocal lodges, and Provincial and Grand
Lodge communications, once; each member gets only the kinds and lodges they chose, by app and/or email.

**Architecture:**
- **Items and deliveries.** A `PassedOnItem` is what was passed on (or a platform lodge's published summons). Each
  person who gets it has one `PassedOnDelivery` row with a private token and a dedupe key, so a person never gets the
  same summons or circular twice.
- **Recipients.** `PassedOnAudience` works out who gets an item from the lodge's lists (`ClubLodgeLink`), members'
  choices (`PassedOnPreferences`) and lodge followers. The queued `SendPassedOnItem` job writes the deliveries, then
  sends the bell notices and queues the emails.
- **Website and app.** The app is the website in a shell, so every page here serves both.

**Tech Stack:** Laravel 13, Inertia v3 + Vue 3, Tailwind v4, PHPUnit on SQLite; PostgreSQL in dev and production.

**Spec:** `docs/superpowers/specs/2026-11-20-passed-on-communications-design.md`

## Global Constraints

- The four kinds are fixed strings: `sister`, `reciprocal`, `provincial`, `grand`. Labels: "Sister lodges in our
  hall", "Reciprocal visit lodges", "Provincial communications", "Grand Lodge communications".
- Item types are `summons` (meeting date required) and `notice` (no date). Kinds `provincial` and `grand` are always
  `notice`.
- **Defaults:** a member who has changed nothing gets every kind by app and email, with no lodge muted and calendar on.
  Members with no account get email only.
- **Your own lodge:** its summonses and notices are untouched by any of this.
- **Followers:** a passed-on summons for a lodge also goes to anyone who follows that lodge with `notify_summons`. It
  goes by bell and phone only, never email, and only for type `summons`. Never show who or how many follow a lodge.
- **Who may send:** `pass_on_communications` capability (roles owner, admin, secretary) or the Secretary office
  (`Member::userHoldsOffice($club, $user, [LodgeOffice::Secretary])`).
- **Attachments:**
  - PDF only, up to 10 MB, on the private `local` disk under `passed-on/{club_id}/`.
  - Served only to someone holding a delivery for that item, or to the sending lodge's senders.
- **Shown on every item:** "Passed on by {formatted_rank_name or user name}, Secretary of {club name}". Use
  "Secretary" only when they hold the office; otherwise "of {club name}".
- **Duplicates:**
  - A summons's key is `summons:{lodge_id}:{Y-m-d}`.
  - A circular's key is `circular:{kind}:{sha256}`, hashed from the PDF bytes, or from the lower-cased, whitespace-squashed
    title plus plain text.
  - A person is skipped if they hold a delivery with the same key made in the last 60 days.
- **Rate limit:** sending is throttled at `throttle:10,60` per user (ten an hour).
- **Project conventions (CLAUDE.md):**
  - `$this->findClub()`; sanitise the note with `RichTextSanitizer` (the `SanitizedHtml` cast); mail is `ShouldQueue`.
  - Tests use factories; `Mail::assertQueued`.
  - No hard-coded colours outside the palette, no Title Case labels; run `npm run check:templates` and
    `npm run check:design`.
- **Commits:** don't commit. The user commits when they ask. Run `vendor/bin/pint --dirty --format agent` after PHP
  changes.

## Review Focus

1. **A member of two lodges that both list the same sister lodge**, when both Secretaries pass on its summons, gets
   one notice, not two. Test in Task 3.
2. **A lodge on the platform that keeps its summonses to members only.** Nobody outside it gets anything
   automatically, and other Secretaries may pass on its summons by hand. Test in Task 5.
3. **A member who muted a lodge** gets nothing about it, even as a follower of it, and it stays out of their calendar.
   Tests in Tasks 3 and 6.
4. **A delivery token or attachment link used by someone else.** A signed-in user without a delivery gets a 404 on
   the attachment, and a wrong token gets a 404. Test in Task 4.
5. **A roster member with no account who unsubscribes from email** of one kind still gets the other kinds. Test in
   Task 4.

---

### Task 1: Tables, models and members' choices

**Files:**
- Create: `database/migrations/2026_11_20_120000_create_passed_on_communications.php`
- Create: `app/Models/ClubLodgeLink.php`, `app/Models/PassedOnItem.php`, `app/Models/PassedOnDelivery.php`
- Create: `app/Support/PassedOnPreferences.php`
- Create: factories `database/factories/PassedOnItemFactory.php`, `database/factories/ClubLodgeLinkFactory.php`
- Modify: `app/Models/User.php` (fillable/cast `passed_on_prefs`), `app/Domains/ClubAccounting/Models/Member.php` (same)
- Modify: `config/club_permissions.php`: capability `pass_on_communications` (label "Passing on communications",
  roles owner, admin, secretary).
- Test: `tests/Feature/PassedOnPreferencesTest.php`

**Interfaces:**
- **Tables:**
  - `club_lodge_links`: `id`, `club_id` (fk cascade), `lodge_id` (fk cascade), `relation` (`sister`|`reciprocal`),
    `excluded` bool default false, timestamps; unique `(club_id, lodge_id, relation)`.
  - `passed_on_items`: `id`, `club_id` (the lodge that passed it on, or that published it), `sent_by` nullable fk users
    nullOnDelete, `source` (`manual`|`platform`), `kind` nullable (null for `platform`), `type`, `lodge_id` nullable,
    `meeting_id` nullable, `title`, `body` text nullable, `attachment_path` nullable, `attachment_name` nullable,
    `meeting_date` date nullable, `meeting_time` nullable string(5), `venue` nullable, `dress_code` nullable,
    `dedupe_key` index, `recipient_count` default 0, `email_count` default 0, `sent_at` nullable, timestamps.
  - `passed_on_deliveries`: `id`, `item_id` fk cascade, `user_id` nullable fk, `member_id` nullable fk
    `club_acc_members`, `email` nullable, `kind`, `via_club_id`, `lodge_id` nullable, `dedupe_key` index, `bell` bool,
    `emailed` bool, `follower` bool, `token` string(64) unique, `read_at` nullable, timestamps. Index
    `(dedupe_key, user_id)` and `(dedupe_key, member_id)`.
  - `users.passed_on_prefs` json nullable; `club_acc_members.passed_on_prefs` json nullable.
- **`PassedOnPreferences` (`final class`):**
  - `KINDS = ['sister','reciprocal','provincial','grand']`, `LABELS` (the Global Constraints labels).
  - `static fromArray(?array $prefs, bool $hasAccount = true): self`.
  - `channels(string $kind): array` returns a subset of `['app','email']`.
  - `mutes(?int $lodgeId): bool`, `calendar(): bool`, `withMuted(int $lodgeId): self`.
  - `withChannels(string $kind, array $channels): self`, `toArray(): array`.
  - Stored shape: `{"kinds":{"sister":["app","email"],...},"muted_lodges":[12],"calendar":true}`.
  - Without an account, `app` is always dropped.
- **Models:** `PassedOnItem::deliveries()`, `club()`, `lodge()`, `sender()`. `PassedOnDelivery::item()`, `user()`,
  `member()`. `ClubLodgeLink` has a `relation` constant list.

- [ ] **Step 1: Write the failing tests**

```php
public function test_a_member_who_has_chosen_nothing_gets_every_kind_by_app_and_email_with_the_calendar_on(): void
{
    $prefs = PassedOnPreferences::fromArray(null);
    foreach (PassedOnPreferences::KINDS as $kind) {
        $this->assertSame(['app', 'email'], $prefs->channels($kind));
    }
    $this->assertTrue($prefs->calendar());
    $this->assertFalse($prefs->mutes(5));
}

public function test_a_member_without_an_account_only_ever_gets_email(): void
{
    $prefs = PassedOnPreferences::fromArray(['kinds' => ['grand' => ['app', 'email']]], hasAccount: false);
    $this->assertSame(['email'], $prefs->channels('grand'));
    $this->assertSame(['email'], $prefs->channels('sister'));
}

public function test_choices_round_trip_and_unknown_kinds_or_channels_are_dropped(): void
{
    $prefs = PassedOnPreferences::fromArray(['kinds' => ['sister' => ['email', 'sms'], 'bogus' => ['app']], 'muted_lodges' => [7], 'calendar' => false]);
    $this->assertSame(['email'], $prefs->channels('sister'));
    $this->assertTrue($prefs->mutes(7));
    $this->assertSame($prefs->toArray(), PassedOnPreferences::fromArray($prefs->toArray())->toArray());
    $this->assertArrayNotHasKey('bogus', $prefs->toArray()['kinds']);
}
```

- [ ] **Step 2: Run and see it fail.** Run `php artisan test --compact tests/Feature/PassedOnPreferencesTest.php`.
  Expected: FAIL, class `PassedOnPreferences` not found.
- [ ] **Step 3: Write the migration, models, factories, config entry and `PassedOnPreferences`** as in the
  Interfaces block. Run `php artisan migrate` locally (PostgreSQL).
- [ ] **Step 4: Run and see it pass.** Run the same test file. Expected: PASS. Then run
  `php artisan test --compact tests/Feature/AdminRouteSweepTest.php` and expect PASS, since the new capability must
  not break the sweep.

---

### Task 2: The lodge's lists (sister and reciprocal lodges)

**Files:**
- Create: `app/Services/PassedOn/LodgeLinks.php`
- Create: `app/Http/Controllers/PassOnController.php` (holds both the lists actions here and the send actions in Task 3)
- Create: `resources/js/Pages/Member/PassOn/Lists.vue`
- Modify: `routes/club.php` (member area, next to `member.accounts`)
- Modify: `app/Http/Controllers/MemberPortalController.php`: a staff link `['key' => 'pass_on', 'label' => 'Pass on', ...]`
  when `PassOnController::canSend`.
- Test: `tests/Feature/PassOnListsTest.php`

**Interfaces:**
- **`LodgeLinks` service:**
  - `lodges(Club $club, string $relation): Collection<int, Lodge>`. For `sister`, that is every `Lodge::listed()`
    with the club's lodge's `masonic_hall_id` (not the club's own lodge, and not `excluded` for this club), plus manual
    `sister` rows. For `reciprocal`, it is manual rows only.
  - `hallSuggestions(Club $club): Collection<int, Lodge>`: the hall's lodges, each with `excluded` flagged.
  - `clubsListing(Lodge $lodge): Collection<int, array{club: Club, relation: string}>`, the reverse lookup used in
    Task 5.
  - `add(Club, Lodge, string $relation): void`, `remove(Club, Lodge, string $relation): void`. Removing a hall lodge
    from `sister` writes `excluded = true`; adding it back clears it.
- **`PassOnController::canSend(?User $user, Club $club): bool`** (static): the capability or the Secretary office.
- **Routes:**
  - `GET /members/{slug}/pass-on/lists` → `member.pass_on.lists`.
  - `POST /members/{slug}/pass-on/lists` → `member.pass_on.lists.update`, with body
    `{lodge_slug, relation, action: add|remove}` and `throttle:60,1`.
  - Both abort 403 unless `canSend`.
- **Page props:** `{ club, sister: Lodge[], reciprocal: Lodge[], hall: {name, lodges: [{slug, name, excluded}]} }`.
  Lodge search reuses the directory search already used on `Lodges/Index` (look for its endpoint, don't add another).

- [ ] **Step 1: Write the failing tests**
  - `test_sister_lodges_start_as_the_other_lodges_in_the_hall`: three listed lodges in the same hall plus one
    elsewhere. Assert `lodges($club, 'sister')` holds the two others, not the club's own lodge, and not the one
    elsewhere.
  - `test_the_secretary_can_untick_a_hall_lodge_and_add_others_to_either_list`: as a user with role `member` holding
    the Secretary office, post a remove for a hall lodge, add a lodge from another hall to `sister`, and add one to
    `reciprocal`. Assert the sister list changes accordingly, and that the removed one has an `excluded` row. Post an
    add for the removed one and assert it is back.
  - `test_a_plain_member_cannot_see_or_change_the_lists`: GET and POST return 403.
  - `test_lists_are_kept_per_lodge`: another club's changes don't affect this club's list.
- [ ] **Step 2: Run and see it fail.** Run `php artisan test --compact tests/Feature/PassOnListsTest.php`. Expected:
  FAIL (route not defined).
- [ ] **Step 3: Write `LodgeLinks`, the controller's lists actions, the routes, `Lists.vue` and the dashboard link.**
  The page is phone-first: two lists, each lodge with a Remove button, an "Add a lodge" search, and the hall's
  lodges with switches.
- [ ] **Step 4: Run and see it pass.** Run the same test file. Expected: PASS.

---

### Task 3: Passing something on, and who gets it

**Files:**
- Create: `app/Http/Requests/StorePassedOnItemRequest.php` (extends `ClubRequest`)
- Create: `app/Services/PassedOn/PassedOnAudience.php`
- Create: `app/Jobs/SendPassedOnItem.php`
- Create: `app/Mail/PassedOnMail.php`
- Create: view `resources/views/mail/passed-on.blade.php`
- Create: `resources/js/Pages/Member/PassOn/Index.vue`
- Modify: `app/Http/Controllers/PassOnController.php` (`index`, `store`)
- Modify: `app/Notifications/ClubNotification.php`: `PASSED_ON = 'passed_on'` and
  `static passedOn(PassedOnDelivery $delivery): self`. It must not be in `PUSH_OPTIONAL`, because members' own choices
  decide it.
- Modify: `routes/club.php`
- Test: `tests/Feature/PassOnSendTest.php`

**Interfaces:**
- **Routes:**
  - `GET /members/{slug}/pass-on` → `member.pass_on`. Props: the four kinds with labels, `sister`/`reciprocal`
    lodges (each with `publishes_own: bool` from Task 5, false until then), and the last 20 items sent with counts.
  - `POST /members/{slug}/pass-on` → `member.pass_on.store`, with `throttle:10,60`.
- **Request rules:**
  - `kind` in KINDS; `type` in `summons,notice`, and `provincial`/`grand` force `notice`.
  - `lodge_slug` is required for `sister` and `reciprocal`, and must be on that list (`LodgeLinks::lodges`).
  - `title` is required, max 150. `body` is nullable, max 20000.
  - `attachment` uses `UploadRules` and must be a PDF of at most 10240 KB. At least one of `body` or `attachment` is
    required.
  - For a summons: `meeting_date` is a required date, today or later. `meeting_time` (H:i), `venue` and `dress_code`
    are nullable.
- **`PassedOnAudience`:**
  - `recipients(PassedOnItem $item): Collection<int, array{user: ?User, member: ?Member, email: ?string, kind: string, via_club_id: int, lodge_id: ?int, follower: bool, bell: bool, emailed: bool}>`.
  - It covers active non-visitor `club_user` members of the item's club, and that club's roster members with
    `membership_status` Active, an email, and no `user_id`.
  - Choices come from `PassedOnPreferences` (`users.passed_on_prefs`, or the roster row's when there is no account).
    Anyone muting `lodge_id`, or with no channels for the kind, is skipped.
  - For type `summons` with a lodge, it adds `$lodge->followers()->wherePivot('notify_summons', true)` who aren't
    already included and don't mute it, as `follower: true, bell: true, emailed: false`.
  - It skips anyone holding a delivery with the same `dedupe_key` made in the last 60 days.
- **`PassedOnItem::dedupeKeyFor(...)`:** a static helper using the Global Constraints formula.
- **`SendPassedOnItem($itemId)` job:**
  - Runs on the `bulk` queue and does nothing if `sent_at` is set.
  - Writes the deliveries (`token = Str::random(64)`), then sends `ClubNotification::passedOn` to users with `bell`.
  - Queues a `PassedOnMail` for each delivery with `emailed`, then sets the counts and `sent_at`.
- **`PassedOnMail(PassedOnDelivery $delivery)`:**
  - Queued on `bulk`. Subject is the item title; the body holds the "Passed on by" line, the note, the meeting
    details, and a link "Open it" to `passed_on.public` (Task 4).
  - Headers carry `List-Unsubscribe` and `List-Unsubscribe-Post` pointing to `passed_on.unsubscribe` (Task 4), as
    `NewsletterMail` does.
  - The PDF is not attached; the email links to it. My call: this keeps the bulk mail small.

- [ ] **Step 1: Write the failing tests** (`Mail::fake()`, `Notification::fake()`, `Bus` not faked so the job runs on
  the sync queue)
  - `test_a_secretary_passes_on_a_sister_lodge_summons_and_members_get_it_as_they_chose`: three members, choices
    "both", "email only" and "off" for `sister`. Post a summons with a PDF. Assert:
    - one `PassedOnItem` with key `summons:{lodge}:{date}`;
    - bell notices go to the "both" member only;
    - `PassedOnMail` is queued for "both" and "email only";
    - nothing reaches "off".
  - `test_roster_members_without_an_account_are_emailed`: a roster member with an email and no user gets a
    `PassedOnMail` and a delivery with `member_id` set.
  - `test_followers_of_the_lodge_get_the_summons_in_the_app_but_not_by_email`: a user in another club following the
    lodge with `notify_summons` gets the bell and no mail; one with `notify_summons` false gets nothing.
  - `test_a_member_who_mutes_the_lodge_gets_nothing_about_it_even_as_a_follower` (Review Focus 3).
  - `test_two_secretaries_passing_on_the_same_meeting_reach_a_shared_member_once` (Review Focus 1): one user active in
    two clubs, and both clubs list the same lodge. Both pass on the same date. Assert one delivery and one bell notice
    for that user.
  - `test_the_same_provincial_circular_from_two_lodges_reaches_a_person_once`: the same PDF bytes are sent by two
    clubs. Assert one delivery.
  - `test_only_lodges_on_the_list_can_be_chosen_and_provincial_items_have_no_lodge`: a lodge not on the list gives a
    validation error on `lodge_slug`.
  - `test_a_plain_member_cannot_pass_anything_on`: 403.
  - `test_the_note_is_sanitised`: `<script>` is stripped from `body`.
- [ ] **Step 2: Run and see it fail.** Run `php artisan test --compact tests/Feature/PassOnSendTest.php`. Expected:
  FAIL.
- [ ] **Step 3: Write the request, audience, job, mail, notification factory method, controller actions and
  `Index.vue`.** The form is phone-first:
  - the kind as four large choices;
  - the lodge picker when needed;
  - type summons/notice;
  - title, note, PDF;
  - date, time, venue and dress for a summons.
  On send, flash "Passed on. It is going to N people." using the count of recipients worked out before queuing.
- [ ] **Step 4: Run and see it pass.** Run the same test file. Expected: PASS.

---

### Task 4: What members open, their choices, and the links in the email

**Files:**
- Create: `app/Http/Controllers/PassedOnController.php` (member side and token side)
- Create: `resources/js/Pages/Members/PassedOn/Show.vue`, `resources/js/Pages/Members/PassedOnChoices.vue`
- Create: Blade views `resources/views/passed-on/public.blade.php` and `resources/views/passed-on/choices.blade.php`,
  for no account and no sign-in, styled like `newsletters/unsubscribe.blade.php`.
- Modify: `routes/club.php` (signed-in) and `routes/public.php` (tokens)
- Modify: `resources/js/Pages/Members/AppSettings.vue` and `resources/js/Pages/Members/Notifications.vue`: a link
  "Choose what other lodges, Province and Grand Lodge send you".
- Modify: `app/Http/Controllers/NotificationController.php`: add `'passed_on'` to `CATEGORIES`.
- Test: `tests/Feature/PassedOnMemberTest.php`

**Interfaces:**
- **Signed-in routes:**
  - `GET /members/passed-on/{delivery}` → `members.passed_on.show`. The delivery must belong to the user, else 404.
    Opening it sets `read_at`.
  - `GET /members/passed-on/{delivery}/attachment` → `members.passed_on.attachment`.
  - `POST /members/passed-on/{delivery}/mute` → `members.passed_on.mute`, which mutes the item's lodge.
  - `GET /members/passed-on-choices` → `members.passed_on.choices`. Props: `{kinds: [{key, label, channels}],
    muted: [{id, name, slug}], calendar: bool}`.
  - `POST /members/passed-on-choices` → `members.passed_on.choices.update`, with body
    `{kinds: {kind: [channels]}, unmute?: lodge_id, calendar: bool}` and `throttle:30,1`.
- **Token routes** (`routes/public.php`, `throttle:60,1`):
  - `GET /webhooks/passed-on/{token}` → `passed_on.public`: the item, its PDF link, and the person's choices.
  - `GET /webhooks/passed-on/{token}/attachment` → `passed_on.public.attachment`.
  - `POST /webhooks/passed-on/{token}/choices` → `passed_on.choices`: email on or off per kind, and mute this lodge.
  - `POST /webhooks/passed-on/{token}/unsubscribe` → `passed_on.unsubscribe`: the one-click route (CSRF-exempt like
    the newsletter one). It turns email off for that delivery's kind.
  - A choice made by token writes to the user's choices if the delivery has `user_id`, else the roster row's.
- **Sender access:** the sending lodge's senders (`PassOnController::canSend`) may also download the attachment from
  `member.pass_on` (link `member.pass_on.attachment`, `GET /members/{slug}/pass-on/{item}/attachment`).

- [ ] **Step 1: Write the failing tests**
  - `test_a_member_opens_what_was_passed_on_and_downloads_the_pdf`: show returns 200 with the title and the
    "Passed on by" line, and the attachment streams a PDF.
  - `test_someone_without_a_delivery_cannot_open_it_or_its_pdf` (Review Focus 4): another user gets 404 on show and
    attachment, and a made-up token gets 404.
  - `test_muting_a_lodge_from_the_item_stops_the_next_one`: mute, then pass on another summons for that lodge, and
    assert no new delivery for that user.
  - `test_a_member_changes_their_choices`: post `sister => []` and `grand => ['email']`; assert they are stored and
    the next sister item doesn't reach them.
  - `test_one_click_unsubscribe_turns_off_email_for_that_kind_only` (Review Focus 5): a roster member with no
    account posts to unsubscribe from a `provincial` delivery. Then a `grand` item still emails them and a
    `provincial` one doesn't.
  - `test_the_lodges_sender_can_download_the_pdf_they_passed_on`.
- [ ] **Step 2: Run and see it fail.** Run `php artisan test --compact tests/Feature/PassedOnMemberTest.php`.
  Expected: FAIL.
- [ ] **Step 3: Write the controller, routes, pages and links.**
  - The item page shows the title, the "Passed on by" line, the meeting details, the note (`v-html`, already
    sanitised), an "Open the PDF" button, an "Ask to visit" link to `lodges.show` when the lodge is listed, and
    "Stop hearing about {lodge}".
  - The bell notice from Task 3 links here.
- [ ] **Step 4: Run and see it pass.** Run the same test file. Expected: PASS.

---

### Task 5: Lodges on the platform: their own summons reaches listing lodges

**Files:**
- Create: `app/Services/PassedOn/PlatformSummons.php`
- Modify: `app/Http/Controllers/MeetingSummonsController.php:114-115`: after `toMembers`, call
  `$reached = app(PlatformSummons::class)->publish($club, $meeting)`, then `toFollowers($club, $meeting, $reached)`.
- Modify: `app/Services/ClubNotifier.php`: `toFollowers(Club $club, Meeting $meeting, array $exceptUserIds = [])`.
- Modify: `app/Http/Requests/StorePassedOnItemRequest.php` and `PassOnController::index` (`publishes_own`).
- Test: `tests/Feature/PassedOnPlatformTest.php`

**Interfaces:**
- **`PlatformSummons::publishesOwn(Lodge $lodge): bool`:** the lodge `isManaged()` and its club's
  `VisitorSummons::visibility()` is not `MEMBERS`.
- **`PlatformSummons::publish(Club $club, Meeting $meeting): list<int>`.** Returns the user ids reached.
  - If `$club->lodge` exists and `publishesOwn`, it creates one `PassedOnItem` with `source = platform`,
    `type = summons`, `meeting_id`, and `lodge_id`.
  - The item carries the fields from `VisitorSummons::present()`: date, time, venue, dress code and title. `body` is
    null, because only the key facts are ever shared.
  - It dispatches `SendPassedOnItem` synchronously with `dispatchSync`, so the ids can be returned.
  - `PassedOnAudience` is extended for `source = platform`. For each `LodgeLinks::clubsListing($lodge)`, it takes that
    club's recipients with `kind` = the relation and `via_club_id` = that club.
  - It leaves out members of the publishing club, and users for whom `VisitorSummons::canSee($club, $user)` is false.
  - Roster members without an account are included only when visibility is `PUBLIC_SAFE`. Followers are not added
    here; `toFollowers` handles them, except those reached.
- **Form rule:** for `sister`/`reciprocal` with `type = summons`, a lodge where `publishesOwn` is true gives the error
  "{Lodge} publishes its own summonses on Club Manager, so your members already get them." unless an `attachment` is
  given.
  - With an attachment, an existing delivery with the same dedupe key is repointed to the new item silently, with no
    new bell or mail. Its `item_id` changes to the Secretary's item, which carries the PDF.
  - Recipients without one get it as normal.
  - My call: this is how "adds the PDF to the same item, no second notice" works without exposing the PDF to other
    lodges' members.
- **The publishing lodge's own item:** `PassedOnItem.club_id` is the publishing club, and the "Passed on by" line
  reads "Published by {club name}".

- [ ] **Step 1: Write the failing tests**
  - `test_a_platform_lodges_summons_reaches_members_of_lodges_that_list_it_with_only_the_key_facts`: lodge B is on
    the platform with `summons_visibility = public_safe`, and club A lists B as sister. Publish B's meeting and
    assert A's member gets a delivery with kind `sister` and no body or attachment, and B's own members get none.
  - `test_a_members_only_lodge_sends_nothing_outside_and_can_be_passed_on_by_hand` (Review Focus 2): with
    visibility `members`, publish and assert no deliveries, and A's Secretary can pass on B's summons with no error.
  - `test_a_secretary_attaching_the_pdf_to_a_platform_summons_sends_no_second_notice`: after the auto delivery, A's
    Secretary passes on the same date with a PDF. Assert the member's delivery now points to the new item and the
    bell count for them is still 1.
  - `test_a_follower_who_is_also_in_a_listing_lodge_is_told_once`: B's follower is in A, and is notified once in total
    (one `ClubNotification`, category `passed_on`, and no `followedSummons`).
  - `test_without_a_pdf_the_form_refuses_a_summons_for_a_lodge_that_publishes_its_own`.
- [ ] **Step 2: Run and see it fail.** Run `php artisan test --compact tests/Feature/PassedOnPlatformTest.php`.
  Expected: FAIL.
- [ ] **Step 3: Write `PlatformSummons`, extend the audience, change `toFollowers`, the request rule and
  `publishes_own`.**
- [ ] **Step 4: Run and see it pass.** Run the same test file, plus
  `php artisan test --compact --filter=Follow` for the existing follower tests. Expected: PASS.

---

### Task 6: Passed-on meetings in the calendar

**Files:**
- Modify: `app/Services/MemberCalendar.php`: new `private function passedOnItems(User $user, Collection $memberClubIds, CarbonInterface $from, CarbonInterface $to): Collection`,
  merged in `items()` before `flagClashes`.
- Test: `tests/Feature/PassedOnCalendarTest.php`

**Interfaces:**
- **What goes in:** the user's deliveries whose item is a `summons` with `meeting_date` in range, when
  `PassedOnPreferences::calendar()` is on and the lodge isn't muted.
- **Item shape:** the same as `followedLodgeItems`, with:
  - `key` `lodge-{lodge_id}-{Y-m-d}`;
  - `type` `'passed_on'`;
  - `title` = item title;
  - `where` = venue;
  - `all_day` when there is no time;
  - `url` = `members.passed_on.show`.
- **Duplicates:** where a followed lodge's expected item has the same key, the passed-on one replaces it
  (`keyBy('key')`, passed-on last).
- **Vue:** check `resources/js/Pages/Members/Calendar.vue` handles `type: 'passed_on'` like `lodge`. Add it to any
  type switch.

- [ ] **Step 1: Write the failing tests**
  - `test_a_passed_on_summons_is_in_the_calendar_and_replaces_the_expected_meeting`: the user follows lodge L with
    `in_calendar`, and a delivery for L on a date the pattern also gives. Assert one item on that date with
    `type === 'passed_on'`.
  - `test_it_stays_out_when_the_member_turned_the_calendar_off_or_muted_the_lodge`.
- [ ] **Step 2: Run and see it fail.** Run `php artisan test --compact tests/Feature/PassedOnCalendarTest.php`.
  Expected: FAIL.
- [ ] **Step 3: Implement `passedOnItems` and the Vue type handling.**
- [ ] **Step 4: Run and see it pass.** Run the same test file and
  `php artisan test --compact --filter=Calendar`. Expected: PASS.

---

### Task 7: Checks across the whole change, and the screens on a phone

**Files:** none new, except fixes the checks turn up.

- [ ] **Step 1: Run the PHP checks.** Run `vendor/bin/pint --dirty --format agent`, then `composer analyse`.
  Expected: pint passes and PHPStan reports 0 errors. Don't add to the baseline.
- [ ] **Step 2: Run the front-end checks.** Run `npm run lint`, `npm run check:templates`, `npm run check:design`
  and `npm run build`. Expected: no errors, design counts not raised, and the build succeeds.
- [ ] **Step 3: Run the full suite.** Run `php artisan test --compact`. Expected: all pass, with the 1 existing skip.
- [ ] **Step 4: Check the screens in the browser pane at phone width (375×812).**
  - Move `public/hot` to the scratchpad first, and restore it afterwards.
  - Use the local demo login (`database/seeders/ClubSeeder.php`).
  - Go through each screen in turn:
    1. Dashboard "Pass on" link, then the lists page.
    2. Pass on a Provincial notice with a PDF.
    3. The bell, then the item page and its PDF.
    4. The choices page, where you switch email off for Provincial.
    5. The calendar, after passing on a sister summons with a date.
  - Also check dark mode on the item page.
  - Remove any test data made, and reset the viewport to desktop.
- [ ] **Step 5: Report** what was checked and anything not checked. Leave everything uncommitted for the user.
