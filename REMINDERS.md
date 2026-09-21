# Reminders

Things to do outside the code, to tick off later. Update this file when something changes.

## Server (staging / production)

- [ ] Add the scheduler cron: `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1` (event payment reminders run daily at 09:00).
- [ ] Set up mail: a real `MAIL_MAILER`, `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`. Sign-up email confirmation only starts once a real driver is set.
- [ ] Run `php artisan migrate` (adds guest emails, PayPal columns and the editable event email templates).
- [ ] After migrating, open Superadmin, Email templates, and check the event templates read the way you want.

## Card payments (Stripe, lodge's own keys)

- [ ] In Stripe test mode: save the test secret key and webhook secret on a card option (Payment options page).
- [ ] Run `stripe listen --forward-to <site>/webhooks/stripe/<club id>`, make one test booking and pay it, and check it shows Paid with the history entry.
- [ ] Check Apple Pay and Google Pay appear on the Stripe Checkout page in test mode (no lodge account is needed; wallets just need to be on in the Stripe dashboard).
- [ ] Set `EVENTS_ONLINE_PAYMENTS=true` once the above works.

## PayPal

- [ ] Create a PayPal sandbox REST app, copy the client ID and secret, add a webhook (Payment capture completed) and copy its webhook ID.
- [ ] Save them on a PayPal option (mode: Sandbox), make one test booking and pay it, then refund it from the booking.
- [ ] Switch the option to Live with live credentials.
- [ ] Optional: a simple manual "PayPal by email or PayPal.me link" option (organiser ticks people paid) if lodges don't want the full setup.

## Platform-collected payments (Stripe Connect): built, needs setup before it can be switched on

Lodges see "Connect with Stripe" on their card option once these are done. Until then they only see the paste-your-keys option.

- [ ] Enable Stripe Connect on the platform's Stripe account and complete the platform profile (branding, support details).
- [ ] In Stripe, Connect settings, OAuth: turn on OAuth for Standard accounts, copy the client ID (`ca_...`) and add the redirect URI `https://<your-site>/members/payments/stripe-callback`.
- [ ] Add a Connect webhook (listening to **events on connected accounts**) pointing at `https://<your-site>/webhooks/stripe-connect` with `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `account.updated` and `account.application.deauthorized`; copy its signing secret.
- [ ] Set in `.env`: `EVENTS_PLATFORM_PAYMENTS=true`, `PLATFORM_STRIPE_SECRET` (or `STRIPE_SECRET`), `STRIPE_CONNECT_CLIENT_ID`, `STRIPE_CONNECT_WEBHOOK_SECRET`, and the commission `PLATFORM_COMMISSION_PERCENT`, `PLATFORM_COMMISSION_FIXED`, `PLATFORM_COMMISSION_MIN`, `PLATFORM_COMMISSION_MAX`. `EVENTS_ONLINE_PAYMENTS=true` is still needed too.
- [ ] Write the terms between the platform and lodges (fee, chargebacks, refunds, payout timing) and set `PLATFORM_PAYMENT_TERMS_URL` so lodges can read them before they accept.
- [ ] Ask an accountant about VAT/tax on the commission.
- [ ] Test in Stripe test mode with `stripe listen --forward-connect-to <site>/webhooks/stripe-connect`: connect an existing test account, make a booking, pay it, check the booking is Paid and the commission shows on Superadmin, Platform Payments, then refund it.
- [ ] Test "create one for me" (Express) end to end. Payments on Express accounts are created as direct charges; confirm this works in test mode before going live, and tell me if Stripe refuses it (the fix is small).
- [ ] Check which lodge countries Stripe Connect supports (South Africa and India lodges probably keep their own keys), and that each lodge's currency matches its Stripe account.
- [ ] Not built yet, decide later: hold-then-pay-out mode (we hold the money and pay lodges after the event), lodge payout statements, and ledger lines for the commission. Hold mode needs your accountant's and Stripe's agreement first.

## Later clean-up

- [ ] Drop the unused `event_user` table once the new event pages are verified.
