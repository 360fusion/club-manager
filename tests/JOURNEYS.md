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

## On-screen run (before each app release)

On one phone or simulator at a time (this Mac runs short of memory with two), signed in as a member with a booking,
in this order: A1, A2, A4, A5, A8, then as an organiser B2, B5 (scan the ticket on a second screen, or open its door
link), B7, then D1, D2, D5 and D4. Take a screenshot at each step. D3 needs Stripe test keys in the lodge's payment
options and `EVENTS_ONLINE_PAYMENTS=true`.
