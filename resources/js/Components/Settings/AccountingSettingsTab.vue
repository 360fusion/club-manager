<script setup>
// Accounting tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';

defineProps({
    form: { type: Object, required: true },
    currencies: { type: [Object, Array, Function, String, Number, Boolean], required: false },
    currencyLocked: { type: [Object, Array, Function, String, Number, Boolean], required: false }
});
</script>

<template>
    <div class="space-y-6">
      <!-- 1. Fiscal Year & Accounting Controls -->
      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Financial year and tax</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Configure fiscal year boundaries, accounting basis, lock dates, and tax rules.</p>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <Select
            v-model="form.fiscal_year_start_month"
            label="Financial year start month"
            :options="[
              { value: 'January', label: 'January' },
              { value: 'February', label: 'February' },
              { value: 'March', label: 'March' },
              { value: 'April', label: 'April (UK tax year)' },
              { value: 'May', label: 'May' },
              { value: 'June', label: 'June' },
              { value: 'July', label: 'July' },
              { value: 'August', label: 'August' },
              { value: 'September', label: 'September' },
              { value: 'October', label: 'October (club dues cycle)' },
              { value: 'November', label: 'November' },
              { value: 'December', label: 'December' },
            ]"
          />

          <Select
            v-model="form.accounting_method"
            label="Accounting basis"
            :options="[
              { value: 'accrual', label: 'Accrual basis (recognise when billed)' },
              { value: 'cash', label: 'Cash basis (recognise when paid)' },
            ]"
          />

          <Input v-model.number="form.standard_vat_rate" type="number" step="0.1" min="0" max="100" label="Standard VAT rate (%)" />
          <Input v-model="form.lock_accounting_date" type="date" label="Closed period lock date" hint="Prevents posting journals before this date." />
        </div>
      </Card>

      <!-- 2. Sales Invoicing & Member Receivables -->
      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Sales invoices and receivables</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Set invoice number prefixes, default revenue accounts, payment terms, and lead times.</p>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          <Input v-model="form.invoice_prefix" placeholder="INV-2026-" label="Invoice number prefix" />

          <Select
            v-model="form.invoice_due_terms"
            label="Default payment terms"
            :options="[
              { value: 'Due on Receipt', label: 'Due on receipt' },
              { value: 'Net 7', label: 'Net 7 days' },
              { value: 'Net 14', label: 'Net 14 days' },
              { value: 'Net 30', label: 'Net 30 days' },
            ]"
          />

          <Input v-model="form.default_ar_account_code" placeholder="1200" label="Default receivables account code" />
          <Input v-model="form.default_revenue_account_code" placeholder="4000" label="Default revenue account code" />
          <Input v-model.number="form.auto_invoice_days_before" type="number" min="0" max="90" label="Auto-invoice lead time (days)" />
          <Input v-model.number="form.dues_grace_period_days" type="number" min="0" max="180" label="Overdue grace period (days)" />
        </div>
      </Card>

      <!-- 3. Purchases & Vendor Bills Payables -->
      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Purchases and payables</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Set bill prefixes, default expense accounts, and vendor approval workflows.</p>
        </template>

        <div class="space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <Input v-model="form.bill_prefix" placeholder="BILL-2026-" label="Bill number prefix" />
            <Input v-model="form.default_ap_account_code" placeholder="2000" label="Default payables account code" />
            <Input v-model="form.default_expense_account_code" placeholder="5000" label="Default expense account code" />
          </div>

          <label class="flex items-start gap-3 text-sm cursor-pointer border-t border-slate-200 dark:border-slate-800 pt-4">
            <input type="checkbox" v-model="form.require_bill_approval" class="mt-0.5 h-4 w-4 rounded accent-blue-600" />
            <span>
              <span class="font-medium text-slate-900 dark:text-white">Require bill approval</span>
              <span class="block text-slate-500 dark:text-slate-400">Require explicit administrator approval before a vendor bill can be marked as paid.</span>
            </span>
          </label>
        </div>
      </Card>

      <!-- 4. General Ledger, Currency & Company Address Profile -->
      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Currency, ledger and billing address</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Base accounting currency, double-entry validation, and company registration address.</p>
        </template>

        <div class="space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <Select
              v-model="form.currency"
              :disabled="currencyLocked"
              label="Base accounting currency"
              :options="(currencies || []).map((c) => ({ value: c.code, label: `${c.code} (${c.symbol.trim()}) - ${c.name}` }))"
              :hint="currencyLocked
                ? 'Locked: this club already has financial records, and amounts are not converted between currencies.'
                : 'Every amount in this club\'s accounts uses this currency. It is locked once you record any money.'"
              :error="form.errors?.currency"
            />

            <Input v-model="form.default_bank_account_code" placeholder="1000" label="Operating bank account code" />
            <Input v-model="form.bank_sort_code" placeholder="20-65-18" label="Bank sort code (summons dues and dining)" />
            <Input v-model="form.bank_account_number" placeholder="83920145" label="Bank account number (summons dues and dining)" />
            <Input v-model="form.tax_registration_number" placeholder="GB 987 6543 21" label="VAT or tax registration number" />
            <Input v-model="form.address_line_1" placeholder="100 Boathouse Way" label="Registered billing address (line 1)" class="sm:col-span-2" />
            <Input v-model="form.city" placeholder="Oxford" label="Town or city" />
            <Input v-model="form.postcode" placeholder="OX1 1AA" label="Postcode" />
            <Textarea v-model="form.receipt_footer_notes" :rows="3" placeholder="Thank you for supporting our club. Fees support equipment & clubhouse operations." label="Receipt and invoice footer terms" class="sm:col-span-2" />
          </div>

          <label class="flex items-start gap-3 text-sm cursor-pointer border-t border-slate-200 dark:border-slate-800 pt-4">
            <input type="checkbox" v-model="form.enforce_balanced_journals" class="mt-0.5 h-4 w-4 rounded accent-blue-600" />
            <span>
              <span class="font-medium text-slate-900 dark:text-white">Enforce double-entry balance</span>
              <span class="block text-slate-500 dark:text-slate-400">Require total debits to exactly equal total credits before manual journal entries can be posted.</span>
            </span>
          </label>
        </div>
      </Card>
    </div>
</template>
