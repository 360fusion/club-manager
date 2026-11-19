# Running the app on your own iPhone, and testing card payments

Two runs that need you: they use your Apple account and your Stripe account, which only you can sign in to.
Each takes about 15 minutes. Afterwards, run the on-screen journey in `tests/JOURNEYS.md` on the phone.

## 1. On your iPhone

**What you need:** your iPhone and its cable, and an Apple ID (a free one works for a 7-day test install; the paid
developer account is needed for push notifications and the App Store).

**The phone has to reach this Mac's local site.** `club-manager.test` only exists on this Mac, so pick one:

- **Herd's share link (simplest):** run `herd share` in the project. It prints a public https address for the local
  site while it runs. Put that address in `server.url` and its host in `allowNavigation`; no Info.plist change is
  needed, as it is https. Anyone with the link can reach your local site while it runs, so stop it afterwards.
- **Same Wi-Fi, no public link:** the phone can reach this Mac at `192.168.0.14` (today's address), but Herd only
  answers to `club-manager.test`, so the phone needs a local DNS entry for that name, which is more set-up. Use the
  share link unless the local site must stay private.

Then:

1. `npx cap sync ios`, then `open mobile/ios/App/App.xcodeproj`.
2. In Xcode, click **App** in the left list, then the **Signing & Capabilities** tab. Tick **Automatically manage
   signing** and choose your **Team** (your Apple ID). If it says the bundle identifier is taken, change
   `uk.clubmanager.app` to something of your own, such as `uk.clubmanager.app.ben`, for testing.
3. Plug in the iPhone, unlock it, and tap **Trust** if asked. Choose it at the top of Xcode next to **App**.
4. Press **Run** (the triangle). The first time, the phone refuses to open an app from an unknown developer: on the
   phone go to Settings, General, VPN & Device Management, tap your Apple ID and **Trust**.
5. Run the journey in `tests/JOURNEYS.md`. On a real phone you can also scan a ticket with a second phone's camera
   (B5) and try Face ID for real (D2).

Push notifications on a real phone need an APNs key from the paid developer account (see `REMINDERS.md`, iOS and
Android app). Without it everything else works; the phone just gets no pushes.

Put `capacitor.config.json` and `Info.plist` back before committing (`git checkout` both).

## 2. A card payment, start to finish

Stripe's test mode takes no real money: you pay with Stripe's published test card numbers.

1. Sign in to dashboard.stripe.com, switch on **Test mode** (top right), and open Developers, API keys. You need
   the **Publishable key** (`pk_test_…`) and the **Secret key** (`sk_test_…`). Only ever use test keys locally.
2. In `.env` set `EVENTS_ONLINE_PAYMENTS=true`, then `php artisan config:clear`.
3. In the app (or on the site) as the lodge admin: Admin, Events, Payment options, **Card**: paste both test keys
   and switch it on. It is then offered on upcoming events.
4. Stripe has to tell this Mac when a payment succeeds. Install the Stripe CLI (`brew install stripe/stripe-cli/stripe`),
   run `stripe login`, then
   `stripe listen --forward-to http://club-manager.test/webhooks/stripe/1` (1 is the demo lodge's id). It prints a signing secret
   (`whsec_…`); paste that as the webhook secret in the same Card settings.
5. As a member, book a dinner and choose **Card**. Stripe's page opens in the browser. Pay with card number
   `4242 4242 4242 4242`, any future date, any CVC and postcode.
6. Expected: the browser hands back to the app (D3), the booking shows **Paid**, and the payment appears in the
   event's payment history and the lodge's books. Also try `4000 0000 0000 9995` (declined): the booking stays
   unpaid and nothing is recorded.

Afterwards set `EVENTS_ONLINE_PAYMENTS` back if you don't want card payments locally, and stop `stripe listen`.
