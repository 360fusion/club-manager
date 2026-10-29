<script setup>
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { formatMoney } from '@/Utils/currency';

// Download links stay plain links (a kit Button with href would make an Inertia visit), styled as secondary buttons.
const linkButtonClass = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
const fieldClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';

// treasurer_report section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    auditorOneLocked: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    auditorOneSignature: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    auditorTwoLocked: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    auditorTwoSignature: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    bothAuditorsLocked: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    cancelAuditSignature: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    changeTreasurerReportYear: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    closeYearForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    resendAuditSignature: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    signOffForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitCloseYear: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitSignOff: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    accounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    annualTreasurerReport: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    members: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50 dark:bg-slate-800/50 p-6 rounded-2xl border border-slate-200 dark:border-slate-800">
        <div>
          <h3 class="text-xl font-semibold text-slate-900 dark:text-white">Annual Treasurer's report — {{ annualTreasurerReport.financial_year }}</h3>
          <p v-if="annualTreasurerReport.period_start" class="text-sm text-slate-600 dark:text-slate-300 mt-1">{{ annualTreasurerReport.period_start }} to {{ annualTreasurerReport.period_end }}</p>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex flex-wrap items-center gap-2">
            <Badge :variant="annualTreasurerReport.is_closed ? 'success' : 'warning'">
              {{ annualTreasurerReport.is_closed ? 'Books closed' : 'Books open — draft' }}
            </Badge>
            <span v-if="annualTreasurerReport.audit_sign_off">
              Signed off by {{ annualTreasurerReport.audit_sign_off.auditor_one }} &amp; {{ annualTreasurerReport.audit_sign_off.auditor_two }} on {{ annualTreasurerReport.audit_sign_off.signed_off_at }}
            </span>
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Button variant="secondary" size="sm" @click="changeTreasurerReportYear(annualTreasurerReport.financial_year - 1)">← {{ annualTreasurerReport.financial_year - 1 }}</Button>
          <Button variant="secondary" size="sm" @click="changeTreasurerReportYear(annualTreasurerReport.financial_year + 1)">{{ annualTreasurerReport.financial_year + 1 }} →</Button>
          <a :href="route('admin.accounting.treasurer_report.export_pdf', { clubSlug: club.slug, year: annualTreasurerReport.financial_year })" target="_blank" :class="linkButtonClass">
            <UiIcon name="document" :size="14" />
            PDF
          </a>
          <a :href="route('admin.accounting.treasurer_report.export_csv', { clubSlug: club.slug, year: annualTreasurerReport.financial_year })" :class="linkButtonClass">
            <UiIcon name="download" :size="14" />
            CSV
          </a>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <StatCard label="General Fund net surplus" :value="formatMoney(annualTreasurerReport.general_fund.net_surplus)" />
        <StatCard label="Charity Fund movement" :value="formatMoney(annualTreasurerReport.charity_fund.collections_total - annualTreasurerReport.charity_fund.grants_disbursed_total)" />
        <StatCard label="Members in arrears" :value="annualTreasurerReport.membership.in_arrears_count" />
      </div>

      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400"><tr><th scope="col" class="py-3 px-3 font-medium">General Fund account</th><th scope="col" class="py-3 px-3 font-medium text-right">Amount</th></tr></thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900">
            <tr v-for="row in annualTreasurerReport.general_fund.rows" :key="row.code">
              <td class="py-2 px-3">{{ row.code }} — {{ row.name }}</td>
              <td class="py-2 px-3 text-right tabular-nums">{{ formatMoney(row.amount) }}</td>
            </tr>
            <tr v-if="!annualTreasurerReport.general_fund.rows.length"><td colspan="2" class="py-4 px-3 text-center text-slate-500 dark:text-slate-400">No General Fund activity this year.</td></tr>
          </tbody>
          <tfoot>
            <tr class="bg-slate-100 dark:bg-slate-800 font-semibold text-slate-900 dark:text-white"><td class="py-2 px-3">Net surplus / (deficit)</td><td class="py-2 px-3 text-right tabular-nums">{{ formatMoney(annualTreasurerReport.general_fund.net_surplus) }}</td></tr>
          </tfoot>
        </table>
      </div>

      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400"><tr><th scope="col" class="py-3 px-3 font-medium">Bank / account</th><th scope="col" class="py-3 px-3 font-medium text-right">Opening</th><th scope="col" class="py-3 px-3 font-medium text-right">Closing (ledger)</th><th scope="col" class="py-3 px-3 font-medium text-right">Closing (statement)</th></tr></thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900">
            <tr v-for="(b, idx) in annualTreasurerReport.bank_accounts" :key="idx">
              <td class="py-2 px-3">{{ b.bank_name }} — {{ b.account_name }}</td>
              <td class="py-2 px-3 text-right tabular-nums">{{ formatMoney(b.opening_balance) }}</td>
              <td class="py-2 px-3 text-right tabular-nums">{{ formatMoney(b.closing_ledger_balance) }}</td>
              <td class="py-2 px-3 text-right tabular-nums">{{ formatMoney(b.closing_statement_balance) }}</td>
            </tr>
            <tr v-if="!annualTreasurerReport.bank_accounts.length"><td colspan="4" class="py-4 px-3 text-center text-slate-500 dark:text-slate-400">No bank accounts connected.</td></tr>
          </tbody>
        </table>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <form @submit.prevent="submitCloseYear" class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
          <h4 class="text-base font-semibold text-slate-900 dark:text-white">Close financial year</h4>
          <p class="text-xs text-slate-500 dark:text-slate-400">Blocks new entries dated in {{ annualTreasurerReport.financial_year }}. A super admin can reopen it later if needed.</p>
          <Input v-model="closeYearForm.notes" label="Notes" placeholder="Notes (optional)" />
          <Button type="submit" variant="secondary" :disabled="annualTreasurerReport.is_closed || closeYearForm.processing">
            {{ annualTreasurerReport.is_closed ? 'Already closed' : `Close ${annualTreasurerReport.financial_year}` }}
          </Button>
        </form>

        <form @submit.prevent="submitSignOff" class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
          <h4 class="text-base font-semibold text-slate-900 dark:text-white">Auditors' sign-off</h4>
          <template v-if="!annualTreasurerReport.audit_sign_off">
            <p class="text-xs text-slate-500 dark:text-slate-400">Two elected members, neither the Treasurer nor Secretary (Rule 153). Each is emailed a private link to sign themselves. To replace someone, cancel their request first — picking someone else here does nothing on its own.</p>
            <div v-if="auditorOneLocked" class="w-full px-3 py-2 text-sm bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-600 dark:text-slate-300">{{ auditorOneSignature.signer_name }} — {{ auditorOneSignature.label }}</div>
            <select v-else v-model="signOffForm.auditor_one_user_id" aria-label="First auditor" :class="fieldClass">
              <option value="">Auditor 1…</option>
              <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <div v-if="auditorTwoLocked" class="w-full px-3 py-2 text-sm bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-600 dark:text-slate-300">{{ auditorTwoSignature.signer_name }} — {{ auditorTwoSignature.label }}</div>
            <select v-else v-model="signOffForm.auditor_two_user_id" aria-label="Second auditor" :class="fieldClass">
              <option value="">Auditor 2…</option>
              <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <template v-if="!bothAuditorsLocked">
              <Input v-model="signOffForm.notes" label="Notes" placeholder="Notes (optional)" />
              <Button type="submit" :disabled="!signOffForm.auditor_one_user_id || !signOffForm.auditor_two_user_id || signOffForm.processing">
                Request signatures
              </Button>
            </template>
            <p v-else class="text-xs text-slate-500 dark:text-slate-400">Both signatures have been requested. Cancel one below to send it to someone else.</p>
          </template>
          <p v-else class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Fully signed off — download each auditor's certificate below.</p>

          <div v-if="Object.keys(annualTreasurerReport.audit_signatures || {}).length" class="pt-2 space-y-1.5 border-t border-slate-200 dark:border-slate-800">
            <div v-for="(sig, purpose) in annualTreasurerReport.audit_signatures" :key="purpose" class="flex items-center justify-between gap-2 text-xs bg-slate-50 dark:bg-slate-800/50 rounded-xl px-3 py-2">
              <span class="font-medium text-slate-700 dark:text-slate-200">{{ sig.signer_name }}</span>
              <span class="flex items-center gap-2">
                <Badge :variant="sig.status === 'signed' ? 'success' : sig.status === 'pending' ? 'warning' : 'neutral'">{{ sig.label }}</Badge>
                <template v-if="sig.status === 'pending'">
                  <button type="button" @click="resendAuditSignature(purpose)" class="font-medium text-blue-700 dark:text-blue-300 hover:underline">Resend</button>
                  <button type="button" @click="cancelAuditSignature(purpose)" class="font-medium text-rose-700 dark:text-rose-400 hover:underline">Cancel</button>
                </template>
              </span>
            </div>
          </div>

          <a
            v-if="annualTreasurerReport.audit_sign_off"
            :href="route('admin.accounting.treasurer_report.export_pdf', { clubSlug: club.slug, year: annualTreasurerReport.financial_year })"
            target="_blank"
            :class="[linkButtonClass, 'w-full']"
          >
            <UiIcon name="download" :size="14" />
            Download signed PDF
          </a>
        </form>
      </div>
    </div>
</template>
