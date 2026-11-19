# Club Manager: App Store and Google Play listing

Draft text and answers for both stores. Check the privacy answers against how the live site is actually set up before
submitting: they describe this codebase as of 19 Nov 2026.

## Names and short text

| Field | Limit | Text |
|---|---|---|
| App name | 30 | Club Manager |
| Subtitle (Apple) | 30 | Your lodge, in your pocket |
| Short description (Google) | 80 | Summonses, dining bookings and tickets for your lodge or club, all in one place. |
| Promotional text (Apple, can change without review) | 170 | Book the festive board, show your ticket at the door and never miss a summons. New: a QR ticket for every dinner booking. |

**Keywords (Apple, 100 characters, commas, no spaces):**
`lodge,masonic,freemason,summons,festive board,dining,club,members,tickets,events,secretary,calendar`

**Category:** Lifestyle (primary), Social Networking (secondary). Google Play: Lifestyle.

## Description (both stores)

Club Manager is the members' app for lodges and clubs that run on Club Manager.

For members:
- See every summons and meeting across your lodges in one calendar, and add them to your phone's calendar.
- Book dinners and events, choose your courses, add guests, and pay by card, bank transfer or on the night.
- Show your ticket at the door: each booking has a QR code the organiser scans to check your party in.
- Get a notification when a summons is issued, a new event opens, a payment is due, or your dinner is tomorrow.
- Read your lodge's news and notices, and keep your own details up to date.
- Lock the app with Face ID or your fingerprint if you wish.

For secretaries, treasurers and organisers:
- Publish events, see who has booked and what they are eating, and check guests in at the door by scanning their tickets.
- Everything you manage on the Club Manager website is available in the app, under the same sign-in.

Club Manager is for members of lodges and clubs that use it. Your lodge or club gives you your account.

## What's new (first release)

First release: summonses, calendar, dinner bookings with QR tickets, notifications and an optional app lock.

## App Review notes (Apple)

- A reviewer needs a working sign-in. Before submitting, create a demo member on the live site (not the local seed
  account) in a demo lodge with an upcoming event, and give its email and password in App Store Connect's sign-in
  section. Remove any two-factor sign-in on that account.
- Paying for dinners is for real-world services (a meal at a lodge), so it uses Stripe or PayPal, not in-app purchase
  (App Review Guideline 3.1.3(e)). No digital goods or subscriptions are sold in the app.
- Account deletion: avatar menu, Data and privacy, Close my account (Guideline 5.1.1(v)).
- Native features: push notifications, Face ID or Touch ID app lock, Add to calendar, QR tickets, and links that
  open the app after paying.

## Apple privacy answers ("App Privacy")

Tracking: **No.** The app shows no ads, has no third-party analytics or tracking code, and shares nothing with data
brokers.

Data collected, all **linked to the user**, none used for tracking, used for **App functionality** (and Customer
support where noted):

| Apple category | What | Why |
|---|---|---|
| Contact info: name, email address, phone number, physical address | Account and member record kept by the lodge | App functionality |
| User content: photos | Profile photo, if uploaded | App functionality |
| User content: other | Dietary requirements and guests' names on bookings, messages to the lodge | App functionality, customer support |
| Purchases: purchase history | Event bookings and what was paid | App functionality |
| Identifiers: user ID | The account id | App functionality |
| Sensitive info | Masonic rank and membership (may reveal beliefs), dietary notes (may reveal health or religion) | App functionality. **Decide whether to declare this; declaring it is the safer choice.** |

Not collected by the app: card numbers (Stripe or PayPal take them on their own pages), location, contacts, browsing
history, diagnostics or usage analytics, advertising data. The push token stays on our server only to deliver
notifications.

## Google Play "Data safety"

- Data is **encrypted in transit:** yes (HTTPS only in release builds).
- Users **can request deletion:** yes, in the app (Data and privacy) and on the website.
- **Shared with third parties:** payment details go to Stripe or PayPal when the member chooses to pay online
  (declared as shared for payment processing). Nothing else is shared.
- Collected: Personal info (name, email, phone, address), Financial info (purchase history), Photos (optional),
  Messages (optional), App activity: other user-generated content (bookings, dietary notes). Purpose: App
  functionality, Account management.
- Optional: profile photo, phone, address, dietary notes. Required: name and email.

## Age rating

No objectionable content, no unrestricted web browsing (the app only opens the Club Manager site; other links open
in the browser), no gambling, no user-to-user chat. Apple: 4+. Google (IARC): Everyone.

## Screenshots needed

- iPhone 6.9" (1320 × 2868) and 6.5" (1284 × 2778): at least 3 each. Suggested: Calendar, an event with menu
  choices, the ticket QR, Notifications, the Lodge page.
- **iPad (13"):** the iOS project supports iPad (`TARGETED_DEVICE_FAMILY = "1,2"`), so App Store Connect will ask
  for iPad screenshots too. Make it iPhone-only (`"1"`) if you don't want an iPad version yet.
- Google Play: at least 2 phone screenshots (1080 × 1920 or larger), a 512 × 512 icon and a 1024 × 500 feature
  graphic.

Take screenshots on the live site with demo data, not the local seed data, so no real member names appear.
