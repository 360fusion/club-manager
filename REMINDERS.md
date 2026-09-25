# Reminders

Things to do outside the code, to tick off later. Update this file when something changes.

## Server (staging / production)

- [ ] Add the scheduler cron: `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1` (event payment reminders run daily at 09:00).
- [ ] Set up mail: a real `MAIL_MAILER`, `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`. Sign-up email confirmation only starts once a real driver is set.
- [ ] Run `php artisan migrate` (adds guest emails, PayPal columns and the editable event email templates).
- [ ] After migrating, open Superadmin, Email templates, and check the event templates read the way you want.

## Email sending (needed for newsletters and all emails now)

Emails are now sent from a **queue**, so a worker must be running or nothing is delivered (booking confirmations, invitations, newsletters, receipts).

- [ ] **Create the queue worker** (a background process that stays running and sends the queued emails). Nothing is delivered without it.
  - [ ] On Forge: Server, Daemons, New Daemon. Command: `php artisan queue:work --queue=transactional,default,bulk --tries=3 --max-time=3600`. Directory: the site's root (the folder containing `artisan`). User: the site user. Processes: 1 to start (add more if newsletters are slow).
  - [ ] Without Forge: create a Supervisor program with the same command (`autostart=true`, `autorestart=true`, `stopwaitsecs=3600`), then `supervisorctl reread && supervisorctl update`.
  - [ ] Add `php artisan queue:restart` to the deploy script, so the worker picks up new code after every deploy.
  - [ ] Confirm `QUEUE_CONNECTION=database` in the server's `.env` (or `redis` if you move to Redis later).
  - [ ] Check it is running: send a booking confirmation or a "Send test to me" newsletter and see it arrive within a minute. If it does not, run `php artisan queue:work --once` on the server and check `storage/logs/laravel.log`, and look at the `failed_jobs` table.
  - [ ] Keep an eye on failures: `php artisan queue:failed` lists them and `php artisan queue:retry all` retries them.
  - [ ] Do the same for **every environment** that sends email: staging as well as production.
- [ ] Choose the mail provider (SendGrid, SES, Postmark or Mailgun) and set `MAIL_MAILER`, its credentials, `MAIL_FROM_ADDRESS` (an address on your sending domain) and `MAIL_FROM_NAME` in `.env`. SendGrid works over SMTP (`MAIL_MAILER=smtp`, host `smtp.sendgrid.net`, user `apikey`, password = the API key).
- [ ] Authenticate the sending domain with the provider once (SPF, DKIM and a DMARC record). Lodges then appear as the display name with their own reply-to, so lodges need no DNS work.
- [ ] Optional: if the provider limits sending speed, set `NEWSLETTER_PER_SECOND` (0 = no limit).
- [ ] Test: send a newsletter to yourself with "Send test to me", then a real one to yourself and a second address, click Unsubscribe in one, and send again to confirm it is skipped.
- [ ] Later: SendGrid's event webhook (bounces, complaints, opens, clicks) can feed a suppression list and reports; not built yet.
- [ ] Consider a policy for visitors' consent: they must actively subscribe, and every newsletter carries an unsubscribe link.

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

## Custom domains for lodge websites (serving and SSL): the DNS check is built, serving the site is not

Today a lodge can save a domain, is shown the DNS record to add and can press Check Now (`App\Support\ClubDomain`, guide in `resources/js/Components/DomainSetupGuide.vue`). "Active" only means the DNS record was found: `IdentifyTenantByDomain` is not registered, so the site is **not** served on the domain, and no certificate is issued. Decision: **Caddy with on-demand TLS on the Ubuntu droplet** (DigitalOcean, DNS on Cloudflare). Cloudflare for SaaS is the alternative if this grows to hundreds of domains or needs a CDN.

Still to build in the app:

- [ ] Endpoint `GET /internal/domain-allowed?domain=…` that returns 200 only for a domain that is `Active` (verified), and is reachable only from the server itself (Caddy calls it before issuing a certificate, so nobody can make the server request certificates for arbitrary domains).
- [ ] Register `IdentifyTenantByDomain` and use its result: on a verified custom domain, `/` and `/{page}` show that lodge's site, plus news articles (`/news/{article}`), events, `sitemap.xml`, `robots.txt` and the calendar feed. A Pending domain must never serve the site.
- [ ] Redirect `/site/{slug}` (and its pages) to the custom domain once it is Active, so there is one canonical address; canonical URLs and the sitemap use it too.
- [ ] Emails and payment return links use the lodge's custom domain where it makes sense (today they use `APP_URL`); Stripe and PayPal webhooks can stay on the main address.
- [ ] Set `services.club_domain.target` in `config/services.php` to the real target hostname (the code currently falls back to `manager.360fusionhosting.co.uk`).
- [ ] Tests: pending domain not served and not approved for a certificate; active domain serves its own lodge only (never another lodge's pages); the allowed-domain endpoint refuses outside callers; redirects.
- [ ] Update the in-app guide's wording once serving works (it currently only promises that the DNS record is seen).

Server setup (outside the code):

- [ ] Install Caddy and PHP-FPM on the droplet; open ports 80 and 443 in the DigitalOcean firewall and `ufw`.
- [ ] Caddyfile: a global `on_demand_tls { ask http://127.0.0.1/internal/domain-allowed }`, then a catch-all `https://` block with `tls { on_demand }`, `root * /var/www/<app>/public`, `php_fastcgi unix//run/php/php8.4-fpm.sock`, `file_server`, `encode zstd gzip`. The main domain gets its own normal site block.
- [ ] `.env`: `TRUSTED_PROXIES` correct for the setup and `SESSION_SECURE_COOKIE=true`.
- [ ] Cloudflare DNS: the target hostname lodges point at (`manager.360fusionhosting.co.uk` today) must be **DNS only (grey cloud)**, not proxied. If proxied, lodges' CNAMEs hit Cloudflare (error 1014) and the A-record comparison in the DNS check fails. The main domain itself may stay proxied.
- [ ] Test end to end with a spare domain: add the CNAME, press Check Now (Active), open the domain over https (certificate issued on first visit), check redirects from `/site/<slug>`, and check a member can log in on that domain (sessions are per domain).

## Later clean-up

- [ ] Drop the unused `event_user` table once the new event pages are verified.
