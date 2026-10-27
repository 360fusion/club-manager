<script setup>
import { formatMoney } from '@/Utils/currency';

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
          <h3 class="text-xl font-black text-slate-900 dark:text-white">Annual Treasurer's Report — {{ annualTreasurerReport.financial_year }}</h3>
          <p class="text-xs text-slate-600 dark:text-slate-300 font-semibold mt-0.5">
            <span :class="annualTreasurerReport.is_closed ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'">
              {{ annualTreasurerReport.is_closed ? '✓ Books Closed' : '● Books Open — Draft' }}
            </span>
            <span v-if="annualTreasurerReport.audit_sign_off" class="ml-2 text-slate-500 dark:text-slate-400">
              Signed off by {{ annualTreasurerReport.audit_sign_off.auditor_one }} &amp; {{ annualTreasurerReport.audit_sign_off.auditor_two }} on {{ annualTreasurerReport.audit_sign_off.signed_off_at }}
            </span>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" @click="changeTreasurerReportYear(annualTreasurerReport.financial_year - 1)" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 font-extrabold text-xs rounded-xl cursor-pointer">← {{ annualTreasurerReport.financial_year - 1 }}</button>
          <button type="button" @click="changeTreasurerReportYear(annualTreasurerReport.financial_year + 1)" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 font-extrabold text-xs rounded-xl cursor-pointer">{{ annualTreasurerReport.financial_year + 1 }} →</button>
          <a :href="route('admin.accounting.treasurer_report.export_pdf', { clubSlug: club.slug, year: annualTreasurerReport.financial_year })" target="_blank" class="px-3 py-2 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl cursor-pointer">📄 PDF</a>
          <a :href="route('admin.accounting.treasurer_report.export_csv', { clubSlug: club.slug, year: annualTreasurerReport.financial_year })" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-xl cursor-pointer">⬇️ CSV</a>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800">
          <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block">General Fund Net Surplus</span>
          <span class="text-lg font-black text-slate-900 dark:text-white">{{ formatMoney(annualTreasurerReport.general_fund.net_surplus) }}</span>
        </div>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800">
          <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block">Charity Fund Movement</span>
          <span class="text-lg font-black text-slate-900 dark:text-white">{{ formatMoney(annualTreasurerReport.charity_fund.collections_total - annualTreasurerReport.charity_fund.grants_disbursed_total) }}</span>
        </div>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800">
          <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block">Members in Arrears</span>
          <span class="text-lg font-black text-slate-900 dark:text-white">{{ annualTreasurerReport.membership.in_arrears_count }}</span>
        </div>
      </div>

      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase text-[10px]"><tr><th class="py-2 px-3">General Fund Account</th><th class="py-2 px-3 text-right">Amount</th></tr></thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold text-slate-800 dark:text-slate-100 bg-white dark:bg-slate-900">
            <tr v-for="row in annualTreasurerReport.general_fund.rows" :key="row.code">
              <td class="py-2 px-3">{{ row.code }} — {{ row.name }}</td>
              <td class="py-2 px-3 text-right font-mono">{{ formatMoney(row.amount) }}</td>
            </tr>
            <tr v-if="!annualTreasurerReport.general_fund.rows.length"><td colspan="2" class="py-4 px-3 text-center text-slate-400 italic">No General Fund activity this year.</td></tr>
          </tbody>
          <tfoot>
            <tr class="bg-slate-100 dark:bg-slate-800 font-extrabold text-slate-900 dark:text-white"><td class="py-2 px-3">Net Surplus / (Deficit)</td><td class="py-2 px-3 text-right font-mono">{{ formatMoney(annualTreasurerReport.general_fund.net_surplus) }}</td></tr>
          </tfoot>
        </table>
      </div>

      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase text-[10px]"><tr><th class="py-2 px-3">Bank / Account</th><th class="py-2 px-3 text-right">Opening</th><th class="py-2 px-3 text-right">Closing (Ledger)</th><th class="py-2 px-3 text-right">Closing (Statement)</th></tr></thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold text-slate-800 dark:text-slate-100 bg-white dark:bg-slate-900">
            <tr v-for="(b, idx) in annualTreasurerReport.bank_accounts" :key="idx">
              <td class="py-2 px-3">{{ b.bank_name }} — {{ b.account_name }}</td>
              <td class="py-2 px-3 text-right font-mono">{{ formatMoney(b.opening_balance) }}</td>
              <td class="py-2 px-3 text-right font-mono">{{ formatMoney(b.closing_ledger_balance) }}</td>
              <td class="py-2 px-3 text-right font-mono">{{ formatMoney(b.closing_statement_balance) }}</td>
            </tr>
            <tr v-if="!annualTreasurerReport.bank_accounts.length"><td colspan="4" class="py-4 px-3 text-center text-slate-400 italic">No bank accounts connected.</td></tr>
          </tbody>
        </table>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <form @submit.prevent="submitCloseYear" class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 space-y-3">
          <h4 class="font-extrabold text-slate-900 dark:text-white text-sm">Close Financial Year</h4>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">Blocks new entries dated in {{ annualTreasurerReport.financial_year }}. A super admin can reopen it later if needed.</p>
          <input v-model="closeYearForm.notes" placeholder="Notes (optional)" class="w-full px-3 py-2 text-xs bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl" />
          <button type="submit" :disabled="annualTreasurerReport.is_closed || closeYearForm.processing" class="px-3 py-2 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl cursor-pointer disabled:opacity-50">
            {{ annualTreasurerReport.is_closed ? 'Already closed' : `Close ${annualTreasurerReport.financial_year}` }}
          </button>
        </form>

        <form @submit.prevent="submitSignOff" class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 space-y-3">
          <h4 class="font-extrabold text-slate-900 dark:text-white text-sm">Auditors' Sign-Off</h4>
          <template v-if="!annualTreasurerReport.audit_sign_off">
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Two elected members, neither the Treasurer nor Secretary (Rule 153). Each is emailed a private link to sign themselves. To replace someone, cancel their request first — picking someone else here does nothing on its own.</p>
            <div v-if="auditorOneLocked" class="w-full px-3 py-2 text-xs bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-600 dark:text-slate-300 font-semibold">{{ auditorOneSignature.signer_name }} — {{ auditorOneSignature.label }}</div>
            <select v-else v-model="signOffForm.auditor_one_user_id" class="w-full px-3 py-2 text-xs bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl">
              <option value="">Auditor 1…</option>
              <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <div v-if="auditorTwoLocked" class="w-full px-3 py-2 text-xs bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-600 dark:text-slate-300 font-semibold">{{ auditorTwoSignature.signer_name }} — {{ auditorTwoSignature.label }}</div>
            <select v-else v-model="signOffForm.auditor_two_user_id" class="w-full px-3 py-2 text-xs bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl">
              <option value="">Auditor 2…</option>
              <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <template v-if="!bothAuditorsLocked">
              <input v-model="signOffForm.notes" placeholder="Notes (optional)" class="w-full px-3 py-2 text-xs bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl" />
              <button type="submit" :disabled="!signOffForm.auditor_one_user_id || !signOffForm.auditor_two_user_id || signOffForm.processing" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs rounded-xl cursor-pointer disabled:opacity-50">
                Request signatures
              </button>
            </template>
            <p v-else class="text-[11px] text-slate-500 dark:text-slate-400">Both signatures have been requested. Cancel one below to send it to someone else.</p>
          </template>
          <p v-else class="text-[11px] text-emerald-700 dark:text-emerald-300 font-semibold">Fully signed off — download each auditor's certificate below.</p>

          <div v-if="Object.keys(annualTreasurerReport.audit_signatures || {}).length" class="pt-2 space-y-1.5 border-t border-slate-100 dark:border-slate-800">
            <div v-for="(sig, purpose) in annualTreasurerReport.audit_signatures" :key="purpose" class="flex items-center justify-between text-[11px] bg-slate-50 dark:bg-slate-800/50 rounded-xl px-3 py-2">
              <span class="font-semibold text-slate-700 dark:text-slate-200">{{ sig.signer_name }}</span>
              <span class="flex items-center gap-2">
                <span :class="['font-bold', sig.status === 'signed' ? 'text-emerald-700 dark:text-emerald-300' : sig.status === 'pending' ? 'text-amber-700 dark:text-amber-300' : 'text-slate-500']">{{ sig.label }}</span>
                <template v-if="sig.status === 'pending'">
                  <button type="button" @click="resendAuditSignature(purpose)" class="text-blue-600 dark:text-blue-400 font-bold underline cursor-pointer">Resend</button>
                  <button type="button" @click="cancelAuditSignature(purpose)" class="text-rose-600 dark:text-rose-400 font-bold underline cursor-pointer">Cancel</button>
                </template>
              </span>
            </div>
          </div>

          <a
            v-if="annualTreasurerReport.audit_sign_off"
            :href="route('admin.accounting.treasurer_report.export_pdf', { clubSlug: club.slug, year: annualTreasurerReport.financial_year })"
            target="_blank"
            class="block text-center px-3 py-2 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl cursor-pointer"
          >
            📄 Download signed PDF
          </a>
        </form>
      </div>
    </div>
</template>
