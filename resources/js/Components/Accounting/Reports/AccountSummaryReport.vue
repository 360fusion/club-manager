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
      <div class="flex items-center justify-between gap-2">
        <h4 class="text-base font-semibold text-slate-900 dark:text-white">Account summary</h4>
        <span class="text-xs text-slate-500 dark:text-slate-400">All active accounts</span>
      </div>
      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
        <table class="w-full text-left border-collapse text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="py-3 px-4 font-medium">Code</th>
              <th scope="col" class="py-3 px-4 font-medium">Account name</th>
              <th scope="col" class="py-3 px-4 font-medium">Type</th>
              <th scope="col" class="py-3 px-4 font-medium text-right">Total debit ({{ $cs }})</th>
              <th scope="col" class="py-3 px-4 font-medium text-right">Total credit ({{ $cs }})</th>
              <th scope="col" class="py-3 px-4 font-medium text-right">Net balance ({{ $cs }})</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200">
            <tr v-for="acc in reports.account_summary" :key="acc.code" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
              <td class="py-2.5 px-4 tabular-nums font-medium text-slate-900 dark:text-white">{{ acc.code }}</td>
              <td class="py-2.5 px-4 font-medium text-slate-900 dark:text-white">{{ acc.name }}</td>
              <td class="py-2.5 px-4">
                <span :class="['inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-medium capitalize border', getTypeBadge(acc.type)]">
                  {{ acc.type }}
                </span>
              </td>
              <td class="py-2.5 px-4 text-right tabular-nums">{{ formatMoney(acc.total_debit) }}</td>
              <td class="py-2.5 px-4 text-right tabular-nums">{{ formatMoney(acc.total_credit) }}</td>
              <td class="py-2.5 px-4 text-right tabular-nums font-semibold text-slate-900 dark:text-white">{{ formatMoney(acc.net_balance) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
