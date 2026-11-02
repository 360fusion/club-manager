# Members, imports, member area and account access: end-to-end results

Run 2 Nov 2026 against the e2e app (`http://127.0.0.1:8001`, PostgreSQL `club_manager_e2e`, Mailpit). Four test agents covered the member import, the roster and export, the member's own area (`/members/...`) and accounts/2FA/access. Their full per-test notes, with every repro, are in the shared notes folder (`import.md`, `roster.md`, `memberarea.md`, `accounts.md` under `/Users/ben/Library/Application Support/Claude/side-session-notes/local_a0cb9e9d-7f9f-4548-b51f-6dbdacaed989/`). Nothing in the application was changed or committed. Everything created (prefixed members, throwaway `mem.*@e2e.test` users, imports, avatars, test content) was removed and checked by count query.

Three findings were re-checked against the code by the coordinator: the summons PDF call (`MemberMeetingController.php:85-88` calls `MeetingAdminController::pdf`, which no longer exists; the method is in `MeetingSummonsController.php:31`), the notification filter (`NotificationController.php:22` uses `data->category`), and the active check in `MemberPortalController.php:35-39` (only tests that a pivot row exists).

## Results

### 1. Member import (`/lodge-of-fraternity/admin/members/import`)

| ID | Result | Note |
|---|---|---|
| IMP1 file types | PASS | Only CSV and TXT (max 5 MB, 5,000 rows). **XLSX is not supported** (no spreadsheet library); the message tells the user to save as CSV. |
| IMP2 wrong types | PASS | `.svg`, `.html`, `.php`, `.exe`, PDF/XLSX renamed `.csv` all refused, no import record. |
| IMP3 good rows | PASS | Auto-matched columns, correct rank, status, dates. |
| IMP4 duplicates against roster | PASS | Email match ignores case and spaces; name-only match is "Possible"; Compare details works. |
| IMP5 duplicates inside the file | PASS | Keep first / last / both work at run time; the preview count is wrong; double-space names slip through. (see F86, F87) |
| IMP6 bulk choices ("update every duplicate / possible") | PASS | Applies only to rows that can take it; fill-only and overwrite behave as described. |
| IMP7 bad emails, missing names, odd rank/status | PASS | Clear messages with the value; unknown rank is a warning. |
| IMP8 dates | PASS | Many formats and Excel serials fine; impossible dates refused; **two-digit years rejected**. (see F84) |
| IMP9 long text | PASS | Over-length fields refused; notes of exactly 5000 accepted. |
| IMP10 formulas and HTML | PASS | Stored as written, displayed escaped everywhere checked, apostrophe-prefixed in the roster export (checked via the export code in tinker, not a browser download). |
| IMP11 encodings | PASS | BOM, semicolon, CRLF with multi-line cells, Windows-1252, tab-delimited all read correctly. |
| IMP12 empty / header-only | PASS | Message for an empty file is misleading (Low). |
| IMP13 size limits | FAIL | 5,001+ rows refused cleanly; a 5,000-row import cannot be run or undone from the UI. (see F85) |
| IMP14 mapping (save, reuse, required fields) | PASS | Remove-saved-mapping button not clicked in the browser. |
| IMP15 run, summary, blocked rows | PASS | Preview and summary counts disagree. (see F86) |
| IMP16 double-click Run / two tabs | PASS | One set of members; the second tab gets a bare 404 overlay. (see F86) |
| IMP17 undo | PASS | Created removed, updated restored, accounts kept, later phone edit kept; a created member edited since is deleted silently. (see F87) |
| IMP18 cross-lodge isolation | PASS | Other lodge's admin gets 403 on every route; `undoImport(5)` from another lodge returns 404; locked properties reject tampering. |
| IMP19 permissions | PASS | Coach, treasurer, plain member: 403 on page, list, detail and export. |
| IMP20 no email unless chosen | PASS | Mailpit empty for all imports without "send invitations"; with it, exactly the right invitations. |
| IMP21 Back button | PASS | Resumes the staged import. |
| IMP22 `app:prune-member-imports` | PASS | Run on back-dated rows of the test's own; it touches only imports past the stale/keep thresholds and deletes the stored file. |

### 2. Roster, export and member form

| ID | Result | Note |
|---|---|---|
| ROS1 export columns, filename, quoting | PASS | 16 columns, commas/quotes/newlines quoted correctly. |
| ROS2 export formula injection | PASS | `= + - @`, tab and CR neutralised (side effect on phones). (see F106) |
| ROS3 export private fields and isolation | PASS | No notes, DOB, tokens; no other-lodge rows. |
| ROS4 export honours filters | FAIL | Rank and portal-account filters ignored. (see F106) |
| ROS5 export of a large roster | FAIL | 5,076 members: 27-29 s, HTTP 500 from the browser. (see F89) |
| ROS6 search by name/email/partial/unicode | PASS | |
| ROS7 search case-insensitive and full name | FAIL | `archer` finds nothing; full/preferred name not searched. See F90, F91 |
| ROS8 search with SQL-ish, 60k-char and NUL input | PASS | |
| ROS9 LIKE wildcards `%` `_` | FAIL (Low) | Match everything. See F106 |
| ROS10 filters and pagination | PASS | All counts match the database; filters kept on page 2. |
| ROS11 add member, rank dropdown | PASS | Options equal `Member::MASONIC_RANKS` exactly; bad rank, status, office refused; inline errors visible in the browser. |
| ROS12 duplicate email | FAIL | Same email accepted repeatedly. See F92 |
| ROS13 HTML in names | PASS | Escaped everywhere. |
| ROS14 dates, notes length | FAIL (Low) | Future/1850 dates and unlimited notes accepted. See F106 |
| ROS15 profile page and `formatted_rank_name` | PASS | The old `title` column is ignored. The roster list shows names without the rank. (see F106) |
| ROS16 rowing-club rank hiding | FAIL | Masonic fields shown on a rowing club. See F93 |
| ROS17 resign / mark past | PASS | Status and pivot change; the past user still reads the club's member pages. (see F82) |
| ROS18 reinstate | FAIL (Low) | Portal access not restored. See F106 |
| ROS19 remove from roster | FAIL | Cascade deletes dues and giving history. (see F88) |
| ROS20 offices | PARTIAL | One office field per member, no dates; several members can hold WM. Roster assignment via the officers screen not run. (see F106) |
| ROS21 permissions and id tampering | PASS | Coach, treasurer, member, other lodge's admin, logged-out refused on every roster route and export; cross-lodge ids 404; snapshot replay 403. |
| ROS22 no N+1 | PASS | Constant 11 queries, including with 5,000 members. |
| ROS23 bad ids and tampered input | FAIL (Low) | 500s instead of validation messages. (see F106) |

### 3. The member's own area (`/members/...`)

| ID | Result | Note |
|---|---|---|
| MA1 every page opens (dashboard, calendar, events, meetings, news, dues, directory, notifications, profile, security, signed documents, subscriptions) | PASS | 200, no console errors, empty states for a user with no clubs. There is no `/members/payments` page and no member directory (the "directory" is the national lodge directory). |
| MA2 workspace switcher / `?club=` scoping | PASS | No item from the other lodge in any page's props. |
| MA3 per-club pages of a lodge you do not belong to | PASS | Refused (mixed 302/403/404, Low). |
| MA4 other users' data by id (notification, signature request, admin member pages) | PASS | 403/404, nothing changed. |
| MA5 draft, expired, future, foreign news | PASS | 404. |
| MA6 inbox read/unread, mark all read | PASS | Only the user's own rows. |
| MA7 notification category filter | FAIL | HTTP 500 on PostgreSQL. See F94 |
| MA8 subscription toggles, mandatory channels | PASS | Mandatory channel cannot be switched off. |
| MA9 non-member subscribing to a channel | FAIL | Not-subscribable channel accepted. See F96 |
| MA10 profile validation | PASS | Empty, long, bad and duplicate email refused. |
| MA11 email in a different letter case | FAIL | Accepted as a second account. See F98 |
| MA12 HTML in name | PASS | Escaped. |
| MA13 email change | PARTIAL | Verification goes to the new address and login is blocked until verified; no notice to the old address; roster not updated. The verify link click itself was not done (shared limiter returned 429). (see F100) |
| MA14 password change rules | PASS | Weak passwords accepted outside production, as designed. Other sessions survive. (see F101) |
| MA15 avatar upload | PASS | PNG/JPG ok; SVG, HTML, PHP-as-JPG, over 4 MB, over 6000 px refused. Photos are shrunk to **800 px**, not the 1920 px stated for other uploads. |
| MA16 avatar replace / remove | FAIL | Remove does nothing; old file kept. See F97 |
| MA17 `.ics` feed token | PASS | Wrong token 404; regenerate invalidates the old one; only the user's active clubs; no drafts. |
| MA18 pending and past user on `/members/{slug}` | FAIL | Club content readable. See F82 |
| MA19 whole `Club` model and draft events in props | FAIL | Club settings and draft/cancelled events reach the browser. See F83 |
| MA20 summons PDF | FAIL | HTTP 500 for every member. See F81 |
| MA21 per-club dues page | FAIL | Contradicts the all-clubs page. See F95 |
| MA22 RSVP and quick RSVP | PASS | Pending, past and other-club refused. |
| MA23 mobile 375 px, dark mode, Back/forward | PASS | |

### 4. Accounts, 2FA and access

| ID | Result | Note |
|---|---|---|
| ACC1 2FA enable, wrong then right code, recovery codes | PASS | TOTP is PragmaRX Google2FA (SHA1, 6 digits, 30 s). No password asked; wrong code shows no message on screen. (see F102, F103) |
| ACC2 2FA login challenge | PASS | Wrong code refused; used code cannot be replayed; challenge page unreachable without the half-login; members pages blocked until the code is given. |
| ACC3 recovery-code login | PASS | Consumed and replaced; reuse refused. |
| ACC4 challenge throttling | PASS | 5/min, keyed by user id, 429 with Retry-After. |
| ACC5 disable 2FA | PASS | Needs no password or code. (see F102) |
| ACC6 API token for a 2FA account | PASS | 403 "Tokens cannot be issued for accounts with two-factor authentication"; non-2FA account gets a token. A token issued before enabling 2FA keeps working. (see F101) |
| ACC7 pending member sees no lodge content | FAIL | Dashboard, events, meetings, news, calendar, calendar feed, RSVP, booking: empty/refused; but `/members/{slug}` and `/members/{slug}/events` return content. (see F82) |
| ACC8 past member loses access | FAIL | Same page; passwordless RSVP link still works. (see F82, F80) |
| ACC9 password reset end to end | PASS | Mail arrives, link works once, expired/tampered/second use fail, old password dies. The unknown-address reply is not neutral. Sessions and tokens survive. (see F99, F101) |
| ACC10 registration weak/duplicate data | PASS | Duplicate and case-different addresses get the neutral message; whitespace trimmed, email lowercased; HTML escaped; verification links work and fail when tampered. |
| ACC11 registration and reset-request throttle | BLOCKED | The limiter is keyed by IP only and shared; not run to avoid locking out other sessions. (see F104) |
| ACC12 login throttling | PASS | Email+IP, 5/min, 429 with Retry-After, works again after the window. |
| ACC13 logout from every layout | PASS | `POST /logout` works from members, public, superadmin, admin and the 2FA challenge page; `GET /logout` does not log out; session id changes on login. |
| ACC14 cache headers after logout | FAIL (Low) | Inertia pages send `no-cache, private`, not `no-store`. (see F105) |

## Findings (not fixed)

Numbering continues after the plan's F1-F35 (F80 onward, as agreed for this group).

### F80 High. Passwordless RSVP page leaks whole user, club and meeting records and ignores membership status
- Steps: `GET /summons/rsvp/{token}` with no login, with a token from `RsvpTokenService::createTokenForUser` for an active, a pending and a past user (all three load). `POST /summons/rsvp/{token}` with `attendance_status=attending_dining` and `guests[0][guest_name]=G` from the pending user saved the RSVP and the guest; the past user's POST saved "apologies".
- Saw: the page props hold the whole `user` row (`calendar_token`, `stripe_id`, `pm_type`, `pm_last_four`, `is_super_admin`, `two_factor_confirmed_at`), the whole `club`, and the whole `meeting` (`bank_sort_code`, `bank_account_number`, `almoner_notice`, `sick_distressed_notes`, `payment_link`). The leaked `calendar_token` opens `/calendar/{token}.ics` with no login (23 events for the active user). Anyone who gets the emailed link gets the person's permanent calendar link.
- Expected: only name, meeting date/venue/cost and the person's own RSVP; refuse a token whose holder is not an active member (visitors excepted).

### F81 High. Member summons PDF is broken for every member
- Steps: as an active member open a meeting's summons page and click "Download PDF summons", or `GET /members/lodge-of-fraternity/meetings/{id}/pdf`.
- Saw: HTTP 500, `Call to undefined method MeetingAdminController::pdf()` (`MemberMeetingController.php:85-88`; the method now lives in `MeetingSummonsController::pdf`). The button is in `Member/Meetings/Summons.vue:50` and `Member/Events.vue:142`.

### F82 High. Pending and past members can read the lodge's member pages
- Steps: with `club_user.status` pending or past, `GET /members/lodge-of-fraternity` and `/members/lodge-of-fraternity/events` (also `GET`/`POST /members/{slug}/profile`).
- Saw: HTTP 200 with the club's published meetings (venue, dress code, dining cost, own RSVP), every sent newsletter with its full content, and the club's events. For a past member `isPending` is false, so the page shows no notice and presents them as a normal member. A past member can also save phone, emergency contact and dietary notes for the club. Cause: `MemberPortalController::show` (`:35-39`), `events()` and `MemberProfileController` only check that a pivot row exists, not that it is `active`; `MemberScope` and `dues()` do it right. Resigning a member from the roster (which sets the pivot to `past`) therefore does not remove this access. `/members/dashboard` and the other `/members/{slug}/...` pages refuse correctly.
- Expected: 403/404 or a "membership ended" page for anyone not active.

### F83 Medium. Whole `Club` model, settings and draft events sent to the browser
- Steps: any active member, `GET /members/lodge-of-fraternity` (Inertia JSON), `/events`, `/dues`, `/profile`.
- Saw: the `club` prop is the whole model: every column and the full `settings` JSON (permission matrix, approval threshold, VAT, charity numbers), and `club.events` with all draft and cancelled events (for example a draft with "SECRET draft text" and prices) that the page's own list filters out. No gateway secret was found in the e2e clubs, but nothing is hidden on the model.
- Expected: pass a small array (id, name, slug, logo, colours) and only published events.

### F84 Medium. Two-digit years rejected by the member import
- Steps: CSV with Date of joining `25/12/15` or `1 Jan 99`; analyse.
- Saw: "Date of joining '25/12/15' is not a date we can read", row not imported. `MemberImportValues::date()` tries `d/m/Y` first, which PHP reads as year 0015, then returns null instead of trying the later `d/m/y` and `d M y` formats.
- Expected: 25 Dec 2015, with a pivot rule for `99`.

### F85 Medium. A 5,000-row import cannot be run or undone from the UI
- Steps: upload a valid 5,000-row CSV (inside the advertised limit), Continue (about 9 s), click Import members; then click Undo.
- Saw: both die with "Maximum execution time of 30 seconds exceeded", roll back, and the user sees a server error. In tinker the run took 115 s and the undo 44 s (the server was busy). Anything over about 2,000 rows is probably at risk.
- Expected: a lower row limit, chunking or queueing the run and undo, or a clear message.

### F86 Low to medium. The "Ready to import" counts ignore the file-duplicate choice, and a stale tab shows a bare 404
- Steps (counts): rows 3 and 4 share an email, choose "Keep the last", read "4 will be added, 2 updated and 2 skipped". Saw: the run added 3 and skipped 3.
- Steps (404): open the same staged import in two tabs, or upload another file in tab 2, then click Continue/Import in tab 1. Saw: a white "404 Not Found" overlay (`stagedImport()` uses `findOrFail`); the friendly "already run or cancelled" text is never reached.

### F87 Low to medium. Import undo deletes a created member that was edited afterwards; other import points
- Steps: import, edit a created member's phone or notes, undo. Saw: "Undone: 5 removed", the edited member deleted with its edits and no warning (updated rows are protected, created ones are not).
- Also: names that differ only by a double space are not flagged as duplicates (`MemberImportValues::nameKey`); the notice banner persists across steps; an empty file gets "Please upload a CSV" instead of "empty"; undoing an invited import leaves the stub user rows; history shows only the 8 latest imports so older ones cannot be undone in the UI; row numbers skip blank lines; `Csv.php` raises PHP 8.4 `fputcsv` deprecation warnings.

### F88 Medium. Deleting a member destroys dues and giving history and leaves portal access
- Steps: member with a £160 dues row (£40 paid) and a festival-giving row (£250), linked to an active portal user; profile, Delete member, confirm.
- Saw: the member and both financial rows are hard-deleted (`ON DELETE CASCADE`; the dialog says only "This action cannot be undone"). The linked user's `club_user` row stays `active` (`MemberObserver::deleting` uses `endAccess: false`).
- Expected: block or soft-delete members with financial history, or say what is lost; end or warn about portal access.

### F89 Medium. Export of a large roster is slow and can 500
- Steps: club with 5,076 active members, click Export CSV. Saw: HTTP 500 "Maximum execution time of 30 seconds exceeded" after about 30 s (27-29 s from curl, 7 s on a quiet server). The file is built in memory and returned base64 inside the Livewire response.

### F90 Medium. Roster search is case-sensitive on PostgreSQL
- Steps: search `archer`, `ARCHER` or `zoë`. Saw: "No members match"; `Archer` finds her. `Member::scopeSearch` uses `like`. Expected: case-insensitive.

### F91 Medium. Roster search does not match what the list shows
- Steps: search "Comma Smith", a preferred name, or a middle name. Saw: 0 rows. Search covers only first name, last name, email, Hermes ID and ranks.

### F92 Medium. Duplicate emails are accepted on the roster
- Steps: add a member with the same email three times, in other letter case, and with `member.a1@e2e.test` (an existing member's). Saw: all saved with no error or warning, in the form and the profile quick edit. Expected: refuse or warn within the same club.

### F93 Medium. Rowing-type club shows masonic fields
- Steps: open the roster, add form or profile of `lodge-of-fraternity` ("Rowing & Water Sports"). Saw: Masonic rank, Grand/Provincial rank lists, offices WM/SW/Tyler/IPM, "Rule 153 roster", "brethren". `MasonicRanks::isMasonic()` is only used to seed settings. The Users page has a separate rowing rank setting, so the club has two unrelated rank systems. The craft lodge (`apollo-lodge`) is right.

### F94 Medium. Notification category filter returns 500 on PostgreSQL
- Steps: `GET /members/notifications?category=news` (or click "News"). Saw: `SQLSTATE[42883] operator does not exist: text ->> unknown` (`NotificationController.php:22`, `where('data->category', ...)`; `notifications.data` is a `text` column). Also `GET /members/notifications/nonsense/open` gives 500 (`invalid input syntax for type uuid`), expected 404.

### F95 Medium. Per-club dues page contradicts the all-clubs one
- Steps: member with an unpaid £101 subscription; compare `/members/dues` ("£101.00 to pay, Unpaid") with `/members/lodge-of-fraternity/dues` ("Good standing", "No billing receipts found"). The per-club page reads only invoices, not `club_acc_member_subscriptions`; the status chip is hard-coded, an "Active subscription tier" shows although the member has none, and the member number falls back to a fake `MEM-1001`.

### F96 Medium. Any signed-in user can subscribe to any club's channel; the broadcast feed ignores membership status
- Steps: as a non-member `POST /members/subscriptions/bath-rfc/5/toggle` for a channel with `is_external_subscribable=false`. Saw: an `active` `newsletter_subscriptions` row (and the user would then be mailed as a visitor); `require_approval` is skipped too. `ClubDirectoryController::subscribe` checks both.
- Also `/members/subscriptions` lists sent newsletters (full HTML) of clubs where the user is pending or past, and ignores `target_roles` (the latter from code reading only).

### F97 Medium. "Remove photo" does nothing; replaced avatars are left on disk
- Steps: profile, upload a photo, Remove photo, Save profile. Saw: success flash, the photo returns on reload (the server only acts on a non-empty `avatar_url`; same in `Admin\ProfileController::update`). Replacing an avatar leaves the old file in `storage/app/public/avatars` (three uploads, three files). The `avatar_url` field also accepts any `https://` URL or an `avatars/../` path.

### F98 Medium. Email uniqueness is case-sensitive on the profile
- Steps: `PUT /members/profile` with `email=MEMBER.A4@E2E.TEST` while `member.a4@e2e.test` exists. Saw: accepted, saved in mixed case, verification mail sent (Mailpit). Two accounts can share one mailbox.

### F99 Medium. Forgot-password reveals whether an address has an account
- Steps: `POST /forgot-password` with an unknown address: "We can't find a user with that email address."; with a real one: "We have emailed your password reset link."; again within 60 s: "Please wait before retrying." Registration is neutral; this is not.

### F100 Low to medium. Profile name or email changes do not reach the roster; no notice to the old address
- Steps: change name/email on the profile. Saw: `club_acc_members` keeps the old values, so lodge mail that uses the roster goes to the old address; no email to the old address; the existing session keeps working.

### F101 Medium. Sessions and API tokens survive a password reset, a password change and enabling 2FA
- Steps: log in on two sessions, reset (or change) the password in one. Saw: the other session still loads `/members/dashboard`; a Sanctum token issued before 2FA was enabled still works on `/api/v1/user` while new tokens get 403. Expected: `logoutOtherDevices` or delete session rows and tokens.

### F102 Medium. 2FA can be disabled, and its secret and recovery codes read at any time, with no password or code
- Steps: `DELETE /members/security/disable` (or `DELETE /user/two-factor-authentication`); `GET /user/two-factor-secret-key`, `/user/two-factor-recovery-codes`, or open `/members/security`. `config/fortify.php` has `confirmPassword => false`. A hijacked or left-open session defeats 2FA and can read the permanent secret. Fortify's own routes are live beside the app's.

### F103 Medium. A wrong 2FA setup code shows no message
- Steps: `/members/security`, Enable, type 000000, Confirm. The server answers under the error bag `confirmTwoFactorAuthentication`, but `TwoFactorSetting.vue` reads `confirmForm.errors.code`, so the button stops "Confirming..." with no message. Fix: post with `errorBag: 'confirmTwoFactorAuthentication'`.

### F104 Medium. The shared rate limiters are keyed by IP only
- `auth-forms` (10/min: register, forgot/reset password, verification, invitation, signature, public booking manage/pay/cancel links) and `public-forms` (6/min: passwordless RSVP POST, public event register, contact form). Everyone behind one address (a lodge hall's wifi, a mobile NAT) shares them. Seen: 4-5 requests left before the first request, and 429s after 5-6 requests. The registration and reset-request throttle probes were therefore not run. Suggest adding the email or token to the key. Login (`email+IP`, 5/min) and the 2FA challenge (user id) are fine; note that five successful logins in a minute also lock that email+IP.

### F105 Low. Smaller account and member-area findings
- No "password reset" confirmation on the login page afterwards (`status` is not passed to `Login.vue`).
- The reset mail says "Laravel" (`APP_NAME`); its plain-text part has no link at all (the HTML part is fine).
- The 2FA challenge page has no log-out or "back to login" button; a past member's dashboard is empty with no explanation; the Security page says "Secure your administrator account" for every member.
- A new user who signs in straight after registering gets two verification mails.
- Inertia pages send `Cache-Control: no-cache, private` (Livewire admin pages send `no-store`), so Back after logout may show a cached private page (not checked in a browser).
- `GET /members/directory?region[]=a` returns 500 (`(string)` cast of an array).
- `/members/subscriptions` sends the whole user model to the page (`calendar_token`, `stripe_id`, `pm_type`, `pm_last_four`, `two_factor_confirmed_at`): a second place besides the fixed `/api/v1/user` (F33).
- Forbidden `/members/{other-club}/...` answers vary (302 to public site, 403, 404).

### F106 Low. Smaller roster and export findings
- Export ignores the rank and portal-account filters and defaults to Active only, with no hint on the page.
- `Csv::safe` prefixes phones like `+44 7700 900123` with an apostrophe.
- `%` and `_` in search act as wildcards.
- HTTP 500 instead of a message on `/admin/members/abc`, a 20-digit id, a tampered `sortField`/`sortDirection`, and `membership_status`/`current_office` on the profile quick edit (`MembershipStatus::from`). `annual_dues_override` is unvalidated (a huge value gives a 500).
- Future and 1850 dates accepted; notes have no length cap.
- `current_office` is one field per member: several members can be WM, resigned members keep their office and are counted as officers; the "Active, subscribing" stat includes Historical members.
- Setting a resigned member back to Active does not restore portal access (the pivot stays `past`); the Users page "Move to Past" changes only the pivot, leaving the roster member Active.
- The roster list shows `full_name` without the rank title (CLAUDE.md says to show members with `formatted_rank_name`); the export's Current Office column carries the label, not the code.
- Filters are not in the URL, so a reload loses them.

## Not run

- Import: the download buttons (Export CSV, "Download rows with errors", template) were not captured from the browser pane; removing a saved mapping with the button; grand/provincial rank warnings, Hermes ID matching and the ambiguous/conflict duplicate cases (no seed data); a truly simultaneous double submit.
- Roster: invitation emails from the roster (invite, resend, revoke, bulk; already covered by A2); real browser clicks on the resign and delete confirm dialogs (Livewire calls were made directly); office assignment through the officers roster.
- Member area: the verification link click after an email change (429 from the shared limiter, set verified in the database); real Stripe/PayPal member payments; sent-newsletter HTML sanitising in the broadcast feed; the `target_roles` leak (code reading only); hidden-from-directory members (the concept does not exist under `/members`).
- Accounts: the registration and reset-request throttle probes (BLOCKED, F102); the recovery-code UI, the 429 and 419 pages as a user sees them, Back after logout in a browser; the API token throttle; a 20,000-character password; the production-only password rules (min 10, breached-password check).
- The shared browser pane keeps one cookie jar per host, and other sessions logged in over the test tabs more than once, so most checks used curl, Livewire calls and tinker; the real-browser coverage is the import flow (own hostname), the add-member form, and the 2FA setup screens.

## Side effects on shared state

- Other sessions flipped `member.a3`'s club 1 pivot to `past` for a while and renamed club 1 to "sec-iso"; both were back to normal at the end. The test agents did not change shared settings or seeded users.
- The member-area agent's first Subscriptions visit created bath-rfc's default newsletter types (ids 4-6); they were deleted with no references.
- 11 Mailpit messages to `mem.acct.*@e2e.test` and a few to `mem.imp.*` remain (not deleted, per the rules).
