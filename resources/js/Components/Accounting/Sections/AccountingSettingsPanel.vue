<script setup>
import { Link } from '@inertiajs/vue3';

// settings section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    approvalThresholdForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    charityCommissionForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    financialYearEndForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    numberFormat: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitApprovalThreshold: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitCharityCommission: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitFinancialYearEnd: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitVatSettings: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    vatSettingsForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    approvalThreshold: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    bills: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    canExportAllData: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    canManageBilling: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    charityCommission: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    invoices: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    settings: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    vatLocked: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 space-y-6">
      <div class="border-b border-slate-100 dark:border-slate-800 pb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h3 class="text-lg font-black text-slate-900 dark:text-white">Financial & Accounting Settings</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">Configure company address, VAT registration numbers, invoicing rules, and billing defaults.</p>
        </div>
        <Link
          :href="route('admin.settings.show', club.slug) + '#accounting'"
          class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-xl shadow-sm transition-all flex items-center gap-1.5 self-start sm:self-auto"
        >
          <span>⚙️ Full system settings</span>
        </Link>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
        <!-- Card 1: Company & Address Profile -->
        <div class="bg-slate-50 dark:bg-slate-800/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
          <div class="flex items-center gap-2">
            <span class="text-lg">🏢</span>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm">Company & Address</h4>
          </div>
          <div class="space-y-2 text-slate-600 dark:text-slate-300">
            <div>
              <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px]">Organization Name</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ club.name }}</span>
            </div>
            <div>
              <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px]">Registered Address</span>
              <span v-if="settings.address_line_1">{{ settings.address_line_1 }}</span>
              <span v-if="settings.city" class="block">{{ settings.city }}, {{ settings.postcode }}</span>
              <span class="block text-slate-500 dark:text-slate-400 font-mono text-[10px]">{{ settings.country || 'United Kingdom' }}</span>
              <span v-if="!settings.address_line_1 && !settings.city" class="text-slate-400 italic">Not set</span>
            </div>
            <div>
              <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px]">Contact Information</span>
              <span class="block font-mono">{{ settings.contact_email || 'Not set' }}</span>
              <span class="block font-mono text-slate-500 dark:text-slate-400">{{ settings.phone || 'Not set' }}</span>
            </div>
            <form v-if="canManageBilling" @submit.prevent="submitFinancialYearEnd" class="pt-2 border-t border-slate-200 dark:border-slate-800">
              <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px] mb-1">Financial Year End</span>
              <div class="flex items-center gap-2">
                <select
                  v-model.number="financialYearEndForm.financial_year_end_month"
                  class="flex-1 px-3 py-2 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option v-for="(name, idx) in ['January','February','March','April','May','June','July','August','September','October','November','December']" :key="idx" :value="idx + 1">{{ name }}</option>
                </select>
                <button
                  type="submit"
                  :disabled="financialYearEndForm.processing"
                  class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-extrabold rounded-xl transition-all cursor-pointer disabled:opacity-50"
                >
                  Save
                </button>
              </div>
            </form>
            <div v-else class="pt-2 border-t border-slate-200 dark:border-slate-800">
              <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px]">Financial Year End</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ ['January','February','March','April','May','June','July','August','September','October','November','December'][(settings.financial_year_end_month || 4) - 1] }}</span>
            </div>
          </div>
        </div>

        <!-- Card 2: VAT & Tax Registration -->
        <div class="bg-slate-50 dark:bg-slate-800/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
          <div class="flex items-center gap-2">
            <span class="text-lg">📋</span>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm">VAT & Tax Configuration</h4>
          </div>
          <form @submit.prevent="submitVatSettings" class="space-y-3 text-slate-600 dark:text-slate-300">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                v-model="vatSettingsForm.enabled"
                :disabled="vatLocked || !canManageBilling"
                class="rounded border-slate-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500"
              />
              <span class="font-bold text-slate-800 dark:text-slate-100 text-sm">This club is VAT registered</span>
            </label>
            <p class="text-[10px] text-slate-500 dark:text-slate-400">Most small lodges/clubs stay under the UK VAT registration threshold and should leave this off. Enable it only once actually registered with HMRC.</p>

            <template v-if="vatSettingsForm.enabled">
              <div>
                <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px] mb-1">VAT Scheme</span>
                <select
                  v-model="vatSettingsForm.scheme"
                  :disabled="vatLocked || !canManageBilling"
                  class="w-full px-3 py-2 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="standard">Standard VAT Accounting</option>
                  <option value="flat_rate">Flat Rate Scheme</option>
                  <option value="cash_accounting">Cash Accounting Scheme</option>
                  <option value="annual_accounting">Annual Accounting Scheme</option>
                </select>
              </div>
              <div v-if="vatSettingsForm.scheme === 'flat_rate'">
                <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px] mb-1">Flat Rate %</span>
                <input
                  type="number" step="0.01" min="0" max="100"
                  v-model="vatSettingsForm.flat_rate_percent"
                  :disabled="vatLocked || !canManageBilling"
                  class="w-full px-3 py-2 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <div>
                <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px] mb-1">VAT Registration Number</span>
                <input
                  type="text"
                  v-model="vatSettingsForm.vat_number"
                  :disabled="!canManageBilling"
                  placeholder="GB 123 4567 89"
                  class="w-full px-3 py-2 text-sm font-mono bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <div>
                <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px] mb-1">Default VAT Rate %</span>
                <input
                  type="number" step="0.01" min="0" max="100"
                  v-model="vatSettingsForm.default_rate"
                  :disabled="vatLocked || !canManageBilling"
                  class="w-full px-3 py-2 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </template>

            <p v-if="vatLocked" class="text-[10px] text-amber-700 dark:text-amber-300 font-semibold">This club already has VAT-inclusive bills or invoices on its books, so whether VAT is on and which scheme is used are locked.</p>

            <button
              v-if="canManageBilling"
              type="submit"
              :disabled="vatSettingsForm.processing"
              class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-extrabold rounded-xl transition-all cursor-pointer disabled:opacity-50"
            >
              Save VAT settings
            </button>

            <div>
              <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px]">Currency</span>
              <span class="font-mono font-bold text-emerald-700 dark:text-emerald-300">{{ $page.props.currency.code }} ({{ $cs }})</span>
            </div>
          </form>
        </div>

        <!-- Card 3: Invoicing Setup & Footer Notes -->
        <div class="bg-slate-50 dark:bg-slate-800/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
          <div class="flex items-center gap-2">
            <span class="text-lg">🧾</span>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm">Invoicing & Dues Setup</h4>
          </div>
          <div class="space-y-2 text-slate-600 dark:text-slate-300">
            <div>
              <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px]">Auto-Invoicing Lead Time</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ settings.auto_invoice_days_before || 7 }} Days before due date</span>
            </div>
            <div>
              <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px]">Overdue Grace Period</span>
              <span class="font-bold text-amber-800 dark:text-amber-200">{{ settings.dues_grace_period_days || 14 }} Days</span>
            </div>
            <div>
              <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px]">Receipt & Invoice Notes</span>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 italic">"{{ settings.receipt_footer_notes || 'Thank you for supporting our club.' }}"</p>
            </div>
          </div>
        </div>

        <!-- Card 4: Approval Threshold -->
        <div class="bg-slate-50 dark:bg-slate-800/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
          <div class="flex items-center gap-2">
            <span class="text-lg">🔏</span>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm">Approval Threshold</h4>
          </div>
          <form v-if="canManageBilling" @submit.prevent="submitApprovalThreshold" class="space-y-3 text-slate-600 dark:text-slate-300">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                v-model="approvalThresholdForm.enabled"
                class="rounded border-slate-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500"
              />
              <span class="font-bold text-slate-800 dark:text-slate-100 text-sm">Hold large bills/invoices for a second sign-off</span>
            </label>
            <p class="text-[10px] text-slate-500 dark:text-slate-400">A bill or invoice at or above the amount is held as "Pending Approval" — no ledger entry is posted — until a different admin or treasurer approves it.</p>
            <div v-if="approvalThresholdForm.enabled">
              <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px] mb-1">Threshold Amount ({{ $cs }})</span>
              <input
                type="number" step="0.01" min="0.01"
                v-model="approvalThresholdForm.amount"
                placeholder="e.g. 500.00"
                class="w-full px-3 py-2 text-sm bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <p v-if="approvalThresholdForm.errors.amount" class="text-[10px] text-rose-600 dark:text-rose-400 font-semibold mt-1">{{ approvalThresholdForm.errors.amount }}</p>
            </div>
            <button
              type="submit"
              :disabled="approvalThresholdForm.processing"
              class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-extrabold rounded-xl transition-all cursor-pointer disabled:opacity-50"
            >
              Save
            </button>
          </form>
          <div v-else class="space-y-2 text-slate-600 dark:text-slate-300">
            <span class="font-bold text-slate-900 dark:text-white block">
              {{ approvalThreshold.enabled ? `Enabled — ${$cs}${numberFormat(approvalThreshold.amount, 2)} and above` : 'Off' }}
            </span>
          </div>
        </div>

        <!-- Card 5: Charity Commission -->
        <div class="bg-slate-50 dark:bg-slate-800/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-3">
          <div class="flex items-center gap-2">
            <span class="text-lg">🤝</span>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm">Charity Commission</h4>
          </div>
          <form v-if="canManageBilling" @submit.prevent="submitCharityCommission" class="space-y-3 text-slate-600 dark:text-slate-300">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="charityCommissionForm.registered" class="rounded border-slate-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500" />
              <span class="font-bold text-slate-800 dark:text-slate-100 text-sm">Our Charity / Benevolent Fund is separately registered</span>
            </label>
            <p class="text-[10px] text-slate-500 dark:text-slate-400">Turns on the Independent Examiner's Report, which registered charities file each year.</p>
            <div v-if="charityCommissionForm.registered">
              <span class="font-bold text-slate-800 dark:text-slate-100 block text-[11px] mb-1">Registered Charity Number</span>
              <input type="text" v-model="charityCommissionForm.charity_number" class="w-full px-3 py-2 text-sm font-mono bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <p v-if="charityCommissionForm.errors.charity_number" class="text-[10px] text-rose-600 dark:text-rose-400 font-semibold mt-1">{{ charityCommissionForm.errors.charity_number }}</p>
            </div>
            <button type="submit" :disabled="charityCommissionForm.processing" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-extrabold rounded-xl transition-all cursor-pointer disabled:opacity-50">Save</button>
          </form>
          <span v-else class="font-bold text-slate-900 dark:text-white block">{{ charityCommission.registered ? `Registered — ${charityCommission.charity_number}` : 'Not registered' }}</span>
        </div>
      </div>

      <div v-if="canExportAllData" class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3 text-xs">
        <div>
          <span class="font-black text-slate-900 dark:text-white block">Export all accounting data</span>
          <span class="text-slate-500 dark:text-slate-400">Every ledger, bill, invoice, bank line, member and committee record as CSV files in one zip. Passwords and API keys are left out.</span>
        </div>
        <a :href="route('admin.accounting.export_all', club.slug)" class="px-3.5 py-1.5 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 text-white font-bold rounded-xl shadow-sm whitespace-nowrap">⬇️ Download zip</a>
      </div>

      <div class="p-4 bg-blue-50 dark:bg-blue-950/40 rounded-2xl border border-blue-200 dark:border-blue-800/60 flex items-center justify-between text-xs">
        <div class="flex items-center gap-3">
          <span class="text-xl">⚙️</span>
          <div>
            <span class="font-black text-blue-900 dark:text-blue-200 block">Edit Organization Settings & Billing Rules</span>
            <span class="text-blue-700 dark:text-blue-300">Update company address, VAT registration number, logos, and role permission matrix.</span>
          </div>
        </div>
        <Link
          :href="route('admin.settings.show', club.slug) + '#accounting'"
          class="px-3.5 py-1.5 bg-blue-700 hover:bg-blue-800 text-white font-bold rounded-xl shadow-sm transition-all whitespace-nowrap"
        >
          Edit settings →
        </Link>
      </div>
    </div>
</template>
