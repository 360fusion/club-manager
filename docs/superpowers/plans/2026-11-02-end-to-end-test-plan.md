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

### Phase D results (run 2 Nov 2026, e2e database, Mailpit)

Setup used: a platform superadmin `super@e2e.test` created in the database (the seed has none), event capacity raised to 10, one digest channel switched on, a lodge contact address set for the contact-form tests.

| ID | Result | Note |
|---|---|---|
| D1 | PASS | Superadmin edited the "booking confirmation" template; the test send came to the superadmin with placeholders shown as `[name]`; a real booking then used the new subject and wording with every placeholder filled. |
| D2 | PASS | Lodge override saved for that lodge only; the next booking used the lodge wording and sender name. Refused with a clear message: a guest email asking for `{{payment_details}}` or `{{manage_url}}`, a booking email that drops `{{manage_url}}`, an empty body. An unknown template key returns 404. `<script>` was stripped from the saved body. |
| D3 | PARTIAL | Content and links checked for: verification, reset, invitation, summons (earlier), booking (member and guest), payment received, refund, place available, payment reminder, newsletter, digest, contact form. Not run: signature request, committee agenda pack, lodge claim. |
| D4 | PASS | Newsletter to a non-mandatory type reached the 7 active members in the chosen roles (not the treasurer, not the invitee who had not accepted yet) plus 1 test copy; 20 delivery rows for 3 sends, none failed. `<script>`, `onerror` and `javascript:` links were removed. Every email carries a browser-copy link, an unsubscribe link and `List-Unsubscribe` one-click headers. The unsubscribe link asks for confirmation first (mail scanners cannot trigger it), then works and offers "Subscribe again". The opted-out member was left out of the next newsletter. A mandatory type cannot be switched off from the link ("official notice"). A sent newsletter cannot be resent or edited. |
| D5 | PASS with F22 | Digest: "No approved updates waiting" blocks a run unless `--force`; with it, 4 emails to the members of the digest channel's default role, and a second run in the same hour sent nothing more. |
| D6 | FAIL (F23) / PASS | With a contact address set: the message goes to the lodge, an outsider as recipient or cc is ignored (an active member as cc is allowed), the visitor's email is the reply-to, injected HTML is escaped, and the form is rate limited (429). With no contact address set it says "sent successfully" but nothing arrives (F23). |
| C5.2 | PASS | Payment reminders: 3 sent for unpaid bookings with a due date (due soon and overdue), none for paid or cancelled, none on a second run. |

**Email findings (not fixed)**
- **F21 Low.** Booking again after replying "can't go" sends no confirmation email: it is only sent when the booking record is new (`MemberRsvpController`).
- **F22 Low.** The weekly digest lists committee meetings only, not published lodge meetings, though its subtitle says "Upcoming Meetings".
- **F23 Medium.** Public contact form with no contact address configured for the lodge: the visitor is told the message was sent, but each email fails in the queue ("An email must have a To, Cc or Bcc header") and the message is lost. Nothing tells the lodge.
- **F24 Low.** Payment reminders say what is owed and the due date but give no link or way to pay, and an overdue reminder reads the same as a due-soon one ("due by 1 Nov").
- **F3 again.** A superadmin created without a verified email got one verification email per failed login attempt.

**Fixed after Phase D (retested in the e2e app):** F23 (a contact form with no address to send to now shows "This lodge has not set up an address for messages yet, so yours could not be sent." under the message box and queues nothing) and F3 (signing in again while unverified sends one confirmation link per 5 minutes; three attempts gave one email, and the message says to check the inbox). Still open: F11, F19 to F22, F24, and the low items F4 to F10.

**Fixed after Phase H, third round (retested in the e2e app):** F11 (opening Bank accounts no longer creates a "High Street Bank" account; a lodge with none sees "No bank accounts yet" with an add button, Bath RFC stayed at 0 accounts after a visit, and the first account a lodge adds takes the chart's "Operating Bank Account" 1000 so the cash reports keep working), F19 (a refused file shows "Bank statement file must be a CSV, TXT, or OFX format export."; a file with no transactions in it shows "No transactions were found in that file..." and no empty preview; the size limit has its own message). **F20 was not a bug:** "Ignore line" asks a browser confirm box ("Mark this bank statement transaction line as ignored?") that the automated click had dismissed; with the box accepted the line became `ignored`. Still open: F4 to F10, F21, F22, F24.

**Fixed after the email and accounting runs, low findings (retested where it can be seen):**
- F4: the platform name defaults to "ClubManager" (`config/app.php`, `.env.example`). A server's own `.env` still wins: set `APP_NAME=ClubManager` on staging and production, or mail subjects and footers keep saying "Laravel".
- F5: the plain member role is labelled "Member", not "Media manager", on the users list, user profile and roles settings.
- F6: meeting times read "18:30" and "17:30" everywhere (summons, RSVP page, events list, dashboard, PDF). Checked on the summons page.
- F7: the waiting-list email says "(1 person)" or "(3 people)"; a migration rewords the platform wording and any lodge's own copy. The other gaps in F7 (no email when waitlisted, cancelling, or after an RSVP) are new emails, not fixes, and are left.
- F21: booking again after "can't go" or cancelling sends the confirmation; editing a booking still does not.
- F22: the weekly digest lists published lodge meetings (not drafts) together with committee meetings.
- F24: an overdue reminder says "which is now overdue (it was due on ...)". The reminder had no way to pay because that lodge had no payment option set up; with one set, the bank details or a pay link appear.
- **Not bugs:** F9 (the cooldown message does show, as a red banner at the top of the page; it was off screen because I had scrolled), F10 (the stale "Logged out successfully." came from my test script's own logout request).
- **Left alone:** F8 (membership approval is in-app only) needs a new email and template, so it is a feature, not a fix.

### Phase F results, part 1 (run 2 Nov 2026, e2e database, Mailpit)

| ID | Result | Note |
|---|---|---|
| F-subs 1 | PASS with F25 | Fee tier "Full member" £160 saved. The annual billing run for 2026 with no tiers billed nobody and said "5 members were not billed because they have no subscription tier and no tier is set up", although the page said "Default £160.00 rate will apply" (F25). |
| F-subs 2 | PASS | With a tier set: 5 invoices, £800.00; the same run again created 0 and skipped 5. Due date defaults to 1 April of the year. |
| F-subs 3 | FAIL (F27) | A £500 payment against a £160 dues invoice was accepted (paid £500, status "paid"); "Outstanding" then showed £300 instead of £640. Zero or negative amounts are refused. |
| F-subs 4 | FAIL (F26) | Neither the billing run nor the recorded payment posted anything to the ledger (journal count unchanged), though the page header mentions "accounts receivable ledger integration". |
| F-subs 5 | PASS | Rule 181 audit flagged the 4 unpaid members, dues page shows them with the warning status. |
| F-comm 1 | PASS | Committee meeting created (draft) with default date, title and venue; roll-call and agenda item added; candidate vetting and accounts audit panels show. |
| F-comm 2 | FAIL (F28) | "Send to 5 selected" with the PDF attached: no emails, every one failed to queue ("Unable to JSON encode payload ... Malformed UTF-8"), the error is only logged, and the roll-call still shows "Pack sent". With the PDF switched off the same send delivered 4 emails (the member with no email address was skipped without a word). |
| F-char | PASS with F29, F30 | Collection (£120 cash, £30 cheque) and grant (£100) saved and appear in the treasurer report (collections £150, grants disbursed £100). Negative and zero amounts refused. An unknown collection type or grant status gives a server error (F29). A grant went from "proposed" straight to "disbursed" with no check of the order (F29). Charity money never posts to the ledger (F30; it is reported in its own fund). |
| F-sign | PASS | Year audit sign-off request emailed both auditors with a private link; signing by typed name works and shows "Signed, thank you"; reopening the link shows it is signed. An auditor who is not in the lodge is refused (404). |

**Findings (not fixed)**
- **F28 High.** Committee agenda pack emails with the PDF attached cannot be sent on the database queue: the raw PDF is put into the job and fails JSON encoding. The failure is swallowed and the page says "Pack sent". The existing test passes only because tests use a queue that does not encode (it logs the same error). Fix direction: attach the PDF inside the mailable when it is sent (build it from the meeting id) instead of passing the bytes, report how many were really queued, and say which people have no email address.
- **F26 Medium.** Dues billing and manually recorded dues payments are not in the books (no ledger entries). Only a bank line reconciled to a subscription posts. Reports and the balance sheet therefore miss dues income and receivables.
- **F27 Medium.** A dues payment can exceed what is owed, and totals then hide the real outstanding balance. Refuse it, or cap it and record a credit.
- **F25 Low.** The subscriptions page tells the lodge a default £160 rate applies when no tier exists; the billing run (correctly) bills nobody. Change the wording.
- **F29 Low to medium.** Charity collection type and grant status are only checked as text, so a wrong value is a server error instead of a form message; use the enums in the rules. A grant can also be moved straight to "disbursed".
- **F30 Info.** Charity collections and grants are not in the ledger. If that is intended, say so on the charity page.

Not run in part 1: candidate pipeline screens, officers roster, live minute taker, notices of motion, Phases E and G.

**Fixed after Phase F part 1 (retested in the e2e app):** F28. Agenda packs with the PDF attached now go out: the PDF is made once and kept in the cache, and each queued email picks it up when it is sent (the job no longer carries the raw bytes). In the e2e app, 4 emails arrived, each with the 10 KB PDF attached, and no failed jobs. The roll-call is marked "Pack sent" only for people whose email was really queued, and the page says how many selected people could not be emailed (no email address, or no account yet). Two new tests fail on the old code. Note for deploys: restart the queue worker after deploying, or it keeps running the old mail class (I saw four jobs fail that way before restarting the e2e worker).

**Fixed after Phase F part 1, second item (retested in the e2e app):** F26 and F27.
- F27: a dues payment of more than is owed is refused ("That is more than the £160.00 still owed on this subscription.", shown on the amount field; nothing is saved or posted), and so is a payment on a waived subscription. The bank reconciliation screens show the same message as an error if a bank line is larger than what is owed.
- F26: dues are now in the books. The billing run posts Accounts Receivable up and Membership Dues Income up for each member billed; a recorded payment posts cash up and the receivable down (to the ledger account of the bank account when it came from a reconciled bank line, else the main bank account, dated the day of the bank line); waiving takes the unpaid part of the income and the receivable back out. Matching a bank line to a subscription no longer adds the income a second time. In the e2e app: Bob's £160 bill and £60 payment and Dan's £160 bill and waiver all appear as balanced journals, and assets (£237.67) equal liabilities (£125.00) plus equity (£112.67).
- **Older dues are not backfilled.** Subscriptions billed before this change reach the books the first time something is paid or waived against them, not before. A lodge that has used dues billing would need a one-off catch-up to see earlier billings in the reports. Alice's £500 on a £160 invoice in the e2e data is from before the fix and is still overpaid.

### Phase E and G results (run 2 Nov 2026, e2e database)

Setup: the directory data was loaded into the e2e database (19 grand lodges, 70 provinces, 945 halls, 9,688 lodges); the lodge claim below created a second managed lodge, Apollo Lodge.

| ID | Result | Note |
|---|---|---|
| G1 | PASS | All 15 superadmin pages (plus the club type detail and the payments export) return 200 for a superadmin; a lodge admin and a member are sent away from every one. |
| E6a | PASS | Public lodge directory: search by name and number, "meeting this week" filter, province filter, 120 requests per minute rate limit. Hostile input (quotes, `<script>`, over-long, array parameters, bad day or range) gives results or a clean redirect, never an error or reflected markup. A lodge page, its `.ics` calendar, unknown lodge and hall (404) all behave. "Near me" needs an outside geocoding service and was not exercised. |
| E6b | PASS | Claiming a lodge: wrong role, no confirmation, a too-short message and a second claim by the same person are all refused with messages; a valid claim emails the superadmin and notifies them. "More information needed" emails the claimant the question; approving as "new" creates the lodge's club, makes the claimant its owner and emails "You now manage Apollo Lodge"; approving again is refused. |
| E6c | PASS | Visiting a lodge: past date, more than a year ahead, missing home lodge and a repeat request for the same date are refused; a valid request emails the lodge secretary; asking to visit an unmanaged lodge is a 404. Following a lodge works. |
| E2 | PASS with F32 | Website pages: scripts, `onerror`, iframes and `javascript:` links are stripped from text blocks and a button's `javascript:` address is emptied; nothing runs on the public page. A draft page is a 404 and a members-only page sends visitors to log in. A bad slug is refused; a duplicate slug is a server error (F32). |
| E4 | PASS | Media uploads: a PNG is accepted; SVG, HTML, `.php`, PHP disguised as `.jpg`, `shell.php.png`, a file over 10 MB and an image over 6000 px are all refused with a clear message. |
| G4 | PASS with F33 | API: no token 401, wrong password 401, three bad tries then 429, good login returns a token, the token reads the user and a club, a made-up token is 401. The user endpoint returns more than it should (F33). |
| G5 | PASS with F35 | 403 and 404 answer correctly and show no trace; a post with no CSRF token is redirected back, not run. The error pages are Laravel's plain default look (F35). |
| G-sec | PASS | Security headers present on public pages (nosniff, frame options, referrer and permissions policy, a report-only content policy); non-production copies send `noindex`. |

**Findings (not fixed)**
- **F32 Medium.** Saving a page with a slug the lodge already uses gives a database error (HTTP 500) instead of a "that address is taken" message.
- **F33 Low to medium.** `/api/v1/user` returns the whole user record: `calendar_token` (the secret behind the calendar feed), `stripe_id`, card type and last four digits, `is_super_admin` and `two_factor_confirmed_at`. Return only what an app needs (id, name, email, avatar).
- **F35 Low.** 403 and 404 pages are Laravel's unbranded defaults.
- **Note.** The content policy is report-only and allows inline and eval scripts; fine as a first step, worth tightening later.

Not run in Phases E and G: the website builder screens themselves (drag and drop, every block's editor, page history restore, themes, header and footer), posts and news, the public calendar, custom domains, "near me", the superadmin editing screens (grand lodge, province, district, hall, lodge, club type edits), platform payments settings, and cross-lodge id tampering inside Livewire requests.

**Fixed after Phases E and G (retested in the e2e app):** F32 (saving a page with an address the lodge already uses, including one in the trash, now answers "A page with this address already exists (it may be in the trash). Choose another address." as a form error; a page can still be saved under its own address, and another lodge may use the same one) and F33 (`/api/v1/user` returns only id, name, email and avatar). Both have a test that fails on the old code. Still open: F35 (unbranded 403 and 404 pages), F29, F25, F30, F8 and the other missing emails, and the dues backfill.

### Website builder results (run 2 Nov 2026, e2e database)

Six test agents ran at once against `lodge-of-fraternity` (five over HTTP with curl and the Inertia page data, one in the built-in browser). Their detailed tables are in the side-session notes folder (`lifecycle.md`, `blocks.md`, `history.md`, `settings.md`, `public.md`, `browser.md`). Because the front end is client-rendered Vue, "renders on the public page" means the data the page receives; script execution was not seen in a browser, so the XSS findings come from the stored data plus the Vue source. Part of the run was done as the superadmin: another test session set `member.a3@e2e.test` to `past` in club 1 from 22:03 to about 22:15, which made the lodge admin pages answer 403 (not a builder bug). Server was slow and shared, with some throttling (429) between agents.

| ID | Result | Note |
|---|---|---|
| W-life 1 | PASS | Create, update (one POST with `id`; there is no PUT), mass-assigned `club_id`, `preview_token`, `deleted_at`, `sort_order`, `is_homepage` ignored; renaming a slug leaves a 301 from the old address. |
| W-life 2 | PASS | Slug rules: space, slash, dot, empty, 256 characters refused; duplicate incl. trashed refused with the F32 message. Reserved words (`admin`, `events`, `files`...) are accepted and shadow nothing. |
| W-life 3 | PASS | Scheduling: future `publish_at` and past `unpublish_at` give 404 and drop out of navigation, footer and sitemap; `unpublish_at` not after `publish_at` is refused. |
| W-life 4 | PASS | `toggle-publish`, draft save / discard / publish (draft never public, publish copies and clears it, a full save clears it), duplicate (unpublished, out of navigation), trash, restore, force delete (removes revisions and redirects), reorder (other lodge's ids ignored, bad input 422). |
| W-life 5 | FAIL (F38) | Homepage: forced public when saved; renaming its slug away from `home` makes the site root 404. |
| W-life 6 | PASS | Members-only, noindex (meta tag, left out of the sitemap), header styles, navigation and footer flags, SEO limits, HTML in titles escaped on output. |
| W-life 7 | FAIL (F40, F51) | Two more routes hit the unique index (F32 siblings); saving with a foreign or made-up `id` creates a new page. |
| W-life 8 | PASS | A page id of the other lodge under this lodge's slug gives 404 on toggle, duplicate, destroy, restore, force delete, draft, preview-link, revisions and copy-block; rows untouched. |
| W-blk 1 | PASS | All 24 block types create, save and appear in the public page data; edit, reorder, remove, 200-block cap work; `npm run check:blocks` passes. |
| W-blk 2 | PASS | `copy-block` (takes the whole block as JSON): sanitises like a save; invalid, oversize, other lodge's, trashed or missing page refused. |
| W-blk 3 | PASS | `javascript:`, tab-split, `data:`, `vbscript:` URLs and about 30 rich-text XSS payloads are removed from every URL and `v-html` field **when the value is a string**. Plain-text fields keep raw HTML in the database but Vue escapes it. |
| W-blk 4 | FAIL (F36) | The same fields given as an array skip the sanitiser (stored XSS by an editor). Reproduced by the coordinator: a text block with `content: ["<img src=x onerror=alert(1)>"]` and a YouTube `button_url: ["javascript:alert(1)"]` were stored and sent to the public page. |
| W-blk 5 | FAIL (F39, F50) | Scalar items in `blocks` make the public page 500; no per-block size limit. |
| W-blk 6 | FAIL (F43, F44, F45) | Data blocks show the wrong things (past and cancelled events, inactive plans, finished campaigns); two buttons lead nowhere. |
| W-blk 7 | PASS | Logged-out visitors see only public events and posts in feed and calendar blocks; members-only downloads are locked for them, including the file route. YouTube link forms and look-alike hosts, map clamping and geocode work. |
| W-hist 1 | PASS | Revisions: made on a content change, none on an unchanged save; a slug, noindex or publish change adds none. Restore loads the old content into the editor and a normal save writes it (so it can be undone); keeps 15 per page, age pruning keeps the newest, settings limits validated. |
| W-hist 2 | PASS | Revision ids of another page or lodge, a trashed page, a plain member, treasurer, coach, anonymous: 404 / 403 / 401 as expected. |
| W-hist 3 | PASS | Preview link: 40-character random token, same link on repeat, `renew` switches the old one off, shows the unpublished page and the unpublished draft of a published page to a logged-out holder only, `no-store`; wrong, empty, other page's or other lodge's token shows the live page or 404; not in sitemap or public data; not copied by duplicate. |
| W-hist 4 | PASS with F56 | Saving or discarding a draft adds no revision, publishing one adds one; hostile HTML is sanitised before it is stored in revisions and drafts. |
| W-set 1 | PASS | Themes: valid keys saved; unknown, array, null all 422. Colour schemes: only `#rrggbb`, name HTML-stripped and max 40, 2 per lodge, delete refuses a scheme in use and another lodge's. Nothing can break out on the public page. |
| W-set 2 | PASS with F54 | Header and footer and site settings: `javascript:`, `data:`, `vbscript:` links refused; HTML in text fields shown as text; mass-assigned `domain_status`, `stripe_secret` ignored; revisions limits validated. |
| W-set 3 | FAIL (F41, F47) | Custom domain: hostile hostnames all refused and verify-domain without DNS stays `pending` (never active, pending domain does not serve the site, `/internal/domain-allowed` is 404); but a saved domain cannot be removed, and the form shows no error text. |
| W-set 4 | PASS with F52 | Redirects: applied with the right 301 / 302 and Location, case and trailing slash tolerant, never shadow a published page, left out of the sitemap, chains work; delete of another lodge's id is 404. |
| W-set 5 | PASS with F55 | Places and geocode (internet reachable, rate limit about 30 per minute), downloads upload: pdf, docx, xlsx, jpg, png, txt, csv, zip accepted; svg, html, php, exe, js, disguised and double extensions, over 10 MB, missing or array refused; path-traversal names reduced to the base name; served file has nosniff, correct inline or attachment, and 404 for other lodges' or unlisted media. |
| W-pub 1 | PASS | Published 200; draft, trashed, scheduled and expired pages 404 for anonymous, a member and the lodge admin; members-only sends visitors to login with no content and returns them after login; sitemap valid and lists only live indexable pages; no `draft_blocks`, `preview_token`, secrets or member emails in public data. |
| W-pub 2 | FAIL (F37) | Members-only page titles and slugs appear in the public navigation and footer data. |
| W-pub 3 | PASS with F42 | Contact form with a lodge address: arrives in Mailpit, header injection neutralised, HTML escaped, length and email validation, 6 per minute per IP, CSRF 419; recipient is taken from the client. |
| W-pub 4 | PASS | Roles: every builder GET is refused for treasurer, coach, plain member (403) and logged-out (login redirect); all 22 POST and DELETE routes refused (403 / 401) and nothing changed; CSRF enforced (419); lodge admin of another lodge 403. |
| W-pub 5 | FAIL (F46) | `?preview[]=x` gives a 500 for anyone on any public page; `?cal[]=x` on a calendar page too. |
| W-ui 1 | PASS | Editor in the browser: create page, 10 block types inserted, rich text bold and link, move up and down, remove with confirm, save and reload, unsaved-changes modal, save draft / preview link / publish draft / discard, revisions restore, unpublish and republish, redirects screen. |
| W-ui 2 | PASS | Duplicate, copy block to another page, delete to trash, restore, delete forever, move page up and down, themes apply (restored), colour scheme create / edit / delete, header and footer toggle (restored), places geocode and public map. |
| W-ui 3 | FAIL (F47, F48, F49) | Settings form silently fails; no mobile menu; insert menu clipped at 1024px. |
| W-ui 4 | PASS with F57 | Public page at 375, 768 and dark: no horizontal overflow from content, form, cards, stats, map; members-only redirects to login, draft 404. `npm run check:templates` and `check:components` pass. |

**Findings (not fixed)**
- **F36 High.** Stored script injection by anyone who can edit the website: the sanitiser and the `javascript:` check only look at string values. `POST /lodge-of-fraternity/admin/pages` with JSON `{"title":"x","slug":"x","is_published":true,"blocks":[{"type":"text","content":["<img src=x onerror=alert(1)>"]}]}` stores the array unchanged and `GET /site/lodge-of-fraternity/x` sends it to the page, where `v-html` writes it as HTML (`BlockRenderer.vue:257`; same for `media_text`). A YouTube block with `"button_url":["javascript:alert(1)"]` is rendered as a link (`BlockRenderer.vue:353`); a `post_attachments` block with `items[].url` as an array does the same; a `post_header` block's `back_url` is a plain `javascript:` string that is never checked. The posts' blocks use the same cast. Fix direction: in the cast and normalisers treat a non-string at an HTML or URL key as empty, and add `back_url` to the checked keys.
- **F37 Medium.** Members-only pages are listed to the public: `SiteChrome` builds `navigation` and `footerNavigation` from `live()` with no `is_members_only` filter, so a logged-out visitor sees each members-only page's title and slug in the page data (and the header links to it). `GET /site/lodge-of-fraternity` and read `navigation` in `data-page`.
- **F38 Medium.** Renaming the homepage's slug takes the site down: `POST admin/pages` `{"id":1,"title":"Home","slug":"home-tmp",...}` leaves `/site/lodge-of-fraternity` as a 404 though `home-tmp` is live. The homepage is whatever has slug `home`, and the fallback in `PublicSiteController::showPage` reuses one query that still carries `is_homepage = true`. Lock the slug of the homepage or fall back to the first live page.
- **F39 Medium.** A non-array item in `blocks` (`[1]`, `["x"]`, `[null]`, `[true]`) is saved with a redirect and then every view of the page is a 500 (`PageBlocks::forViewer` type error); on the homepage that is the whole site until the editor is used to fix it. The rule is only `array|max:200`; the draft route has the same one.
- **F40 Medium.** F32 is not fully fixed: (a) duplicate page A, trash `a-copy`, duplicate A again gives a 500 on `pages_club_id_slug_unique` (the free-slug check skips trashed rows); (b) trash a page, then save it by its `id` (editor left open in another tab): validation passes, the trashed row is not found and the insert fails with a 500.
- **F41 Medium.** A custom domain cannot be removed. Once a domain is saved, `POST admin/pages/settings` with `custom_domain` empty or null, or with no `custom_domain` field at all, gives a 500 (`domain_status` NOT NULL violation: `ClubDomain::apply()` sets it to null). A lodge that enters a domain by mistake is stuck, and every later settings save fails.
- **F42 Medium.** The contact form takes the recipient and cc list from the browser: `POST /site/lodge-of-fraternity/contact-form` with `recipient_email=member.a4@e2e.test` and `cc_emails=treasurer@example.com` delivered a lodge-branded email to both with any text. Anyone can use the lodge as a mail relay to its members (throttled to 6 a minute per IP). The block's `recipient_email` and `cc_emails` are also in the public page data. Decide the recipients on the server from the saved block.
- **F43 Medium.** The "Events & Summons" block (`events_calendar`) shows past and cancelled events and ignores its `limit`: the page gets `events()->published()->visibleTo()->limit(3)` with no date filter or order, so anonymous visitors see last month's and the cancelled event while the next five public events never appear.
- **F44 Medium.** `pricing_cards` shows inactive membership plans and `donation_campaign` shows completed campaigns (each confirmed with a temporary row, since removed).
- **F45 Medium.** The campaign's "Make a contribution" posts to `/site/{club}/donations/{id}`, which has no POST route (405); the pricing card's "Subscribe" goes to `/{club}/admin/subscriptions`, which is 403 for a member.
- **F46 Medium.** `GET /site/lodge-of-fraternity/<any page>?preview[]=x` is a 500 for any visitor (`(string) request()->query('preview')` in `PublicSiteController::showPage`, line 40); `?cal[]=x` does the same on a page with a calendar block.
- **F47 Medium.** The Website and SEO settings form hides its validation errors: typing `http://not a domain/x` as the custom domain and pressing Save returns the error `custom_domain ... format is invalid`, but `PageSettingsPane.vue` shows none for that field (nor contact email, phone or SEO fields), so Save appears to do nothing. The live DNS instructions are built from the raw text. There are also two buttons both labelled "Save website settings".
- **F48 Medium.** The public site has no mobile menu: the header is a wrapping row of every page. With 57 pages it is 303 px of an 812 px screen at 375 px, links are 34 px tall, and a very long unbroken page title (255 characters) makes every public page scroll sideways.
- **F49 Medium.** In the editor at 1024 px wide the insert-element menu is clipped under the sidebar; Text, Callout, FAQ, Downloads and Button cannot be clicked. Fine at 1400 px.
- **F50 Medium.** No size limit on a block: 3 MB of notice text and 20,000 gallery items were saved (each save also stores a revision, and each took 10 to 20 seconds). Rich text over 2,000,000 characters is cut off silently and the save reports success.
- **F51 Low.** Saving with an `id` that is not in this lodge (another lodge's, or made up) creates a new page with `sort_order` 0 instead of an error, and `GET admin/pages/<foreign or unknown id>/edit` answers 200 (no other-lodge data is shown).
- **F52 Low.** Redirect targets are checked loosely: a CRLF in `to_url` is saved and then that public address is a 500; A to B to A loops are accepted; `from_path` accepts `..`; `/\evil.com` is accepted (stays on the same host).
- **F53 Low.** Any lodge can park a domain as pending, including the platform's own host, which stops the real owner saving it (no takeover: it can never go active without the TXT record). The failed-verification message names "the ownership (TXT) record" but not `_clubmanager-verify.<domain>`; the host and value are only in the settings panel.
- **F54 Low.** `primary_color` in site settings takes any string up to 50 characters; `share_image` takes any https address including `.svg`; the rich-text sanitiser keeps `class`, so `<div class="fixed inset-0 z-50 bg-white">` can overlay the page. None of these runs script.
- **F55 Low.** More 500s from odd admin input: `DELETE admin/pages/redirects/abc`, `GET admin/pages/<abc>/revisions` and `/revisions/abc`, `POST admin/pages/abc/copy-block`, `GET admin/pages/places?q[]=x`, duplicating a page with a title over 247 characters, uploading a file whose name is about 300 characters.
- **F56 Low.** Small data issues: the revision read endpoint returns stored blocks unsanitised (saves sanitise again); `public.site` has no throttle for `?preview=` guesses and tokens never expire and are stored in plain text; every public page carries all feed, plan, donation and event data whether or not it has such a block; navigation items show `lifecycle_status: "off"` for live pages; `GET` on a lodge with no pages creates its default pages; the history list cannot tell a meta-only save from a block edit and a restore is not labelled.
- **F57 Low.** Editor and public polish: the slug does not fill from the title on create (stays `new-custom-page`); the trash list shows `/site/lodge-of-fraternity/` with no slug; the delete dialog says "cannot be undone" though the page goes to the trash; "Delete forever" uses a native confirm; the button says "Save and publish page" when Published is unticked; `[tiptap warn] Duplicate extension names found: ['link']` on every editor screen; in dark mode the in-content link (rgb 194,65,12 on near black) is 3.8:1; name and message are optional on the contact form (a blank enquiry arrives from "Anonymous Visitor"); a signed-in non-member opening a members-only page lands on the login form instead of a 403; a button address like `example.org:8080/x` is emptied; a YouTube link's `?t=` is dropped.

Not run: the "domain already used by another club" check, a domain going active and `site_redirect_to_domain` with a live domain (need real DNS); the whole-site noindex switch and `noindex` in production mode (code read only); drag-and-drop reordering (move buttons used); an overview search, sort and trash tab (the overview has none); the builder's own 429 limits; the bath-rfc admin direction of the cross-lodge test (no account); a visible check that scripts really run for F36 (read from the Vue source); treasurer and coach on every revisions and preview route (done on the list and preview-link). Cleanup: every `wb-` page, upload, redirect, colour scheme and temporary plan or donation was deleted; theme, header and footer and club settings were restored. Side effects left: the Home page's `updated_at` moved (a probe hit it and was reverted), five default pages were created for bath-rfc by a first public visit, two test contact messages remain in Mailpit.

## Money bugs fixed (from the accounting, events and meetings sessions)

Tests: `tests/Feature/MoneyIntegrityTest.php` (nine, all fail on the old code), a new case in `EventRegistrationServiceTest`, and `EventBookingPaymentTest` changed (a member can no longer make a paid booking cheaper).

| Finding | Fix |
|---|---|
| Invoice numbers shared across lodges, and numbered by count (delete then create collided) | `AccountingService::nextDocumentNumber()` takes the highest number plus one under a lock on the club row; migration `make_invoice_numbers_unique_per_lodge` replaces the platform-wide unique index with one per lodge |
| Draft and pending documents could be paid | `markBillAsPaid`/`markInvoiceAsPaid` refuse anything not issued and approved |
| F103 editing the amount of a posted bill or invoice | refused for unpaid and paid documents (delete and raise a new one) |
| F105 closed year did not lock bills and invoices | edit and delete refused when the document was posted in a closed year (`postedDocumentLockReason`) |
| F106 invoice created or published in a closed year was half saved | create and publish run in one transaction |
| F108 VAT return counted drafts and pending documents | only unpaid and paid documents |
| F112 one closed lodge stopped recurring bills for all | `LedgerRuleException` per template is logged and skipped |
| F101/F102/F140/A3/F118 opening balance | set, not added (only the difference is posted); negative keeps its sign; same figure posts nothing and says so; adding an account with a refused balance leaves no account; date limited to 2000 to today |
| F124 financial return posted twice | a final return already posted is refused |
| F127 member lowers the price of a paid booking | refused for members (adding people is still allowed); organiser rule unchanged |
| F107 auditors replaced after sign-off | refused once signed off; the controller shows the message |

Not done: treasurer or secretary accepted as auditor, Gift Aid declarations, F126 (VAT quarter only), F128 (signed figures change), VAT rate validation, accounting screen N+1.
Deploy note: restart the queue worker and run the migration (safe on existing data: the old index was stricter).

## Round after the first reviews (security and correctness review of the money commit)

Fixed here: F60 (installing a roster stands down officers not on it), F126 (an edit that adds nobody keeps its place at capacity), F123 was already fixed (member PDF test exists).
From the reviews of the money fixes:
- an issued (unpaid or paid) invoice or bill can no longer be saved as draft/unpaid, which re-posted it on republish; the update and its posting run in one transaction; the attachment is only replaced after the amount check;
- paying checks the status on the locked row, so a double click pays once;
- a posted financial return cannot be reopened as a draft; the club row is locked while posting;
- replacing an auditor is refused while that auditor has a pending or signed request (cancel it first);
- opening balance runs under the club lock; account add has the same date limits;
- a member's edit that leaves people, tickets, promo and payment option unchanged is not refused when the organiser later lowered a price.
Left (low): VAT return buckets by created date not issue date and omits refunded invoices; forced root URL on unknown hosts (`IdentifyTenantByDomain`); attachment files written inside a rolled-back transaction stay on disk; user-typed bill numbers can still duplicate; `InvalidArgumentException` not caught in the recurring bill and financial return paths.
