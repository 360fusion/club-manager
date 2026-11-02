# Events and meetings, the rest (e2e run 2 Nov 2026)

Run against the e2e app (`http://127.0.0.1:8001`, PostgreSQL `club_manager_e2e`, Mailpit), lodge `lodge-of-fraternity` (club 1) with `bath-rfc` for isolation. Test only: no application code, tests, docs or commits were changed. Five areas were tested by separate agents: payment options and pricing (PAY), event admin (ADM), public and visitor booking and calendars (PUB), meetings leftovers (MTG), permissions and isolation (PRM). Each area's full notes (with extra evidence and the clean-up list) are in `side-session-notes/local_a0cb9e9d-.../{pay,adm,pub,mtg,perm}.md`. The items I (the coordinator) re-ran myself are marked "re-checked".

Environment notes: another session set `member.a3`, `coach@`, `treasurer@` and `admin@example.com` to `club_user.status = past` from about 22:03 to 22:20 UTC, so some runs used throwaway admins with the same role. Port 8001 dropped briefly under load more than once. The `public-forms` throttle (6/min per IP) is shared by every session on 127.0.0.1.

## Results

| ID | Result | Note |
|---|---|---|
| PAY-1 bank transfer switch-on | PASS | refused while details are incomplete (422 on `PUT /active`), accepted when complete |
| PAY-2 pay later / on the night | PASS | switch on with no extra details |
| PAY-3 card and PayPal | PASS | no keys, half keys, Connect with no platform account all 422; with fake keys they switch on but are never offered while `EVENTS_ONLINE_PAYMENTS` is off; forcing their id in a booking is 422 |
| PAY-4 offer on upcoming events | PASS | adds a row to every upcoming event without one (16), leaves existing rows alone; switching off stops quotes and bookings; "not added to past events" not exercised (none exist) |
| PAY-5 ordering, destroy, type change | PASS | quote follows saved order; foreign or unknown ids ignored; used option switches off instead of deleting; type cannot change |
| PAY-6 config encryption | PASS | stored as an encrypted payload; secrets never in any Inertia props (only `has_*_secret`) |
| PAY-7 format checks on bank details and percentages | FAIL | see F165 |
| PAY-8 111 option quotes vs independent half-up pence calculation | PASS | 0 mismatches, all whole pence (percent and fixed, per person and per booking, three booking-fee types, tiers, dinner, promos, fractional prices) |
| PAY-9 stored booking = quote = advertised price | PASS | also the `all_in` and "lowest" advertised figures and a free event |
| PAY-10 fees on card/bank, negative values, discount larger than price | PASS | fee on card/bank 422 (but see F164 for the message), negatives 422, oversize discounts clamped to the price |
| PAY-11 promo codes | PASS with F138, F139 | valid, lower-case, invalid and used-up codes behave; no expiry feature exists |
| PAY-12 organiser edit after a part payment | PASS | any price-changing edit refused, a rename accepted |
| PAY-13 member edit after a payment | FAIL | see F127 |
| PAY-14 booking emails per option | PASS | right total, bank details and exact reference, pay-later due date, on-the-night text; HTML in labels escaped. The emails do not itemise method fee, discount or promo |
| PAY-15 `app:send-event-payment-reminders` | PASS | picks only attending unpaid or part-paid events with a total in the 3-day window; twice in a row sends 0; 6 days after the last one 0, 8 days 1; reminder carries amount, due date, instructions, bank details and reference. Overdue now has different body wording, but the same subject and heading (F24 only partly fixed). Only pay-later bookings get a due date, so bank bookings never get reminders (looks intended) |
| ADM-1 create event (tiers member/guest/everyone, 3 courses x 2 dishes, capacity, waitlist, guest limit) | PASS | |
| ADM-2 form validation (bad status, end before start, negative price, capacity 0 or huge, audience rules) | PASS | all 422 with clear messages |
| ADM-3 first publish notifies members | PASS | in-app notification only |
| ADM-4 unpublish then republish | FAIL | see F129 |
| ADM-5 dish edited in place by id | PASS | attendee meal choices survive; removing a dish or tier in use is 422 and rolls back the whole save |
| ADM-6 capacity below booked count | PASS with F137 | accepted silently |
| ADM-7 closing date vs "close N days before" | PASS | `rsvp_deadline` wins; open at the exact cutoff second, closed one second later; organiser add after closing is allowed (by design) |
| ADM-8 duplicate event | PASS | copies tiers, dishes, payment options, promos, no bookings, draft, random slug suffix; the date is not shifted |
| ADM-9 cancel event | FAIL | see F130, F157 |
| ADM-10 delete event | FAIL | see F128 |
| ADM-11 duplicate slug | FAIL | see F131 |
| ADM-12 guest list, catering summary, dietary list | PASS | counts correct, waitlist listed |
| ADM-13 CSV export | PASS with F159 | `= + - @` neutralised, commas and quotes quoted, unicode kept; no BOM |
| ADM-14 check-in, undo, walk-in | PASS with F158 | walk-in works and does not touch capacity |
| ADM-15 member search | FAIL | case-sensitive (F132), `q[]=a` 500 (F160); `%` `_` literal, min 2 chars, active members of this club only |
| ADM-16 organiser add for member, visitor, pending or other-club member | PASS | duplicate member 422; pending and other-club member 422 |
| ADM-17 organiser add of the same visitor email twice | FAIL | see F133 |
| ADM-18 organiser add over capacity | PASS | waitlist on: waitlisted; off: 422; `over_capacity:true`: attending |
| ADM-19 organiser edit of a booking | FAIL | see F126, F161; price change after payment correctly refused |
| ADM-20 promote, resend, cancel registration | PASS | promote can exceed capacity (documented); resend sends exactly one email to the right address |
| ADM-21 payments (part, balance, overpay, waive, refund limits, double refund, history, bulk-paid) | PASS with F134, F162 | one email per payment and per refund |
| ADM-22 meal edit endpoint | PASS | other event's dish 422, attendee 404, dining toggle on a paid booking refused |
| ADM-23 bookings (subscribers) page | FAIL | see F135, F136 |
| ADM-24 browser: edit form, create form, bookings page, edit popup, unsaved draft | PASS | no console errors; draft key is per club and event id so nothing leaks between events |
| PUB-1 public event page for public, members-only, draft, unknown and other club's slug | PASS | members-only and draft 404 with no details; foreign slug 404 |
| PUB-2 cancelled event | PASS | page loads, `canBookAsGuest=false`, register 422 |
| PUB-3 past event whose status is still upcoming | FAIL | see F140 |
| PUB-4 closed (deadline passed) | PASS | 422 |
| PUB-5 validation (bad email, missing name, guest limit, foreign tier id, bogus payment method), honeypot, CSRF | PASS | all 422 / 419 |
| PUB-6 booking with tiers and a bank option | PASS | total 22.50, reference and manage link correct in the email |
| PUB-7 email normalisation, member's email used as visitor | PASS | no account-existence leak |
| PUB-8 same visitor email twice, cancelled then rebook | FAIL | see F141, F142 |
| PUB-9 full without waitlist, with waitlist, cancel via manage link promotes first in line | PASS | one "place available" email to the right person |
| PUB-10 rate limit | PASS with F143 | 7th request 429; one bucket for quote and register |
| PUB-11 XSS in names | PASS | escaped in email and pages |
| PUB-12 manage link: view, cancel, cancel twice, wrong/short token, other club's token under this slug, token after cancel, `/pay` with no online payment | PASS | 40 random characters, only sha256 stored; wrong token shows the "expired" page (200), cancel 404, `/pay` 422; page shows only that booking |
| PUB-13 guest emails | PASS | a guest with an email gets a notice with no link |
| PUB-14 public `.ics` (club and directory feeds) | PASS with F144 | valid, CRLF, folded at 75 octets, escaped, stable UIDs; published public events only, no meetings |
| PUB-15 HTML calendar block, bath-rfc isolation, wrong member-feed token, member.a4's feed | PASS | |
| PUB-16 custom-domain rewrite | NOT RUN | needs changing the domain settings |
| MTG-1 season generator dates (2nd Tue, last Fri incl. 2032 leap year, 1st Mon, 4th Thu, last Sun with DST weekends, year rollover) | PASS | all 60 dates match an independent calculation; 5th occurrence refused with 422 |
| MTG-2 second run, bad input | FAIL | see F146 |
| MTG-3 manual create or edit onto an existing date, second duplicate | FAIL | see F145 |
| MTG-4 duplicate meeting | PASS with F145 | draft, date +1 month, agenda and officers copied; RSVPs, dining, financial return not copied |
| MTG-5 delete meeting | PASS with F156 | draft and published-with-RSVPs both delete; plain member 403, cross-club 404 |
| MTG-6 edit after publish | PASS with F150 | RSVP rows and tokens kept; nobody told of a date change (note) |
| MTG-7 dining page and dashboard headcount | FAIL | see F148 |
| MTG-8 dining payment | FAIL | see F147 |
| MTG-9 admin RSVP and payment status | PASS with F149, F166 | foreign-club or non-member user 404, unknown user 422 |
| MTG-10 financial return | FAIL | see F124 (negatives, over-max 422 and pence rounding are fine) |
| MTG-11 officer election and confirm | PASS with F155 | |
| MTG-12 summons PDF as admin | PASS | HTML view 200; `?download=1` is a real PDF (`%PDF-1.4`, 2 pages, about 19 s); é, & and `<script>` escaped |
| MTG-13 summons PDF as member | FAIL | see F123 (re-checked) |
| MTG-14 published summons email | FAIL | see F125 (re-checked in the code) |
| MTG-15 apologies after the RSVP cutoff, private link, expired and wrong tokens | PASS with F150, F153 | wrong, short and expired tokens show the "Expired" page |
| MTG-16 lodge visitor registration | PASS with F151 | creates user + visitor role; duplicates and members untouched |
| MTG-17 visit requests and visitor access | PASS with F152, F167 | past, duplicate, member-of-lodge and cross-club cases refused as designed |
| MTG-18 visitors counted separately from members | PASS with F148 | dashboard shows `visiting_*` separately |
| PRM-1 admin GET routes, 26 routes x 11 roles | PASS | logged-out 302; member, resigned, pending, past, bath-only member and other-club admin 403; events open to admin, coach, treasurer; meetings and visitors to admin, coach; payment options to admin, treasurer; no wrong 200 or 500 |
| PRM-2 mutating routes (42 shapes) as refused roles | PASS | all 403 (401 logged-out JSON), database hash unchanged |
| PRM-3 other club's ids under this slug and the reverse | PASS | 404 or 403 everywhere, database unchanged; meetings sub-routes validate before looking the meeting up, so give 422 not 404 (harmless) |
| PRM-4 registration/attendee ids of another event, same club or other club (show, payment, paid, refund, cancel, resend, update, promote, meal, checkin, bulk-paid) | PASS | all 404; bulk-paid ignores foreign ids |
| PRM-5 per-club `permission_matrix` override (tested on bath-rfc, restored) | PASS | middleware follows the override exactly |
| PRM-6 coach and treasurer on payment actions | PASS | coach refused on paid, refund, bulk-paid and `mark_paid` in the organiser form (`manage_billing` gate, narrower than the events route_map); treasurer's actions land in `event_payment_log` with their user id |
| PRM-7 member area lists, RSVP, quick-rsvp, quote, pay with foreign or draft ids, pending/past/non-member | PASS | 404 or 403; nothing changed |
| PRM-8 `/members/{slug}/events` and `/members/{slug}` for pending and past members | FAIL | see F121 |
| PRM-9 `club` prop of those two pages for plain members | FAIL | see F122 |
| PRM-10 summons page props for a plain member | FAIL | see F120 (re-checked) |
| PRM-11 public site visibility and tokens across clubs; visitor withdraw of another user's request | PASS | |

## Findings (not fixed)

### High

**F120 High. A plain member's summons page sends the whole club's contact data and secrets (re-checked).**
Log in as `member.a4@e2e.test`, then `GET /members/lodge-of-fraternity/meetings/1/summons` with `X-Inertia: true`, and read `props.club.users`. I got 48 users (the permissions agent got 71 on another meeting) with `email`, `stripe_id`, `pm_type`, `pm_last_four`, `is_super_admin`, `calendar_token` (50 occurrences) and `pivot.invitation_token` (50 occurrences), plus `pivot.phone`, `dietary_notes`, `emergency_contact`. A leaked `calendar_token` opens the private feed with no login: `GET /calendar/<token>.ics` returned 200 with 26 events. Invitation tokens are the credential for `/{slug}/invite/{token}` (not tried). `secretaryUser` and `worshipfulMaster` are whole User models too, and `worshipfulMaster` falls back to `$club->users->first()` when no master role exists, so a random member is shown as Worshipful Master. Cause: `MemberMeetingController::summons` passes `'club' => $club` with `users` loaded; `User::$hidden` does not cover `calendar_token` or the pivot columns. Send a plain array for club and map the two named users to name and rank.

**F121 High. Pending and past members can read the lodge's published summonses on `/members/{slug}/events` and `/members/{slug}`.**
Log in as a user whose `club_user.status` is `pending` or `past`; `GET /members/lodge-of-fraternity/events` with `X-Inertia: true` returns 200, component `Member/Events`, and the `meetings` prop lists every published meeting with date, venue, dress code, dining cost and RSVP cut-off. `GET /members/lodge-of-fraternity` does the same. The other member pages (`/meetings`, `/calendar`, `/news`) correctly give these users 404 and the summons 403. Cause: `MemberPortalController::show` and `::events` only check that a pivot row exists, not `status === active`.

**F122 High. Draft events (and every event row) are sent to every club user in the `club` prop of `/members/{slug}/events` and `/members/{slug}`.**
As `member.a4`, `GET /members/lodge-of-fraternity/events` with `X-Inertia: true`, read `props.club.events`: 36 events including `draft` ones, with description, location, price, capacity, menu items and ticket tiers; the filtered `events` prop is correct, so the `club` prop bypasses `published()`, `visibleTo()` and `rsvp_audience`. Pending and past users get the same list. Cause: `Club::where('slug', ...)->with(['events.menuItems', 'events.ticketTiers'])` then `'club' => $club` as a prop.

**F123 High. Members cannot download the summons PDF: HTTP 500 (re-checked).**
`GET /members/lodge-of-fraternity/meetings/1/pdf` as any active member (I used `member.a4`; all four runs by two agents too) returns 500: `Call to undefined method App\Http\Controllers\MeetingAdminController::pdf()` at `MemberMeetingController.php:88`. `pdf()` now lives in `MeetingSummonsController` (the admin route works). Permission checks around it are right (draft and foreign ids 404; pending, past and non-member 403). The existing test only covers the refusal.

**F124 High. Re-submitting a final financial return posts the ledger twice.**
`POST /lodge-of-fraternity/admin/meetings/{id}/financial-return` (final, no `is_draft`) twice with the same data creates two journal entries (Dr bank 93.08 / Cr 75.02, 10.56, 5.00, 2.50) and two unpaid vendor bills (49.38 each). `meeting_financial_returns` keeps one row pointing at the latest pair, so the first pair is orphaned in the books. A third submit with zeros left both earlier entries in place. Cause: `MeetingFinancialReturns::postMeetingFinancialReturn` always creates the bill and journal and only then `updateOrCreate`s the return.

**F125 High. The emailed summons has no RSVP link and shows a literal `{{starts_at}}` (code re-checked).**
Publishing a meeting mails every active member; the body in Mailpit reads "starting at *{{starts_at}}* at Hall ... Please record your attendance and dining RSVP in the member portal." with no link. The seeded `default_email_templates` row `meeting_summons` (`ClubTypeSeeder.php:504-505`) uses the placeholder `starts_at`, but `MeetingSummonsMail` supplies `meeting_time` and `rsvp_url` (lines 42-44), neither of which the template uses. So the private passwordless link is generated and stored but never sent (the successor of F1). Fix the placeholder and add `{{rsvp_url}}`, in the seed and in a new migration (the seeder only runs on an empty table).

**F126 High. Editing a booking when the event is at or over capacity silently moves it to the waiting list, even when paid.**
Event with capacity 4 and 5 people holding places. `PUT /lodge-of-fraternity/admin/events/{id}/registrations/{reg}` for a paid £40 attending booking, changing only `contact_phone` and sending `"status":"attending"`: 302, then `status=waitlisted, payment_status=paid`. Reproduced twice. No email or warning. Cause: `persist()` calls `statusWithCapacity()` on every edit, counting everyone else. An edit that adds no people should keep its status. Lowering capacity (F137) triggers the same.

**F127 High (money). A member can change the price of their own booking after a payment was recorded.**
Booking £55.75 (bank), organiser records £20 (`POST .../registrations/{reg}/paid {"amount":20}`). Then as the member `POST /members/lodge-of-fraternity/events/{id}/rsvp` with the same booking minus the guest: total 27.00 with `amount_paid` 20. Again with a 150% promo: total 0.00, `payment_status` `paid`, no payment-log row, no refund, and the old promo's `uses_count` not returned. Cause: `EventRegistrationService::persist()` refuses a price change after payment only when `$organiser`; the booking modal has no locked state. The organiser path is correctly refused.

### Medium

**F128 Medium to High. Deleting an event wipes its bookings and payment history.**
Publish an event, add a booking via `POST .../registrations`, mark it paid, `DELETE /lodge-of-fraternity/admin/events/{id}`: 302; `events`, `event_registrations` and `event_payment_log` rows for it are gone, but the journal entry "Event payment: ..." (`source_type=EventRegistration`) stays pointing at a missing registration. No refund, no email, the confirm dialog only says the title will be deleted. It contradicts "append-only event_payment_log". Cause: bare `$event->delete()` with cascading FKs (`EventAdminController::destroy`). Refuse when any booking exists or has a payment, and offer Cancel.

**F129 Medium. Unpublish then republish notifies every member again** (the form says members are told once).
Publish a draft event (+21 notifications), save as draft, save as upcoming again (+24); per user two `category:event` rows for the same event. Cause: `$justPublished = status === upcoming && ($wasRecentlyCreated || $existing?->status === 'draft')` in `EventAdminController::store`; there is no stored "announced" flag.

**F130 Medium. Cancelling an event still promotes the waiting list and emails "A place is available".**
Event capacity 2, booking A (2 people), booking B (1, waitlisted). `POST .../events/{id}/cancel`, then `POST .../registrations/{A}/cancel`: B becomes `attending` and an email "A place is available: ..." goes out. `EventRegistrationService::cancel` → `promoteFromWaitlist` never checks the event status.

**F131 Medium. Two events in one club can share a slug, and the public page reaches only one.**
Create `ev-adm-pub` twice (both 302, no error), publish both: `GET /site/lodge-of-fraternity/events/ev-adm-pub` always returns the first; booking via that URL books the first. The title-derived slug does this by default (two "Annual Dinner" events). `store()` has no uniqueness rule; `PublicEventController::findEvent` uses `firstOrFail()`. Duplicate adds a random suffix, so only the form is affected.

**F132 Medium. Organiser member search is case-sensitive on PostgreSQL.**
`GET .../admin/events/{id}/member-search?q=Alice` finds "Alice Archer"; `q=alice` and `q=CAROL` return `[]`. `whereRaw("users.name LIKE ? ESCAPE '!'")` is case-insensitive only on SQLite, which is why the tests pass. Use `ILIKE` or `lower()` on pgsql.

**F133 Medium. An organiser can book the same visitor email twice.**
`POST .../admin/events/{id}/registrations {"contact_email":"x@example.com", attendees:[...]}` twice: two registrations, both holding places and both charged. Only a member id is checked for duplicates in `saveByOrganiser`. A visitor can also be booked with a member's email.

**F134 Medium. A fully refunded booking is a dead end.**
Booking £70 paid then £70 refunded (status `refunded`): mark paid → "nothing left to pay"; take back → "no payment to take back"; waive → "Take the payment back or refund it before waiving"; a price-changing edit → "Adjust the payment first". Each message sends the organiser to an action that is refused. Only cancel works. Cause: `amount_paid` is not reduced by refunds and the guards test `amount_paid > 0`.

**F135 Medium. On the bookings page the "Dining" count and tab are always 0 and dining people show "No dining".**
`resources/js/Pages/Admin/Events/Subscribers.vue` (lines 63, 73, 279) reads `sub.has_food_choice`, which `EventRegistrationAdminController::subscribers` never sends (it sends `attending_dining` and `menu_selections`). A booking with Pate/Veg/Fruit and `attending_dining=true` shows "No dining | Edit meal" and the stat card "Dining 0".

**F136 Medium. The headline counts on the bookings page are people rows, not bookings or places.**
"Booked 17" when 11 places are taken: it counts waitlisted and cancelled attendee rows; "Paid" and "Awaiting payment" count one per person, so a 4-person booking counts 4 times. `places_taken` is in the payload but unused (same file, `stats`).

**F137 Medium. Capacity can be lowered below the places already held, with no warning** (then F126 demotes any edited booking). 4 → 2 with 5 people holding places was accepted silently.

**F138 Medium. Re-saving an event resets promo usage so a limited code can be reused.**
Event with promo `PAYTEN` max_uses 1, used once (a second booking gets 422). Save the event form again (any change): `uses_count` is 0 and the same code is accepted for another member. `EventDetailsSync::promos()` deletes and recreates every promo (and forces `discount_type` to percent); it should update by code like tiers and dishes.

**F139 Medium. Promo rows are not validated.**
`StoreEventRequest` has only `'promos' => 'array'`. `promos:[{"code":"BAD1","discount_amount":"abc","max_uses":5}]` → HTTP 500 (`22P02 invalid input syntax for type numeric`); codes `DUP` and `dup` → 500 (unique key). Accepted silently: discount -50, discount 1000%, `max_uses` -3 (which means unlimited), a 100-character code, `<B>X</B>`. Pricing stays safe (discount clamped to 0..subtotal).

**F140 Medium. An event that has already started stays bookable.**
Event `upcoming` with `starts_at` two weeks ago and no deadline: `POST /site/lodge-of-fraternity/events/{slug}/register` → 302, registration and "Your booking" email; the page shows `canBookAsGuest: true`. `EventRegistrationService::assertOpen` only refuses draft, cancelled, completed, not-yet-open and `is_booking_closed` (which needs `rsvp_deadline` or `booking_cutoff_days`); nothing sets status `completed`. Members too.

**F141 Medium. A visitor who cancelled cannot book the same event again.**
Book as `visitor@example.com`, cancel via the manage link, `POST .../register` again with the same email: 302 "Thank you. If the details are right, we have emailed you a private link", no new registration, and an email for the cancelled booking. The lookup in `PublicEventController::register` does not exclude `cancelled` (members got this fixed in F21).

**F142 Low to Medium. Anyone who knows a visitor's email can invalidate that visitor's manage link.**
Re-submitting the form with the email issues a new token (`token_hash` replaced) and mails link B; link A then shows "expired", and the new form's details are ignored. Limited only by the throttle.

**F143 Medium (usability). Quote and register share one 6-per-minute bucket per IP.**
Six `POST .../events/{slug}/quote` calls leave 5,4,3,2,1,0 remaining; the seventh is 429, and the register call is then 429 too (limiter `public-forms`, key IP only, `AppServiceProvider` line 95). The booking screen quotes on every change of guests, tier, dining or promo, so a booker can use the bucket up before pressing Book; a lodge's shared wifi hits it sooner. Use a much higher limit for the read-only quote.

**F144 Medium. Calendar feeds label local times as UTC.**
An event stored as `2027-07-15 19:30:00` shows 19:30 on the site but the feed has `DTSTART:20270715T193000Z`; in British Summer Time subscribers see it an hour late. The same in `/lodges/{slug}/calendar.ics` and the members feed. `IcsCalendar` uses `gmdate(...\Z)` on the stored value (lines 24-26) and the club timezone setting (`Europe/London`) is not used.

**F145 Medium. A second meeting on an existing date gives HTTP 500.**
`POST /lodge-of-fraternity/admin/meetings` with an existing `meeting_date`, or editing a meeting onto a taken date: 500 `SQLSTATE[23505] meetings_club_id_meeting_date_unique`. Duplicating a meeting twice also 500s because the copy always goes to the original date +1 month. Add a unique rule to `StoreMeetingRequest` and a free-date search or message in `duplicate()`. Two meetings on one day are impossible by this constraint.

**F146 Medium. Season generator: 500 on bad input, orphan rules, misleading message.**
`active_months[]=13`, `0`, `abc`, `-1`, or `starts_at` of `garbage`, `25:99`: HTTP 500; the `RecurringRule` is created before dates are computed and there is no transaction, so each failure leaves an orphan rule (7 of 14 rules). A second identical run reports "Batch generated 12 season meetings" while creating 0 (the counter increments for existing meetings) and adds another rule row. Add `active_months.*` integer 1..12 and `starts_at` `date_format:H:i`. Dates themselves were all correct (60 checked).

**F147 Medium. Dining payment "paid" without a method gives HTTP 500.**
`POST .../admin/meetings/{id}/dining/payment` with `kind=member`, `diner_id=<rsvp>`, `payment_status=paid` and no `payment_method`: 500 `Undefined array key "payment_method"` (`MeetingDiningController` about line 115). Twice. A guest who is not dining or a meeting-only member can also be marked paid.

**F148 Medium. The meeting dashboard headcount is wrong in three ways.**
(a) It counts guests of members who then sent apologies (`MeetingAdminController::show` loops all RSVPs): dashboard dining 4, guests 4, attending 7 while the dining page lists 3 heads. (b) "Awaiting" is `subscribingMembers - memberRsvps`, which ignores the "pending" rows made at publish (showed 35 with 25 pending rows). (c) A visitor's home lodge is blank because `Club::users()` `withPivot` lacks `home_club_name` and `home_club_number`. Also the dining page charges a visitor the member fee and does not mark visitors.

**F149 Medium. Setting a payment status creates an attendance.**
`POST .../admin/meetings/{id}/rsvp-payment-status` for a member with no RSVP (status waived) creates a `meeting_rsvps` row `attending_dining`, so the person appears on the dining list and caterer count. It also works on a past member and on an apologies RSVP.

**F150 Medium. Private RSVP links die after other actions; editing a meeting resets the cutoff.**
`MemberRsvpController::updateMeetingRsvp` and `MeetingDiningController::updateRsvp` overwrite `token_hash` with `Str::random(40)`: after the member saves in the portal (or an admin edits their RSVP) the emailed link shows "Expired". `MeetingAdminController::store` sets `rsvp_cutoff_at = meeting_date - 5 days` on every save, reopening a passed cutoff and discarding a custom one; moving the date does not move `token_expires_at`, so links expire the day before the new date.

**F151 Medium. Visitor registration sends no email.**
`POST /{slug}/visitor-register` creates a user with a random password and no verification and says "You will receive meeting summonses and RSVP invites via email". Nothing is sent (no confirmation, no password link), `publishSummons` skips role `visitor`, and the admin page still shows `summons_sent: true`. Anyone can register someone else's email. Existing members and duplicates get the same success message though nothing changed.

**F152 Medium. Visit requests are for any date, not for a meeting.**
`POST /lodges/{slug}/visits` accepts any date within a year (2027-01-01 had no meeting). Accepting creates no RSVP, so accepted visitors never reach the headcount or dining list; they only get the "looks forward to your visit" email.

**F153 Low to Medium. Apologies are blocked after the RSVP cutoff.**
After `rsvp_cutoff_at` the portal, quick-rsvp and the private link refuse every reply including `apologies` ("The dining deadline has passed. Please contact the Secretary directly."). A member who said attending cannot withdraw alone, and the text calls it a dining deadline.

### Low

**F154 Low. Quote endpoints return HTTP 500 when no attendee entry carries a validated key.**
`POST /site/lodge-of-fraternity/events/{slug}/quote` (and `/members/{slug}/events/{id}/quote`, admin quote) with `{"attendees":[{}]}` or `{"attendees":[{"name":"x"}]}`: 500 `Undefined array key "attendees"` at `EventQuoteController.php:60`. Reproduced in four runs. `validated()` drops `attendees` when the entries have none of the nested rule keys (the trap in CLAUDE.md). Add `'attendees.*' => 'array'`. The booking screen always sends keys.

**F155 Low to Medium. Officer election.**
`status=installed` is accepted from the request; resigned members and a `masonic_year` of "abc" are accepted; unknown office codes are skipped with a success message; `AnnualOfficerRosterService::validateAssignments` uses an unscoped `Member::find`, so assigning another club's member to two progressive offices answers with that member's name; saving any roster rewrites `club_user.committee_role` for the whole club; double confirm is accepted silently.

**F156 Low. Deleting a meeting keeps its ledger entry and bill** (source id points at nothing), and gives no warning about a published meeting with RSVPs.

**F157 Low to Medium. Cancel event does nothing for the people booked.** No email to bookers (the flash says "so people can be told" but there is no tool), and on a cancelled event an organiser can still add bookings and walk-ins, and mark a cancelled booking paid.

**F158 Low. Check-in.** A direct `POST .../checkin` accepts cancelled and waitlisted attendees (and then locks the booking for editing); a second `checkin` overwrites `checked_in_at`.

**F159 Low. CSV export has no UTF-8 BOM** (Excel shows "ZoÃ«"); organiser-added visitors and walk-ins are typed "Member" in the Type column.

**F160 Low. `GET .../member-search?q[]=a` gives HTTP 500** ("Array to string conversion", `EventOrganiserBookingController.php:34`). Use `$request->string('q')`.

**F161 Low to Medium. Editing a booking that predates the payment options forces a payment method.** After the lodge switches options on, `PUT` of a paid, method-less booking returns "Please choose how you will pay."; bank (10% discount) or on-the-night (£2.50 fee) are refused as price changes; only pay later works and stamps a method on an already-paid booking. `PUT` on a cancelled booking with room also revives it to attending without email.

**F162 Low. `mark_paid.received_on` accepts a future date** (2031-01-01 stored as `paid_at`).

**F163 Low. The live quote refuses the promo a booking already holds once the code is used up** (422 "That promo code is not valid."), while re-saving that booking with the same code succeeds.

**F164 Low. Wrong message when a fee is refused on a new option.** `POST .../admin/payment-options {"type":"bank_transfer","label":"bankfee","default_adjustment_kind":"fee",...}` → 422 "A fee can't be added to . Card and bank payments can only be discounted." (`assertAllowed()` runs before `fill()`).

**F165 Low. No format checks on bank details or percentage limits.** `sort_code:"not a code!!"`, `iban:"NOTANIBAN <b>x</b>"`, `account_number:"abc"` are saved and go to payers (escaped in email); a default discount of 150% and a fee of 5000% on "on the night" are accepted (a £33.33 ticket then quotes £1,699.83).

**F166 Low. Admin RSVP details.** Non-dining guests get the full guest fee (30.00) on the admin path (the public path stores 0.00); guest names are stored untrimmed and unescaped (rendered escaped); admin may RSVP a `past` member.

**F167 Low. Visitor access and visits.** Approve, decline and revoke of visitor access send no email (in-app only); withdrawing an accepted visit or an approved access returns the success message but does nothing.

**F168 Info / Low. Public booking details.** The quote accepts a foreign ticket tier id (prices at default) that register refuses; the "A place is available" email to a promoted visitor has no manage link and the booker's guests get no email; unknown manage tokens return 200 "expired" rather than 404 (no leak); cancelling via the manage link redirects with `back()`, so lands on `/` with no Referer; the honeypot error wording tells a bot why. The booking emails do not itemise the method fee/discount or the promo.

**F169 Info (decision). A user with an active `club_user` row whose Member row is `resigned` can still book events and RSVP to meetings**, because the app keys on `club_user.status`. Matches CLAUDE.md; needs a decision.

## Side effects of the test run (other sessions: please read)

- **Roster 14 (masonic year 2030-2031) was overwritten and restored** by the meetings agent (it used the same year for officer tests). Assignments restored to the four originals (wm 51, sw 52, secretary 50, treasurer 53), status confirmed, `meeting_id` null, `confirmed_at` 22:11:31, notes null, `updated_at` changed. Saving a roster rewrites `club_user.committee_role` club-wide (F155): the `member`-role values may have been cleared; only chair and secretary rows remain. Odd results around 22:13 to 22:17 on that roster came from this.
- Between about 22:00 and 22:25 UTC the payments agent had payment options switched on for club 1, so new bookings by anyone then could have been given a payment method and due date. Club 1 now has 0 `ClubPaymentMethod` and 0 `EventPaymentMethod` rows, as at the start (snapshot in `pay-ORIGINAL-state.json`).
- Leftovers: user `ev.pay.4@e2e.test` (id 103, no club membership; cannot be deleted because it is `created_by` on ledger rows); journal entries 196 and 220 (a £20 test payment and its reversal, net zero); `member.a4` now has a `calendar_token`; Mailpit messages to `ev.*`, `visitor.pub.*` and `ev.mtg.*` addresses; id-sequence gaps in events, registrations, meetings and journal tables. Everything else with an `ev-`, `E2E Ev` or `ev.` prefix was deleted and checked.
- Each agent reported `bath-rfc` settings lost `onboarding_dismissed` after the permission-matrix test was restored; the permissions agent restored its own override exactly, so something else changed it afterwards.

## Not run

Lodge's own domain rewriting; card, PayPal and Stripe Connect payments and refunds (no keys, flag off); bank reconciliation matching of event payments; promo codes in organiser bookings; changing an event's date or price after bookings (reviewed in code: nothing re-sent, totals stay); events in the past or cancelled for "offer on upcoming events"; browser screenshots of payment options, booking modal, meeting screens and the public booking page (the browser pane renders blank while `public/hot` exists; props, emails and page text were checked over HTTP, plus one Chrome pass over the event admin screens); the full coach (Secretary) meetings sweep after the status flip (spot-checked); PDF text content; invitation-token acceptance with a leaked token; superadmin POSTs.
