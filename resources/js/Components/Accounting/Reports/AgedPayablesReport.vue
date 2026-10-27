<script setup>
import { formatMoney } from '@/Utils/currency';

// aged_payables section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    bills: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h4 class="text-base font-extrabold text-slate-900 dark:text-white">Aged Payables Summary</h4>
        <span class="text-xs font-semibold text-amber-700 dark:text-amber-300 font-mono">Total Outstanding: {{ formatMoney(reports.aged_payables.total) }}</span>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center text-xs">
        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
          <span class="text-[10px] font-bold text-slate-400 block uppercase">Current</span>
          <span class="font-black text-slate-800 dark:text-slate-100 text-sm block">{{ formatMoney(reports.aged_payables.current) }}</span>
        </div>
        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
          <span class="text-[10px] font-bold text-slate-400 block uppercase">1 - 30 Days</span>
          <span class="font-black text-amber-700 dark:text-amber-300 text-sm block">{{ formatMoney(reports.aged_payables['1_30']) }}</span>
        </div>
        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
          <span class="text-[10px] font-bold text-slate-400 block uppercase">31 - 60 Days</span>
          <span class="font-black text-amber-800 dark:text-amber-200 text-sm block">{{ formatMoney(reports.aged_payables['31_60']) }}</span>
        </div>
        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
          <span class="text-[10px] font-bold text-slate-400 block uppercase">61 - 90 Days</span>
          <span class="font-black text-rose-700 dark:text-rose-300 text-sm block">{{ formatMoney(reports.aged_payables['61_90']) }}</span>
        </div>
        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
          <span class="text-[10px] font-bold text-slate-400 block uppercase">90+ Days</span>
          <span class="font-black text-rose-900 dark:text-rose-200 text-sm block">{{ formatMoney(reports.aged_payables['90_plus']) }}</span>
        </div>
      </div>
      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase">
              <th class="py-3 px-4">Bill #</th>
              <th class="py-3 px-4">Vendor Name</th>
              <th class="py-3 px-4">Due Date</th>
              <th class="py-3 px-4">Aging Bucket</th>
              <th class="py-3 px-4 text-right">Amount</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold text-slate-700 dark:text-slate-200">
            <tr v-if="!reports.aged_payables.items.length">
              <td colspan="5" class="py-6 text-center text-slate-400">No outstanding unpaid vendor bills.</td>
            </tr>
            <tr v-for="b in reports.aged_payables.items" :key="b.bill_number" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
              <td class="py-2.5 px-4 font-mono font-bold text-slate-900 dark:text-white">{{ b.bill_number }}</td>
              <td class="py-2.5 px-4 font-bold text-slate-900 dark:text-white">{{ b.vendor_name }}</td>
              <td class="py-2.5 px-4 text-slate-500 dark:text-slate-400">{{ b.due_date }}</td>
              <td class="py-2.5 px-4">
                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800/60">
                  {{ b.bucket }}
                </span>
                <span v-if="b.status === 'pending_approval'" class="ml-1 px-2 py-0.5 rounded text-[10px] font-black bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border border-blue-200 dark:border-blue-800/60">
                  ⏳ Pending Approval
                </span>
              </td>
              <td class="py-2.5 px-4 text-right font-mono font-black text-slate-900 dark:text-white">{{ formatMoney(b.amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
