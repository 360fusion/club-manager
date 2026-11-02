# Accounting features not covered before: end-to-end results (2 Nov 2026)

Run against the e2e app (`http://127.0.0.1:8001`, PostgreSQL `club_manager_e2e`) by five parallel test agents, each in its own throwaway lodge (`acc-e2e` club 4, `-b` 6, `-c` 7, `-d` 8, `-e` 9) so that closing years or locking periods never touched `lodge-of-fraternity`, which was only read. Test only: no application code was changed and nothing was committed. Roles used: treasurer (user 4), lodge admin (user 8), coach (3), plain member (2), admin of another lodge (user 9), superadmin (user 10), logged out.

Full per-area tables with hand-checked figures, exact requests, and what each agent created are in the appendices at the end (their own finding numbers are quoted as "setup A1", "VAT A3", "year A4", "assets A8", "vol A2").

**Caveats.**
- Other sessions changed the shared database while this ran: they renamed clubs 1, 3, 4 and 7 to "sec-iso" (names are back to normal now), and repeatedly set the treasurer (user 4) to past/deleted or removed them from the throwaway lodges. Treasurer rows were restored each time; some writes in between were done as the lodge admin. Treasurer permission results come from before/after those windows.
- The shared server was saturated for part of the run (load average 22 to 75), so wall-clock seconds are inflated; query counts are reliable.
- F100 (invoice numbers) made it impossible to raise invoices in a new lodge, so agents inserted placeholder invoices (`ACC-PAD-*`, `ACC-PH-*`, `INV-ACC-D-*`) to move the counter, or used journals for revenue. Invoice coverage in lodges B (assets/budgets) and D is therefore thinner than planned.
- Not browser-driven except the 500-line Livewire import: everything else is HTTP plus database checks, so rendered screens (onboarding card, chart of accounts, contacts, budget form) were not looked at.

## Test results

| ID | Area | Result | Note |
|---|---|---|---|
| S1 | Onboarding checklist | PASS | Four steps tick only on their own action; dismiss stored club-wide, survives reloads; coach/member/other-lodge admin get 403. Rendered card not looked at. |
| S2 | Opening balance journal | PASS | 1,000.00 on 1010: Dr 1010 / Cr 3000, balanced, audit row. After a mixed run assets 3852.01 = liabilities 60 + equity 3792.01. |
| S3 | Opening balance set twice / changed | FAIL | Adds each time (1000 twice = 2000), flash says "updated" (F101). |
| S4 | Opening balance negative / zero / future / dates | FAIL | -250 posts as Dr 250 (F102); 1900/2030/9999 dates accepted (F118); zero says "updated" (F140). 3 decimals rounded to pence, 100,000,000 and "abc" refused. |
| S5 | Opening balance in a closed year | PASS / FAIL | Boundary right (30 Apr 2025 refused, 1 May 2025 accepted); but add-account with such a balance still creates the account (F117). |
| S6 | Financial year end month change | PASS / FAIL | Invalid values (0, 13, text, 1.5, array) give 422. 216 (month, date) pairs agree between `financialYearFor` and the JS label in UK time; Jan, Feb (29 Feb 2028 = FY2028), Apr, May, Dec ranges right. Changing the month after a year is closed silently moves the boundary (F119). |
| S7 | Year label in the browser | FAIL (Low) | Wrong west of UTC (F139). |
| S8 | Treasurer report year figures | PASS / FAIL | Income/expenditure by financial year right for each month setting; "closing ledger balance" is today's balance (F120). |
| S9 | Chart of accounts | PASS / BLOCKED | Add, duplicate, bad type, long code refused; HTML stored raw but output escaped. Edit, archive, delete, restore do not exist (F121). |
| S10 | Contacts | PASS with notes | Add/edit/delete, validation, other-club ids 404. No link from contacts to bills/invoices, so "delete with documents refused" cannot exist (F141). |
| S11 | Roles on set-up URLs | PASS | Treasurer, admin allowed; coach, member, user 9 get 403; logged out 401/302; club 1 ids in a throwaway-lodge URL give 404. |
| B1 | Budget vs actual arithmetic | PASS | Variances and percentages hand-checked (4000: -100, -25.0%; 4200: +50.50, 25.3%; 5100: -19.60, -19.6%). Totals row adds income and expense together (F135). |
| B2 | Budget validation / year handling | PASS / FAIL | Negative, huge, text, other club's account refused; April year end puts the seed in FY2027, December in FY2026; export always uses the current year (F132); `budget_year=abc` gives 500 (F134). |
| B3 | Comparative income and expenditure | PASS with notes | Matches paid documents only, no PDF (F138). |
| FA1 | Fixed asset register and validation | PASS / FAIL | Journal Dr 1500 / Cr 3000 right; salvage above cost, cost 0, future dates accepted (F136). |
| FA2 | Depreciation | PASS / FAIL | Straight line (10000-1000)/5 = 1800.00, 1000/3 = 333.33, 60/5 = 12.00; reducing balance 3000 at 50% = 1500 then 750; stops at residual; same period twice posts nothing. Free-text period double-charges and charges a 2030 asset in 2026 (F131). |
| FA3 | Disposal | PASS / FAIL | Gain 7000+3600-10000 = 600 and loss 500+2250-3000 = -250 balance. Second dispose and huge proceeds give 500 (F133). |
| FA4 | Asset linked to a bill | FAIL | Credits 5000, not the bill's 5200 (F130). |
| FA5 | Closed-year behaviour | PASS | Register/depreciate/dispose blocked with 422; superadmin-only reopen. Budgets can still be edited (F138). |
| RB1 | Recurring bills: create/toggle/delete | PASS | Validation, toggle, other-club id 404. Delete of a real template not run. |
| RB2 | Generate + account mapping + VAT | PASS | 5 bills, mapping 5200 / 5100 / 5300 / named account / 5000 right; VAT 120.00 = 100.00 + 20.00 and 10.00 = 8.33 + 1.67; disabled and future templates skipped. |
| RB3 | Idempotent / dates / closed year | FAIL | A template behind by several periods makes one bill per run and month-end dates drift (F137); one closed lodge stops the command for every lodge (F112). |
| V1 | VAT rounding | PASS | Gross 0.01 to 1234.56 at 20%, 5%, 0%: net + VAT = gross every row; journals Dr/Cr right; 2200 balance = output - input (115.58). |
| V2 | VAT rate override / validation | FAIL | 150% accepted, -5 gives 0%, 0 replaced by default, bills ignore it (F125). |
| V3 | VAT return and CSV | FAIL | Draft and pending documents included (F108); quarter only (F126); CSV formula cells neutralised. |
| V4 | VAT lock | PASS | Changing scheme or disabling refused once documents exist; scheme setting does nothing (F142). |
| V5 | Edit posted VAT document | FAIL | Ledger unchanged (F103). |
| G1 | Gift Aid maths | PASS / FAIL | 25% of donation, rounded per collection (33.33 gives 8.33); every collection counted as eligible, no declaration (F109). |
| G2 | Gift Aid schedule export | FAIL | No donor details or donation date (F123). |
| G3 | Reconcile-auto | FAIL | Idempotent, but posts income on a text match (F110, F124). |
| G4 | Festival giving | PASS | Jewel at £250, bar at £500, other club's member 404. |
| G5 | Relief Chest export | BLOCKED | Service has no route or screen (F143). |
| G6 | Cross-check with treasurer report | PASS / FAIL | General fund 4000 3349.14 and charity page totals agree; charity ledger income missing from report (F122). |
| Y1 | Approval threshold | PASS | Validation, roles, `>=` edge (£100 pending, £99.99 posts), raiser cannot approve own, other approver can, double approve refused, approvals logged. |
| Y2 | Pay/edit/delete around approval and close | FAIL | F104 (pay skips approval), F103 (edit), F105 (edit/delete in closed year), F106 (half-saved invoice). |
| Y3 | Year close / reopen | PASS / FAIL | Journals, opening balances, assets, depreciation, new bills/payments blocked with 422; only superadmin reopens; audit rows right. No safeguards (F127); signed figures can change (F128). |
| Y4 | Auditor and examiner sign-off | PASS / FAIL | Request emails with `/sign/{token}`, resend kills old link, cancel kills link, tampered tokens not found, double sign refused, signature and examiner PDFs right. Auditors can be replaced after sign-off (F107); links never expire (F146). |
| Y5 | Annual treasurer's report | PASS / FAIL | FY2026 and FY2027 General Fund hand-checked to the ledger (FY2027 income 770, expenditure 11,108.50). Charity fund ledger income missing (F122). |
| Y6 | Audit log | PASS / FAIL | Journal, update/pay/approve, close/reopen logged with who/when; gaps (F129). |
| Y7 | `export-all` | PASS | Only owner and superadmin; treasurer, admin, coach, member, outsider 403. Contents only that club, no secrets, CSV safe. |
| X1 | 500-line import | PASS | 500 rows read, net 11,487.22 matches by hand; re-import adds no duplicates; 11 MB refused clearly. Empty batch recorded on all-duplicate re-import (F145). |
| X2 | Accounting page at volume | FAIL | 90 / 312 / 2,565 queries at 0 / 50 / 549 lines (F111). Workspace and import list pages are fine. |
| X3 | Large files | FAIL | 5,000 lines: preview errors after 27 s; 10.3 MB: 500 after 30 s (F116). |
| X4 | Attachments | PASS / FAIL | PDF ok; SVG, HTML, PHP, ELF, renamed HTML/PHP, `x.pdf.php`, empty, >10 MB refused with nothing stored; private disk; downloads only for treasurer/admin; foreign media 404. Gaps in F144. |
| X5 | Permission sweep | PASS | 32 GET URLs x 6 roles: no leak; foreign ids both directions 404 and unchanged; Livewire tampering rejected. |
| X6 | Bank reconciliation of documents | FAIL | F113, F114, F115. |

## Findings (not fixed)

### High
- **F100 Invoice numbers collide across lodges (500).** `AccountingInvoiceController::storeInvoice` (line 76) numbers `INV-{year}-{count of this club + 1}` but `invoices.invoice_number` is globally unique. Steps: as treasurer of any lodge with no invoices while another lodge holds `INV-2026-0001`, `POST /{slug}/admin/accounting/invoices` (`user_id`, `title`, `amount`) returns 500 (`invoices_invoice_number_unique`). Also: deleting any invoice that is not the newest makes the next number equal the newest, so every later create fails. A failed create with an attachment leaves an orphan file and media row. Bills and journals use the same per-club count (`AccountingService` lines 159 and 320) and get duplicate numbers silently. Found independently in all four areas that raised invoices.
- **F101 Opening balance is added, not set.** `POST admin/accounting/opening-balance {"account_id":…,"opening_balance":1000,"as_of_date":"2026-05-01"}` twice gives two journals and a 2000 balance; 1500 after 1000 gives 2500. Flash says "Opening balance updated". (setup A1)
- **F102 Negative opening balance loses its sign.** Same request with `-250` posts Dr 250 (`abs()` in `setOpeningBalance`); the asset goes up by 250. Same via `accounts.store`. (setup A2)
- **F103 Editing the amount of a posted bill or invoice changes the document, not the ledger, and bypasses the approval threshold.** `PUT bills/{id}` amount 120 to 240 (or 99.99 to 5000 under a £100 threshold): journal stays at the old figure, VAT return changes, a later payment of 240 posts against a 120 receivable. (VAT A2, year A3)
- **F104 Paying a pending-approval or draft bill/invoice skips approval.** Threshold £100, raise a £250 bill, `POST bills/{id}/pay` as the raiser: status paid, journal Dr 2000 / Cr 1000 with no issue entry, and it can never be approved. Same for invoices and draft bills. (year A2)
- **F105 Closing a year does not lock existing bills and invoices.** After closing FY2027, `PUT` and `DELETE bills/{id}` and `invoices/{id}` succeed; delete voids the posted journal, so closed-year totals change. (year A4)
- **F106 An invoice created or published in a closed year is half saved.** `POST invoices` returns 422 "books are closed" but the invoice exists as `unpaid` with no journal entry; `publish` of a draft does the same. Paying it later posts a payment with no issue entry. (year A5)
- **F107 Auditors can be replaced after sign-off.** After both auditors signed, `POST financial-year/audit` with other users rewrites the auditor ids on the signed record and the page shows the new names beside the old signatures. It also reports "requests sent" with no email. (year A10)
- **F108 The VAT return includes draft and pending-approval documents.** It filters on `vat_amount IS NOT NULL` and `created_at`: a draft invoice (VAT 10.00) and pending invoice and bill (VAT 250.00 each) were counted, giving output VAT 723.21 against 463.21 posted to 2200. No tax-point date. (VAT A3)
- **F109 Gift Aid is not tied to a declaration.** `storeCollection` hard-codes `is_gift_aid_eligible = true`; raffle, anonymous and zero-value collections are claimable and a £199,999,999.98 anonymous plate accepted a £50,000,000.00 claim. (VAT A10)
- **F110 `giftaid/reconcile-auto` posts income on a description match alone.** "MCFARLANE PAINTERS REFUND" +25.00 posted to Relief Chest income (4300) and "HMRC TAX REFUND CORP" +12.00 to Gift Aid income (4310), with no amount check. (VAT A13)
- **F111 Every accounting tab runs match suggestions for every unmatched bank line.** `ReconciliationScreenData::build()` gave 90 / 312 / 2,565 queries at 0 / 50 / 549 lines (about 4.5 per line); the page hit the 30 s PHP limit under load. Home, sales, purchases, reporting and every report cost the same. (vol A2)
- **F112 One closed lodge stops recurring bills for every lodge.** Close a year in a lodge with an active template due; `php artisan app:generate-recurring-bills --env=e2e` throws `LedgerRuleException` and the command stops, so later clubs get no bills that day. (assets A13)

### Medium
- **F113 Bill/invoice reconciliation posts cash to 1000, not the line's bank account.** `markBillAsPaid`/`markInvoiceAsPaid` use `getAccount($club,'1000')`; a line on bank account 3 (ledger 1010) posted Cr 1000 (1000 = -470.30, 1010 = -136.76), and the cash summary only reads 1000 and 1100. The earlier F13 fix covered the generic match, not these paths. (vol A3)
- **F114 Reconciling to a target that does not exist reports success.** `reconcileSuggested(12,'supplier_bill',1)` with an id from another lodge says "Transaction reconciled successfully!", marks the line matched and posts a default entry Dr 5000 / Cr 1010. No other lodge's data was read or changed. (vol A12)
- **F115 No server-side check that a line is unmatched, or that amount and direction fit the target.** A matched 136.76 charge line paid a 114.06 bill; a credit of 239.49 paid a 121.09 supplier bill; one line can settle several bills. (vol A13)
- **F116 Large statements fail instead of being refused.** The preview lives in the Livewire snapshot (845 KB at 500 lines, posted back on confirm); 5,000 lines ends in an error dialog after 27 s, a 10.3 MB file gives 500 after 30 s. (vol A4, A5)
- **F117 Adding an account with an opening balance in a closed year creates the account and returns 422.** `POST accounts {"code":"9100","type":"asset","opening_balance":33,"as_of_date":"2025-02-01"}`; retry says "code already taken". (setup A3)
- **F118 Opening balance dates are unbounded.** 1900, 2030 and 9999 accepted; future ones count in today's dashboard totals. (setup A4)
- **F119 The financial year end month can be changed with years closed or postings present.** Closing FY2025 (April) then switching to May moves a 77.00 entry into the closed year; FY2026 closed under April was re-sliced to 1 Jan to 31 Dec by switching to 12 (expenditure £108 instead of £85.50). No guard, no log. (setup A5, year A12)
- **F120 The treasurer report's "closing ledger balance" is today's balance.** FY2025, 2026 and 2027 all show 3852.01 (expected 5.00, 82.00, 3842.01); PDF and CSV read the same field. (setup A7)
- **F121 No way to edit, deactivate, delete or restore an account.** Only `admin.accounting.accounts.store` exists; `is_active` is never set. The plan's "delete an account with postings" case could not be run. (setup A10)
- **F122 The treasurer's and examiner's reports leave things out.** Charity Fund ledger income (4300/4400/4500) is removed from the General Fund and never shown (a £200 donation vanished from table, CSV, PDF and examiner report); the VAT section uses `{year}-01-01` (calendar Q1) instead of the financial year; the report CSV has no VAT block. (year A11, VAT A7)
- **F123 The Gift Aid schedule is not usable for an HMRC claim.** No donor name, address, postcode or donation date (Date is the entry date); lists already-reconciled rows; no total; invented counter/witness names when missing. (VAT A12)
- **F124 Each matched deposit marks the oldest pending collection reconciled whatever its amount or kind.** A 100.00 Relief Chest payout marked the 0.01 collection reconciled. (VAT A14)
- **F125 Invoice `vat_rate` is unvalidated.** 150 accepted, -5 silently 0%, 0 replaced by the default (cannot zero-rate), bill create ignores it; neither form has a VAT field. (VAT A4)
- **F126 The VAT return screen and export cover only the current quarter**, and `?quarter_start=abc` or an array gives 500. (VAT A5)
- **F127 Year close has no safeguards.** No check for pending or draft documents, unreconciled lines, earlier open years or future years (FY2031 closed); closing twice overwrites closer, time and notes. (year A6)
- **F128 Signed reports can change after signing.** The sign hash does not cover figures; after sign-off a superadmin posted a £10 entry into the closed year and the examiner PDF changed. (year A8)
- **F129 Audit trail gaps.** Not logged: bill/invoice creation (a pending bill has no journal and so no log row), approval threshold, year-end month, VAT/charity settings, who requested an auditor/examiner (the `audited` row has no actor); a failing journal edit names the wrong year. (year A9)
- **F130 A fixed asset linked to a bill credits 5000, not the account the bill charged (5200)**, and the cost is not checked against the bill amount. (assets A8)
- **F131 Depreciation periods are free text.** "FY2026" after "2026" charges again; a 2030 asset is fully written off by runs labelled 2026; a full year is charged whatever the purchase month; entries are dated the day of the run (period "2026" posts into FY2027). (assets A5, year A15)
- **F132 Budget vs actual CSV/PDF always export the current year** (the links carry no `budget_year`). (assets A3)
- **F133 Disposing an already-disposed asset, or proceeds above 9,999,999,999.99, gives 500.** (assets A7)

### Low
- **F134 Unvalidated ids and years give 500 not 404/422.** `budget_year=abc|-5|[]`; `activity/bill/abc`; `attachments/abc`.
- **F135 Budget totals row adds income and expense together** (1020 = 750 + 270). (assets A2)
- **F136 Fixed asset validation gaps:** salvage above cost, cost 0 (all-zero journal), future purchase date, disposal before purchase. (assets A4)
- **F137 Recurring catch-up posts one bill per run** and month-end dates drift 31 to 28. (assets A12)
- **F138 Smaller report gaps:** budget on a bank account accepted and never shown; 3-decimal budgets rounded silently; budgets editable in a closed year; a disposed asset keeps its old net book value; no CSV/PDF for the asset register; comparative I&E uses paid documents only and has no PDF.
- **F139 The browser year label is wrong west of UTC** (`resources/js/Utils/financialYear.js` parses `new Date("2026-05-01")` then `getMonth()`; New York shows 1 May 2026 as 2025-26). `npm run check:ranges` does not cover time zones. (setup A6)
- **F140 Opening balance edge cases:** zero amount and an opening on 3000 itself say "updated" but post nothing; openings on revenue/expense accounts count in the current year's profit. (setup A8)
- **F141 Set-up gaps:** account code accepts spaces and symbols; the contact form cannot create a business contact and a name-only update keeps old first/last name; a pending member gets a member contact; contacts are not linked to bills/invoices at all. (setup A9, A11 to A13)
- **F142 VAT settings:** the scheme (flat rate, cash accounting, ...) changes nothing; 3-decimal rate accepted; `enabled` saved as the string "1". (VAT A6, A8)
- **F143 Charity gaps:** zero-value and 3-decimal collections accepted; festival tiers not checked for order; `ReliefChestExportService` is dead code (no route); Gift Aid income 4310 sits in the General Fund. (VAT A11, A15 to A17)
- **F144 Attachments:** a real image named `x.php.png` gives 500 (nothing stored); checks go by content only (PNG named .pdf, PDF named `x.pdf.html`, polyglots accepted, on the private disk and served with `nosniff`); downloads sent with `Cache-Control: public`; an empty file gets the "file type" message. (vol A6 to A8)
- **F145 Bank import housekeeping:** an all-duplicate re-import records an empty batch; `BankAccountsIndex` eager-loads every transaction of every account; `BankStatementParserService` line 42 emits a `str_getcsv` deprecation per CSV line; the JSON 404 includes exception class, file and trace (APP_DEBUG is on in e2e).
- **F146 Sign-off housekeeping:** reopening a year that is not closed reports success and logs it; resend/cancel on a non-pending request say success; sign links never expire (`expires_at` is null); the approval threshold keeps 3 decimals. (year A7, A14, A16, A17)

## Not run
Bank reconciliation or import dated in a closed year and unreconciled lines at year close (Livewire, code has no check); expired sign links and drawn-signature upload; a non-GBP lodge (currency locks once records exist); VAT with VAT disabled; approving a pending VAT document and the Gift Aid grants/BACS schedule; PDF contents of the treasurer report for VAT; deleting a real recurring template; the rendered onboarding card, chart of accounts, contacts and budget forms in the browser; a 5,000-line import (preview fails first); clean report timings on an idle machine; superadmin reopen of the setup lodge's FY2025; treasurer on every URL in lodges where another session removed the role mid-run.

## Cleanup
The five throwaway lodges (`acc-e2e`, `-b` to `-e`; club ids 4, 6, 7, 8, 9) were deleted with all their rows (journals and items, bills, invoices including the `ACC-PAD-*`, `ACC-PH-*` and `INV-ACC-D-*` placeholders, bank imports and lines, assets, budgets, contacts, charity rows, audit logs, signature requests, club_user rows) in one transaction, plus 13 media rows and their private files belonging to those lodges' bills, invoices and clubs. Lodge 1 was only read (its 2 invoices and 3 media rows are intact). Left behind on purpose: Mailpit mail to `@example.com` and `@e2e.test` addresses (sign-off requests; the mailbox is shared) and scratch files in the session scratchpad. The pending user 71 and the `sec-iso` renames were other sessions' changes, not mine.

---

# Appendices: per-area results as written by each test agent
Finding numbers in the appendices (A1, B1, ...) are each area's own; the table and findings above map them to F100 onwards.


---

## Appendix: setup

# Result: setup (onboarding, opening balance, financial year end, chart of accounts, contacts, permissions)

Lodge acc-e2e (club id 4). Run 2 Nov 2026 against http://127.0.0.1:8001 (e2e DB). Probes were mostly HTTP (JSON), numbers read from Postgres.

Harness notes
- `req.sh` fetches the CSRF token from `/members`, which now answers 301 -> `/members/dashboard`, so every POST through `req.sh` gets 419. I used `setup-req.sh` (same thing, token from `/members/dashboard`).
- Another session was changing club 4 at the same time (contacts named `sec-iso`, club name `sec-iso`, treasurer user 4 turned to `past`, then `member`, then removed from club 4; user 71 added as pending). I tested treasurer access before that happened (results below); after it, admin (user 8) was used for writes. The treasurer row in `club_user` for club 4 is currently gone, so other tests in this lodge that need the treasurer will fail until it is restored. I did not touch `club_user`.
- One unexplained write: early on `onboarding_dismissed` became true on club 4 while my own tests had not dismissed it (probably my first, badly-written permission loop, which ran for a short while with the "skip dismiss" test wrongly coded). I reset it and re-tested: refused roles (coach, user 2, user 9) cannot set it, and VAT save does not set it. Treated as a harness artefact.

## Test table

| ID | Result | Note |
|---|---|---|
| a1 checklist ticks | PASS | New lodge: all four steps false. After bank account (acc-Bank, 1010): bank true. FY end saved (even with the unchanged value 4): true. VAT saved (not_registered): true. Opening balance posted: true. Each step flips only on its own action. |
| a2 dismiss persists | PASS | `onboarding_dismissed:true` is saved in `clubs.settings`, so it survives reloads and is the same for every staff user. Coach, user 2 and user 9 get 403 and do not set it. The card is hidden once the three required steps are done (VAT is optional), and after dismiss. |
| a3 checklist UI | NOT RUN | Only the props were checked, not the rendered card. |
| b1 opening balance journal | PASS | 1,000.00 on 1010 dated 2026-05-01: JE-2026-0001, source OpeningBalance, Dr 1010 1000.00 / Cr 3000 Retained Earnings 1000.00, balanced. Audit log row written (user, entry). Debit-type accounts (asset, expense) debit the account, credit/equity accounts credit it. |
| b2 dashboard / balance sheet | PASS | After 1,000: summary assets 1000, equity 1000, balance sheet 1010 = 1000, 3000 = 1000. After a full mixed run: assets 3852.01 = liabilities 60 + equity 3792.01 (3000 balance 3892.01 less net income -100). Hand-added: 1000+1000+1500+250+10.01+77(2025)+5+5+5(odd dates) = 3852.01. All journals in the lodge balance (sum debit = sum credit = 4452.01 at that point, no unbalanced entry). |
| b3 posted twice | FAIL (A1) | Same request twice = two journals, 2000 on the account. Flash says "Opening balance updated". |
| b4 changed amount later | FAIL (A1) | Posting 1500 after 1000 gives 2500, not 1500. Nothing is replaced or reversed. |
| b5 negative | FAIL (A2) | -250 on a bank asset posts Dr 250 (sign dropped with `abs()`), balance goes UP by 250. |
| b6 zero | PASS with note | 0 posts nothing and the flash still says "Opening balance updated" (Low, A8). |
| b7 3 decimals | PASS | 10.005 stored as 10.01 (rounded to the penny). |
| b8 huge | PASS | 100000000 and "abc" refused (422, max 99999999.99 / must be numeric). |
| b9 closed period | PASS | FY 2025 closed (April end): 2025-03-01 and 2025-04-30 refused with 422 `errors.ledger` "The books for 2025 are closed..."; 2025-05-01 accepted (FY 2026). Boundary correct. |
| b10 closed period on account store | FAIL (A3) | Adding an account with an opening balance dated in a closed year returns 422, but the account (9100) is created anyway; a retry then says the code is taken. |
| b11 future / extreme dates | FAIL (A4) | 2030-01-01, 9999-12-31 and 1900-01-01 all accepted. The dashboard "as of today" totals include the future ones (+5 +5). |
| b12 other club / bad ids | PASS | account_id of club 1 = 404; missing id = 422; bad date = 422. |
| b13 P&L / equity accounts | PASS with note | Opening on revenue 4000 (200) and expense 5000 (300) is accepted and counts in this year's income/expense (net income -100). On 3000 itself it posts Dr and Cr to the same account (no effect, flash says updated). See A8. |
| c1 invalid months | PASS | 0, 13, -1, "abc", "", missing, 1.5, [5], "05" all 422 with a clear message. |
| c2 Club::financialYearFor / Range hand check | PASS | End April: 30 Apr 2026 = FY 2026, 1 May 2026 = FY 2027, range 2026 = 1 May 2025..30 Apr 2026. End May: 1 May 2026 = FY 2026. End Jan: 31 Jan 2026 = FY 2026, 1 Feb 2026 = FY 2027, range 2026 = 1 Feb 2025..31 Jan 2026. End Dec: range 2026 = 1 Jan..31 Dec 2026. End Feb: range 2028 = 1 Mar 2027..29 Feb 2028 (2029 = 1 Mar 2028..28 Feb 2029). 29 Feb 2028 is FY 2028 for a February end, 1 Mar 2028 is FY 2029; for April end it is FY 2028. Checked 216 (month, date) pairs in tinker. |
| c3 treasurer report ranges and figures | PASS | Period strings for months 1, 2, 4, 5, 12 match the model. Figures: revenue/expense entries dated 2026-05-01 (income 200, expense 300) are in FY 2027 for April/January/February ends (report 2027: 200/300, report 2026: 0/0), and in FY 2026 for May and December ends. Leap-day test: revenue 50 on 29 Feb 2028 and expense 7 on 1 Mar 2028, February end: FY 2028 report shows income 50 / expenditure 0, FY 2029 shows 0 / 7. |
| c4 PHP vs JS year labels | PASS in UK time, FAIL in US time (A6) | `financialYearLabel()` agrees with `financialYearFor()` for all 216 pairs when the browser is in UTC/UK. With TZ=America/New_York the first of each month goes to the previous year (e.g. 1 May 2026 labelled 2025-26 instead of 2026-27). `npm run check:ranges`: ok (7 ranges, 15 labels) but it does not cover time zones. |
| c5 change month after year closed / transactions exist | FAIL (A5) | Allowed with no warning. With FY 2025 closed under an April end, switching to May re-reads it as 1 Jun 2024..31 May 2025: a 77.00 entry dated 2025-05-01 that was in the open FY 2026 silently moves into the closed year, and 15 May 2025 is then refused while 15 Jun 2025 is accepted. |
| c6 treasurer report "closing ledger balance" | FAIL (A7) | Shows today's ledger balance (3852.01) for FY 2025, 2026 and 2027 alike. Expected as at each year end: 5.00, 82.00, 3842.01 (2030 and 9999 entries excluded). |
| d1 add account | PASS | 9001 asset created. Duplicate code, a code already seeded (1000) and a code equal after trimming ("9001 ") all 422 "code has already been taken". Bad type "income" 422. Empty code 422. Code of 31 chars 422 (max 30). |
| d2 odd codes / names | PASS with note | Code `90 03 x`, `=1+1`, name `<script>alert(1)</script>...` and name `=HYPERLINK(...)` are all accepted and stored raw (A9, Low). Output is safe: page JSON is escaped and Vue renders text (no `v-html` in accounting); CSV exports prefix `'` (balance sheet, account summary, P&L checked); PDF export 200. |
| d3 delete / archive / restore / edit account | BLOCKED (A10) | Only `admin.accounting.accounts.store` exists. No edit, delete, archive or restore route (DB has `is_active` but nothing sets it). So "delete account with postings keeps history" cannot be tested; there is also no way to fix a typo or remove an account. |
| d4 account of another club | PASS | Club 1 account id in opening-balance = 404. Same code in another club is fine (unique per club). |
| e1 add contact | PASS | Business with name, person with first/middle/last (name built as "Acc Mid Person"). Bad email 422, phone over 50 chars 422, bad type 422, no name 422, whitespace name 422, no role 422. |
| e2 edit contact | PASS with notes | Update works; invalid email 422; missing role 422. Changing only `name` leaves old first/last parts (A11, API only). |
| e3 delete contact | PASS | Delete works; deleting again 404. There is no link between contacts and invoices/bills in the schema (bills/invoices have no contact_id), so "delete with documents refused" does not exist and cannot apply; only `club_acc_members.customer_account_id` points at a contact (ON DELETE SET NULL). |
| e4 member vs business | PASS with notes | `contacts/member/{userId}/edit` creates a member contact (role forced "Club Member" on update). A user who is not in the lodge (users 9, 5, 99999) = 404. A pending user (71) gets a contact (A12). The UI form has no business option and no contact_person; "Club member" can be picked as the role for a normal contact (A11). |
| e5 XSS in contacts | PASS | Name, role and notes with HTML stored raw; page JSON escaped, no script in the HTML. |
| f1 treasurer / admin allowed | PASS | Treasurer (before its removal) and admin: all POST/PUT/DELETE return 422/404 on empty or unknown payloads (so passed the gate) and GETs 200. Admin full writes verified above. |
| f2 refused roles | PASS | Coach (user 3), plain member (user 2), user 9 (owner of another lodge): 403 on accounting page, contacts create / edit / member edit, accounts, opening-balance, financial-year-end, onboarding dismiss, contact PUT/DELETE (also with a real contact id 22; contact unchanged). |
| f3 logged out | PASS | GET redirects to /login; POST/PUT/DELETE with a valid CSRF token = 401 (JSON). |
| f4 other club's ids in acc-e2e URLs | PASS | Contacts 6 and 7 (club 1): PUT / DELETE / edit = 404, rows unchanged. Account 1 (club 1): 404. |

## Findings

**A1 (High) Opening balance is added, not set; double submit doubles money.**
Repro: `POST /acc-e2e/admin/accounting/opening-balance {"account_id":128,"opening_balance":1000,"as_of_date":"2026-05-01"}` twice. Expected: second call replaces/reverses the first (or is refused as "already set"), flash "Opening balance updated" implies a replace. Actual: JE 94 and JE 98 each Dr 1010 1000 / Cr 3000 1000; balance 2000. Posting 1500 afterwards gives 3500 on the account. The dashboard card (onboarding step "Set an opening balance") turns green on the first one, so a user who sees no change and clicks again doubles the lodge's funds.

**A2 (High) Negative opening balance loses its sign.**
Repro: `{"account_id":128,"opening_balance":-250,...}`. Expected: an overdrawn account posts the other way (Cr 250) or is refused. Actual: Dr 250 (`abs()` in `AccountingService::setOpeningBalance`), asset goes up by 250. Same through `accounts.store` with `opening_balance:-100` (asset 9300 got Dr 100.00).

**A3 (Medium) Add-account with an opening balance in a closed year creates the account but not the balance.**
Repro (FY 2025 closed): `POST accounts {"code":"9100","name":"acc-closed-open","type":"asset","opening_balance":33,"as_of_date":"2025-02-01"}`. Actual: 422 "books for 2025 are closed" yet account 171 exists. Retry gives "code already taken". Wrap both in one transaction (or check the date first).

**A4 (Medium) Opening balance dates are unbounded and future-dated entries count in today's totals.**
Repro: `as_of_date` 2030-01-01, 9999-12-31, 1900-01-01 accepted. Dashboard totals went 3837.01 -> 3852.01 (all three counted). Also the reports tab "All time" preset starts at 1970-01-01, so the 1900 entry is outside it, although the code comment says it reaches back past any entry. Limit the date (not in the future, not before e.g. the lodge's founding or 1970).

**A5 (Medium) Financial year end can be changed after year closes / postings exist, and it moves the closed-year boundary.**
Repro: close FY 2025 with April end; `POST financial-year-end {"financial_year_end_month":5}`. Closed year 2025 now covers 1 Jun 2024..31 May 2025; the 2025-05-01 entry (77.00) silently moves into it. Expected: refuse (like the VAT scheme, which locks once VAT transactions exist) or warn, once any year is closed or entries exist.

**A6 (Low to Medium) FY label in the browser is wrong for users west of UTC.**
`resources/js/Utils/financialYear.js` does `new Date("2026-05-01")` (UTC midnight) then `getMonth()` (local). With TZ=America/New_York 1 May 2026 -> April -> "2025-26" (server says FY 2027); 1 Jan 2026 for a December year end -> "2025". Affects the FY filters on Sales and Purchases. Use string parts of the date instead of `Date`.

**A7 (Medium) Annual treasurer report "closing ledger balance" is the current balance, not the year-end balance.**
Repro: `/acc-e2e/admin/accounting/treasurer-report?treasurer_report_year=2025|2026|2027`: all show closing_ledger_balance 3852.01 (`AnnualTreasurerReportService` uses `$b->ledger_balance`, today's). A signed-off report for a past year changes whenever later entries are posted. Expected at year end: 5.00 / 82.00 / 3842.01 (also in the PDF and CSV, which read the same field). The "opening_balance" column is the bank account's initial opening balance, not the opening of the year.

**A8 (Low) Opening-balance edge cases give a success message.**
Zero amount posts nothing but says "Opening balance updated"; opening balance on 3000 itself posts Dr 3000 / Cr 3000 (net nothing); opening balances on revenue/expense accounts are accepted and count in the current year's profit and loss (income 200, expense 300 in my test), which is not what an opening (balance sheet) balance should do.

**A9 (Low) Account code and name are unconstrained.**
Code accepts spaces, symbols and leading `=`/`+` (`90 03 x`, `=1+1`); name accepts HTML. Safe on output as checked, but add a code pattern (digits/letters, no spaces) for tidy charts.

**A10 (Medium, feature gap) No way to edit, deactivate, delete or restore an account.**
Only `POST /{club}/admin/accounting/accounts` exists. A mistyped name/code or a test account stays forever. `accounting_accounts.is_active` is never set. Needed before "delete an account with postings must keep history" can be enforced.

**A11 (Low) Contact form gaps.**
(a) The UI cannot create a business contact or set contact person; a business created by API is split "acc-Supplier" / "Ltd" into first and last name, and a later `name`-only update keeps the old parts (row 22: name "acc-Supplier Ltd 2", first "acc-Supplier", last "Ltd"; the edit form then shows the old parts). (b) "Club member" is offered as a role for an ordinary contact, and the server accepts any role text. (c) Phone accepts any text (`abc!!<>`).

**A12 (Low) Member contact is created for pending/past members.**
`GET contacts/member/71/edit` for a pending applicant (user 71) creates an `is_member` contact; the check is "has any club_user row".

**A13 (Info) Contacts are not tied to documents.**
There is no contact_id on invoices or bills, so deleting a contact never affects documents (and no "delete refused" rule can exist). If contacts are meant to be the supplier/customer master, this is a gap.

## Created outside the lodge
Nothing outside acc-e2e. Within it: accounts 9001, `90 03 x`, 9100, 9200, `=1+1`, 9300, bank account "acc-Bank / acc-Main" (ledger 1010), about 16 opening-balance journals (some with odd dates 2030, 9999, 1900), FY 2025 closed (not reopened), contacts 22-24 (25, 26 deleted), `onboarding_dismissed` true, VAT settings not_registered, FY end month 4 (restored). Scratch files `setup-*` in the scratchpad.

## Not run
- Rendered onboarding card / chart-of-accounts / contact screens in the browser (props and HTTP only).
- Deleting or archiving an account with postings (no such route, A10).
- Treasurer role retested after the other session removed user 4 from club 4 (earlier results stand).
- Reopen of FY 2025 by superadmin; the super-admin exception when posting into a closed year.
- `AccountingErpTest` / `AccountingContactTest` files not re-run.


---

## Appendix: bfa

# Result: bfa (budgets, fixed assets, recurring bills) - lodge acc-e2e-b (club 6)

Seed (lodge 6): opening balance bank 1000 = 5000.00 (2026-06-01); bills 120.00 (Bar & Dining Supplies, later paid), 80.40 (Utilities & Fuel), 60.00 (Other); journal Dr bank 550.50 / Cr 4000 300.00 / Cr 4200 250.50 (2026-10-15). Invoices could not be created (see B1), so revenue was seeded with a journal entry.
Financial year: default April end, so all the seed (June to Nov 2026) is FY2027; with December end it is FY2026.
Harness notes: server answered 5-25 s per request; /members now 301 so I used my own copy of req.sh (bfa-req.sh). Another session removed treasurer (user 4) from lodge 6 mid-test (coordinator re-added as active treasurer); I used admin (user 8) for most writes, treasurer (4) for a spot check (run depreciation 302, ok).

## Results

| ID | Result | Note |
|---|---|---|
| BU1 budget save + vs actual | PASS | Budgets 4000=400, 4200=200, 4300=150, 5000=50, 5100=100, 5200=120, 5300=0. Actuals 300 / 250.50 / 0 / 60 / 80.40 / 120. Variances -100 (-25.0%), +50.50 (+25.3%: 50.5/200 = 25.25 rounded half up), -150 (-100%), +10 (+20%), -19.60 (-19.6%), 0 (0%). Row for 5300 (budget 0, no actual) correctly omitted. Budget for account with no actuals (4300) shown with actual 0, variance -150. |
| BU2 totals | FAIL (A2) | total_budgeted 1020 = 750 revenue + 270 expense, total_actual 810.90 = 550.50 + 260.40; revenue and expense are added together. UI "Totals" row variance = -209.10, meaningless. |
| BU3 CSV/PDF export budget vs actual | PASS (A3 for year) | CSV rows match the hand figures (Code,Name,Type,Budgeted,Actual,Variance; no % column). PDF 200 application/pdf. |
| BU4 over-budget display | PASS with note | UI colours variance green for revenue above budget, rose for expense above budget, grey otherwise; revenue UNDER budget (e.g. -100 on 4000) is not flagged. Variance % is in the data but not shown anywhere in the UI or CSV. |
| BU5 validation | PASS | negative, > 99999999.99, "abc", year 1999, year 2027.5, empty lines, other club's account id (1): all 422 with field messages. 99999999.99 accepted and stored. "1e3" accepted (is_numeric). |
| BU6 3 decimals | PASS with note | 10.005 stored as 10.01 (DB rounding), 0.001 stored as 0.00. Silent. Extra `notes` field ignored (dropped). |
| BU7 non-revenue account | FAIL (A9) | Budget for the bank account (91, asset) accepted and stored (777.00) but never shown in any report. |
| BU8 year-end April vs December | PASS with note | April: seed lands in FY2027 (budget_year=2027 shows actuals, 2026 empty). Dec (month 12): same entries land in FY2026, and the 2027 budgets then show actual 0 / -100% on every row. Budgets are keyed on the year number only, so changing the year-end month re-attaches existing budgets to a different period (info). Month 13 refused 422. |
| BU9 budget_year tampering | FAIL (A1) | budget_year=abc, -5, 99999999999, budget_year[]=2027 on the export gives HTTP 500 (SQL date/smallint errors). |
| BU10 comparative I&E | PASS with notes (A6) | CSV: Subs expenditure current 120 (the one paid bill), income 0, prior all 0; matches paid bills only. No PDF for it (export-pdf 404 "No PDF export defined"; the UI does not offer one either). |
| FA1 register asset (no bill) | PASS | Boat 10000, salvage 1000, life 5, 2026-06-01 -> Dr 1500 10000 / Cr 3000 10000 dated purchase date. Same shape for the others. |
| FA2 validation | PASS | negative cost, life 0, 101, 2.5, bad method, non-numeric cost, bad date, no name, negative salvage, cost > max, unknown bill id: 422. Bill of another club (id 15): redirect back with bill_id error, nothing created. |
| FA3 validation gaps | FAIL (A4) | Salvage greater than cost (cost 100, salvage 500) accepted; its net book value shows 500 (more than cost). Future purchase date (2030-06-01) accepted. Cost 0 accepted and posts an all-zero journal entry. Salvage has no upper limit. Disposal date before purchase date (2020 vs 2026) accepted. |
| FA4 straight-line depreciation | PASS | Run "2026": boat (10000-1000)/5 = 1800.00; thirds 1000/3 = 333.33; billasset 60/5 = 12.00; future asset 200/5 = 40.00. Journal each: Dr 5900 Depreciation Expense / Cr 1510 Accumulated Depreciation, same amount, dated today (2026-11-02). Method "none" and salvage>cost asset skipped. Total 3685.33. |
| FA5 reducing balance | PASS | Rigger 3000, salvage 500, life 4: rate 2/4 = 50%: y1 1500.00 (NBV 1500), y2 750.00 (min(750, 1500-500=1000)). |
| FA6 idempotent per period | PASS with note (A5) | Second "2026" run posted nothing (5 rows stay). But the period is free text: "FY2026" posted 40 + 12 again for the same assets (double charge for the same year). |
| FA7 before purchase / partial year | FAIL (A5) | Asset bought 2030-06-01 got depreciation in "2026" (40.00) and every later run (5 x 40 = 200 = fully depreciated before it exists). A full year is charged in the first run whatever the purchase month. Entries are dated "today", not the period. |
| FA8 stops at residual | PASS with note | Thirds (1000/3): 333.33 x 3 = 999.99, a 4th run ("2029") posts 0.01 (a charge in year 4 of a 3-year life), then 0. Billasset stopped at 60. Boat would stop at 1000. |
| FA9 disposal above book | PASS | Boat: cost 10000, accumulated 3600 (2 runs), proceeds 7000 -> gain 7000+3600-10000 = 600. Journal Dr 1000 7000, Dr 1510 3600, Cr 1500 10000, Cr 4900 600. Balanced. |
| FA10 disposal below book | PASS | Rigger: cost 3000, accumulated 2250, proceeds 500 -> loss 500+2250-3000 = -250. Dr bank 500, Dr 1510 2250, Dr 4900 250, Cr 1500 3000. Proceeds 0 on nodep (cost 500): Dr 4900 500 / Cr 1500 500. |
| FA11 dispose twice / disposed asset | FAIL (A7) | Second dispose -> HTTP 500 (unhandled InvalidArgumentException "already disposed"). Disposed assets are skipped by depreciation (run "2028" posted nothing for them) - PASS. Negative / non-numeric proceeds, bad date: 422. Unknown id and another club's asset id (club 8 id 2): 404, nothing changed. Proceeds 99999999999999: HTTP 500 numeric overflow. |
| FA12 bill-linked asset | FAIL (A8) | Asset linked to bill 29 (category Bar & Dining Supplies, posted to 5200): the reclass credits 5000, not 5200. 5200 stays overstated by 120 and 5000 is understated. Asset cost (60) is not checked against the bill amount (120). |
| FA13 fixed assets report | PASS with note (A10) | List: accumulated and NBV agree with the ledger (boat accum 3600; future asset accum 200, NBV 0). Balance sheet: 1500 = 410 (A5 100 + future 200 + billasset 60 + old 50), 1510 = -270, total assets 13070.50 = L 1714.23 + E 11356.27; ledger D = C = 44,656.40. Disposed assets still show their old NBV (boat 6400). No CSV or PDF exists for the fixed assets report (not in the export lists). `<script>` asset name is stored raw; shown escaped by Vue. |
| FA14 closed year | PASS (A11) | Close FY2027 (own lodge): register asset dated in 2027 -> 422 "books for 2027 are closed"; run depreciation (entry dated today) -> 422, nothing posted; dispose today -> 422 and the asset stays undisposed; dispose/register dated in the open FY2026 -> allowed. Admin and treasurer reopen -> 403 "Only a platform super admin"; superadmin reopen OK (closes row removed); treasurer then ran depreciation: 50/5 = 10.00. Budget save in the closed year is allowed (A11). |
| RB1 template validation | PASS | amount 0, negative, > max, "abc", frequency weekly, bad date, no vendor: 422. 1.005 stored as 1.01. `<img onerror>` vendor stored raw. |
| RB2 toggle / delete / 404 | PASS | Toggle off/on works (template 6 now inactive). Nonexistent id and another club's template id (club 21, id 9): 404, template untouched. Delete nonexistent: 404. (Deleting a real template was not run.) |
| RB3 generation | PASS with notes | Other clubs had no due active templates (only clubs 21/22 have two templates dated 2030), so I ran the command once (touches ALL clubs; it has no club option). Generated 5 bills totalling 1455.50: 100 + 90.50 + 1200 + 55 + 10. Disabled, future (12-01) and 2030 templates skipped. |
| RB4 account mapping | PASS | Bar & Dining Supplies -> 5200; Utilities & Fuel -> 5100; Insurance & Legal Fees -> 5300; category "Utilities" (name of expense account 5100) -> 5100; "Other" -> 5000. Each: Dr expense / Cr 2000 Accounts Payable. |
| RB5 dates | PASS with notes (A12) | Next run: monthly 11-01 -> 12-01; quarterly 10-31 -> 2027-01-31; annual 2026-02-28 -> 2027-02-28; 2026-11-02 -> 12-02. Bill due date = run date + 14 days (the annual insurance bill is due 2026-03-14, already past). Ledger date is always today. |
| RB6 idempotent | FAIL (A12) | The second command run created 1 more bill: the month-end template (next run 2026-01-31) was still behind after one bill (-> 2026-02-28 -> 2026-03-28). One bill per template per run, so a backlog needs one run (one day) per missed period, and the day drifts from 31 to 28. |
| RB7 VAT | PASS | VAT standard 20% enabled: template 120.00 -> bill gross 120, net 100.00, VAT 20.00; Dr 5100 100, Dr 2200 Input VAT 20, Cr 2000 120. Template 10.00 -> 8.33 + 1.67. Template amount is treated as VAT-inclusive. |
| RB8 closed year + command | FAIL (A13) | With FY2027 closed and a due template, `generateDueBills` throws LedgerRuleException (template unchanged); the artisan command crashes with the exception, so every club later in the loop gets no bills that day. |
| PERM1 coach, member (2), user 9 | PASS | page, CSV, PDF, budget, asset, run depreciation, dispose, recurring store/toggle/delete, close, reopen, financial-year-end: all 403. |
| PERM2 logged out | PASS | all 401. |
| PERM3 treasurer (4) | PASS | Allowed to run depreciation (302); reopen 403. |
| PERM4 other club's ids | PASS | account ids (budget) 422; asset id 404; template id 404; bill id (redirect with error). |

## Findings

**A13 High - one closed lodge stops recurring bills for every lodge.** Repro: close a financial year in a lodge that has an active template due today or earlier (POST financial-year/close, year 2027), then `php artisan app:generate-recurring-bills --env=e2e` -> "LedgerRuleException: The books for 2027 are closed" and the command stops. `RecurringBillService::generateDueBills` has no try/catch per template, and the command loops all clubs. Expected: skip (and log) the lodge, carry on with the others; ideally date the bill in the open period or flag it. Same lodge is skipped again every day forever.

**B1 High (probably owned by the invoices area) - invoice numbers collide across lodges.** `storeInvoice` numbers INV-{year}-{count of this lodge + 1} but `invoices.invoice_number` is globally unique. A lodge issuing its first invoice (INV-2026-0001) gets HTTP 500 (Unique violation invoices_invoice_number_unique) because club 1 already has it. Repro: POST /acc-e2e-b/admin/accounting/invoices with user_id=2,title,amount,due_date as admin of club 6 -> 500. Drafts fail too. Expected: number per lodge (unique club_id + number) or a global sequence. I could not seed invoices because of this.

**A8 Medium - bill-linked fixed asset credits the wrong expense account.** POST fixed-assets with bill_id=29 (a Bar & Dining Supplies bill that posted to 5200). Journal: Dr 1500 60 / Cr 5000 60. Expected: credit the account the bill actually charged (5200, or `expenseAccountForBill`). Result: 5200 overstated, 5000 understated. Also cost vs bill amount not checked.

**A5 Medium - depreciation periods are free text and not tied to the asset or the date.** (a) Run period "FY2026" after "2026" charges the same assets again (double charge). (b) An asset with purchase date 2030-06-01 is depreciated in "2026" and every later run (five charges, fully written off before it exists). (c) A full year is always charged in the first run whatever the month of purchase; entry date is always today, not the period. Expected: period must be a financial year number, validated; skip assets bought after the period end; pro-rata or document the convention; date the journal in the period.

**A7 Medium/Low - disposing an already disposed asset gives HTTP 500** (unhandled InvalidArgumentException; "This asset has already been disposed of." never reaches the user). Proceeds above 9,999,999,999.99 also 500 (numeric overflow). Expected: validation message.

**A1 Low/Medium - `budget_year` is not validated** (`/reports/budget_vs_actual/export?budget_year=abc|-5|99999999999|[]`, also the PDF and, by the same code, `?budget_year=abc` on the accounting page) -> 500. Expected: integer 2000-2100 or fall back to the current year.

**A3 Medium - budget vs actual CSV/PDF always export the current financial year.** The Export CSV / PDF links in ReportingTab.vue are `route('admin.accounting.reports.export', {clubSlug, report})` with no `budget_year`, so while viewing another year on screen the file is for the current one. Expected: pass the year being viewed.

**A2 Low/Medium - Budget vs actual totals add revenue and expense together** (total_budgeted 1020, total_actual 810.90, variance -209.10 in the Totals row). Expected: separate totals for income and expenditure (and a net).

**A4 Low/Medium - fixed asset validation gaps:** salvage > cost accepted (NBV then shows 500 for a 100 asset), salvage has no max, cost 0 accepted (zero-value journal entry), future purchase date accepted, disposal date before purchase date accepted. Expected: salvage <= cost, cost > 0, dates sensible.

**A12 Low/Medium - recurring bills run is not "complete" per run:** a template behind by n periods creates one bill per daily run, each posted dated today with a due date in the past; month-end dates drift (31 Jan -> 28 Feb -> 28 Mar ...). Expected: catch up all missed periods in one run, or document; use the original day-of-month.

**A9 Low - budgets can be set on non-income/expense accounts** (bank account accepted, never reported) and 3-decimal budgets are silently rounded.

**A10 Low - fixed assets list:** a disposed asset keeps its old net book value (boat shows 6400 after disposal); there is no CSV/PDF for the fixed asset register; the Comparative I&E has no PDF and is built from paid bills/invoices and meeting returns for the current and prior year only (manual journals and accruals are not in it, so it disagrees with the Profit & Loss figures), with no year selector.

**A11 Low - a closed year still lets budgets be edited** (budget 4000 changed to 450 for closed 2027), and a year can be closed without the earlier years being closed.

Also noted: registering a new asset (not from a bill) credits Retained Earnings ("Opening asset value") without touching cash, so buying an asset by hand raises equity; fine for an opening register, wrong for a purchase. Settings values for the year end and VAT are stored as strings ("4", "1") when the form posts urlencoded (harness artefact likely; `financialYearEndMonth()` casts, so no effect).

## Created outside the lodge
None (no Mailpit mail, no files). Templates 9/10 in clubs 21/22 belong to another session; I only did a toggle attempt on id 9 through my own slug (404, unchanged).

## Not run
Deleting a real recurring template (only nonexistent-id delete); fixed-asset PDF/CSV (none exists); superadmin posting into a closed year; templates with approval threshold enabled (pending_approval bills); treasurer on every URL (only depreciation and reopen); budget_year=abc on the HTML page (500 by code reading only; CSV/PDF confirmed); the browser/UI rendering of the budget form; running the real command with another club's due template present (none existed). Lodge data left in place: 8+ assets, 9 templates, 2 closed/reopened year records, VAT enabled, year-end April.


---

## Appendix: vatgift

# vatgift results (lodge acc-e2e-c, club id 7), run 2 Nov 2026

Harness notes: my own server on :8041 (8001 dropped for a while); jars `vatgift-*.jar`; helper `vg.sh`. Another session ("sec-iso") renamed club 7 (and clubs 1, 3, 4) to "sec-iso", and treasurer (user 4) was set to `deleted` in club 7 at 22:03 (coordinator re-added them; all treasurer checks below were done after that, VAT writes in the middle were done as admin 8). Club 7 contains 600 placeholder invoices `ACC-PAD-C-n` (status void, amount 0, no VAT) that I inserted by tinker to get past finding A1.

## Test table

| ID | Result | Note |
|---|---|---|
| V1 VAT settings validation | PASS with A8 | rate -1, 101, abc, empty: 422. 12.345 (3 decimals) accepted and stored as "12.345". vat_number >20 chars: 422. Scheme not in list: validated. |
| V2 invoice/bill VAT at 20% | PASS | gross 0.01/0.07/1.00/99.99/100.05/1234.56 -> net 0.01/0.06/0.83/83.33/83.38/1028.80, VAT 0.00/0.01/0.17/16.66/16.67/205.76; net+VAT = gross every row (hand-checked: e.g. 99.99/1.2=83.325 -> 83.33). Invoices and bills identical. |
| V3 VAT at 5% | PASS | 0.01->0.01/0.00; 0.07->0.07/0.00; 1.00->0.95/0.05; 99.99->95.23/4.76; 100.05->95.29/4.76; 1234.56->1175.77/58.79. All hand-checked. |
| V4 VAT at 0% | PASS | net = gross, VAT 0.00, no 2200 line posted. |
| V5 per-document override | FAIL (A4) | Invoice `vat_rate` 5 and 17.5 work (120.00 -> 114.29/5.71 and 102.13/17.87). 0 is read as "use default"; -5 silently gives 0% VAT; 150 accepted (net 48.00, VAT 72.00); "abc" silently default. Bill create ignores `vat_rate` (not in StoreBillRequest). No VAT field on either form. |
| V6 journals | PASS | Invoice: Dr 1200 gross / Cr 4000 net / Cr 2200 VAT. Bill: Dr 5100 net / Dr 2200 VAT / Cr 2000 gross. All entries balanced; no 2200 line when VAT is 0.00. |
| V7 VAT control = output - input | PASS | After the 20/5/0/override docs: 2200 credits 443.21 (= sum of invoice VAT), debits 327.63 (= sum of bill VAT), balance 115.58. |
| V8 VAT return CSV | PASS with A3, A5, A6 | Rows, Net Sales 1196.41, Output 239.27, Input 239.27, Net 0.00 hand-checked for the first 12 docs. Range = quarter of `quarter_start` (default current quarter), by created_at. Formula injection (`=HYPERLINK`, `+cmd`, `-2+3`) prefixed with `'`; quotes/commas escaped correctly. |
| V9 draft/void/deleted in the return | FAIL (A3) | Deleted invoice/bill (hard delete, journal voided) correctly gone. Draft and pending_approval documents ARE included (draft inv VAT 10.00, pending inv/bill VAT 250.00 each) although not in the ledger. |
| V10 VAT lock | PASS with A9 | After documents exist, changing scheme or disabling is refused (redirect with error, setting unchanged). Changing the default rate is allowed (20 -> 15): existing docs keep their own rate, new docs use the new rate. Editing a doc keeps its stored rate. |
| V11 edit posted document | FAIL (A2) | Edit amount of posted bill 120 -> 240 and invoice 120 -> 240: document and VAT return change, journal does not. |
| V12 treasurer report VAT | FAIL (A7) | Uses quarter starting `{year}-01-01`, wrong period for a financial year. CSV has no VAT at all. |
| V13 VAT export permission | PASS | `vat-return/export` = view_accounting: treasurer and admin 200; coach, member (user 2), user 9: 403; logged out 401. |
| G1 collections / Gift Aid maths | PASS with A10, A11 | 100+50 -> 37.50; 0.01 -> 0.00; 0.02 -> 0.01; 33.33 -> 8.33; 33.33+66.67 -> 25.00; 0.07+0.07 -> 0.04; 10.005 stored 10.01, claim 2.50; 99999999.99+99999999.99 -> 50,000,000.00 (accepted). Rule = 25% of the donation (20/80 basic rate), rounded to pence per collection. |
| G2 validation | PASS with A10 | negative cash 422; non-numeric 422; > 99,999,999.99 422; donor/counter id of another club 422; zero donation accepted (0.00 claim); 3 decimals accepted. |
| G3 schedule export | FAIL (A10, A12) | Arithmetic right, CSV safe. See A10/A12 for content. |
| G4 reconcile-auto | FAIL (A13, A14) | Idempotent (second and third run: 0 new, 4 journals, 4 reconciled). But matches on text only. |
| G5 reconciled-donations filter | PASS | `status=reconciled` returns the 4 reconciled collections, totals 183.36 / 45.84 (33.33+0.02+0.01+150.00; 8.33+0.01+0+37.50). |
| G6 gift aid summary | PASS | eligible 200,000,318.49; reclaimed 45.84; pending 50,000,033.79 (7 claims): hand-added. |
| G7 festival / member giving | PASS with A15 | Target saved; donated 249.99 -> no jewel; 250 -> jewel; 500 -> jewel and bar; member of another club 404; negative 422. Raised = collections (cash+cheque) + member giving = 200,000,318.49 + 999.99 = 200,001,318.48 (matches page). Target 0 gives 0%, no crash. |
| G8 Relief Chest export | BLOCKED (A16) | `ReliefChestExportService` is not used by any route or screen (only a test). |
| G9 treasurer report cross-check | PASS with A17 | General fund 4000 3349.14 = ledger credit; 5100 2906.44; 4310 49.50; income 3398.64; surplus 492.20. Charity collections total 200,000,318.49 = charity page. Financial year 2027 (Nov 2026, April year end). |
| P1 permissions | PASS | coach, user 2, user 9: 403 on all 9 GET and 6 POST URLs (vat, vat export, report CSV/PDF, giftaid export/reconciled/reconcile-auto/transactions, charity index/festival/collections/grants/target/giving). Logged out 401. Treasurer and admin allowed (treasurer: 200 on GETs, 422/302 on POSTs). |

## Findings

**A1 High. Invoice numbers collide, so creating an invoice returns HTTP 500.**
`invoices.invoice_number` has a global UNIQUE index, but the number is `INV-{year}-{count of THIS club's invoices + 1}` (AccountingInvoiceController::storeInvoice).
(a) Any second lodge's first invoice is `INV-2026-0001` and fails if another lodge has it. (b) Deleting an invoice (hard delete) that is not the newest makes the next number equal to the newest existing one, so every later create in that lodge fails until the count changes (reproduced: delete INV-2026-0621, next attempt gets `INV-2026-0622`, 500 "duplicate key invoices_invoice_number_unique"). Bills use the same scheme (no unique index) and silently produce duplicate bill numbers (BILL-2026-0018 twice).
Repro: POST `/acc-e2e-c/admin/accounting/invoices` (user_id=2, title, amount=1) in a lodge whose count+1 matches an existing number. Expected: a unique number per club (or a per-club unique index and max()+1). Actual: 500.

**A2 High. Editing a posted (unpaid) invoice or bill changes the document but not the ledger.**
`PUT .../bills/59` amount 120 -> 240 (doc 240.00 / net 200.00 / VAT 40.00; journal still 120 / 100 / 20). `PUT .../invoices/656` 120 -> 240 (doc 240 / 228.57 / 11.43; journal 120 / 114.29 / 5.71). Paying invoice 656 then posted Dr 1000 240 / Cr 1200 240, so Accounts Receivable is understated by 120 and income and VAT never reflect the extra. The VAT return then disagrees with account 2200. Expected: refuse the amount change after posting, or reverse and repost.

**A3 High. The VAT return includes draft and pending-approval documents (and anything not yet in the ledger).** `getVatReturnData` filters only on `vat_amount IS NOT NULL` and `created_at`. Draft invoice 60.00 and draft bill 60.00 each added 10.00; pending invoice and bill (1500.00) each added 250.00. Output VAT on the return 723.21 vs 463.21 posted to 2200 credits. Expected: only issued (unpaid/paid) documents. Also the return is by `created_at`, there is no invoice/tax-point date, so a bill entered late falls in the wrong quarter.

**A4 Medium. Per-document VAT rate is unvalidated or missing.** Invoice `vat_rate=150` accepted (VAT 72.00 of 120.00); `-5` becomes 0% silently; `0` is replaced by the club default (`?: null`), so an invoice cannot be zero-rated when the default is 20%; text silently uses the default. Bill create drops `vat_rate` (validated() keeps only listed keys), and neither form has a VAT field.

**A5 Medium. The VAT return screen and export can only show the current quarter.** The report card has no quarter selector and the export link carries no `quarter_start`. `?quarter_start=abc` and `?quarter_start[]=x` give HTTP 500. A mid-quarter start (`2026-07-15`) gives a part quarter labelled "2026 Q3".

**A6 Low. The VAT scheme setting changes nothing.** `not_registered`, `flat_rate`, `cash_accounting`, `annual_accounting` and `flat_rate_percent` are stored but never used: any enabled club charges standard VAT on all documents (also with `enabled=1` and `scheme=not_registered`). Cash accounting should count VAT on payment.

**A7 Medium. Treasurer's report VAT section uses the wrong period.** `build()` calls `getVatReturnData($club, "{$year}-01-01")`: for the 2027 financial year (1 May 2026 to 30 April 2027) it shows Q1 calendar 2027 (all zeros) although VAT of 115.58 was due in Q4 2026. The report CSV has no VAT block, and also leaves out the charity-fund ledger rows (account 4300 Relief Chest 125.00) that the PDF shows.

**A8 Low. VAT settings: 3-decimal rates accepted; `enabled` is saved as the string "1" from form posts and compared with `!==`.** A JSON boolean `true` posted over the stored string "1" counted as a locked change and was refused although nothing changed (the Vue form posts JSON, so the real UI should store a boolean; worth an `(bool)` cast).

**A9 Low. After the first VAT document VAT cannot be turned off or the scheme changed by an admin/treasurer ("contact support").** Works as designed. The default rate can still be changed at any time (no warning that it only applies to new documents).

**A10 High. Gift Aid is not tied to any declaration.** `storeCollection` hard-codes `is_gift_aid_eligible = true` and a claim of 25% for every collection (alms plate, raffle, charity box, anonymous, zero value). There is no donor declaration, name, address or postcode. A £20 raffle and a £199,999,999.98 anonymous plate (accepted, claim £50,000,000.00) both appear as claimable. Expected: only donations with a valid declaration are eligible, with a plausibility cap/confirmation.

**A11 Low. Zero-value collection accepted** (cash 0, cheque 0, Gift Aid eligible, appears on the schedule). Three-decimal amounts accepted (10.005 is stored as 10.01 but the claim is worked on 10.005).

**A12 Medium. The HMRC Gift Aid schedule is not usable for a claim.** Columns are only Ref, Date (the date the row was entered, not the donation date), Type, Donation, Reclaim, Status, Counter, Witness: no donor name/address/postcode, no donation date. It lists reconciled (already claimed) rows as well as pending ones, with no total row. Missing counter/witness are printed as invented names "Charity Steward" / "Assistant DC". CSV escaping is fine (a counter named `=HYPERLINK(...)` is prefixed with `'`).

**A13 High. Bank-deposit matching in `giftaid/reconcile-auto` posts income on a text match alone.** `MCFARLANE PAINTERS REFUND` +25.00 (matches `mcf`) was posted Dr 1000 / Cr 4300 Relief Chest income; `HMRC TAX REFUND CORP` +12.00 (matches `hmrc\s*tax`) posted Cr 4310 Gift Aid income. No amount check against the claim; no check that the bank line is not already matched elsewhere. The ledger bank account is the chart's 1000, not the bank account the line came from.

**A14 Medium. Each matched deposit marks the oldest pending collection "reconciled" whatever its amount or kind.** The 100.00 Relief Chest payout marked the 0.01 collection reconciled; 25.00 marked the 0.02 one; 12.00 the 33.33 one. "Gift Aid reclaimed" (45.84) is therefore meaningless, and a Relief Chest payout can consume a Gift Aid claim. Re-running is idempotent (checked three times).

**A15 Low. Festival target accepts bronze > silver > gold ordering errors** (bronze 900, silver 200, gold 400, platinum 800, target 0). Tier shown "Platinum Honor" for any amount above 800. `relief_chest_ref` free text (also `=cmd`, stored as is; it is CSV-escaped on export).

**A16 Info. `ReliefChestExportService` (Relief Chest deposit CSV and BACS schedule) is dead code:** no route or screen calls it.

**A17 Low. "Gift Aid Tax Reclaim Income" (4310) is reported in the General Fund** in the treasurer's report; only 4300/4400/4500 count as charity fund. Charity collections themselves (200,000,318.49) never reach the ledger, so charity fund collections and ledger charity income differ by design (known F30).

## Created outside the lodge
Nothing outside club 7. In club 7: 600 placeholder invoices `ACC-PAD-C-*`; 4 club_acc_members `acc-Donor/Counter/Witness`, one with an injection name (ids 434-436, 438); a `club_acc_bank_imports` row `acc-gift-aid-test.csv` (id 8) with 6 bank transactions (ids 563-568); charity collections 3-13; festival target `acc-fest`; VAT settings left enabled, scheme standard, default rate 15; approval threshold set then disabled. I started an extra `php artisan serve --env=e2e --port=8041` (log in the scratchpad `vatgift-serve.log`); kill it at the end.

## Not run
Non-UK currency lodge (club 7 has financial records so the currency cannot be changed through the UI); VAT behaviour with VAT disabled (locked, and I did not use the superadmin to unlock); approving the pending invoice/bill (second-approver pair); grants and the BACS schedule; PDF contents of the treasurer's report (only checked that it returns 200 and read the template); examiner role (no such user); the browser UI of the charity and VAT screens.


---

## Appendix: yearend

# Result: yearend (lodge acc-e2e-d, club id 8)

Method: curl against http://127.0.0.1:8001 with cookie jars `yearend-{tre,adm,coach,mem,a4,super}.jar`, DB checks with psql/tinker, PDFs read with a small PDFKit script (`swiftbin/pdftxt`). Today is 2 Nov 2026, year end April, so FY2026 = 1 May 2025 to 30 Apr 2026, FY2027 = 1 May 2026 to 30 Apr 2027 (all invoices/bills/payments post dated today = FY2027).

Environment notes: the app on :8001 went down once mid-run (connection refused) and came back; user 4 (treasurer) was switched to `past`/`pending` in my lodge by another session for about 15 minutes (coordinator restored it). For that period I used admin 8 as raiser. The lodge had no owner, so I temporarily set user 9 to owner to test export-all, then back to examiner.

Seed (real routes): opening balance bank £1,000 (2025-09-01); journals J1 donation Dr bank / Cr 4500 £200 (2025-10-01), J2 repairs Dr 5000 / Cr bank £75.50 (2026-02-10); invoice £50 (INV14); bill £30 (BILL6). The invoice could not be raised normally, see A1: 9 draft placeholder invoices (INV-ACC-D-001..009, £0) were inserted by SQL to move the per-club counter.

## Results

| ID | Result | Note |
|---|---|---|
| 1.1 threshold validation | PASS | text -> 422 "must be a number"; -5, 0 -> "at least 0.01"; blank with enabled -> required; enabled=maybe -> 422; > 99,999,999.99 -> 422. `enabled=0` with blank amount saves null. 100.005 saved as 100.005 (3 decimals kept, Low). `enabled` stored as string "1"/"0" from form posts (works). |
| 1.2 threshold update permission | PASS | coach 403, plain member 403, user from another lodge 403; treasurer and admin allowed. |
| 1.3 edge: exactly equal | PASS | threshold 100: bill £100 and invoice £100 -> pending_approval (rule is >=); £99.99 -> unpaid and posted at once; £250/£150/£400/£120 -> pending. Pending items post nothing to the ledger until approved. |
| 1.4 raiser cannot approve own | PASS | treasurer on own bill/invoice and admin on own bill/invoice: "You raised this bill, so you cannot also approve it" (flash error), status unchanged. |
| 1.5 other approver | PASS | admin approves treasurer's bill/invoice and treasurer approves admin's: status -> unpaid, one issue journal posted (BILL35 Dr 5100 100 / Cr 2000 100; INV32 Dr 1200 100 / Cr 4000 100). |
| 1.6 approve twice / not pending | PASS | second approve by the same or another user: "is not awaiting approval", no second journal. Approving an unpaid/draft/paid bill gives the same message. Other club's id -> 404. |
| 1.7 coach / member / outsider approve | PASS | 403 for coach, plain member, admin of another lodge. |
| 1.8 publish/pay before approval | FAIL (A2) | Paying a pending_approval bill (£250) and invoice (£150) is accepted by the raiser; also paying a draft bill (£300). Posts a payment journal with no issue journal (AP debited 250 and 300 with no credit), and the document is now "paid" so it can never be approved. Publishing a draft over the threshold correctly becomes pending_approval (bill and invoice). |
| 1.9 edit after posting/approval | FAIL (A3) | Raising a bill/invoice at £99.99 (no approval), then editing the amount to £5,000 is accepted with status unpaid, no approval, and the ledger entry stays at £99.99. Editing a pending bill's amount is allowed by anyone, including the approver (admin raised 500 -> 9,000 as admin edit then approved by the same admin; logged as "updated"). |
| 1.10 threshold off | PASS | enabled=false, £1,000 bill -> unpaid and posted; re-enabled at 100 afterwards. |
| 1.11 audit of approvals | PASS with A9 | approve rows have who (user_id and name in the summary) and time. Bill/invoice creation is NOT logged (only the journal entry is; a pending bill that has no journal leaves no log row at all). The threshold change itself is not logged. |
| 2.1 close validation/permission | PASS | year 1999, 2101, "abc", blank -> 422; coach, member, outsider -> 403; treasurer and admin may close. |
| 2.2 what a closed year blocks | PASS | FY2026 closed: journal dated 2025-05-01, 2026-03-01, 2026-04-30 -> 422 `errors.ledger` "The books for 2026 are closed. Reopen the financial year before posting to it."; opening balance and new-account opening balance blocked; asset purchase blocked; editing journals in the year (same date, or moving out, or moving a 2027 entry into it) refused (message under `errors.items`); 2026-05-01 (first day of FY2027) and 2025-04-30 (FY2025, never closed) allowed. FY2027 closed: new bill, new invoice, publish, pay, approve, depreciation run, asset purchase, opening balance all -> 422/error flash, nothing posted for bills. Journal dated 2027-06-01 (FY2028) allowed. |
| 2.3 edits/deletes of docs in closed year | FAIL (A4) | After FY2027 closed, the treasurer could edit the amount of a posted bill/invoice (60 -> 90, 70 -> 90) and delete both; deleting voids the ledger entries (JE225/226 -> void), so closed-year income and expenses changed. |
| 2.4 invoice raised/published in closed year | FAIL (A5) | HTTP 422 returned but invoice row 681 (£20) was created as `unpaid` with no journal; publishing draft invoice 679 (£40) left it `unpaid` with no journal. Bills are fine (transaction). After reopen, paying invoice 681 posts Dr bank 20 / Cr AR 20 with no issue entry. |
| 2.5 closing twice | PASS with A6 | no error; closer/time/notes overwritten (treasurer's close replaced by admin's "second close"); both closes appear in the audit log. |
| 2.6 close with unapproved docs or unreconciled lines | FAIL (A6) | No pre-check at all: FY2027 closed with a pending bill (£300), a pending invoice (£200), drafts. Not tested: unreconciled bank lines (Livewire, see Not run), but the code has no check. |
| 2.7 close a future year | PASS with A6 | FY2031 and FY2028-style future years close without warning; a journal dated 2030-08-01 is then blocked. Also earlier years can be left open while a later year is closed. |
| 2.8 reopen permission | PASS | treasurer 403 "Only a platform super admin can reopen...", admin 403, coach/member/outsider 403; superadmin (not a member of the lodge) reopens FY2027 (302). |
| 2.9 after reopen | PASS | new bill, publish, approve, pay path, journal, depreciation all work again. |
| 2.10 period close rows and audit log | PASS with A7 | accounting_period_closes rows correct (year, closed_at, closed_by, notes); log rows `closed`/`reopened` have actor. Reopening a year that was never closed (2030, and 2027 twice) answers success and writes a `reopened` log row (A7). |
| 2.11 superadmin in closed year | NOTE (A8) | superadmin posted a journal into closed FY2026 (JE213, £10) with no extra mark; by design in code (`is_super_admin` bypass) but see A8 for the effect on signed reports. |
| 3.1 year audit request | PASS | same auditor twice -> 422; non-lodge user -> 404; auditors 2 and 3 get one email each (Mailpit, subject "Please sign: Auditor sign-off — 2026 statement of accounts", `/sign/{40 chars}`); the mail shows no figures, only the label. |
| 3.2 resend | PASS | old token -> "found: false", new email with new token; wrong purpose 404; year with no audit 404; coach 403. Resend on a signed or cancelled request answers "Signature request resent." though nothing happens (Low). |
| 3.3 cancel | PASS | link then shows status `cancelled`; signing it is refused and status stays cancelled; cancel again says "cancelled" with no change (Low). Requesting again after cancel creates a new pending request and a new email. |
| 3.4 sign | PASS | no consent 422; empty typed name 422; drawn without image 422; bad method 422; valid typed signature stores method, IP, user agent, time, hash. A typed name does not have to match the signer (accepted "Mickey <b>Mouse</b> & Co"; shown escaped in sign page props and PDFs, no XSS). |
| 3.5 sign twice / decline after sign | PASS | refused ("already actioned"), data unchanged. A signed link still shows the signed record (read-only). |
| 3.6 tampered tokens | PASS | last char changed, truncated, <20 chars -> "found: false" (HTTP 200, not 404; POST with an unknown token 404). Token of another request works only for that request. Wrong purpose in the URL is not possible (purpose is not in the sign URL). Expiry: requests are created with `expires_at` null, so links never expire (Low). Sign pages are throttled (one 429 seen after several quick calls). |
| 3.7 status shown after signing | PASS with A10 | with one signed and one cancelled, JSON props show signed/cancelled per auditor, `audit_sign_off` null (not shown as signed). After both signed, `signed_off_at` set, PDF has "AUDITORS' SIGN-OFF" with both names and a certificate page. A10: auditors can be swapped after sign-off. |
| 3.8 signature PDF | PASS | `signatures/6/pdf` 200 for treasurer/admin; coach/member/outsider 403; cancelled request 404; another club's signature id 404. |
| 3.9 independent examiner | PASS | before the lodge is marked registered: 404; non-Examiner user -> form error; coach 403; request emails the examiner (member.a4@e2e.test) with a link; resend invalidates the old link; sign OK; re-request after sign -> "already been signed"; resend after sign says "resent" (Low). Export PDF: club name, "Registered charity number 1234567", "year ended 30 April 2026", currency £, receipts and payments 75.50 (before the superadmin entry), examiner "signature pending" before and "Dan Dixon — 2 November 2026" plus certificate page after. Treasurer and examiner can download the PDF, coach/member 403. The pending PDF already prints the whole "no material matters have come to my attention" statement (Low). |
| 4.1 treasurer report FY2026 | PASS for general fund / FAIL (A11) | CSV and PDF: 5000 £75.50 expenditure (hand check: J2 only, 1 May 2025 to 30 Apr 2026), net -£75.50, period label "1 May 2025 to 30 April 2026", "BOOKS CLOSED". The £200 donation on 4500 (in this year) appears nowhere in the CSV, PDF or screen (A11). After superadmin's £10 entry it shows £85.50, matches SQL. |
| 4.2 treasurer report FY2027 | PASS | CSV: 4000 £770 (invoices 50+100+120+300+200), 5000 £22.50 (12.50 + 7 + 3), 5100 £10,886 (30+100+400+9,000+1,000+300+25+20+11), 5900 £200 (600/3), income 770, expenditure 11,108.50, net -10,338.50; matches SQL on journals. The ledger as a whole balances (debits = credits = £14,499.00). Orphan payments from A2/A5 are visible as AP debit 550 with no matching credit. |
| 4.3 year-end month change | FAIL (A12) | Changing the year-end month to 12 while FY2026/2031 are closed re-slices the "closed" FY2026 (now 1 Jan to 31 Dec 2026, expenditure £108) with no refusal and no log; reverted to April. |
| 5.1 audit log coverage | PASS with A9 | journal create/edit (before/after items), invoice/bill update/pay/approve/void, opening balance (as journal), close/reopen/audited/examined all log with user and time. Not logged: bill/invoice creation, approval threshold, year-end month, VAT, charity commission, examiner/auditor requests, `audited` row has no user. |
| 5.2 activity endpoint permission | PASS with Low | adm/treasurer/superadmin/examiner 200; coach, member, other-lodge admin 403; unknown type 404; other club's entity ids (club 1 journal/bill/invoice 1) return empty list for my slug (club-filtered); non-numeric id -> HTTP 500 (TypeError, Low). |
| 6.1 export-all roles | PASS | owner (user 9 temporarily) 200 and superadmin 200 (zip, 38 CSVs); treasurer 403, admin 403, coach 403, member 403, outsider 403, anonymous 302 to login. Message says only the lodge owner can export. |
| 6.2 export contents | PASS | every row in every table has club_id 8; all journal items belong to this club's entries and accounts; no password/secret/token columns (README says secrets excluded); CSV injection safe (cells starting `=`, `@` are prefixed with `'`). Not included: signature_requests (the sign-off evidence) and the platform payment tables (Low). |

## Findings

**A1 High: Invoice numbers collide, so a lodge cannot raise invoices.** `invoices.invoice_number` is unique across the whole platform, but `storeInvoice` numbers per club as `INV-<year>-<count of this club's invoices + 1>`. Repro: as treasurer of any lodge with 0 invoices while another lodge already holds INV-2026-0001: `POST /acc-e2e-d/admin/accounting/invoices` (user_id=2, title, amount=50) -> HTTP 500 `invoices_invoice_number_unique`. The counter never advances (the failed insert adds nothing), so it fails forever. Second form of the same bug in one lodge: delete an invoice (hard delete lowers the count), then raise another -> 500 with a number already in use (seen with INV-2026-0013). Bills have the same numbering but no unique index, so deleting a bill produces two bills with the same number (BILL-2026-0009 twice, ids 74 and 92). Expected: number from a per-club sequence (max + 1) with a per-club unique index.

**A2 High: Pay a bill/invoice that is waiting for approval (or a draft) and the approval step is skipped.** Repro: threshold 100; treasurer raises a £250 bill (pending_approval); same treasurer `POST .../bills/{id}/pay` -> "marked as paid", status paid, journal Dr 2000 250 / Cr 1000 250 with no earlier Dr expense / Cr 2000. Same for invoices (Dr bank 150 / Cr AR 150) and for a draft bill (£300). Result: expense never recorded, accounts payable debit balance, document can never be approved. Expected: pay refused unless status is unpaid. Cause: `AccountingService::markBillAsPaid/markInvoiceAsPaid` check only "already paid".

**A3 High: Editing the amount of an issued bill/invoice does not touch the ledger or the approval rule.** `PUT .../bills/{id}` (status unpaid, amount 99.99 -> 5000) -> success; bill shows £5,000 unpaid, journal still £99.99, no approval. The later payment posts £5,000 against a £99.99 payable. Same for invoices. Expected: refuse the amount change once posted, or void and repost, and re-apply the threshold.

**A4 High: Closing a year does not lock existing bills/invoices.** In closed FY2027 the treasurer edited posted bill/invoice amounts and deleted both (`DELETE .../bills/94`, `.../invoices/680` -> success); the delete marks the posted journal entries void, so closed-year totals change. No `LedgerRuleException`. Expected: block edit/delete when the document's entry date is in a closed year.

**A5 High: Invoice created or published in a closed year is half saved.** `POST .../invoices` in closed FY2027 returns 422 "books are closed" but row INV-2026-0018 exists with status unpaid and no journal entry (the invoice is created, then the journal post throws outside any transaction). Same for `POST .../invoices/{draft}/publish` (status flips to unpaid, no entry). Paying such an invoice later posts a payment with no issue entry (AR credit 20 without a debit). Bills are wrapped in a transaction and are fine, except bill publish flows already rolled back. Expected: one transaction around create + post.

**A6 Medium: Year close has no safeguards.** No check for pending-approval or draft documents, unreconciled bank lines, or earlier years still open; future years (2031) can be closed; closing again silently overwrites closed_by/closed_at/notes (the original closer is lost from the period row, though both closes are logged).

**A7 Low: Reopen of a year that is not closed** answers success and writes a `reopened` audit row (seen for 2030 and 2027 twice).

**A8 Medium: Signed reports can change after signing.** The sign hash covers only purpose, ids and the signable row's updated_at, not the figures. After both auditors and the examiner signed FY2026 and the year was closed, a superadmin posted a £10 entry into the closed year (allowed by the `is_super_admin` bypass); the examiner PDF now shows expenditure £85.50 with the old signature and no warning (it was £75.50 when signed). The sign email and sign page show no figures either. Suggest: store a figures hash at signing and show "changed since signed", or refuse posting into a signed year.

**A9 Medium: Audit trail gaps.** Not logged: bill/invoice creation (a pending bill has no journal and so no log row at all), approval-threshold change, financial-year-end change, VAT/charity settings, who asked an auditor/examiner. `audited` row has no actor. Also the journal-edit error is under `errors.items` while posting uses `errors.ledger`, and moving a 2027 entry into a closed 2026 date says "The books for 2027 are closed" (names the wrong year). A new account with an opening balance in a closed year returns 422 but the account (code 9901) is already created (no transaction), so the retry says the code is taken.

**A10 High: Auditors can be replaced after sign-off.** After both auditors signed FY2026, `POST .../financial-year/audit` with other users (8 and 4) rewrote `auditor_one_user_id/auditor_two_user_id` on the signed audit row, kept `signed_off_at`, and the UI/props then showed "Carol Cooper and Elena Rostova" as the signing auditors while the signatures were Taylor's and Marcus's. The page answered "Signature requests sent to Carol Cooper and Elena Rostova" though no email was sent. The treasurer (user 4) was accepted as an auditor (the code comment says Rule 153 forbids Treasurer/Secretary). Re-posting without notes also wiped the audit notes. Restored to users 2 and 3.

**A11 Medium: Treasurer's report and Independent Examiner's report drop Charity Fund ledger income.** Accounts 4300/4400/4500 are taken out of the General Fund and the `charity_fund.ledger` block is computed but never shown. FY2026 has a £200 donation (4500, dated 2025-10-01): General Fund table, CSV, PDF ("Alms, Raffle & Donation Collections £0.00", "Net Charity Fund Movement £0.00"), the screen card and the examiner's report all omit it. Money recorded in the books is missing from both statements.

**A12 Medium: Year-end month can be changed with closed years.** `POST .../financial-year-end` month=12 accepted while FY2026 and FY2031 were closed; the closed FY2026 now covers 1 Jan to 31 Dec 2026 with different figures (expenditure £108 instead of £85.50). No guard, no log.

Low items: A13 `GET .../activity/bill/abc` -> 500 (int type error, should be 404); A14 threshold amount keeps 3 decimals (100.005); A15 depreciation and similar entries are dated the day of the run, not the period (period "2026" posts into FY2027); A16 resend/cancel on a non-pending request say success; A17 request expiry is never set so sign links never expire; A18 the pending examiner PDF already prints the finished statement.

## Created outside the lodge
- Mailpit: 7 emails to member@example.com, coach@example.com (auditor requests, subjects "Please sign: Auditor sign-off — 2026 statement of accounts") and 2 to member.a4@e2e.test (examiner, "Please sign: Independent Examiner's Report — 2026 accounts"); all addressed to @example.com / @e2e.test. Nothing deleted.
- Local scratch files under the scratchpad (`yearend-*`, `swiftbin/`, `yearend-export/`, `yearend-unz*`). No storage files besides the app's own PDF output (none stored).
- In my lodge only (for the coordinator to remove with it): club_user row for user 9 (examiner), 9 placeholder invoices, charity commission set to registered with number 1234567, closed years 2026 and 2031 left closed, account 9901 "acc-newacc", fixed asset "acc-asset open" with a 2027 depreciation entry.
- The treasurer's `past`/`pending` status in my lodge was changed by another session, not by me.

## Not run
- Bank reconciliation / statement import dated in a closed year, and "unreconciled bank lines at close" (Livewire/bank routes; code review shows no check).
- Hand check of the on-screen balance sheet and bank account closing balances (lodge has no bank account rows, so the report's bank block is empty).
- Expired sign link (no way to set `expires_at` through the app); drawn-signature upload.
- Treasurer Report January/leap-year range edges (covered by check:ranges).
- Owner role test used a temporary role change by SQL, not the UI.


---

## Appendix: volsafe

# volsafe: volume, attachments, permissions and isolation (lodge acc-e2e-e, club 9)

Run 2 Nov 2026 against the e2e app. Caveats first:
- The shared server (`php artisan serve`, one process) was saturated by other sessions (load average 22 to 75), so wall-clock curl times against :8001 were useless (a login page took 17 s). I started my own 4-worker server on :8011 for timings and the browser, and stopped it at the end. Even so, times below are inflated several times; **query counts are the reliable figure**. In-process request timings (kernel handle, authenticated user) are in the table.
- In my lodge, user 4 (treasurer) was set to status `deleted` and then removed by another session at about 22:03; I re-added him as active treasurer (club_user row) before the permission sweep. Treasurer 403s before that were this, not an app fault. Treasurer is also "past" in club 1 for some of the run; later active again.
- Data in the lodge: 25 bills acc-Vendor-01..25 (107.03 + 7.03 steps, sum 4,784.75), 5 invoices INV-2026-0003..0007 for user 2 (211.07, 222.14, 233.21, 244.28, 255.35 = 1,166.05), 2 draft placeholder invoices ACC-PH-1/2 (see A1), bank account "acc-Bank, acc-Main" (id 3, ledger 1010), 549 staged bank lines.

## Results

| ID | Result | Note |
|---|---|---|
| V1 import 500 lines (browser, Livewire) | PASS with A3, A4 | File 500 data rows: paid in 45,224.66, paid out 33,737.44, net 11,487.22. Preview showed 500 lines, total 11,487.22 (matches by hand), 1 duplicate of the earlier 50-line file, 499 new. Upload+parse+preview 3.8 s. Confirm 2.8 s. Staged total 11,623.98 = 11,487.22 + the 136.76 debit that was a duplicate (so the batch sum is right); table total 12,601.56 = 977.58 + 11,623.98. 549 distinct hashes. Preview response 845 KB, confirm request sends it back (A4). |
| V1 import 50 lines (service) | PASS | 53 queries for 50 rows, 449 ms (one INSERT per row, 9 ms/row: 500 rows about 2.5 s, linear). |
| V1 duplicate re-import of the 500 lines | PASS with A9 | Preview: 500 lines, 0 new, 500 duplicates. Confirm: no lines added (549 still), but an empty batch "imported successfully! 0 new transaction lines" is recorded (A9). |
| V1 accounting page (every tab) at 0 / 50 / 549 staged lines | FAIL (A2) | Queries per request: 90 / 312 / 2,565 (about 4.5 per unmatched line; every tab runs it, tabs home, sales, purchases, reporting, bank-accounts, reconciliation all identical). In-process time 0.35 s / 4 s / 3 to 17 s (loaded machine); HTML 540 KB / 566 KB / 830 KB. Under the 30 s PHP limit it returned 500 ("Maximum execution time of 30 seconds exceeded", logged for user 8) on the reporting tabs while the machine was busy. |
| V1 reconcile tab / reporting tab / each report | see row above | Reports are not separate requests: all reports are computed inside the single accounting index request, so each report URL costs the same as above. |
| V1 `/admin/bank-reconciliation` | PASS | 302 to the reconcile tab (12 ms); tab costs as above. |
| V1 reconciliation-workspace (Livewire) | PASS | 18 queries at 549 lines, 29 ms in-process (paginated 15, only the selected line gets suggestions). |
| V1 bank-accounts page | PASS with note | 20 queries both at 0 and 549 lines, but it eager-loads every transaction of every account (`with('transactions')`): rows grow with the import (A10). |
| V1 bank-imports page | PASS | 15 to 17 queries at any size, paginated (20). |
| V1 report exports CSV (account_summary, aged_payables, aged_receivables, balance_sheet, cash_summary, executive_summary, profit_and_loss, comparative, budget_vs_actual, provincial_due, vat-return, treasurer-report) | PASS | All 200 in under 0.12 s on the idle workers; treasurer-report/export-pdf 0.9 s, account_summary PDF 3.3 s first hit then 0.6 s. `export-all` 403 for treasurer and admin 8 (owner only presumably). |
| V1 5,000-line file | FAIL (A5) | Upload fine; the preview update took 27 s, returned 4.4 MB and the browser ended in a Livewire error dialog instead of a preview. Not imported. |
| V1 file near the limit (10.3 MB, 140,134 rows, under the 10 MB rule) | FAIL (A5) | Upload accepted, preview request ran 30 s and returned HTTP 500 (error page 843 KB). Nothing imported. |
| V1 11 MB file | PASS | After a full upload: "That file is too large. Statements can be up to 10 MB." |
| V2 attachments: PDF accepted (bill store, bill update, invoice update) | PASS | Stored on disk `local` at storage/app/private/{mediaId}/, never under storage/app/public; a request for /storage/{id}/ok.pdf gives 403/404. |
| V2 refused with message, nothing stored: .svg, .html, .php, executable (ELF), HTML saved as .pdf, PHP saved as .pdf, `x.pdf.php`, empty file, 10.6 MB file | PASS | 422 "The attachment field must be a file of type: pdf, png, jpg, jpeg, webp." or "must not be greater than 10240 kilobytes". Media count unchanged after each. Empty file gets the "file type" message, not "file is empty" (Low). |
| V2 a real PNG named .pdf | ACCEPTED (A7) | Stored as png-as.pdf with mime image/png; served as image/png. |
| V2 real PDF named `x.pdf.html` | ACCEPTED (A7) | Stored under that name, served `application/pdf` with `inline; filename="x.pdf.html"`. |
| V2 polyglots (PNG + PHP tail as .png and .jpg; PDF with JavaScript OpenAction as .pdf) | ACCEPTED (A7) | Allowed because they are valid files of an allowed type; they sit on the private disk and are never executed. The PDF is served inline. |
| V2 real PNG named `x.php.png` | FAIL (A6) | HTTP 500 with the Spatie exception text ("The extension `php` is not allowed ...") instead of a validation message. Nothing stored, no bill created. |
| V2 odd names (`a"b;c`, `../../../etc/x.pdf`, newline in name, `name"; filename=evil.html.pdf`) | PASS | Sanitised (`x.pdf`, `evil.html.pdf`, `nl%0Ax.pdf`, `a-b%22c`); no path escape; Content-Disposition stays well formed. |
| V2 download as treasurer, admin | PASS | 200, `Content-Type` from stored mime, `Content-Disposition: inline; filename="..."`, `X-Content-Type-Options: nosniff`, but `Cache-Control: public` (A8). |
| V2 download as coach, plain member, user 9 | PASS | 403 each. Logged out: 302 to /login. |
| V2 mediaId of club 1 (id 1, image; id 33, accounting) and a non-accounting media row I put in my lodge (id 20, collection images) via the acc-e2e-e URL | PASS | 404 each; unknown id 404. Non-numeric `/attachments/abc` gives 500 (A11). |
| V2 my attachment id via the club 1 URL | PASS | 404 (treasurer) / 403. |
| V2 replace attachment (invoice update, bill update) | PASS | New media row, old row and old file gone from disk. A rejected replacement (SVG, HTML) keeps the old file. |
| V2 delete attachment routes (bill, invoice) | PASS | Row and file removed, `media_id` null. Deleting a whole bill removes its attachment and voids the journal entry. |
| V2 invoice store with attachment | FAIL (A1) | 500 on the invoice number collision and the file is left orphaned (media row + file on disk with no invoice). |
| V3 GET sweep (32 accounting/banking/report/export URLs x treasurer, admin 8, coach 3, member 2, user 9, logged out) | PASS | Treasurer and admin: 200 (independent-examiner-report/export-pdf 404 with no report, export-all 403). Coach, member, user 9: 403 on every URL. Logged out: 302 to /login on every URL. Heavy index tabs were checked for the denied roles and for treasurer/admin earlier (200). No data returned to any role that should not see it. |
| V3 foreign ids through my URL (club 1 / 8 ids: bills, invoices, contacts, member contacts, journal entries, activity logs, attachments, signatures 4 and 7, bank accounts; GET plus DELETE/PUT/pay/approve/publish/attachment delete/dispose/sync/cancel) | PASS | 404 on all (or 422 when the form request validates before the lookup, and the code reads `where('club_id', ...)` for each). Club 1 rows unchanged (bank transactions 1 and 2 still matched). Activity log for a foreign id returns `{"entries":[]}`, no data. |
| V3 my ids through the club 1 URL (as treasurer, who is in both) | PASS | Same list incl. bank account toggle, reconcile, ignore, statement-lines delete/restore: 404 or no effect; my rows unchanged. `contacts/member/2/edit` 200 only because user 2 is also a member of club 1. |
| V3 Livewire: foreign transaction ids (`selectTransaction`, `ignoreLine`, `openManualDrawer`, `reconcileSuggested`, `deleteTransaction`, `deleteImportBatch`) | PASS | 404 for each, club 1 untouched. |
| V3 Livewire: tampered locked `clubSlug`, tampered snapshot data | PASS | Both rejected with 500 (Livewire "corrupt data" / locked property); no state change. |
| V3 Livewire: admin's snapshot replayed by coach, member, user 9 on `/livewire/update` | PASS | Coach and member 403; treasurer 200 as expected. (User 9 request timed out in my script; the persistent middleware answers 403 the same way for a non-member.) |
| V3 Livewire: wrong-club or missing target id in `reconcileSuggested` / `executeManualAllocation` | FAIL (A12) | Success message, line marked matched. |
| V3 reconcile an already matched line again, or with a different bill | FAIL (A13) | Pays an unrelated bill. |
| Figures | PASS | Cash and balance sheet re-added by hand from journal items: AR 1,166.05 (5 invoices), expenses 5100 4,784.75 (25 bills) + 5000 256.76, AP credits 4,904.75, surplus -3,875.46, assets 558.99 = liabilities 4,434.45 + equity -3,875.46. |

## Findings

**A1. High. Invoice numbers collide across lodges; the second lodge cannot create invoices.**
`AccountingInvoiceController::storeInvoice` builds the number as `INV-<year>-` + (invoices already in this club + 1), but the `invoices.invoice_number` unique index is global (`invoices_invoice_number_unique`). Club 1 holds INV-2026-0001, 0002 and club 8 holds 0008, so in a new lodge: `POST /acc-e2e-e/admin/accounting/invoices` with `user_id=2 title=x amount=211.07 due_date=2026-11-30` returns 500 (`duplicate key ... invoices_invoice_number_unique`). I only got invoices into my lodge by inserting two placeholder rows (ACC-PH-1/2) so the count reached 2. Expected: numbers unique per lodge (index on club_id + number) and allocated safely; also deleting an invoice lowers the count and reuses a number, and two simultaneous creates race. Related: the attachment is saved before the failing insert, so a failed create leaves an orphan media row and file (media count 13 to 14 with the 500). Deleting or voiding is not needed to reproduce.

**A2. High. Accounting screen cost grows with every unmatched bank line (about 4.5 queries each) and is unbounded.**
`ReconciliationScreenData::build()` calls `suggestMatches()` for **every** unmatched line (no limit) and each call re-queries the club, all unpaid subscriptions (+users), unpaid invoices, unpaid bills, event bookings and the match rule. Every accounting tab runs it, because the controller builds all tabs' data. Measured on `/acc-e2e-e/admin/accounting`: 90 queries with 0 lines, 312 with 50, 2,565 with 549 (HTML 540 KB to 830 KB; the 549 lines also grow with the number of unpaid documents, N x M rows). 3 to 17 s in-process and 500 "Maximum execution time of 30 seconds exceeded" while the machine was busy; a lodge importing a year of statements (2 to 5 thousand lines) will not load the accounting page at all. Fix: load unpaid documents and rules once, match in memory, and page or cap the unmatched list (the workspace already pages at 15).

**A3. Medium. Bill and invoice reconciliation posts cash to account 1000, not the bank account the line belongs to; cash summary and the balance sheet then split or hide cash.**
`markBillAsPaid` / `markInvoiceAsPaid` use `getAccount($club,'1000')`. For a line on bank account 3 (ledger 1010) the matched bill posted Dr 2000 / Cr 1000 (107.03, 114.06, 121.09, 128.12), while a generic match posts to 1010 (136.76). Result in my lodge: 1000 = -470.30, 1010 = -136.76. `AccountingReports` cash summary (`whereIn('code', ['1000','1100'])`) and "Total cash reserves" then show -470.30 and leave out 1010 (true cash -607.06). Also: a bank account created at `bank-accounts/store` (AccountingGatewayController) gets a new ledger account 1010 even when it is the lodge's first, whereas the Livewire bank accounts page uses 1000 for the first; and the account's `opening_balance` (1,000 entered) is posted nowhere, so no report includes it.

**A4. Medium. The statement preview is held in the Livewire snapshot.**
`previewBundle` holds every parsed line (`public ?array`), so a 500-line file makes an 845 KB response and the confirm request posts the same 845 KB back; any other Livewire call while the preview is open does too. It also renders every line in the modal. See A5 for the limit.

**A5. Medium. Large statements fail rather than being refused.**
A 5,000-line CSV (325 KB): the preview update took 27 s and 4.4 MB and the page ended in a Livewire error dialog. A 10.3 MB file (140,134 rows, under the 10 MB rule): 30 s then HTTP 500. An 11 MB file is refused properly. Either cap rows (and say so) or parse/preview a summary and page the lines. Import itself inserts row by row (9 ms/row measured).

**A6. Low/Medium. A genuine image named `x.php.png` gives a 500 (Spatie `FileNameNotAllowed`) instead of a validation message.** Safe (nothing stored, no bill), but the user sees a server error. Repro: `POST accounting/bills` with `attachment=@ok.png;filename=x.php.png`.

**A7. Low. Attachment checks go by content type only, not by the name.** A PNG named `.pdf`, a real PDF named `x.pdf.html`, polyglot PNG/JPG with a PHP tail and a PDF with a JavaScript OpenAction are accepted. They are on the private disk and served with the stored mime type and `nosniff`, so I could not make anything execute, but the stored name can disagree with the content, and PDFs with scripts are shown inline. Consider forcing `Content-Disposition: attachment` and renaming to the detected extension.

**A8. Low/Medium. The attachment download is sent with `Cache-Control: public`** (`response()->file()` default; the 403/404 pages are `private`). A private financial document may be kept by a shared proxy. Add `Cache-Control: private, no-store`.

**A9. Low. Re-importing a file that is entirely duplicates records an empty batch** (`club_acc_bank_imports` row with 0 lines, "imported successfully! 0 new transaction lines"). Better to refuse or say "nothing new".

**A10. Low. `BankAccountsIndex` eager-loads all transactions of all accounts** (`with(['account','transactions'])`) just to show counts; use `withCount`/sums. Grows with every import.

**A11. Low. Non-numeric ids give a 500, not 404:** `GET /acc-e2e-e/admin/accounting/attachments/abc` (the `int $mediaId` argument throws a TypeError); the same applies to the other `{id}` routes without `whereNumber`.

**A12. Medium. `BankReconciliationMatcherService::reconcileTransaction` silently posts a default entry when the target is not found.** `reconcileSuggested(12, 'supplier_bill', 1)` (id 1 is club 1's bill, not in this lodge) answered "Transaction reconciled successfully!", marked line 12 matched and posted Dr 5000 / Cr 1010 136.76 (`RECON-12`). Same for member_subscription and invoice. No cross-lodge data was read or changed, but a stale or tampered target yields wrong books with no error. Expected: refuse when the target is not in the club.

**A13. Medium. No server-side check that a line is still unmatched, or that amount and direction fit the target.** In the workspace component (and the accounting `reconcile` route): `reconcileSuggested(12, 'supplier_bill', 8)` on the already matched 136.76 bank-charge line paid the 114.06 bill; `reconcileSuggested(16, 'supplier_bill', 9)` used a **credit** of 239.49 to pay a 121.09 supplier bill; `reconcileSuggested(15, 'supplier_bill', 10)` used the same debit line (107.03, already matched to another bill) for a 128.12 bill. Each marks the bill paid and posts Dr 2000 / Cr 1000 for the bill amount, so one bank line can settle several bills of different sizes. Reachable by a treasurer sending a tampered call; the UI itself does not offer it.

Also noted, not numbered: the 404 JSON for a missing record includes exception class, file and stack trace when the request asks for JSON (APP_DEBUG is on in e2e; should be off on staging/production). The `dev` PHP 8.4 deprecation `str_getcsv(): the $escape parameter must be provided` is emitted once per CSV line (`BankStatementParserService` line 42), flooding output and logs. The Livewire upload route allows 60 uploads a minute per user (429 after my user shared it with other sessions; the page then says only "The statementFile failed to upload.").

## Created outside the lodge
- Nothing in other lodges. Club 1 was only read (and requests that could have changed it came back 404; rows verified unchanged).
- `club_user`: re-added user 4 as active treasurer in club 9 (my lodge) after another session removed it.
- Media row id 20 (copy of a page image row) in my lodge; files under `storage/app/private/{mediaId}/` (about 20 small files plus two 10 MB PDFs, some deleted by the tests); invoice placeholder rows ACC-PH-1/2.
- Scratchpad (all prefixed volsafe-): CSVs (50, 500, 5,000, 10.3 MB, 11 MB), attachment test files (`volsafe-att/`), scripts, jars, `volsafe-sweep-out.txt`. I started extra servers on :8011 (4 workers) and :8012 (file server for the browser) and stopped them. A browser tab `seed` (opened by me) is still open on the bank import page.
- No Mailpit mail was sent.

## Not run
- 5,000-line import (only the preview was tried, which fails); a clean timing of each report with an idle machine (counts and in-process times only).
- Mutating foreign-id tests on club 6/8 records for fixed-asset dispose and recurring-bill toggle (read the controllers instead: all use `where('club_id', ...)`), and the reverse direction for those two (no data in my lodge).
- Livewire replay by user 9 (script timed out under load; coach and member gave the expected 403).
- A PDF attachment from a real bank statement tool; virus scanning (none exists in the app).
