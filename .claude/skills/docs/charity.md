Here is the comprehensive, production-grade technical specification prompt covering the entire Charity & Benevolence domain. You can save this file directly as `.tasks/charity-and-relief-chest-spec.md` or pass it straight to your IDE agent.

---

```markdown
# TASK SPECIFICATION: Charity, Relief Chest & Member Philanthropy Domain

## 1. Context & Architectural Isolation
We are extending our membership club / Masonic lodge domain (`app/Domains/ClubAccounting/`) integrated with Liberu Accounting.

### Core Architectural Rules:
- **Upstream Safety:** DO NOT modify any Liberu core files or vendor packages. All models, migrations, views, and services must reside strictly in `app/Domains/ClubAccounting/`.
- **Database Prefix:** All new database tables must use the `club_acc_` prefix.
- **Double-Entry Ledger Integration:** Charitable grants and cash deposits must post standard double-entry records into Liberu's ledger using its existing models (`Transaction`, `Account`, `JournalEntry`):
  - `4210 - Charity Collection (Alms)`
  - `4220 - Charity Raffle Proceeds`
  - `4230 - Member Charitable Donations`
  - `1210 - Uncleared / Unpresented Cheques` (Liability account for issued cheques)
  - `5200 - Charitable Grants Paid` (Expense)
  - `1220 - SumUp / Card Clearing`
- **Provincial Appeal Segregation:** Direct continuous giving from members to Province/MCF (e.g., Durham 2029 Festival via Relief Chest E1418) does NOT flow through the lodge's bank account. It is tracked as an aggregate statistical appeal ledger, updated periodically from Provincial returns.

---

## 2. Directory Structure to Scaffold

```text
app/Domains/ClubAccounting/
├── Commands/
│   └── SyncAppealMilestonesCommand.php
├── Enums/
│   ├── CharityChannel.php ('alms', 'raffle', 'personal_donation', 'special_appeal')
│   ├── GrantPaymentMethod.php ('cheque', 'bacs', 'relief_chest_voucher')
│   ├── GrantStatus.php ('unpresented', 'cleared', 'cancelled')
│   └── HonorificLevel.php ('steward', 'vice_patron', 'patron', 'grand_patron')
├── Livewire/
│   ├── Admin/
│   │   ├── CharityDashboard.php           <-- Steward admin view
│   │   ├── IssueGrantModal.php            <-- Cheque & donation issuance modal
│   │   ├── CashTallySheetModal.php        <-- On-the-night alms & raffle counter
│   │   └── ProvincialAppealUpdateModal.php<-- Updates verified aggregate & honors
│   └── Portal/
│       └── MemberCharityImpact.php        <-- Member-facing transparency view
├── Models/
│   ├── ClubCharityCollection.php
│   ├── ClubCharityGrant.php
│   ├── ClubAppeal.php
│   └── ClubMemberHonorific.php
├── Resources/
│   └── views/
│       ├── admin/
│       │   ├── charity-dashboard.blade.php
│       │   ├── modals/issue-grant.blade.php
│       │   ├── modals/cash-tally.blade.php
│       │   └── pdf/presentation-letter.blade.php
│       └── portal/
│           └── member-charity-impact.blade.php
└── Services/
    ├── Charity/
    │   ├── GrantLifecycleService.php      <-- Issues grants, handles cheque matching
    │   ├── ReliefChestExportService.php   <-- Generates MCF CSV remittance schedule
    │   └── CashCollectionPostService.php  <-- Posts meeting cash alms/raffles to ledger
    └── Matching/
        └── Rules/ChequeMatchRule.php      <-- Injects into BankMatcherService

```

---

## 3. Database Schema & Migrations (`club_acc_*`)

Generate Laravel migrations for the following tables:

### 1. `club_acc_charity_grants`

Tracks all outgoing philanthropic grants and unpresented cheques:

* `id` (ULID or bigint primary key)
* `club_id` (foreign key / tenant scope)
* `meeting_id` (foreign key nullable, links to regular meeting where vote passed)
* `recipient_name` (string, e.g., "Great North Air Ambulance Service")
* `charity_number` (string nullable, e.g., "1028638")
* `category` (string, e.g., "Emergency Medical", "Hospice", "Youth & Rescue")
* `amount` (decimal 10,2)
* `payment_method` (enum: `cheque`, `bacs`, `relief_chest_voucher`)
* `cheque_number` (string nullable, indexed for rapid bank matching)
* `authorized_date` (date)
* `lodge_minute_reference` (string nullable, e.g., "Minute 8, October 2026 Summons")
* `status` (enum: `unpresented`, `cleared`, `cancelled` default `unpresented`)
* `statement_line_id` (foreign key nullable to `club_acc_bank_statement_lines`)
* `cleared_at` (timestamp nullable)
* `notes` (text nullable)
* `timestamps`

### 2. `club_acc_charity_collections`

Tracks meeting alms, raffles, and personal donations:

* `id` (ULID or bigint primary key)
* `club_id` (foreign key)
* `meeting_id` (foreign key nullable)
* `collection_date` (date)
* `channel` (enum: `alms`, `raffle`, `personal_donation`, `special_appeal`)
* `amount` (decimal 10,2)
* `payment_method` (enum: `cash`, `card_sumup`, `bacs`)
* `donor_member_id` (foreign key nullable to `members` table)
* `gift_aid_eligible` (boolean default false)
* `statement_line_id` (foreign key nullable to `club_acc_bank_statement_lines`)
* `remitted_to_relief_chest_at` (timestamp nullable)
* `timestamps`

### 3. `club_acc_appeals`

Tracks Provincial Festival appeals (e.g., Durham 2029 Festival):

* `id` (ULID or bigint)
* `club_id` (foreign key)
* `name` (string, e.g., "Province of Durham 2029 Festival")
* `relief_chest_number` (string, e.g., "E1418")
* `target_amount` (decimal 10,2, e.g., 25000.00)
* `bronze_target` (decimal 10,2)
* `silver_target` (decimal 10,2)
* `gold_target` (decimal 10,2)
* `platinum_target` (decimal 10,2)
* `current_provincial_total` (decimal 10,2 default 0.00, verified aggregate from Province)
* `last_provincial_return_date` (date nullable)
* `end_date` (date)
* `is_active` (boolean default true)
* `timestamps`

### 4. `club_acc_member_honorifics`

Tracks individual member qualification for Festival Jewels and Bars:

* `id`
* `club_id` (foreign key)
* `appeal_id` (foreign key to `club_acc_appeals`)
* `member_id` (foreign key to `members`)
* `honorific_level` (enum: `steward`, `vice_patron`, `patron`, `grand_patron`)
* `qualified_date` (date)
* `jewel_presented` (boolean default false)
* `presented_meeting_id` (foreign key nullable to `meetings`)
* `timestamps`

### 5. Schema Alteration: Members Table

Add Gift Aid tracking to the existing members table:

* `has_gift_aid_declaration` (boolean default false)
* `gift_aid_declaration_date` (date nullable)

---

## 4. Backend Services & Integration

### A. Cheque Issuance & Ledger Posting (`GrantLifecycleService`)

When a grant is recorded in the admin dashboard:

1. Insert record into `club_acc_charity_grants` with status `unpresented`.
2. Post double-entry transaction into Liberu Ledger:
* **Debit:** `5200 - Charitable Grants Paid` (£250.00)
* **Credit:** `1210 - Uncleared / Unpresented Cheques` (£250.00)


3. If "Generate Formal Presentation Letter" was checked, compile PDF using `pdf/presentation-letter.blade.php` containing the Lodge Seal, Master & Charity Steward signatures, recipient charity details, and lodge minute citation.

### B. Reconciliation Auto-Matcher Hook (`ChequeMatchRule`)

Extend `BankMatcherService` with a rule for cheques:

* When a bank line has reference matching `/(?:CHEQUE|CHQ)\s*(\d+)/i`:
* Extract the cheque number.
* Query `club_acc_charity_grants` where `cheque_number = ?` and `status = 'unpresented'`.
* If found and amount matches:
* Auto-suggest: Contact = `recipient_name`, Account = `1210 - Uncleared Cheques`.
* Upon reconciliation click **[OK]**:
* Post Debit `1210 - Uncleared Cheques` / Credit `1200 - Bank Current Account`.
* Update grant: `status = 'cleared'`, `cleared_at = transaction_date`, `statement_line_id = line_id`.







### C. Cash Tally Sheet Post Service (`CashCollectionPostService`)

On meeting nights, the Charity Steward enters coin, note, and card counts:

* Posts total alms cash to `4210` and raffle tickets to `4220`.
* Holds balance in `1230 - Undeposited Cash / Alms Plate` until the bank statement shows the counter cash deposit, reconciling it to £0.00.

### D. MCF Relief Chest Remittance Generator (`ReliefChestExportService`)

Generates the official CSV schedule required by the Masonic Charitable Foundation (MCF):

* Columns: `Title`, `First Name`, `Last Name`, `Address Line 1`, `Postcode`, `Donation Date`, `Amount`, `Gift Aid Declared (Y/N)`, `Relief Chest No (E1418)`.
* Filters personal donations from `club_acc_charity_collections` not yet flagged with `remitted_to_relief_chest_at`.

---

## 5. User Interface 1: Admin Charity Steward Dashboard

Build Livewire Component `App\Domains\ClubAccounting\Livewire\Admin\CharityDashboard`:

### Layout & Elements:

1. **Header & Actions:**
* Active Appeal Badge (e.g. `★ Festival 2029 Appeal`).
* Button **[ Export MCF Remittance ]**: Triggers CSV download.
* Button **[ Record Cash Tally ]**: Opens on-the-night alms/raffle calculator.
* Button **[ + New Grant / Cheque ]**: Opens `IssueGrantModal`.


2. **Top KPI Cards (4-Grid):**
* **Total Raised YTD:** Dynamic sum of all collections in current Masonic year.
* **Relief Chest Balance:** Verified balance + pending unremitted total.
* **Unpresented Cheques:** Sum of grants where `status = 'unpresented'`, showing count.
* **Gift Aid Claimable:** 25% tax uplift calculated on eligible personal donations.


3. **Provincial Festival Appeal Sync Panel:**
* Displays Bronze, Silver, Gold, Platinum threshold meters.
* Combines MCF confidential Direct Debits with local lodge collections:
`Total Festival Contribution = MCF Aggregate + Reconciled Lodge Collections`.
* Button **[ Update Provincial Return ]**: Modal to input the new aggregate total and log members awarded Festival Jewels/Bars.
* **Jewel Presentation Alert Queue:** Highlights brethren eligible for jewel investiture on the next regular meeting summons.


4. **Active Grants & Unpresented Cheque Drawer:**
* Table of issued cheques, displaying days unpresented (`14d`, `45d`, warning badge if `>90d`).
* Visual badge for `Unpresented` (Amber) vs `Cleared` (Green).



---

## 6. User Interface 2: Member Portal Charity View

Build Livewire Component `App\Domains\ClubAccounting\Livewire\Portal\MemberCharityImpact`:

### Layout & Elements:

1. **Philanthropic Hero Banner:**
* Lifetime giving total (all-time grants sum).
* Total number of local community causes supported.
* Current Masonic year progress.


2. **Festival Progress Tracker:**
* Animated visual progress bar showing percentage to next tier (e.g., Silver achieved, £3,130 to Gold).
* Explanatory callout explaining that personal giving is confidential between member and the MCF.
* Quick link to set up continuous giving direct debits via the Provincial website.


3. **Community Grants & Donations Directory:**
* Filterable table by Masonic Year.
* Displays: Beneficiary Charity Name, Charity Commission Registration Number, Purpose Category tag (Emergency Medical, Hospice, Youth, etc.), Date Issued, Payment Ref, and Grant Amount.
* Read-only, verified against Liberu General Ledger Account 5200.



---

## 7. Deliverable Expectations

* Fully functional Laravel Livewire 3 components and Blade templates.
* Complete migrations with indexes on foreign keys, cheque numbers, and dates.
* Clean Tailwind CSS styling matching our dark slate navigation and modern card design system.
* Zero placeholder comments in core accounting logic or math calculations.

```

***

### How this completes the system:
1. **Reconciliation Engine:** Automatically detects and clears cheque numbers (`CHEQUE 100482`) and tags incoming BACS alms and raffle money.
2. **Admin Charity Dashboard:** Gives the Charity Steward complete tracking over unpresented cheques, cash tallying, Relief Chest remittances, and Provincial Festival milestones.
3. **Member Portal:** Lets members see the tangible community impact of their giving, track the lodge's Festival award tiers, and understand how to qualify for breast jewels without violating giving confidentiality.

```