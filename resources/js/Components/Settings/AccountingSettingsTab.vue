<script setup>
// Accounting tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
defineProps({
    form: { type: Object, required: true },
    currencies: { type: [Object, Array, Function, String, Number, Boolean], required: false },
    currencyLocked: { type: [Object, Array, Function, String, Number, Boolean], required: false }
});
</script>

<template>
    <div class="space-y-6">
      <!-- 1. Fiscal Year & Accounting Controls -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">📊 Financial Year & Tax Controls</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configure fiscal year boundaries, accounting basis, lock dates, and tax rules.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Fiscal Year Start Month</label>
            <select v-model="form.fiscal_year_start_month" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold">
              <option value="January">January</option>
              <option value="February">February</option>
              <option value="March">March</option>
              <option value="April">April (UK Tax Year)</option>
              <option value="May">May</option>
              <option value="June">June</option>
              <option value="July">July</option>
              <option value="August">August</option>
              <option value="September">September</option>
              <option value="October">October (Club Dues Cycle)</option>
              <option value="November">November</option>
              <option value="December">December</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Accounting Basis / Method</label>
            <select v-model="form.accounting_method" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold">
              <option value="accrual">Accrual Basis (Recognize when billed)</option>
              <option value="cash">Cash Basis (Recognize when paid)</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Standard VAT Rate (%)</label>
            <input v-model.number="form.standard_vat_rate" type="number" step="0.1" min="0" max="100" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Closed Period Lock Date</label>
            <input v-model="form.lock_accounting_date" type="date" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            <p class="text-[11px] text-slate-400 mt-1">Prevents posting journals before this date.</p>
          </div>
        </div>
      </div>

      <!-- 2. Sales Invoicing & Member Receivables -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">🧾 Sales Invoicing & Receivables Rules</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Set invoice number prefixes, default revenue accounts, payment terms, and lead times.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Invoice Number Prefix</label>
            <input v-model="form.invoice_prefix" type="text" placeholder="INV-2026-" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default Payment Due Terms</label>
            <select v-model="form.invoice_due_terms" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold">
              <option value="Due on Receipt">Due on Receipt</option>
              <option value="Net 7">Net 7 Days</option>
              <option value="Net 14">Net 14 Days</option>
              <option value="Net 30">Net 30 Days</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default A/R Account Code</label>
            <input v-model="form.default_ar_account_code" type="text" placeholder="1200" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default Revenue Account Code</label>
            <input v-model="form.default_revenue_account_code" type="text" placeholder="4000" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Auto-Invoice Lead Time (Days)</label>
            <input v-model.number="form.auto_invoice_days_before" type="number" min="0" max="90" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Overdue Grace Period (Days)</label>
            <input v-model.number="form.dues_grace_period_days" type="number" min="0" max="180" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
          </div>
        </div>
      </div>

      <!-- 3. Purchases & Vendor Bills Payables -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">📄 Purchases & Vendor Payables Rules</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Set bill prefixes, default expense accounts, and vendor approval workflows.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Bill Number Prefix</label>
            <input v-model="form.bill_prefix" type="text" placeholder="BILL-2026-" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default A/P Account Code</label>
            <input v-model="form.default_ap_account_code" type="text" placeholder="2000" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default Expense Account Code</label>
            <input v-model="form.default_expense_account_code" type="text" placeholder="5000" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
          </div>
        </div>

        <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
          <label class="flex items-center gap-3 cursor-pointer text-xs">
            <input type="checkbox" v-model="form.require_bill_approval" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
            <div>
              <div class="font-bold text-slate-900 dark:text-white">Vendor Bill Approval Workflow</div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">Require explicit Administrator approval before a vendor bill can be marked as paid.</div>
            </div>
          </label>
        </div>
      </div>

      <!-- 4. General Ledger, Currency & Company Address Profile -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">🏢 Organization Address & General Ledger Controls</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Base accounting currency, double-entry validation, and company registration address.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Base Accounting Currency</label>
            <select v-model="form.currency" :disabled="currencyLocked" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold disabled:opacity-60">
              <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }} ({{ c.symbol.trim() }}) - {{ c.name }}</option>
            </select>
            <p v-if="currencyLocked" class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Locked: this club already has financial records, and amounts are not converted between currencies.</p>
            <p v-else class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Every amount in this club's accounts uses this currency. It is locked once you record any money.</p>
            <p v-if="form.errors?.currency" class="mt-1 text-[11px] font-semibold text-rose-600">{{ form.errors.currency }}</p>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Operating Bank Account Code</label>
            <input v-model="form.default_bank_account_code" type="text" placeholder="1000" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Bank Sort Code (Summons Dues/Dining)</label>
            <input v-model="form.bank_sort_code" type="text" placeholder="20-65-18" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Bank Account Number (Summons Dues/Dining)</label>
            <input v-model="form.bank_account_number" type="text" placeholder="83920145" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">VAT / Tax Registration Number</label>
            <input v-model="form.tax_registration_number" type="text" placeholder="GB 987 6543 21" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
          </div>

          <div class="sm:col-span-2">
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Company Registered Billing Address (Line 1)</label>
            <input v-model="form.address_line_1" type="text" placeholder="100 Boathouse Way" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">City / Town</label>
            <input v-model="form.city" type="text" placeholder="Oxford" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Postcode / ZIP</label>
            <input v-model="form.postcode" type="text" placeholder="OX1 1AA" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-semibold" />
          </div>

          <div class="sm:col-span-2">
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Receipt & Invoice Footer Terms</label>
            <textarea v-model="form.receipt_footer_notes" rows="3" placeholder="Thank you for supporting our club. Fees support equipment & clubhouse operations." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500"></textarea>
          </div>
        </div>

        <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
          <label class="flex items-center gap-3 cursor-pointer text-xs">
            <input type="checkbox" v-model="form.enforce_balanced_journals" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
            <div>
              <div class="font-bold text-slate-900 dark:text-white">Enforce Double-Entry Balance Validation</div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">Require total debits to exactly equal total credits before manual journal entries can be posted.</div>
            </div>
          </label>
        </div>
      </div>
    </div>
</template>
