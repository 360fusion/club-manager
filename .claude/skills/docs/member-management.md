# TASK SPECIFICATION: Decoupled Club Accounting Domain Module

## 1. Architectural Mandate: Upstream Isolation
We are integrating custom membership club and Masonic lodge accounting capabilities into an existing application that uses **Liberu Accounting ERP**.
To guarantee that we can update Liberu Accounting via `git pull` or `composer update` without code collisions or merge conflicts, **YOU MUST NOT MODIFY ANY CORE LIBERU FILES, VENDOR PACKAGES, OR ORIGINAL LIBERU MIGRATIONS.**

All code, services, migrations, and views for this task must live in a self-contained domain namespace:
- Target Directory: `app/Domains/ClubAccounting/`
- Service Provider: `app/Domains/ClubAccounting/Providers/ClubAccountingServiceProvider.php`
- Database Prefix: All new tables must use the `club_acc_` prefix (e.g., `club_acc_bank_statement_lines`).
- Interoperability: Interact with Liberu strictly as a consumer by importing its public models/services (e.g. `Liberu\Accounting\Models\Account`, `Liberu\Accounting\Models\Invoice`, `Liberu\Accounting\Models\Transaction`).

---

## 2. Domain Directory Structure
Ensure all files are generated within this bounded context:

```text
app/Domains/ClubAccounting/
├── Commands/
│   └── ProcessAnnualSubscriptionRenewals.php
├── Enums/
│   ├── MidYearBillingPolicy.php ('full_annual', 'pro_rata')
│   ├── StatementLineStatus.php ('pending', 'suggested', 'reconciled')
│   └── TransactionType.php ('spent', 'received')
├── Http/
│   └── Controllers/
├── Livewire/
│   ├── BankReconcile.php            <-- Xero-style Side-by-Side UI
│   ├── StatementImportModal.php     <-- Ingestion & Mapping Modal
│   └── SubscriptionTierManager.php  <-- Admin Tier Matrix
├── Models/
│   ├── ClubBankStatementBatch.php
│   ├── ClubBankStatementLine.php
│   ├── ClubBankRule.php
│   ├── ClubBankCsvTemplate.php
│   ├── ClubSubscriptionTier.php
│   └── ClubLodgeSettings.php       <-- Renewal month/day & billing policies
├── Providers/
│   └── ClubAccountingServiceProvider.php
└── Services/
    ├── Banking/
    │   ├── BankReferenceCleaner.php
    │   ├── CsvBankFingerprinter.php
    │   ├── CsvStatementParser.php
    │   ├── OfxStatementParser.php
    │   └── StatementIngestionService.php
    ├── Matching/
    │   └── BankMatcherService.php
    ├── Reconciliation/
    │   └── ReconcileExecutionService.php
    └── Subscriptions/
        ├── CandidateInvoiceService.php
        └── SubscriptionRenewalService.php