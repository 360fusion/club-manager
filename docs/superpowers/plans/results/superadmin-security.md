# Superadmin screens and security pass (2 Nov 2026, e2e app)

Run against the e2e app (`php artisan serve --env=e2e`, PostgreSQL `club_manager_e2e`) with real HTTP requests (curl, valid CSRF, sessions) plus in-process requests where the shared dev server was slow. Five areas ran in parallel: `sa-dir` (directory screens), `sa-claims` (corrections, claims, platform payments, public directory), `iso` (cross-lodge isolation, Livewire tampering), `auth` (login, session, 2FA, CSRF, API), `io` (injection, uploads, SSRF, webhooks, headers). No application code was changed and nothing was committed. Detailed per-area notes (full request lists, evidence) are in the session notes folder `/Users/ben/Library/Application Support/Claude/side-session-notes/local_a0cb9e9d-7f9f-4548-b51f-6dbdacaed989/` (`sa-dir.md`, `sa-claims.md`, `iso.md`, `auth.md`, `io.md`).

**Test-run incident (restored).** The first `iso` route sweep sent write requests (kitchen-sink bodies, no id filter) as member.a3, the treasurer and member.a4 against clubs they legitimately administer, and changed shared e2e data: club 1 members set to `past`, a3's club 5 role, the treasurer's rows in clubs 4/6-9, a few email-template/redirect/tag/event rows, page previews/duplicates in clubs 4-9, clubs 1/3/4/7 renamed "sec-iso" (plus notifications and meetings that copied the name). All of it was restored row by row from a pre-sweep `pg_dump` (each row checked against the sweep's end state before writing) and the accounts were re-verified. Not touched: `onboarding_dismissed`/`vat`/`approval_threshold` default keys on clubs 2, 6, 8 (other sessions have since written there), and the treasurer now has two `club_user` rows in clubs 4, 6-9 (the restored originals plus new ones another session added). The `iso` pass was then re-run in two throwaway clubs. Other sessions saw broken club 1 access (member.a3, treasurer, coach) between about 21:50 and the restore; any failure they logged in that window may come from this.

## Results

| ID | Result | Note |
|---|---|---|
| SA-GL1 grand lodge create/edit validation | PASS | empty, long, bad URL, arrays, duplicate code: 422 |
| SA-GL2 URL schemes | FAIL (F156) | `ftp://`, `ssh://`, `gopher://`, `data://` accepted |
| SA-GL3 code uniqueness | FAIL (F157) | case-sensitive on PostgreSQL |
| SA-GL4 ranks page, US/AU pages | PASS | 200; bad input 422; raw HTML stored but escaped on output |
| SA-GL5 delete grand lodge in use | FAIL (F151) | not refused, children nulled |
| SA-PR1 province/district validation | PASS | 422; `code` not changeable on update |
| SA-PR2 province admins (add/remove/duplicate/non-user/other province) | PASS | all handled; no-op remove reports success (F166) |
| SA-PR3 delete province with club/hall/lodge/admin | FAIL (F151, F152) | deleted, references nulled, club currency flipped |
| SA-PR4 non-numeric ids in bodies/URLs | FAIL (F150) | 500 |
| SA-HL1 hall create/edit/delete, postcode | PASS | validation 422; postcode not normalised (info) |
| SA-HL2 geocoding | PASS | Nominatim reachable; bad/empty postcode handled |
| SA-LG1 lodge edit validation | PASS with F150 | |
| SA-LG2 meeting schedule parser | PASS | 12+ odd inputs (empty, HTML, unicode, 2000 chars, NUL, regex bait), no exception, max 38 ms |
| SA-LG3 sources / `lodges:check-sources` | PASS (info F167) | no web path writes sources; command fetches any stored URL |
| SA-LG4 `listing-checked`, `lodges:remind-secretaries` | PASS | permissions and throttle right; reminder run scoped (rolled-back transaction, fake notifications) |
| SA-LG5 managed lodge delete/link/unlink | PASS | refused with message |
| SA-CT1 club types | PASS with F158 | create/edit validated; modules/terminology unvalidated; no delete route |
| SA-US1 superadmin user management | BLOCKED | no user list/edit/delete screens exist; only `superadmin:grant` (no last-superadmin guard, info) |
| SA-AC1 access to all 50 `/superadmin` routes | PASS | guest 401; lodge admin, treasurer, coach, member, province admin 403; real HTTP re-check with CSRF on 8 routes |
| SA-AC2 `POST /auth/make-me-superadmin` | PASS (F159) | 404 in e2e (`isLocal()` false) |
| CL1 suggest-fix validation, URL schemes, contact details, duplicates, throttle, mass assignment | PASS | |
| CL2 `masonic_hall_id` = `abc` / `[1]` | FAIL (F150) | 500 |
| CL3 superadmin apply/reject, tampered `values`/`fields`, double apply, rejected-then-apply | PASS | only ticked fields change; slug/club_id/status untouched |
| CL4 notifications | PASS | visitor mail + lodge bell (lodges have no contact email), no private data, escaped |
| CL5 non-superadmin access | PASS | 403 |
| LC1 claim validation, mass assignment, duplicate, managed lodge | PASS | |
| LC2 approve/reject state machine, 3 parallel approvals | PASS | exactly one club and one owner |
| LC3 claim by unverified session | PASS (F164) | allowed, shown to superadmin |
| PP1 platform payments screen, per-lodge commission validation, tamper keys | PASS | no editable global setting (env/config) |
| PP2 CSV export | PASS | `=`, `+`, `-`, `@`, tab, CR neutralised, quoting right |
| PP3 secrets in props/HTML | PASS | none |
| PP4 non-numeric account id | FAIL (F150) | 500 |
| DIR1 public directory odd input (quotes, wildcards, huge page/per_page, unicode) | PASS | no 500 except invalid UTF-8 (F153); `%` wildcard (F164) |
| ISO1 foreign record ids and foreign slugs on every admin route (own slug+foreign id, foreign slug), as admin/treasurer/coach/member, plus a safe re-run in two throwaway clubs | PASS | 403/404/422 throughout; 0 writes outside the throwaway clubs; 500s F154 |
| ISO2 Livewire locked properties, `meeting.club_id`, snapshot swap between sessions | PASS | refused by Livewire / persistent admin middleware (403) |
| ISO3 Livewire method ids (bank reconciliation, committee, candidates, members, subscriptions) | FAIL (F141) | every id club-scoped except `assignTierToMember` `tierId` |
| ISO4 plain member and coach hitting admin-only actions | PASS | 403 |
| AU1 login throttle (5/min per email+IP), generic message | PASS | timing leaks (F160) |
| AU2 session fixation, logout invalidation, remember-me | PASS | |
| AU3 password reset token single use, wrong-user token, expiry, throttle | PASS | |
| AU4 reset/verification link host | FAIL (F140) | |
| AU5 email verification link tamper/expiry/other user | PASS | all 403 |
| AU6 2FA challenge bypass, wrong-code throttle, TOTP replay, recovery reuse | PASS | |
| AU7 2FA via invitation accept | FAIL (F142) | |
| AU8 disable 2FA / read secret needs password | FAIL (F145) | |
| AU9 other sessions after password change/reset | FAIL (F143) | |
| AU10 enumeration via forgot-password | FAIL (F144) | |
| AU11 CSRF on 264 state-changing web routes, none and wrong token | PASS | 528 requests, all 419; only `webhooks/*` exempt |
| AU12 API token create/throttle/2FA refused/revocation/expiry/malformed header/query token | PASS | array `email` 500 (F146) |
| AU13 token abilities, tokens after password change | FAIL (F143) | |
| AU14 API rate limit, CORS | FAIL (F161) | `/api/v1/user` unthrottled; `*` CORS |
| AU15 mass assignment on profile/registration, open redirects | PASS | |
| IO1 stored XSS in post/event/newsletter/page/member/contact fields, sanitizer (28 payloads), JSON-LD, meta tags, emails | PASS | |
| IO2 attachment URLs | FAIL (F148) | `javascript:` stored and rendered as `href` |
| IO3 sanitizer `class` | FAIL (F155) | |
| IO4 CSV export formula injection on all drivable exports | PASS | |
| IO5 uploads: SVG/HTML/PHP/polyglot/10 MB/6000 px/bomb header/EXIF/downscale/filename tricks/private disk | PASS | `.php.png` 500 (F154); other nits (F162) |
| IO6 open redirects | PASS | none |
| IO7 forged Host / X-Forwarded-* | FAIL (F140) / BLOCKED | Host trusted; X-Forwarded-* untestable (no trusted proxies in e2e) |
| IO8 SSRF | FAIL (F147) | summons PDF logo; no other server-side fetch of user URLs |
| IO9 mass assignment | PASS | |
| IO10 signed/tokenised URLs (booking, RSVP, sign, unsubscribe, invite, preview) | PASS | |
| IO11 webhooks unsigned/forged/malformed/huge/unknown club | PASS | no DB side effect; non-numeric club id 500 (F154) |
| IO12 GoCardless webhook club scoping | FAIL (F149) | |
| IO13 security headers, debug endpoints, sitemap/robots | PASS | API lacks headers (F161) |

## Findings (not fixed)

**F140 (High): Host header builds password-reset, verification and booking links.** No `trustHosts`, no `URL::forceRootUrl`. `GET /forgot-password` (keep cookie), then `POST /forgot-password` with `Host: evil.example`, `X-CSRF-TOKEN`, `email=<victim>`: the mail in Mailpit holds `http://evil.example/reset-password/<token>?email=...`. Signing in as an unverified user with the same Host gives `http://evil.example/email/verify/<id>/<hash>?expires=...&signature=...` (validly signed for the forged host). The public booking mail's private manage link (`POST /site/lodge-of-fraternity/events/{slug}/register` with the forged Host) and canonical, `og:url`, `sitemap.xml`, `calendar.ics` and the page preview link are forged too. Attacker needs only the victim's email; the click hands the token to the attacker (account takeover). Impact depends on the real web server passing unknown Hosts through. `X-Forwarded-Host` was ignored here because `TRUSTED_PROXIES` is unset. Expected: links from `config('app.url')` or the club's active domain, plus `trustHosts`.

**F141 (High): a club admin can attach another club's subscription tier to their own member (cross-tenant write and disclosure).** `SubscriptionIndex::assignTierToMember(int $memberId, ?int $tierId)` (`app/Domains/ClubAccounting/Livewire/Subscriptions/SubscriptionIndex.php`, line 170) scopes `$memberId` to the club but writes `$tierId` unchecked. Repro as an admin of club A: load `/{slugA}/admin/dues-subscriptions`, take the `wire:snapshot`, `POST /livewire-518b5e38/update` with `calls:[{"path":"","method":"assignTierToMember","params":[<own member id>, <club B tier id>]}]` returns 200 and `club_acc_members.subscription_tier_id` becomes club B's tier; `/{slugA}/admin/members/{id}` (finances tab) then renders club B's tier name and annual amount. Seen with two throwaway clubs. Expected: `SubscriptionTier::where('club_id', $club->id)->findOrFail($tierId)` first. All other id parameters across the Banking, Candidates, Committee, Members and Subscriptions components were club-scoped (read and tampered).

**F142 (Medium): invitation acceptance skips two-factor.** `POST /{club}/invite/{token}` with a valid token and an existing user's correct password calls `Auth::login` (`InvitationController::accept`); for a user with confirmed 2FA, `/members/dashboard` is then 200 with no challenge. Also bypasses the 5/min per-email login throttle (10/min per IP only).

**F143 (Medium): password change or reset does not end other sessions; API tokens survive.** Session B stays 200 on `/members/dashboard` after `PUT /members/profile/password` from session A and after a completed `POST /reset-password` (six checks over 30 s). A token issued earlier still returns 200 on `GET /api/v1/user` after both (30-day lifetime, no revoke endpoint). Also: tokens created with abilities `[]`, `["admin:everything"]`, `["*"]` all pass, since no route checks `abilities:`/`ability:`, so `profile:read` means nothing yet.

**F144 (Medium): `/forgot-password` reveals whether an account exists.** Existing: "We have emailed your password reset link."; unknown: "We can't find a user with that email address."; a repeat within 60 s: "Please wait before retrying." (`ForgotPasswordController::store`).

**F145 (Medium): 2FA can be disabled, recovery codes regenerated and the secret read with the session alone.** Fortify `confirmPassword` is false. `DELETE /members/security/disable` (and `DELETE /user/two-factor-authentication`), `POST /members/security/recovery-codes`, `GET /user/two-factor-secret-key`, `/user/two-factor-recovery-codes` and `/user/two-factor-qr-code` all succeed with just a logged-in session; the security page also carries the secret and codes in props. A hijacked session defeats 2FA permanently.

**F146 (Medium): array `email` gives 500 on login and API token creation.** `POST /login` with `email[]=a&password=x` (valid CSRF) and `POST /api/v1/tokens/create` with `{"email":["a"],"password":{"x":1},"device_name":"d"}`: "Array to string conversion" at `AppServiceProvider.php:90` and `:93` (limiter closures cast `input('email')` before validation). Unauthenticated. Expected 422.

**F147 (Medium): SSRF and local image read through the summons PDF logo.** `POST /lodge-of-fraternity/admin/meetings` with `front_page_logo=http://127.0.0.1:18777/ssrf-logo.png` (free string, max 1000, no scheme check; also `http://2130706433:18777/`), then `GET /lodge-of-fraternity/admin/meetings/{id}/pdf?download=1`: headless Chrome requested the URL from my listener. `file:///.../storage/app/public/23/ok.png` embedded the image in the PDF; `localtest.me` gave a 500. Any lodge admin with `manage_meetings` can probe internal hosts (blind unless the response is an image) and read local image files. Expected: own `/storage/` upload or public https only.

**F148 (Medium): `javascript:` attachment URLs on newsletters and posts.** `existing_attachments[]` is stored without checking `url`. Newsletter: `POST /{club}/admin/newsletters` with `existing_attachments=[{"name":"x","url":"javascript:alert(document.domain)","size":"1 KB","mime_type":"application/pdf"}]`; `GET /site/newsletter/{delivery token}` (public) renders `<a href="javascript:...">` (CSP is report-only). Post: same via `POST /{club}/admin/posts`; the member route `GET /members/lodge-of-fraternity/news/{id}` returns the URL and `Member/PostShow.vue:192` binds `:href` with `target="_blank"` (the public route blanks it). Needs a click by a more privileged member. Fix: scheme check on save and render.

**F149 (Medium): GoCardless webhook updates another club's mandates.** `GoCardlessWebhookController` verifies the HMAC with club 1's own (admin-typed) secret, then `DirectDebitMandate::where('gocardless_mandate_id', $id)->first()` with no `club_id` filter. Repro: club 1 bank account with secret `S`, a mandate `MDsecio1` in club 2; `POST /webhooks/gocardless/1` with `Webhook-Signature: hmac_sha256(body, S)` and `{"events":[{"resource_type":"mandates","action":"cancelled","links":{"mandate":"MDsecio1"}}]}` returned 200 and cancelled club 2's mandate. Needs the victim's mandate id. Add `->where('club_id', $clubId)`.

**F150 (Medium): non-numeric ids give 500 instead of 404/422 (PostgreSQL).** Rules `exists:` without `integer` and routes without `whereNumber`. Examples: `POST /superadmin/provinces` `{"grand_lodge_id":"abc"}` (same `province_id`, `masonic_hall_id`, `club_type_id`, `club_id` on halls, lodges, districts, `link-club`, claim approve); `PUT|DELETE|GET /superadmin/{grand-lodges,provinces,districts,masonic-halls,lodges,club-types}/abc`; `POST /superadmin/lodge-corrections/abc/apply|reject`; `POST /superadmin/lodge-claims/abc/reject`; `PUT /superadmin/platform-payments/abc`. Any logged-in user: `POST /lodges/{slug}/suggest-fix` with `{"masonic_hall_id":"abc"}` (bigint error) or `[1]` ("Array to string conversion" at `LodgeCorrectionService.php:165`), and the same two on the superadmin apply. Payment webhooks: `POST /webhooks/stripe/abc`, `/webhooks/paypal/abc`, `/webhooks/gocardless/abc` (TypeError on `int $clubId`). GoCardless also answers 404 for "not configured" and 403/498 for configured, which shows which club ids use it.

**F151 (Medium): deleting a grand lodge, province, district or hall that is in use is not refused.** `DELETE /superadmin/provinces/{id}` for a province with a club, a hall, a lodge and a province admin returns a success redirect; `clubs/masonic_halls/lodges.province_id` become NULL and the admins are removed. `DELETE /superadmin/grand-lodges/{id}` leaves its provinces and districts with NULL parent. No confirmation or count (halls are documented as intended). Suggest refusing when children exist, as the managed-lodge delete does.

**F152 (Medium): editing or deleting a province's country changes the currency of its clubs that never chose one.** `Club::currencyCode()` falls back to the province's country. Throwaway club without `settings.currency` in a throwaway province: GBP; `PUT /superadmin/provinces/{id}` with `country: Australia` gives AUD, `United States` gives USD, deleting the province gives GBP. The currency lock only covers clubs that saved a currency, so existing books show another currency with amounts unchanged.

**F153 (Medium): invalid UTF-8 in a request gives 500.** `GET /lodges?q=%C3%28` (also `q=%FF`, `province=%C3%28`, `order=%C3%28`): the filters are echoed into Inertia props and `JsonResponse` fails ("Malformed UTF-8 characters"). `POST /superadmin/masonic-halls` with `name=sec+%B1%FF` and `POST /superadmin/lodges` with `meets_text=...%B1`: PostgreSQL invalid byte sequence 500. Bots fill the error log.

**F154 (Low-Medium): other 500s.** `GET /members/notifications/1/open` (id column is a uuid; `SQLSTATE 22P02`). `POST /{club}/admin/users/{userId}/rank` with `{}` ("Undefined array key rank"). `PUT /user/password` and `PUT /user/profile-information` (Fortify actions not bound: "not instantiable"; unused routes that should be disabled). `POST /{club}/admin/events` with four 105-character address parts (each passes `max:255`, joined `location` is varchar 255, SQLSTATE 22001). `poly.php.png` as post `cover_image` or `new_attachments[]` (Spatie `FileNameNotAllowed`; only the media library and page downloads call `assertSafeUpload`). Withdrawn: the `pages/settings` and pack-pdf 500s from the unsafe sweep did not reproduce with sane bodies.

**F155 (Low-Medium): the HTML sanitizer keeps `class`, so a writer can cover the page.** Saved post content `<div class="fixed inset-0 z-50 bg-white">FAKE LOGIN</div>` comes back unchanged and is sent to members (those utilities exist in the built CSS); same for page text blocks and newsletters. Allow-list classes or drop `class`.

**F156 (Low): URL fields accept non-web schemes or internal targets.** Grand lodge, province, district and club type `website_url`/`twitter_url`/`facebook_url` accept `ftp://`, `ssh://`, `tel://`, `gopher://`, `data://` (halls and lodges restrict to `https?://`). `post_header` page block `back_url` / `cover_image_url` accept `javascript:` (`POST /lodge-of-fraternity/admin/pages`; the keys are missing from `URL_KEYS`). Profile `avatar_url` and post `cover_image_url` accept any external URL (tracking pixel). Lodge `website_url` accepts `http://[::1]/`.

**F157 (Low): unique `code` is case-sensitive on PostgreSQL.** `SEC-DIR-P` was accepted next to `sec-dir-p` (provinces, grand lodges, club types), and lookups use `where('code', ...)` so the twin cannot be addressed.

**F158 (Low): club type `available_modules`, `terminology`, `rulers_schema` are only validated as arrays.** `available_modules: ["nope","<script>","../etc"]` is stored; `PUT` with `[]` removes every module for all clubs of that type.

**F159 (Low): `POST /auth/make-me-superadmin` is gated only by `app()->isLocal()`.** 404 in e2e (`APP_ENV=e2e`), but `.env.example` ships `APP_ENV=local`, so a server deployed from it would let any logged-in user become superadmin. Register the route only inside a local-only check, or delete it and keep `superadmin:grant`.

**F160 (Low): timing shows which emails exist.** Unknown email skips bcrypt: about 205 ms vs 559 ms in-process (`Auth::validate`); register also differs (1.6 s vs 8.5 s). Hash a dummy password.

**F161 (Low): API hardening.** `/api/v1/user` has no rate limit (70 rapid requests, all 200, no `X-RateLimit-*`); CORS is `Access-Control-Allow-Origin: *` on `OPTIONS /api/v1/*`; API JSON lacks nosniff/frame/referrer headers.

**F162 (Low): caching and storage.** `GET /{club}/admin/accounting/export-all` and `/accounting/attachments/{mediaId}` send `Cache-Control: public` (CSV and PDFs send `no-cache, private`). Attachments of members-only posts live on the public disk (`GET /storage/43/ok.pdf` anonymous 200 while the post is 404; ids sequential). Files under `/storage` carry no `nosniff` on `php -S` (web server config decides). A PNG with appended `<?php` / `<script>` is stored verbatim; a NUL-byte filename is stored extensionless.

**F163 (Low): mail abuse and account changes.** The public contact form accepts `recipient_email` / `cc_emails` for any active member or the club domain, so a visitor makes the lodge mail a member (6/min per IP; member.a4 received it). Public booking mails every guest address (up to 50). Email change (`PUT /members/profile`) needs no password and does not notify the old address (code read). `BillingController::checkout`, `BankImportIndex` and `MemberFormPage` echo raw exception messages.

**F164 (Low): smaller correctness points.** The lodge `.ics` carries `<script>` lodge names raw (suggest-fix and superadmin apply accept markup). A signed-in user whose email became unverified can still send a fix and a lodge claim. `%` / `_` are not escaped in `GET /lodges?q=` (`q=%25` matches all 9,693).

**F165 (Low): shared throttle.** `auth-forms` is 10/min per IP only and shared by forgot-password, register, reset, verify, invitations, `/sign/{token}` and public booking pages (other sessions' traffic consumed the test bucket); `public-forms` is 6/min per IP. A shared NAT locks people out for a minute.

**F166 (Low): misleading success messages.** `POST /{slug}/members/{userId}/approve|reject` for a non-member shows "Member approved successfully" with no change; removing a province admin who is not one reports success; `BankReconciliationMatcherService::reconcileTransaction` with `matchType=member_subscription` and an unresolved `targetId` marks the line `matched` though nothing was reconciled.

**F167 (Info): environment and hardening notes.** `lodges:check-sources` fetches any stored URL (127.0.0.1, 10.x, 169.254.x) with no redirect or size limit, but no web path writes sources. `/internal/domain-allowed` is 404 unless `REMOTE_ADDR` is loopback, which every request has behind a same-host proxy unless `/internal/` is blocked there. Cookies are not `Secure` unless `SESSION_SECURE_COOKIE` is set (check Forge). `POST /_boost/browser-logs` exists while debug is on. `superadmin:grant --revoke` has no last-superadmin guard and matches the email case-sensitively. The reset mail subject reads "Laravel - Reset Your Account Password" (check `APP_NAME` in production).

## Not run

Livewire-driven exports and uploads in a browser (roster, relief chest, statement and member import, signature image; code read only); `X-Forwarded-*` with trusted proxies, HSTS and `Secure` cookies (e2e is plain http with no proxy); replay of a correctly signed Stripe/PayPal webhook (covered by `PlatformPaymentsTest`); media and accounting-attachment download routes with foreign ids and create-route body-id fuzzing beyond a spot check (the existing `CrossClubBodyIdsTest` covers part); the province-admin correction queue and per-user caps on open suggestions and claims; the Stripe side of platform payments; `halls:geocode` (global); superadmin user management and club-type delete (no such routes); XSS markers in club settings and committee/candidate notes (code read only); browser rendering of the superadmin Vue pages; exhausting the shared `auth-forms` bucket (would have blocked other sessions). Mailpit messages for the `sec-` accounts were left (shared).
