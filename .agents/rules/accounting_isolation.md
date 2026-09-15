# Accounting ERP Component Isolation Guidelines

To ensure the accounting and ERP module remains modular, maintainable, and easily updateable from upstream repositories:

1. **Strict Namespace & Directory Isolation**:
   - All accounting domain models MUST reside in `App\Models\Accounting\*`.
   - All accounting services MUST reside in `App\Services\AccountingService`.
   - All accounting controllers MUST reside in `App\Http\Controllers\AccountingAdminController`.
   - All accounting Vue 3 / Inertia views MUST reside in `resources/js/Pages/Admin/Accounting/*`.
   - All accounting database migrations MUST be prefixed with `accounting_*` (`accounting_accounts`, `accounting_journal_entries`, `accounting_journal_items`, `accounting_bills`).

2. **No Mutation of Third-Party / Vendor Code**:
   - Do not directly modify core vendor packages or third-party files.
   - Extend or wrap upstream APIs cleanly via service interfaces so that upgrading upstream dependencies via Git / Composer will never break custom interface logic or core application features.
