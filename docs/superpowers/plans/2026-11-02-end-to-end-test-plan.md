# End-to-End Test Plan

> **For agentic workers:** execute phase by phase with superpowers:executing-plans. Steps use checkbox syntax. Drive the UI with the built-in browser pane, check every email with the Mailpit API, and log each result in the Results table at the bottom.

**Goal:** Prove every built feature works as a real user would meet it, with emphasis on accounts, members, roles (privileges), meeting summonses/RSVPs, and event booking/cancelling, and that every email lands in Mailpit with the right content.

**Approach:** Run the real app against a throwaway PostgreSQL database, with mail going to Mailpit over SMTP. Walk each persona through the UI. After every action that should send mail, query Mailpit and assert recipient, subject, links and placeholders. The existing PHPUnit suite (`composer check`) is the regression net; this plan covers what it cannot: real browser, real queue, real mail, real navigation.

**Tech stack:** Laravel 13 / Inertia v3 / Vue 3 / Livewire 4, PostgreSQL, Mailpit v1.33 (already installed at `/opt/homebrew/bin/mailpit`), built-in browser pane, queue worker (`database` driver).

---

## 0. Environment setup (do once)

Mailpit is already running: SMTP `127.0.0.1:1025`, web UI and API `http://localhost:8025`. `.env` currently has `MAIL_MAILER=log`, `MAIL_PORT=2525`, so nothing reaches it yet.

- [ ] **0.1 Throwaway database.** Create `club_manager_e2e` on the local Postgres so the real dev data (8 users, 2 clubs) is untouched. Use a separate env file `.env.e2e` (copy of `.env`, with `DB_DATABASE=club_manager_e2e`, `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`, `MAIL_FROM_ADDRESS=noreply@club-manager.test`, `QUEUE_CONNECTION=database`). Run with `APP_ENV=e2e` so Laravel loads it. Verify: `php artisan migrate:fresh --seed --env=e2e` succeeds.
- [ ] **0.2 Serve without Vite hot reload.** Run `npm run build` and make sure `public/hot` does not exist (the browser pane renders blank while Vite dev is running). Serve with `php artisan serve --env=e2e` (or Herd pointed at the e2e env).
- [ ] **0.3 Queue worker.** `php artisan queue:work --queue=transactional,default,bulk --tries=1` in a terminal tab. Mailables are queued, so no worker means no mail. Newsletters use `bulk`.
- [ ] **0.4 Mailpit helper.** Helper commands used throughout (put in the Results log as the reference):
  - Clear: `curl -s -X DELETE localhost:8025/api/v1/messages`
  - List: `curl -s localhost:8025/api/v1/messages | jq '.messages[] | {To:.To[0].Address, Subject, ID}'`
  - Read one: `curl -s localhost:8025/api/v1/message/<ID> | jq '{Subject, Text, HTML}'`
  - Search: `curl -s 'localhost:8025/api/v1/search?query=to:alice@e2e.test'`
- [ ] **0.5 Smoke check.** Send one email (superadmin email-template test send, or password reset) and confirm it appears in Mailpit. Do not continue until it does.
- [ ] **0.6 Baseline green.** `php artisan test --compact` and `npm run check:templates && npm run check:components` pass before manual testing starts, so failures found later are not pre-existing.

## 1. Test data (personas)

Create through the UI where the flow is itself under test (marked UI), otherwise via a one-off seeder in the scratchpad (not committed). All emails use `@e2e.test`; password for all: set by the invite or reset flow, never reuse a real one.

| Persona | Email | Role / state | Created by |
|---|---|---|---|
| Superadmin | `super@e2e.test` | platform superadmin | seeder |
| Owner A | `owner.a@e2e.test` | owner, Lodge A | seeder |
| Admin A | `admin.a@e2e.test` | admin, Lodge A | UI (role assign) |
| Treasurer A | `treasurer.a@e2e.test` | treasurer, Lodge A | UI |
| Coach R | `coach.r@e2e.test` | coach, Rowing Club R | UI |
| Member A1 to A4 | `member.a1`..`a4@e2e.test` | active member, Lodge A | UI (invite) |
| Pending A | `pending.a@e2e.test` | status pending, Lodge A | UI (join request) |
| Past A | `past.a@e2e.test` | status past, Lodge A | UI (resign) |
| Dual | `dual@e2e.test` | member of Lodge A and Lodge B | UI |
| Admin B | `admin.b@e2e.test` | admin, Lodge B (isolation tests) | seeder |
| Visitor | `visitor@e2e.test` | no club, public booker and lodge visitor | UI |
| Guest | `guest@e2e.test` | guest on a booking, no account | UI |

Clubs: **Lodge A** (masonic, with meetings, events, payment options), **Lodge B** (masonic, second tenant), **Rowing Club R** (non-masonic type, checks rank fields are hidden/neutral).

---

## 2. Feature inventory

Everything below is a test area; section 3 holds the cases. Counts of routes come from `route:list`.

| # | Area | Entry points |
|---|---|---|
| F1 | Authentication and accounts | `/login`, `/register`, `/forgot-password`, `/reset-password`, email verification, 2FA (`/two-factor-challenge`, `/members/security`), `/invite/{token}`, logout |
| F2 | Member invitations and onboarding | `MemberInvitationService`, `/invite/{token}`, `app:send-invitation-reminders`, directory invitations |
| F3 | Members roster | `/{slug}/admin/members`, member profile, add/edit/resign/remove, rank dropdown, offices, import (spreadsheet), export |
| F4 | Roles and privileges | `club_user.role/status`, `config/club_permissions.php`, per-club permission matrix, `/{slug}/admin/users`, pending approvals, owner-only owner changes |
| F5 | Meetings (admin) | `/{slug}/admin/meetings*`: create, season generator, edit, duplicate, publish, delete, dining, financial return, officer election, PDF |
| F6 | Meeting summonses and invites | Publish notification, `/summons/rsvp/{token}`, `/members/{slug}/meetings/{id}/summons`, RSVP and quick RSVP, apologies, dining, visitors, summons PDF |
| F7 | Events (admin) | `/{slug}/admin/events*`: create, tickets, meals, capacity, closing date, audience, guest list, catering summary, CSV, organiser booking |
| F8 | Event booking (member and public) | `/members/events`, public `/site/{club}/events/...`, guests, meals, ticket types, waitlist |
| F9 | Event cancelling and refunds | Member cancel, organiser cancel, waitlist promotion, refund email, capacity release |
| F10 | Event payments | Payment options, bank transfer, pay later, on the night, Stripe card, Stripe Connect, PayPal, discounts/fees, reminders, payment log, ledger entry |
| F11 | Email templates | Superadmin `/superadmin/email-templates`, lodge overrides `/{slug}/admin/email-templates`, placeholders, test send |
| F12 | Newsletters and digest | `/{slug}/admin/newsletters*`, audiences, channels, opt-out, one-click unsubscribe, browser copy, weekly digest |
| F13 | Members area | `/members/dashboard`, calendar, inbox/notifications, news, dues, payments, signed documents, directory, profile, subscriptions |
| F14 | Public website and builder | `/site/{club}`, pages, posts, blocks, theme, header/footer, search, contact form, sitemap, custom domain, media library |
| F15 | Accounting | Chart of accounts, journals, bills, invoices, contacts, budgets, fixed assets, recurring bills, reports, financial year close, period locking, examiner reports, attachments |
| F16 | Banking and payment sync | Bank import, reconciliation, match rules, GoCardless, Stripe, PayPal, SumUp syncs, webhooks |
| F17 | Subscriptions and billing | Tiers, member subscriptions, direct debit mandates, billing run, dues |
| F18 | Governance | Committee meetings, agenda, minutes, live minute taker, tasks, notices of motion, agenda pack email and PDF |
| F19 | Candidates | Pipeline stages, transitions, notes, reject/close/hold/reopen, initiation to member |
| F20 | Charity and Gift Aid | Collections, grants, festival giving, relief chest export and reconciliation |
| F21 | Officers | Annual officer roster, offices, election confirmation |
| F22 | Signatures | `/sign/...`, signature requests, member signed documents |
| F23 | Lodge directory and visitors | `/lodges`, search, distance search, follow, claim, corrections, yearly check, visit requests, visitor register |
| F24 | Superadmin | Grand lodges, provinces, districts, lodges, halls, club types, claims, corrections, platform payments, users |
| F25 | Platform and security | Multi-tenant isolation, route sweep, security headers, upload rules, rich text sanitising, CSV safety, API tokens (`/api/v1`) |
| F26 | Cross-cutting UI | Dark mode, mobile width, nav mode, ui-kit, error pages, currency formatting, financial year labels |

---

## 3. Test cases

Format: **ID**, steps, expected. Mark each in the Results table: PASS / FAIL / BLOCKED, with a note and screenshot for failures. "Mail:" lines are Mailpit assertions (recipient, subject, link works, no unreplaced `{placeholder}`).

### Phase A. Accounts, users and roles (F1, F2, F3, F4)

**A1 Registration and verification**
- A1.1 Register `visitor@e2e.test`. Expected: account created, redirect sensible. Mail: verification email (only enforced with a real mail driver; confirm it is enforced now that SMTP is on). Click link, verified.
- A1.2 Register with duplicate email, weak password, mismatched confirmation. Expected: field errors, no account.
- A1.3 Login wrong password x6. Expected: throttled message.
- A1.4 Forgot password for `visitor@e2e.test` and for an unknown address. Expected: same neutral message both times. Mail: reset link only for the real one; link works once, a second use fails.
- A1.5 Logout, then back button. Expected: no authenticated page shown, CSRF token sent on logout from every layout (admin, members, public).
- A1.6 Two-factor: enable at `/members/security`, scan, confirm code, log out, log in, pass `/two-factor-challenge`; use a recovery code; disable.

**A2 Inviting members (UI, Owner A)**
- A2.1 `/lodge-a/admin/members` > add member A1 with email. Expected: member row, rank dropdown values equal `Member::MASONIC_RANKS`, name shows via `formatted_rank_name`.
- A2.2 Send invitation. Mail: `MemberInvitationMail` to A1 with working `/invite/{token}` link, lodge name and inviter correct.
- A2.3 Open the link while logged out: set password, land in the members area, member now linked to the roster entry, status active.
- A2.4 Resend invitation (A2): new token, old one dead. Withdraw invitation: link shows an expired/invalid page.
- A2.5 Bulk invite several members from the roster. Expected: summary count, one mail each, none to members without an email.
- A2.6 Invite an email that already has an account on Lodge B. Expected: existing user is joined to Lodge A, not duplicated (this is the Dual persona).
- A2.7 Run `php artisan app:send-invitation-reminders` with one unanswered invite older than the cutoff. Mail: exactly one reminder; running again sends none.
- A2.8 Member import (spreadsheet) with good rows, a duplicate, a bad email. Expected: analysis tabs, mapping save/remove, run, undo; no mail unless chosen.
- A2.9 Rowing Club R: members have no masonic rank fields; coach persona can be added.

**A3 Roles and privileges (UI)**
- A3.1 Owner assigns Admin A, Treasurer A, Coach R through `/lodge-a/admin/users`. Expected: each role visible; only an owner can grant, change or remove `owner`; an admin trying gets a refusal.
- A3.2 Permission matrix per role. For each of Owner, Admin, Treasurer, Member log in and request: members admin, accounting, events, meetings, newsletters, payment options, settings, users, pages, media. Expected: matches `config/club_permissions.php` plus the lodge's `permission_matrix` overrides; refused routes show 403 or redirect, never a blank page. Record a grid (role x section).
- A3.3 Change a capability override for Treasurer (e.g. remove billing), re-test: takes effect immediately, including inside Livewire updates (accounting screens).
- A3.4 Pending join: `pending.a` requests to join from the public site. Expected: appears pending, cannot see members area content; Owner approves. Mail/notification: `membershipApproved` notification arrives in the member inbox. Reject path as well.
- A3.5 Resign/mark past (`past.a`): loses access to members pages and event booking; history kept.
- A3.6 Dual member: workspace switcher shows both clubs, dashboard aggregates, per-club pages limited to the chosen club; Lodge A admin cannot open Lodge B admin URLs by editing the slug.
- A3.7 Superadmin impersonation-free access: superadmin can open every club admin; ordinary users cannot reach `/superadmin/*`.

### Phase B. Meetings and summonses (F5, F6)

**B1 Create and publish (Owner A, then Admin A)**
- B1.1 Create a meeting (date, time, venue/hall, type, dining, dress/agenda text). Expected: appears in admin list and, once published only, in member calendar.
- B1.2 Season generator: generate a season from lodge schedule. Expected: correct dates for the rule (e.g. 2nd Tuesday), no duplicates when run twice.
- B1.3 Duplicate a meeting; edit the copy; delete the copy. Expected: original untouched.
- B1.4 Publish A. Expected: summons notification to every active member (not pending/past, not Rowing Club R members). Mail: summons mail with correct date/venue, a passwordless RSVP link `/summons/rsvp/{token}` and a link to the portal. Check the count equals active members with email.
- B1.5 Re-publish or edit after publish. Expected: no duplicate invite flood; edits visible to members; behaviour recorded.
- B1.6 Summons PDF (`admin.meetings.pdf`, `member.meetings.pdf`): downloads, renders names/date/dining.

**B2 RSVP (members)**
- B2.1 Member opens emailed link without logging in: sees meeting, chooses attending + dining + dietary note. Expected: saved, confirmation page, RSVP shows in admin attendance with counts (attending, dining, apologies, visitors).
- B2.2 Reuse the same link to change to apology. Expected: updates the same RSVP, counts move.
- B2.3 Logged-in RSVP and quick RSVP from dashboard and calendar. Expected: same record, no duplicates.
- B2.4 Tamper with the token / use another meeting's token / use a past member's token. Expected: refusal, no data leak.
- B2.5 Admin overrides a member's RSVP (`admin.meetings.rsvp.update`) and sets dining payment status. Expected: logged, visible to member.
- B2.6 Visitors: a lodge visitor request for the meeting (F23) shows in visitor count, not member count.
- B2.7 Dining page (`/admin/meetings/{id}/dining`): headcount, dietary summary, payment recording, matches RSVPs.
- B2.8 Past meeting RSVP attempt after the meeting date. Expected: closed or clearly flagged.

**B3 After the meeting**
- B3.1 Financial return for the meeting: save, totals, locks per period.
- B3.2 Officer election page: record results, confirm, roster updates (F21).

### Phase C. Events: booking and cancelling (F7, F8, F9, F10)

**C1 Create the event (Admin A)**
- C1.1 Event with: date, venue, capacity 6, closing date, ticket types (Member £30, Guest £35), meals (starter/main/dessert), guest limit 2, audience members-only. Expected: saved, draft kept in browser if the page is reloaded mid-edit (`useDraft`).
- C1.2 Payment options (`/admin/payment-options`): switch on Bank transfer, Pay later, On the night (complete details required); Stripe/PayPal stay unavailable without keys and `EVENTS_ONLINE_PAYMENTS`. Expected: newly switched-on option appears on upcoming events; per-event discount (e.g. 10 % early) and fee on pay-later work, fees are refused on card/bank.
- C1.3 Publish event. Expected: members are notified (`ClubNotification::event`); appears in member and public calendars per audience.

**C2 Member booking**
- C2.1 Member A1 books themself + 1 guest, selects ticket types and meals, bank transfer. Expected: price from `EventPricing` matches the screen, stored booking, capacity decremented by 2. Mail: booking confirmation to A1 (bank details, reference, total), guest email to the guest if they have an address (no payment details in it).
- C2.2 Guest limit: try 3 guests. Expected: refused with message.
- C2.3 Missing meal choice or invalid ticket tier. Expected: validation error, no booking.
- C2.4 After closing date and for non-audience user. Expected: booking blocked.
- C2.5 Capacity: fill to 6 with A1 to A4, then A4 books again. Expected: waitlist entry, clear message, no overbooking even with two browser tabs submitting at once (row lock).
- C2.6 Edit own booking (add dessert, change guest) before payment: allowed; after payment recorded, price change refused.
- C2.7 Booking appears on `/members/events`, dashboard, calendar, in admin guest list, catering summary and CSV (open the CSV: formula characters in names are neutralised).

**C3 Public / visitor booking**
- C3.1 `visitor@e2e.test` books a public event on the lodge site without an account. Expected: booking created with public guest token. Mail: confirmation with manage link.
- C3.2 Manage link lets the booker view and cancel; wrong token refused.

**C4 Cancelling (core)**
- C4.1 Member cancels own booking (unpaid). Expected: status cancelled, capacity released (guests too), admin list updated, confirmation shown. Mail: cancellation/refund-style email as designed; none to the guest unless intended.
- C4.2 Waitlist promotion: with A4 waitlisted, A1 cancels. Expected: A4 promoted or notified. Mail: `EventPlaceAvailableMail` to A4 with working link; place cannot be taken twice.
- C4.3 Cancel a paid booking. Expected: refund path offered; `EventPaymentService` writes append-only `event_payment_log` row (who, when, amount, reason) and ledger entry reverses; Mail: `EventRefundMail` with the right amount.
- C4.4 Organiser cancels a single guest and a whole booking on a member's behalf; organiser adds a booking for a member and a visitor (`EventOrganiserBookingController`), skipping closing date and audience rules but not meal/tier validation.
- C4.5 Cancel event itself (status change). Expected: bookings handled, members notified, event disappears from booking pages.
- C4.6 Cancel after closing date as a member. Expected: behaviour matches the lodge rule (record what it is).
- C4.7 Cancelling twice, or cancelling someone else's booking by editing the id in the URL. Expected: refusal.

**C5 Payments**
- C5.1 Mark bank-transfer booking as paid by the treasurer; unmark. Expected: log rows, ledger entries; member sees paid. Mail: `EventPaymentReceivedMail`.
- C5.2 Reminders: `php artisan app:send-event-payment-reminders` for an unpaid booking due soon and one overdue. Mail: `EventPaymentReminderMail` for each, none for paid, none duplicated on re-run.
- C5.3 Stripe card in test mode (lodge's own keys, Stripe test card `4242...` in test mode): Checkout, return, webhook marks paid once; reload does not double-pay. Skip with BLOCKED if no test keys.
- C5.4 Stripe Connect and PayPal: same, BLOCKED unless test credentials exist.
- C5.5 Bank reconciliation: import a statement line matching a booking reference; it matches and marks paid.

### Phase D. Email content and templates (F11, F12)

- D1 Superadmin edits the "booking" template; send test; place a booking. Mail: new wording used, placeholders replaced.
- D2 Lodge A override of the same template; Lodge B still uses the platform wording. A guest template cannot gain payment placeholders.
- D3 Every system mail rendered once and checked in Mailpit HTML and plain text, links point to `club-manager.test` (not localhost), images/logos load: invitation, reset, verification, summons, booking, guest booking, payment received, reminder, refund, place available, newsletter, committee pack, contact form, claim.
- D4 Newsletter: draft, test send to self, send to members. Expected: one per person (unique per newsletter and email), mandatory channels cannot be opted out, opted-out member skipped, unsubscribe link works and is one click, browser copy renders, re-send blocked (draft to sent once). Attachments included.
- D5 Weekly digest: `php artisan app:send-weekly-digest --scheduled` produces one digest per eligible member, nothing for empty weeks.
- D6 Contact form on the public site delivers to the lodge contact address.

### Phase E. Members area and public site (F13, F14, F23, F26)

- E1 Dashboard, calendar (meetings + events, per-club filter), inbox (read/unread, mark read), news, dues, payments, signed documents, directory (privacy of hidden members), profile and avatar upload, subscriptions.
- E2 Website builder: create page, add every palette block, reorder, preview link, publish, version history/restore, SEO fields, header/footer, theme. Public render matches; sanitiser strips `<script>` pasted into rich text.
- E3 Posts: create, schedule, publish (notifies members), tag; public news page.
- E4 Media library: upload (jpg, png), SVG/HTML refused, over-size image downscaled, folders, trash, version history, quota.
- E5 Public site search, sitemap.xml, contact form, calendar, custom domain behaviour (cannot be fully tested locally; mark BLOCKED, covered by `ClubCustomDomainTest`).
- E6 Lodge directory: search by name/place, distance search, follow a lodge, claim a lodge (superadmin approves; mail `LodgeClaimMail`), submit a correction, yearly check, visit request and visitor register.
- E7 Layout: mobile width (375), tablet, dark mode, side-nav option, long names, empty states for each list page.

### Phase F. Finance and governance (F15 to F22)

Walk each as Treasurer A with seeded numbers, checking arithmetic by hand once:
- F1 Accounts and journals: create accounts, post a balanced journal, unbalanced refused, period lock blocks edits in a locked period.
- F2 Bills and invoices: create, attach file (private disk, served only with `manage_billing`), PDF, mark paid, status uses `DocumentStatus`; recurring bill generation (`app:generate-recurring-bills`).
- F3 Reports: trial balance, P&L, balance sheet across a financial-year boundary (April year end), preset ranges, CSV exports.
- F4 Year close, auditors'/examiner's sign-off, budgets vs actuals, fixed assets and depreciation.
- F5 Bank: import statement, auto-match, manual match, rules; multi-bank account; GoCardless/Stripe/PayPal/SumUp syncs BLOCKED without credentials, webhook signature rejection checked.
- F6 Subscriptions: tiers, assign to members, billing run generates correct dues (right members only), direct debit mandates.
- F7 Committee governance: meeting, agenda items, attendees, live minute taker (task assigned notification), notice of motion, agenda pack. Mail: `CommitteeAgendaPackMailable` with PDF.
- F8 Candidate pipeline: enquiry through initiated, skipping needs a reason, events log is unchangeable, initiation creates a member.
- F9 Charity: collection, grant, festival giving, Gift Aid / Relief Chest export and reconciliation.
- F10 Officers roster and signatures: request a signature from a member, member signs at `/sign/...`, appears under signed documents; tamper token refused.
- F11 Currency: a club with a non-GBP province shows the right symbol everywhere (events, invoices, reports, emails); currency locks once records exist.

### Phase G. Superadmin and platform (F24, F25)

- G1 Create grand lodge, province, district, lodge, hall, club type; approve/reject claim and correction; platform payments settings; email templates.
- G2 Isolation sweep: as Admin B, try Lodge A ids on members, events, meetings, accounting attachments, media, Livewire components (edit `clubSlug`/ids in requests). Expected: refused or not found, never data.
- G3 Role sweep: run `AdminRouteSweepTest` plus manual spot checks of the permission grid from A3.2.
- G4 Security: security headers present, upload rules, CSV formula injection in exports, rate limits on login and RSVP tokens, API token issue/use/revoke at `/api/v1`.
- G5 Error pages: 403, 404, 419 (expired session while submitting a form: recovers cleanly), 500 page without debug leak.

### Phase H. Accounting, bank import, reconciliation and reports (F15, F16)

Run as the Treasurer (`treasurer@example.com` in the seeded data) so the role itself is tested. Screens: Accounting page tabs (Home, Sales, Purchases, Reporting, Accounting, Contacts, Reconciliation) and the banking pages `/{slug}/admin/bank-accounts`, `/bank-imports`, `/bank-reconciliation`. Dummy statements live in `docs/superpowers/plans/fixtures/`:

| File | Use |
|---|---|
| `bank-statement-oct-2026.csv` | 7 clean lines. Credits £275.42, debits £235.50, net +£39.92. Running balance goes £1,000 to £1,039.92 (assumes a £1,000 opening balance). |
| `bank-statement-with-problems.csv` | 1 good line, a bad date, a bad amount, a `=HYPERLINK(...)` description, a quoted line with a comma and quotes. |
| `bank-statement-oct-2026.ofx` | 2 lines (one credit £60.00, one debit £22.75) in OFX format. |

Hand-check list for the clean file: SUBS-A1 £120 in matches a £120 sales invoice to M Archer; HALL-OCT £80 out matches an £80 bill from Oxford Masonic Hall; DINNER £35 in matches Carol Cooper's event booking; FEE £5.50 out and INT £0.42 in are manual; 000123 £150 out matches a £150 catering bill; SUBS-A2 £120 in is left unmatched on purpose (no invoice).

**H1 Set-up**
- H1.1 Onboarding checklist on the Accounting home: add first bank account, set opening balance, set financial year end, review VAT. Each item ticks off when done; Dismiss hides the card and it stays hidden.
- H1.2 Add the bank account "Lodge Current Account" (current, dummy sort code 00-00-00, account 00000000, GBP). Add a second (savings) account. Toggle one inactive: it disappears from import and reconcile choices. Missing name, bad type, and non-GBP in a GBP-locked club are refused.
- H1.3 Chart of accounts: the 18 seeded accounts show. Add 4600 "Hall hire income" and 5400 "Bank charges". Duplicate code refused. Edit a name. Try to delete an account that has postings (must be refused or archived, never silently lose history), then restore or re-add it ("add back").
- H1.4 Contacts: create customer "M Archer" and supplier "Oxford Masonic Hall"; edit; delete one that has documents (refused).
- H1.5 Opening balance £1,000 on the bank account dated 1 Oct 2026. Expected: balanced journal, dashboard and balance sheet show £1,000 assets and £1,000 equity.
- H1.6 Financial year end month set to April; check year labels on reports (year number is the calendar year the year ends in).

**H2 Documents that the bank lines will match**
- H2.1 Sales invoice £120 to M Archer: create, approve, publish, PDF opens. Appears in Sales and Aged receivables.
- H2.2 Bills: £80 from Oxford Masonic Hall (5000), £150 from Catering Supplies Ltd (5200). Approve each. Attachment upload (PDF ok, SVG/HTML refused) and download only with billing permission.
- H2.3 Approval threshold: set it below £100, then confirm the £150 bill needs approval by a second person.
- H2.4 Manual journal: balanced entry saves; unbalanced refused; edit works.

**H3 Bank statement import**
- H3.1 Import `bank-statement-oct-2026.csv`: preview shows 7 lines, £275.42 in, £235.50 out, no duplicates. Confirm. Lines appear as unmatched in the right account.
- H3.2 Import the same file again: every line flagged as a duplicate and none added twice.
- H3.3 Import `bank-statement-with-problems.csv`: bad date and bad amount rows are reported (not silently imported or dropped); the good line is a duplicate; the quoted comma line imports intact; the `=HYPERLINK` description imports as text and is neutralised in any CSV export.
- H3.4 Import the `.ofx` file: 2 lines, correct signs (credit positive, debit negative), dates read as 2 Nov and 3 Nov 2026.
- H3.5 Refused uploads: empty file, `.svg`, `.html`, a PDF renamed `.csv`, a very large file. Clear error, nothing stored.
- H3.6 Delete one line (restorable) and restore it; delete a whole import batch and check its lines and any matches are removed and the ledger is unchanged.
- H3.7 Account choice: import into the savings account and check lines land there only.

**H4 Reconciliation**
- H4.1 Reconcile tab and `/bank-reconciliation` workspace list the unmatched lines. Suggestions: SUBS-A1 to the £120 invoice, HALL-OCT to the £80 bill, 000123 to the £150 bill, DINNER to Carol's booking. Accept each; the document becomes paid, a journal posts, the line shows reconciled.
- H4.2 Manual allocation: FEE to 5400 Bank charges, INT to an income account, SUBS-A2 to member subscription or a nominal account. Check the journal debits and credits.
- H4.3 Ignore a line and bring it back; ignored lines do not count as unreconciled.
- H4.4 Match rule: create a rule from a line (e.g. text "INTEREST" to an income account), import `bank-statement-oct-2026.ofx` plus another interest line and confirm the rule applies; delete the rule.
- H4.5 Wrong amounts: try matching the £120 line to a £150 bill (refused or flagged), and a part payment (invoice part-paid, balance correct).
- H4.6 Undo: "remove and redo" an account transaction reopens the document and removes the journal.
- H4.7 Reconciliation summary report: unreconciled count 0 once everything is done; bank ledger balance equals the statement closing balance (£1,039.92 for the clean file).
- H4.8 Event bank reconciliation: Carol's booking becomes paid through the payment service (log row and `event_payment_log` entry, receipt email in Mailpit).

**H5 Reports** (Accounting, then Reporting tab)
- H5.1 Open every report: profit and loss, balance sheet, cash summary, account summary, aged receivables, aged payables, budget vs actual, comparative income and expenditure, executive summary, treasurer report, reconciliation summary, VAT return, provincial dues. None errors or shows blank when data exists.
- H5.2 Hand-check figures after Phases H1 to H4: income = £120 + £35 + £120 + £0.42 (plus any accounted differently), expenses = £80 + £150 + £5.50, bank = £1,039.92, balance sheet assets = liabilities + equity.
- H5.3 Date presets and custom range, including across the April financial year boundary and a leap-year range; labels use the year the financial year ends in.
- H5.4 Budget: set a budget for 5200, confirm budget vs actual shows variance.
- H5.5 Exports: CSV (opens in a spreadsheet, formula strings neutralised, commas quoted), PDF (loads, correct club name, currency symbol and totals), treasurer-report PDF and CSV, export-all.
- H5.6 Currency: amounts show £ everywhere (screens, PDFs, CSV headers).

**H6 Controls and safety**
- H6.1 Period locking: lock a period, then try to post a journal or change a reconciled line inside it. Refused with a clear message; reopen and retry.
- H6.2 Financial year close and reopen; independent examiner / auditor sign-off request sends an email (Mailpit) with a working link.
- H6.3 Audit log: every manual change (journal, reconciliation, deletion) has a who/when/what entry.
- H6.4 Permissions: admin, treasurer allowed; coach, member, an admin of another club, and a logged-out visitor are refused on every accounting, banking and report URL, including the Livewire update calls and report/PDF/export downloads.
- H6.5 Isolation: with Lodge B data present, change ids in URLs and Livewire payloads (bank import, bank transaction, account, bill, attachment) to ones belonging to Lodge B. Expected: not found or refused, never data.
- H6.6 Many rows: import a 500-line CSV and open reconcile and reports. Page loads in a few seconds with no per-row query blow-up.

---

## 4. Execution order and checkpoints

1. Section 0 (setup), then confirm Mailpit receives mail. Stop if not.
2. Phase A, so personas exist.
3. Phases B and C (the priority flows the request names); clear Mailpit between cases to keep assertions simple.
4. Phase D alongside B/C where a mail has just been sent.
5. Phases E, F, G in that order.
6. After each phase: commit nothing, note defects in the Results table. Fixes are separate work, one defect at a time, each with a regression test in `tests/Feature`.

## 5. Out of scope / cannot be verified locally

Real Stripe Connect, PayPal live, GoCardless and SumUp traffic, Caddy certificate issuing and DNS verification for custom domains, Forge staging (MySQL), and the server scheduler. Each is marked BLOCKED with the existing automated test that covers it.

## Review Focus

- A published meeting emailing people it should not (pending, past, other club).
- Two people taking the last event place at once.
- Cancelling a paid booking releasing the place but leaving the ledger or payment log wrong.
- A reused or tampered RSVP/booking token exposing someone else's booking.
- Queued mail silently not sending (worker not running, `log` mailer left on) so tests look green with no mail.

## Results

| ID | Result | Note / defect | Evidence |
|---|---|---|---|
| 0.1 | PASS | `club_manager_e2e` created, `.env.e2e` written (git-excluded via `.git/info/exclude`), migrate:fresh --seed OK: 5 users, 2 clubs | artisan output |
| 0.2 | PASS | `npm run build` done, no `public/hot`; app served at http://127.0.0.1:8001 | curl /login 200 |
| 0.3 | PASS | `queue:work --env=e2e --queue=transactional,default,bulk` running | pgrep |
| 0.5 | PASS | Password reset for member@example.com reached Mailpit in under 4s | Mailpit API |
| D3-note | FINDING | Mail subject reads "Laravel - Reset Your Account Password": `APP_NAME=Laravel` in env, so platform name is the framework default | Mailpit message |
| 0.6 | PASS | `php artisan test --compact`: 1311 tests, 1310 passed, 1 skipped, 0 failed (520s: machine was busy) | test output |
| A1.1 | PASS | Login blocked until email verified (SMTP is a real driver); verify link from Mailpit works logged out, then "Email confirmed" | Mailpit |
| A1.5 | PASS | Logout via CSRF token works from admin and members layouts | browser |
| A2.1/2.2 | PASS | Add member + "send invitation": `MemberInvitationMail` to member.a1 with inviter, club name, 14-day expiry, working link | Mailpit |
| A2.3 | PASS | Invite link: set password, account verified, joined as active member, lands in member portal | DB + browser |
| A2.4 | PASS | Resend inside 10-minute cooldown refused (no visible message seen, see F9); after cooldown new link sent, old link redirects to login | Mailpit, curl |
| A2.5 | PASS | Bulk "Invite selected": 3 mails; member with no email and already-invited member not selected | Mailpit |
| A3.1 | PASS | Role change to Admin takes effect immediately | DB |
| A3.2 | PASS | Role grid (21 admin sections x admin, treasurer, coach, 2 members) matches `config/club_permissions.php`; members get 403 everywhere | JS probe |
| A3.4 | PASS | Approving a pending member makes them active and creates an in-app "membership approved" notification (no email, see F8) | DB |
| B1.1 | PASS | Meeting created as draft by an admin (non-superadmin) | DB |
| B1.4 | FAIL (design gap) | Publish sends in-app notifications to 7 active members (not publisher, not pending invitee) but NO email, see F1 | Mailpit 0 mails |
| B2.1 | PASS (token made by script) | Passwordless RSVP page works logged out; apology saved. Tampered token shows "invalid or has expired" | DB, curl |
| B2.3 | PASS | Logged-in RSVP (meeting and dining, dietary note) saved; shows as "Attending with dining / Unpaid" on the member events page | DB |
| C2.1 | PASS | Member + 1 guest booking: price £95 matches screen (35+25 meal + 35 guest); booking mail to booker, separate guest mail without payment details | Mailpit |
| C2.3 | PASS | Missing starter/main/dessert refused with "Please choose a starter."; no row stored | DB |
| C2.5 | PASS | Capacity 3 with waiting list: 3 places taken, 4th booking waitlisted | DB |
| C4.1 | PASS | Member "Can't go" cancels booking and removes guest; capacity released | DB |
| C4.2 | PASS | Cancelling a booking promotes the waitlisted member and sends "A place is available" mail with link | Mailpit |
| C4.3/C4.4 | PASS | Organiser marks paid (log row, ledger entry, "Payment received" mail), then cancels and refunds (log row, 2nd ledger entry, "Refund" mail) | Mailpit, DB |

**Findings (not fixed, for triage)**
- **F1 High.** `MeetingSummonsController.php:102-114`: publishing creates passwordless RSVP tokens for every club user (including pending and past members) but discards the raw token and sends no email. Members only get an in-app notification that links to the logged-in summons page. The confirm dialog still says "issues passwordless RSVP links to all 9 members". `RsvpTokenService::createTokenForUser` is only called here and in tests.
- **F2 High.** `resources/js/Pages/Admin/Meetings/Form.vue:156-157` pre-fills a sample bank sort code/account (20-82-18 / 80288373) into every new meeting; it is saved and shown to members on the RSVP page as payment details.
- **F3 Medium.** Each login attempt by an unverified user sends another verification email (two attempts, two mails).
- **F4 Low.** `APP_NAME=Laravel`: mail subjects and footers say "Laravel" (check production `.env`).
- **F5 Low.** User profile role dropdown labels the `member` role "Media manager".
- **F6 Low.** Meeting times show seconds ("18:30:00") on summons, RSVP page and events list.
- **F7 Low.** Wording and gaps: "(1 people)" in the place-available email; no email on being waitlisted, on member cancelling, on organiser cancelling an unpaid booking, or after submitting an RSVP.
- **F8 Low.** Membership approval notifies in-app only.
- **F9 Low (unconfirmed).** Clicking Resend inside the cooldown showed no visible message in the viewport.
- **F10 Low.** The RSVP page shows a stale "Logged out successfully." flash.

**Not yet run:** A1.2 to A1.4, A1.6, A2.6 to A2.9, A3.3, A3.5 to A3.7, B1.2, B1.3, B1.5, B1.6, B2.2, B2.5 to B2.8, B3, C1.x, C2.2, C2.4, C2.6, C2.7, C3, C4.5 to C4.7, C5.2 to C5.5, Phases D to G. Seeded clubs were used instead of new Lodge A/B (the seeded "Lodge of Fraternity" is a rowing-type club, so masonic-only fields were not exercised).

Seeded logins (e2e DB, from `ClubSeeder`): admin@example.com (superadmin), member@, coach@, treasurer@, pending@example.com. Personas in section 1 are still to be created.

### Phase H results (run 2 Nov 2026 as treasurer@example.com on the e2e database)

| ID | Result | Note / defect |
|---|---|---|
| H1.2 | PASS with F11 | Added "Lloyds / Lodge Savings Reserve": a ledger account 1010 "Lloyds — Lodge Savings Reserve" was created and linked. Blank sort code and account number are accepted. Opening the page the first time had already created a placeholder account (F11). |
| H1.3 | PARTIAL | Added account 4600; duplicate code and an invalid type refused with 422. Delete/archive and restore not run. |
| H2.1/H2.2 | PASS | Invoice £120 (member Alice Archer), bills £80 and £150 created and posted (journals balance). Approval threshold, attachments not run. |
| H2.4 | PASS with F18 | Balanced journal posts; unbalanced refused but as an HTTP 500. |
| H3.1 | PASS | `bank-statement-oct-2026.csv`: preview 7 lines, correct signs; 7 staged lines created. |
| H3.2 | PASS | Same file again: 7 of 7 flagged duplicate, none added. |
| H3.3 | FAIL (F17) | Bad-date row silently dropped (5 rows, 4 read, no warning); bad amount `abc` imported as a £0.00 line. Quoted comma/quotes line and `=HYPERLINK` text imported intact. Not confirmed into the books. |
| H3.4 | PASS | OFX: 2 lines, +£60.00 and -£22.75, dates 2 and 3 Nov 2026. |
| H3.5 | PARTIAL (F19) | Empty file, `.svg`, `.html` produce no preview and store nothing, but no visible message. A text file named `.csv` previews 0 lines. Large-file and PDF-renamed cases not run. |
| H3.6, H3.7 | NOT RUN / FAIL (F12) | Import never asks for a bank account, so H3.7 cannot be done. |
| H4.1 | PASS | Suggestions: £120 to invoice INV-2026-0002 (60%, by amount), £80 to Oxford Masonic Hall bill (100%), £150 to Catering Supplies bill (100%), £35 to Carol Cooper's booking EVT-1-00003 (80%, by name). Accepting each marks it paid and posts Dr 1000 / Cr the right account. |
| H4.2 | PASS with F14 | Manual nominal allocation works; the list offered is wrong (F14). Bank charges to 7100 created "General Account (7100)". |
| H4.3 | UNCONFIRMED (F20) | "Ignore line" click did not change the line's status. |
| H4.4 | PARTIAL | Allocating a line saved a match rule (`bank charges monthly` to 7100, hit count 1). Applying the rule on a later import and deleting it not run. |
| H4.7 | PARTIAL | All 9 lines reconciled. Ledger bank 1000 = £77.17 = sum of all 9 statement lines. Reconciliation summary report not opened. |
| H4.8 | PASS | Reconciling the booking marked it paid through the payment service (`event_payment_log` row, source `bank_reconciliation`), Dr 1000 / Cr 4100 £35, and "Payment received" email to Carol arrived. |
| H5.1 | PARTIAL | Report data loaded for all reports; opened balance sheet visually. Others via data only. |
| H5.2 | FAIL (F16) | P&L correct: revenue £335.42, expenses £258.25, net £77.17 (matches bank movement). Balance sheet: assets £77.17 vs liabilities £0 and equity £0 under the heading "Assets = liabilities + equity". |
| H5.5 | PASS | All 10 CSV exports, 9 PDFs, treasurer-report CSV and PDF, VAT and Gift Aid CSV return 200 with the right type. `export-all` is 403 for the treasurer. Treasurer report shows "Closing statement balance 0" because of F12. |
| H6.1 | PASS with F18 | Closing financial year 2027 blocked a journal dated 2 Nov 2026 ("The books for 2027 are closed...") but as an HTTP 500. Reopen refused for non-superadmin (403) as designed; reopened with the service. |
| H6.4 | PASS | Coach and plain member: 403 on all accounting, report, export, bank-account, import and reconciliation URLs. Lodge admin Carol: 403 on the other club's accounting and imports. |
| H6.5 | PARTIAL | URL-level isolation checked above; id tampering inside Livewire payloads not run. |

Not run: H1.1, H1.4 to H1.6, H2.3, H3.6, H3.7, H4.5, H4.6, H5.3, H5.4, H5.6, H6.2, H6.3, H6.6.

**Accounting findings (not fixed)**
- **F11 Medium.** Opening Bank accounts for the first time creates a placeholder "High Street Bank / Main Operating Account" with sort code 20-00-00 and account 12345678 (`BankAccountsIndex::ensureDefaultAccountExists`). Same kind of fake default as F2.
- **F12 Medium.** Imported statement lines get no bank account (`bank_account_id` is null) and the import page never asks which account. The bank accounts page and the treasurer report show a statement balance of £0.
- **F13 Medium.** Reconciliation always posts the cash side to ledger account 1000 (`BankReconciliationMatcherService`, about line 430), so a second bank account such as 1010 never receives any postings.
- **F14 Medium.** The "Nominal code" list in the reconciliation workspace is a fixed list (4000, 4100, 7000, 7100, 8000), not the lodge's chart of accounts. Codes that do not exist are created on the fly as "General Account (7100)". There is no bank charges, interest or donations choice, so the £0.42 interest and £60 donation were recorded as Membership Dues Income.
- **F15 Medium.** Every vendor bill debits 5000 Facility & Clubhouse Maintenance whatever its category (`AccountingService.php:358`): the £150 catering bill sits under Facility, and 5200 Catering stays £0.
- **F16 High.** The balance sheet does not balance: it leaves the current-year surplus out of equity (assets £77.17, liabilities and equity £0.00).
- **F17 Medium.** CSV import drops a row with an unreadable date without warning and imports an unreadable amount as £0.00.
- **F18 Medium.** An unbalanced journal, and posting into a closed year, answer with HTTP 500 (`InvalidArgumentException`) instead of a form error.
- **F19 Low.** Refused uploads show no visible error; a text file named `.csv` previews "0 lines".
- **F20 Low, unconfirmed.** "Ignore line" did not change a line's status when clicked.
- **Note.** The e2e seed has no platform superadmin (`admin@example.com` is a normal user there), so superadmin cases need a user created first.

**Fixed after Phase H (retested in the e2e app):** F16 (balance sheet now shows "Surplus (deficit) to date" in equity: assets £77.17 = liabilities £0 + equity £77.17), F12 (statement import has a bank account picker, required when there are several; the lines and the import batch store the account), F13 (reconciliation posts to the line's own bank account's ledger account: a £0.50 savings line debited 1010, not 1000), F14 (the nominal list is the lodge's own income accounts for money in and expense accounts for money out, a code outside it is refused, and nothing is created as "General Account"). Still open: F11, F15, F17 to F20.

**Fixed after Phase H, second round (retested in the e2e app):** F15 (a bill is charged to the expense account its category belongs to: Bar & Dining Supplies to 5200, Utilities & Fuel to 5100, Admin and Insurance to 5300, a category named like one of the lodge's expense accounts to that account, anything else to 5000), F17 (unreadable dates and amounts are listed in the import preview and not imported; an unreadable amount is no longer a £0.00 line; the success message says how many rows were left out), F18 (an unbalanced or closed-year journal now shows the message: web requests go back with the error, JSON gets a 422; `LedgerRuleException`). Still open: F11, F19, F20.
