Act as a Principal Full-Stack Engineer and Laravel Solutions Architect.

I need you to design and implement an end-to-end "Member Subscriptions & Prepayment Ledger" module for my existing club web application.

### Context & Accounting Requirements
- Members pay an annual subscription ("Subs") due on April 1st each year (e.g., £240.00).
- Payment Methods:
  1. Lump-sum payment (paid on or near April 1st).
  2. Monthly advance payments via Direct Debit / Standing Order throughout the preceding 12 months.
- Accounting Principle (Deferred Income / Subscriptions in Advance):
  - Do NOT generate 12 individual monthly invoices.
  - Monthly Direct Debit payments must be recorded as incoming receipts credited to a liability account ("Subscriptions in Advance" / Member Prepayment Ledger).
  - On April 1st, a single Annual Subscription Invoice is generated.
  - The system automatically executes a drawdown transaction: accumulated prepayments clear the invoice (moving funds from Liability to Earned Subscription Revenue).
  - Surplus handling: Any overpayments remain on the member's account as general credit (usable for future subs or dining/festive board fees).
  - Shortfall handling: If accrued prepayments are less than the annual invoice, the remaining balance is flagged as due, with an outstanding balance shown.

---

### Deliverables Required

1. **Database Schema & Migrations:**
   - `subscriptions` / `subscription_plans` (annual fee, billing season, start date).
   - `member_prepayments` or `member_ledger_entries` (tracks monthly direct debit inflows, payment references, date cleared, amount).
   - `invoices` & `invoice_items` (annual invoice, total amount, status: unpaid, partially_paid, paid).
   - `invoice_payments` / `allocations` (records the drawdown link between a prepayment credit and the annual invoice).
   - Ensure proper indexing, foreign key constraints, and multi-tenant isolation (`club_id` / `member_id`).

2. **Core Backend Business Logic & Services:**
   - `RecordPrepaymentService`: Records an incoming monthly direct debit and updates the member's unallocated credit balance.
   - `AnnualInvoiceGenerationService`: Batch job run on April 1st that creates the yearly invoice for active members.
   - `DrawdownSettlementService`: Automatically applies available member credit balances against the newly created annual invoice, updating invoice status and moving accounting ledger balances.

3. **Frontend UI Components (Blade / Livewire or Tailwind Components):**
   - **Member Details Page / Portal View** with 4 modular info panes:
     - **Pane 1: Subscription Status Card** (Current season standing, membership tier, renewal date).
     - **Pane 2: Next Season Funding Progress Meter** (Progress bar displaying target amount e.g. £240, total accrued cash e.g. £180 [75%], active monthly direct debit amount, and projected funding status for April 1st).
     - **Pane 3: Payment History Table** (List of monthly direct debits/standing orders received, reference numbers, cleared dates).
     - **Pane 4: Invoices & Annual Statements** (Historical annual invoices, paid status, and download links).

4. **Treasurer / Admin Reconciliation Dashboard:**
   - Summary view of total "Subscriptions in Advance" liability held by the club.
   - List of members categorized by status: `Fully Funded in Advance`, `On Track`, `Behind / Shortfall`, and `Lump Sum Payers`.
   - Quick action to manually record or import bank transfers/direct debits.