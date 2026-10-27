<script setup>
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Alert from '@/Components/Ui/Alert.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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

const VAT_SCHEMES = [
    { value: 'standard', label: 'Standard VAT accounting' },
    { value: 'flat_rate', label: 'Flat rate scheme' },
    { value: 'cash_accounting', label: 'Cash accounting scheme' },
    { value: 'annual_accounting', label: 'Annual accounting scheme' },
];

const PANEL = 'space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-800/50';
const PANEL_TITLE = 'flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white';
const LABEL = 'block text-xs font-medium text-slate-500 dark:text-slate-400';
const CHECKBOX = 'rounded border-slate-300 text-blue-600 accent-blue-600 focus:ring-blue-500 dark:border-slate-700';
</script>

<template>
    <Card>
      <div class="space-y-6">
        <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
          <div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Financial and accounting settings</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">Address, VAT registration, invoicing rules and billing defaults.</p>
          </div>
          <Button variant="secondary" size="sm" class="self-start sm:self-auto" :href="route('admin.settings.show', club.slug) + '#accounting'">
            <template #icon><UiIcon name="settings" /></template>
            All club settings
          </Button>
        </div>

        <div class="grid grid-cols-1 gap-6 text-sm md:grid-cols-3">
          <!-- Company and address -->
          <div :class="PANEL">
            <h4 :class="PANEL_TITLE"><UiIcon name="building" class="text-slate-500 dark:text-slate-400" /> Company and address</h4>
            <div class="space-y-2 text-slate-600 dark:text-slate-300">
              <div>
                <span :class="LABEL">Organisation name</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ club.name }}</span>
              </div>
              <div>
                <span :class="LABEL">Registered address</span>
                <span v-if="settings.address_line_1">{{ settings.address_line_1 }}</span>
                <span v-if="settings.city" class="block">{{ settings.city }}, {{ settings.postcode }}</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ settings.country || 'United Kingdom' }}</span>
                <span v-if="!settings.address_line_1 && !settings.city" class="italic text-slate-400">Not set</span>
              </div>
              <div>
                <span :class="LABEL">Contact details</span>
                <span class="block">{{ settings.contact_email || 'Not set' }}</span>
                <span class="block text-slate-500 dark:text-slate-400">{{ settings.phone || 'Not set' }}</span>
              </div>
              <form v-if="canManageBilling" class="border-t border-slate-200 pt-2 dark:border-slate-800" @submit.prevent="submitFinancialYearEnd">
                <label for="financial-year-end-month" :class="[LABEL, 'mb-1']">Financial year end</label>
                <div class="flex items-center gap-2">
                  <select
                    id="financial-year-end-month"
                    v-model.number="financialYearEndForm.financial_year_end_month"
                    class="flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                  >
                    <option v-for="(name, idx) in ['January','February','March','April','May','June','July','August','September','October','November','December']" :key="idx" :value="idx + 1">{{ name }}</option>
                  </select>
                  <Button type="submit" variant="secondary" size="sm" :loading="financialYearEndForm.processing">Save</Button>
                </div>
              </form>
              <div v-else class="border-t border-slate-200 pt-2 dark:border-slate-800">
                <span :class="LABEL">Financial year end</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ ['January','February','March','April','May','June','July','August','September','October','November','December'][(settings.financial_year_end_month || 4) - 1] }}</span>
              </div>
            </div>
          </div>

          <!-- VAT and tax -->
          <div :class="PANEL">
            <h4 :class="PANEL_TITLE"><UiIcon name="clipboard" class="text-slate-500 dark:text-slate-400" /> VAT and tax</h4>
            <form class="space-y-3 text-slate-600 dark:text-slate-300" @submit.prevent="submitVatSettings">
              <label class="flex cursor-pointer items-center gap-2">
                <input
                  v-model="vatSettingsForm.enabled"
                  type="checkbox"
                  :disabled="vatLocked || !canManageBilling"
                  :class="CHECKBOX"
                />
                <span class="font-medium text-slate-800 dark:text-slate-100">This club is VAT registered</span>
              </label>
              <p class="text-xs text-slate-500 dark:text-slate-400">Most small lodges and clubs stay under the UK VAT registration threshold and should leave this off. Turn it on only once registered with HMRC.</p>

              <template v-if="vatSettingsForm.enabled">
                <Select v-model="vatSettingsForm.scheme" label="VAT scheme" :options="VAT_SCHEMES" :disabled="vatLocked || !canManageBilling" />
                <Input
                  v-if="vatSettingsForm.scheme === 'flat_rate'"
                  v-model="vatSettingsForm.flat_rate_percent"
                  type="number" step="0.01" min="0" max="100"
                  label="Flat rate (%)"
                  :disabled="vatLocked || !canManageBilling"
                />
                <Input
                  v-model="vatSettingsForm.vat_number"
                  label="VAT registration number"
                  :disabled="!canManageBilling"
                  placeholder="GB 123 4567 89"
                />
                <Input
                  v-model="vatSettingsForm.default_rate"
                  type="number" step="0.01" min="0" max="100"
                  label="Default VAT rate (%)"
                  :disabled="vatLocked || !canManageBilling"
                />
              </template>

              <Alert v-if="vatLocked" variant="warning">This club already has VAT-inclusive bills or invoices on its books, so whether VAT is on and which scheme is used are locked.</Alert>

              <Button v-if="canManageBilling" type="submit" variant="secondary" size="sm" :loading="vatSettingsForm.processing">Save VAT settings</Button>

              <div>
                <span :class="LABEL">Currency</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ $page.props.currency.code }} ({{ $cs }})</span>
              </div>
            </form>
          </div>

          <!-- Invoicing and dues -->
          <div :class="PANEL">
            <h4 :class="PANEL_TITLE"><UiIcon name="receipt" class="text-slate-500 dark:text-slate-400" /> Invoicing and dues</h4>
            <div class="space-y-2 text-slate-600 dark:text-slate-300">
              <div>
                <span :class="LABEL">Invoices are created</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ settings.auto_invoice_days_before || 7 }} days before the due date</span>
              </div>
              <div>
                <span :class="LABEL">Overdue grace period</span>
                <span class="font-medium text-slate-900 dark:text-white">{{ settings.dues_grace_period_days || 14 }} days</span>
              </div>
              <div>
                <span :class="LABEL">Receipt and invoice notes</span>
                <p class="text-xs italic text-slate-500 dark:text-slate-400">"{{ settings.receipt_footer_notes || 'Thank you for supporting our club.' }}"</p>
              </div>
            </div>
          </div>

          <!-- Approval threshold -->
          <div :class="PANEL">
            <h4 :class="PANEL_TITLE"><UiIcon name="lock" class="text-slate-500 dark:text-slate-400" /> Approval threshold</h4>
            <form v-if="canManageBilling" class="space-y-3 text-slate-600 dark:text-slate-300" @submit.prevent="submitApprovalThreshold">
              <label class="flex cursor-pointer items-center gap-2">
                <input
                  v-model="approvalThresholdForm.enabled"
                  type="checkbox"
                  :class="CHECKBOX"
                />
                <span class="font-medium text-slate-800 dark:text-slate-100">Hold large bills and invoices for a second sign-off</span>
              </label>
              <p class="text-xs text-slate-500 dark:text-slate-400">A bill or invoice at or above the amount waits as "pending approval", with nothing posted to the ledger, until a different admin or treasurer approves it.</p>
              <Input
                v-if="approvalThresholdForm.enabled"
                v-model="approvalThresholdForm.amount"
                type="number" step="0.01" min="0.01"
                label="Threshold amount"
                :prefix="$cs"
                placeholder="For example 500.00"
                :error="approvalThresholdForm.errors.amount"
              />
              <Button type="submit" variant="secondary" size="sm" :loading="approvalThresholdForm.processing">Save</Button>
            </form>
            <div v-else class="space-y-2 text-slate-600 dark:text-slate-300">
              <span class="block font-medium text-slate-900 dark:text-white">
                {{ approvalThreshold.enabled ? `On — ${$cs}${numberFormat(approvalThreshold.amount, 2)} and above` : 'Off' }}
              </span>
            </div>
          </div>

          <!-- Charity Commission -->
          <div :class="PANEL">
            <h4 :class="PANEL_TITLE"><UiIcon name="heart" class="text-slate-500 dark:text-slate-400" /> Charity Commission</h4>
            <form v-if="canManageBilling" class="space-y-3 text-slate-600 dark:text-slate-300" @submit.prevent="submitCharityCommission">
              <label class="flex cursor-pointer items-center gap-2">
                <input v-model="charityCommissionForm.registered" type="checkbox" :class="CHECKBOX" />
                <span class="font-medium text-slate-800 dark:text-slate-100">Our charity or benevolent fund is separately registered</span>
              </label>
              <p class="text-xs text-slate-500 dark:text-slate-400">Turns on the independent examiner's report, which registered charities file each year.</p>
              <Input
                v-if="charityCommissionForm.registered"
                v-model="charityCommissionForm.charity_number"
                label="Registered charity number"
                :error="charityCommissionForm.errors.charity_number"
              />
              <Button type="submit" variant="secondary" size="sm" :loading="charityCommissionForm.processing">Save</Button>
            </form>
            <span v-else class="block font-medium text-slate-900 dark:text-white">{{ charityCommission.registered ? `Registered — ${charityCommission.charity_number}` : 'Not registered' }}</span>
          </div>
        </div>

        <div v-if="canExportAllData" class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-800 dark:bg-slate-800/50">
          <div>
            <span class="block font-medium text-slate-900 dark:text-white">Export all accounting data</span>
            <span class="text-xs text-slate-500 dark:text-slate-400">Every ledger, bill, invoice, bank line, member and committee record as CSV files in one zip. Passwords and API keys are left out.</span>
          </div>
          <a
            :href="route('admin.accounting.export_all', club.slug)"
            class="inline-flex shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
          ><UiIcon name="download" /> Download zip</a>
        </div>

        <div class="flex items-center justify-between gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm dark:border-blue-800/60 dark:bg-blue-950/40">
          <div class="flex items-center gap-3">
            <UiIcon name="settings" :size="20" class="text-blue-700 dark:text-blue-300" />
            <div>
              <span class="block font-medium text-blue-900 dark:text-blue-200">Organisation settings and billing rules</span>
              <span class="text-xs text-blue-700 dark:text-blue-300">Change the address, VAT registration number, logos and who can do what.</span>
            </div>
          </div>
          <Button variant="secondary" size="sm" class="shrink-0" :href="route('admin.settings.show', club.slug) + '#accounting'">
            Edit settings
          </Button>
        </div>
      </div>
    </Card>
</template>
