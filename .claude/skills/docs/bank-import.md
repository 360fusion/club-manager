Act as a Principal Full-Stack Engineer and Laravel / Livewire Architecture Specialist.

I am developing a multi-tenant membership club and Masonic lodge management SaaS platform integrated with Liberu Accounting ERP. 

We have already planned the Xero-style reconciliation screen and domain matcher (`bank-reconciliation-spec.md`). Now, I need you to implement the **Universal UK Bank Statement Ingestion Engine** that feeds raw statements directly into the `bank_statement_lines` staging table.

This engine must handle **all UK high-street banks, challenger banks, and building societies** via OFX/QIF standard formats, zero-config CSV auto-fingerprinting, a dynamic 3-step column mapping fallback for unknown CSVs, and strict cryptographic deduplication.

---

### 1. Functional Scope & Format Coverage

The ingestion engine must accept `.csv`, `.ofx`, and `.qif` file uploads and normalize them into a uniform schema:

1. **Standardized OFX / QIF Engine (Universal UK Coverage):**
   - Must parse standard OFX SGML/XML structures (e.g. `<STMTTRN>`, `<DTPOSTED>`, `<TRNAMT>`, `<FITID>`, `<NAME>`, `<MEMO>`).
   - Automatically handles 100% of UK banks exporting OFX/QIF (Barclays, Lloyds, NatWest, HSBC, Santander, RBS, Nationwide, Metro Bank, Co-op Bank).
   - Zero configuration required from the user.

2. **UK CSV Header Fingerprinter & Normalizer:**
   - Automatically detect the bank format by inspecting Line 1 (column headers) against known bank profiles:
     - **Barclays Business:** `['Number', 'Date', 'Account', 'Amount', 'Subcategory', 'Memo']` (Signed single amount, DD/MM/YYYY).
     - **Lloyds / Bank of Scotland / Halifax:** `['Transaction Date', 'Transaction Type', 'Sort Code', 'Account Number', 'Transaction Description', 'Debit Amount', 'Credit Amount', 'Balance']` (Split debit/credit).
     - **NatWest / RBS:** `['Date', 'Type', 'Description', 'Value', 'Balance', 'Account Name', 'Account Number']` (Signed value column).
     - **HSBC Commercial:** `['Date', 'Payment Type', 'Details', 'Paid out', 'Paid in', 'Balance']` (Split paid out / paid in).
     - **Santander Business:** `['Date', 'Description', 'Amount', 'Balance']` (Signed amount).
     - **Starling Business / Monzo / Revolut:** Signed amounts with clear transaction descriptions.
   - Clean and normalize reference text (strip asterisks, repeated whitespace, and noisy prefixes like `BGC`, `FPI`, `FASTER PAYMENTS`, `BACS`).

3. **Dynamic Visual Column Mapper (Fallback for Unknown CSVs):**
   - If an uploaded CSV does not match any known bank fingerprint (e.g., regional building societies, niche credit unions):
     - Present an interactive preview modal showing the first 3 rows of the uploaded file.
     - Prompt user to map 3 essential data points:
       - **Date column** (and date format: `DD/MM/YYYY` vs `YYYY-MM-DD`).
       - **Description / Reference column**.
       - **Amount structure**: Radio toggle for `[Single Signed Amount Column]` vs `[Split Debit / Credit Columns]`.
     - Checkbox: `[x] Save this layout as a template for this bank account` (persists to `bank_csv_templates`).

4. **Cryptographic Deduplication & Idempotency:**
   - Statements frequently have overlapping date ranges (e.g., importing Sept 1–30, then Sept 15–Oct 15).
   - Generate a deterministic line hash:
     ```php
     $lineHash = md5(implode('|', [
         $bankAccountId,$parsedDate->format('Y-m-d'),
         number_format(abs($normalizedAmount), 2, '.', ''),$transactionType, // 'spent' or 'received'
         trim(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $cleanReference)))
     ]));
     ```
   - If `$lineHash` already exists in `bank_statement_lines`, skip the record silently and increment the `skipped_duplicates_count`.

---

### 2. Database Schema & Migrations

Please generate the required migrations:

1. **`bank_statement_batches` Table:**
   - `id` (ULID / bigint primary key)
   - `bank_account_id` (foreign key to Liberu's `bank_accounts` table)
   - `filename` (string)
   - `file_format` (`csv`, `ofx`, `qif`)
   - `detected_bank` (string nullable, e.g. `'Barclays'`, `'Lloyds'`, `'Custom'`)
   - `total_lines_read` (integer)
   - `lines_imported` (integer)
   - `lines_skipped` (integer)
   - `imported_by` (foreign key to `users`)
   - `created_at`, `updated_at`

2. **`bank_statement_lines` Table (Update / Complete Staging Schema):**
   - `id` (ULID / bigint primary key)
   - `batch_id` (foreign key to `bank_statement_batches`, cascade on delete)
   - `bank_account_id` (foreign key)
   - `line_hash` (string, 32 chars, indexed for rapid duplicate lookup)
   - `transaction_date` (date)
   - `raw_reference` (text)
   - `clean_reference` (string)
   - `transaction_type` (string, e.g., `'DIRECT DEBIT'`, `'BACS'`, `'CARD'`, `'FEE'`)
   - `spent` (decimal 10,2 nullable)
   - `received` (decimal 10,2 nullable)
   - `status` (enum: `'pending'`, `'suggested'`, `'reconciled'`)
   - `suggested_payload` (json nullable)
   - `created_at`, `updated_at`

3. **`bank_csv_templates` Table:**
   - `id` (primary key)
   - `bank_account_id` (foreign key nullable)
   - `template_name` (string)
   - `header_fingerprint_hash` (string nullable)
   - `column_mapping` (json: `{ "date": 0, "ref": 2, "amount_type": "split", "spent": 3, "received": 4, "date_format": "d/m/Y" }`)
   - `timestamps`

---

### 3. Architecture & Service Layer

Build the following clean, modular services:

1. **`App\Services\Banking\Parsers\OfxStatementParser`:**
   - Accepts raw OFX/QIF content, parses transaction elements, extracts `FITID`, amounts, timestamps, and standardizes lines.

2. **`App\Services\Banking\Parsers\CsvBankFingerprinter`:**
   - Inspects CSV headers.
   - Contains definitions for all top UK banks (Barclays, Lloyds, NatWest, HSBC, Santander, Starling, Monzo, Revolut).
   - Returns matched bank profile or triggers the custom mapping requirement.

3. **`App\Services\Banking\Parsers\CsvStatementParser`:**
   - Parses CSV streams using the detected preset or custom column mapping definition.
   - Normalizes varied date formats (`d/m/Y`, `Y-m-d`, `d-M-y`) and monetary formats (removes currency symbols `£`, commas, and whitespace).

4. **`App\Services\Banking\StatementIngestionService`:**
   - Main orchestrator called by the Livewire upload component.
   - Creates the `bank_statement_batches` record.
   - Iterates through normalized transaction DTOs.
   - Evaluates line hashes against `bank_statement_lines`.
   - Inserts unique records.
   - Dispatches a job or invokes `BankMatcherService::analyzeBatch($batchId)` so imported records immediately get auto-suggest payloads for the reconciliation view.

---

### 4. User Interface (Livewire & Blade Modal)

Create the Livewire component `App\Livewire\Accounting\StatementImportModal`:

1. **Trigger:** Connected to the emerald `[+ Import Statement]` button on the Accounting navbar.
2. **Modal States:**
   - **State 1 (Upload):** Clean drag-and-drop zone accepting `.csv`, `.ofx`, `.qif`. Account selector dropdown defaulting to the active bank account.
   - **State 2 (Custom Column Mapper - conditional):** Appears ONLY if an unknown CSV layout is uploaded. Renders a preview table of the first 3 lines and provides dropdowns for Date, Reference, and Amount columns with an option to remember the template.
   - **State 3 (Results Summary):** Shows an alert on completion:
     - `✓ Statement imported successfully!`
     - `• 48 transactions imported`
     - `• 14 duplicates skipped`
     - `• 32 items ready for auto-reconciliation`
     - Primary button: **[ Go to Reconciliation ]** (redirects to the Xero-style reconcile page).

Please provide production-ready code with complete error handling, validation for invalid file types, and clean Tailwind CSS styling matching our dark top navigation and card design system.