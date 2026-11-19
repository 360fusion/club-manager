# User journeys: test plan for the member area and the app

What a real person does, start to finish, and how each step is checked. Each journey has an automated test that runs
the whole story in one go (`tests/Feature/Journeys`, run with `php artisan test --compact tests/Feature/Journeys`) and
a short on-screen run for a phone, done before each app release.

**Pass** means every step's expected result holds. A step marked *device* can only be seen on a phone or simulator.

## A. Member: from the summons to the festive board

| # | Step | Expected | Checked by |
|---|---|---|---|
| A1 | Opens the app for the first time and signs in | Member area dashboard, bottom tabs, navy strip behind the clock | device |
| A2 | Allows notifications | The phone is saved against the member (`push_devices`) | test, device |
| A3 | The organiser publishes a dinner (journey B2) | "New event" in the inbox and a push to the phone | test |
| A4 | Opens Bookings, the dinner, books for self and a guest with three courses each, pays by bank transfer | Booking holds two places; bank details and a payment reference shown; the guest count and meals are right | test, device |
| A5 | Taps Add to calendar | iPhone: calendar file opens in the browser with "Add to Calendar"; Android: the calendar app's new event, at lodge time | test (file), device |
| A6 | The treasurer records the transfer (journey C3) | The booking shows Paid | test |
| A7 | The day before, at 9am lodge time | "Tomorrow: …" in the inbox and as a push, once only; tapping it opens the ticket | test, device (tap) |
| A8 | At the door, opens Show ticket | QR code, both names, meals, Paid | test, device |
| A9 | The organiser scans it (journey B5) | Both people checked in; the ticket and the events page show it | test |

## B. Organiser: setting up the dinner and running the door

| # | Step | Expected | Checked by |
|---|---|---|---|
| B1 | Creates the event as a draft with tickets and a menu | Saved as a draft; members see nothing and get nothing | test |
| B2 | Publishes it (from the app's admin pages too) | Members told once, in the inbox and by push for those with the app | test, device |
| B3 | Watches bookings come in | Guest list shows each person, their meals and payment status | test |
| B4 | Checks the catering numbers | Catering summary counts each dish across bookings | test |
| B5 | On the night, scans a member's ticket with the phone camera | Opens that booking's door page; Check in everyone marks the party arrived; a changed link is refused | test, device (camera) |
| B6 | Adds a walk-in | Added and checked in | test |
| B7 | Back to the member area from admin | "Member area" link in the app's admin bar | test, device |

## C. Treasurer: chasing payment

| # | Step | Expected | Checked by |
|---|---|---|---|
| C1 | A member books with pay later | Booking unpaid with a due date | test |
| C2 | The payment reminder runs near the due date | "Payment due" in the member's inbox and by push (cannot be switched off) | test |
| C3 | The treasurer records the payment | Booking paid; recorded in the payment log; the ticket shows Paid | test |

## D. The app itself

| # | Step | Expected | Checked by |
|---|---|---|---|
| D1 | App settings: switches off News | News still reaches the inbox but no longer pushes; payments still push | test, device |
| D2 | App settings: turns on the Face ID / fingerprint lock | Asks once; then asks on opening and after a minute away; the app switcher shows a cover | device |
| D3 | Pays by card from the app | The browser opens for Stripe; afterwards the app reopens on the events page | test (return page), device with Stripe test keys |
| D4 | Signs out | That phone stops getting pushes; other phones of the same person keep them | test |
| D5 | Opens a `clubmanager://` link | The app opens at that page; links to other sites are refused | test, device |

## E. A lodge meeting: replies, the Secretary's numbers and the festive board

Automated as one story in `tests/Feature/Journeys/MeetingJourneyTest.php`. Days are the lodge's settings (Settings,
Dining): ask 7 days before, numbers 3 days before (dining closes at midnight before), reminder 1 day before.

| # | Step | Expected | Checked by |
|---|---|---|---|
| E1 | The Secretary publishes the summons | Every member is emailed it with a private reply link | test |
| E2 | A member sends apologies from the summons email, with a reason | Recorded with the reason | test |
| E3 | A week before, 9am lodge time | "Will you be there?" by email and app to those who have not replied, once; each answer opens their reply page with it picked | test, device (email link) |
| E4 | A member books dining with a guest and pays by card or PayPal from the summons | Both places paid, recorded once from Stripe's or PayPal's confirmation | test, device with Stripe test keys |
| E5 | The day dining closes, 9am | Last call to whoever still has not replied | test |
| E6 | Dining has closed | The Secretary gets the numbers (dining, apologies with reasons, no reply, dietary, unpaid; brethren named with rank); the caterer gets the count, menu and dietary needs only | test |
| E7 | Late changes | Adding dining is refused (ask the Secretary); dropping it or sending apologies works; each change is emailed to the Secretary, and the caterer hears only when the count changes | test, device |
| E8 | A paid guest is taken off | Listed under "Paid but no longer dining"; the Secretary refunds it through the same provider (an owner or admin, as it needs the payments permission) | test |
| E9 | The day before, 9am | Reminder to those coming, with what they still owe and a link to change | test, device (push) |
| E10 | The Secretary prints the caterer sheet | Final count, menu, dietary needs and who is dining; PDF download | test, device |

## On-screen run (before each app release)

On one phone or simulator at a time (this Mac runs short of memory with two), signed in as a member with a booking,
in this order: A1, A2, A4, A5, A8, then as an organiser B2, B5 (scan the ticket on a second screen, or open its door
link), B7, then D1, D2, D5 and D4, then for a meeting E3 (open the emailed answer link), E7 (try adding dining after
the deadline) and E10. Take a screenshot at each step. D3 needs Stripe test keys in the lodge's payment
options and `EVENTS_ONLINE_PAYMENTS=true`.

## Run log

### 19 Nov 2026, iPhone 17 simulator (iOS 27), local dev server

| Step | Result |
|---|---|
| A1 sign in, tabs, navy strip | Pass |
| A4 book self and a guest (£70 total, bank reference shown) | Pass after fix: the page stayed zoomed in after typing |
| A5 Add to calendar | Pass after fix: an .ics address opened a calendar subscription instead of one event |
| A7 push "Tomorrow: …" with icon badge; tap opens the ticket | Pass |
| A8 ticket QR (decoded: the signed door link for that booking) | Pass |
| B5 door page from the scanned link; Check in everyone (2) | Pass |
| A9 the member's ticket then shows Checked in | Pass |
| B7 Member area link from admin | Pass |
| D1 switch off News (saved on the server) | Pass |
| D2 Face ID lock: on, locked at launch, wrong face stays locked, right face unlocks | Pass after two fixes: the lock never appeared, and a failed Face ID left it unlocked |
| D3 return from payment opens the app on the events page | Pass (return page only; no Stripe test keys) |
| D4 sign out removes this phone's push registration | Pass |
| Header | Fixed: the Admin button overlapped the lodge name |

Not run on screen: a real Stripe or PayPal payment, the camera scanning a ticket on a second device (the QR was
decoded from a screenshot instead).

### 19 Nov 2026, Android 15 emulator (WebView 124), local dev server

Run headless and read-only (`emulator -read-only -no-window`), so nothing is saved to the image. The headless
emulator's own screen stops redrawing parts of the page; screenshots came from the web view through its debugger.

| Step | Result |
|---|---|
| A1 sign in, tabs | Pass after fix: the status bar stayed white with dark icons above the navy bar |
| Bookings tab | Fixed: a booked dinner still offered "Can't go / Choose options"; it now says Booked, View booking |
| Events page, dashboard | Fixed: "Checked in at 2026-11-19T12:30:46+00:00" shown raw; now "Checked in 19 Nov 2026, 12:30" |
| A5 Add to calendar | Pass: opens the Google Calendar app (no Google account on the emulator, so not saved) |
| A8 ticket QR, names, checked in | Pass |
| B5 + D5 `clubmanager://` link to the signed door page | Pass; a changed link answers 403 |
| B7 Member area link from admin | Pass |
| D1 switch off News | Pass (saved on the server) |
| D2 fingerprint lock: on, locked at launch, unknown finger stays locked, right finger unlocks, open again within a minute, locked after 65 s | Pass after fix: signing in with a password then also asked for a fingerprint |
| D4 log out | Pass (push is not registered on Android locally, as there is no google-services.json) |
