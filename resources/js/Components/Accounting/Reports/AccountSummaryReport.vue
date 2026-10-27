<script setup>
import { formatMoney } from '@/Utils/currency';

// account_summary section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    getTypeBadge: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h4 class="text-base font-extrabold text-slate-900 dark:text-white">Account Summary Report</h4>
        <span class="text-xs font-semibold text-slate-400">All Active Accounts</span>
      </div>
      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase">
              <th class="py-3 px-4">Code</th>
              <th class="py-3 px-4">Account Name</th>
              <th class="py-3 px-4">Type</th>
              <th class="py-3 px-4 text-right">Total Debit ({{ $cs }})</th>
              <th class="py-3 px-4 text-right">Total Credit ({{ $cs }})</th>
              <th class="py-3 px-4 text-right">Net Balance ({{ $cs }})</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold text-slate-700 dark:text-slate-200">
            <tr v-for="acc in reports.account_summary" :key="acc.code" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
              <td class="py-2.5 px-4 font-mono font-bold text-slate-900 dark:text-white">{{ acc.code }}</td>
              <td class="py-2.5 px-4 font-bold text-slate-900 dark:text-white">{{ acc.name }}</td>
              <td class="py-2.5 px-4">
                <span :class="['px-2 py-0.5 rounded text-[10px] font-black uppercase border', getTypeBadge(acc.type)]">
                  {{ acc.type }}
                </span>
              </td>
              <td class="py-2.5 px-4 text-right font-mono">{{ formatMoney(acc.total_debit) }}</td>
              <td class="py-2.5 px-4 text-right font-mono">{{ formatMoney(acc.total_credit) }}</td>
              <td class="py-2.5 px-4 text-right font-mono font-black text-slate-900 dark:text-white">{{ formatMoney(acc.net_balance) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
