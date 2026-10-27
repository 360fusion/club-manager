<script setup>
import { formatMoney } from '@/Utils/currency';

// aged_receivables section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    invoices: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h4 class="text-base font-extrabold text-slate-900 dark:text-white">Aged Receivables Summary</h4>
        <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 font-mono">Total Outstanding: {{ formatMoney(reports.aged_receivables.total) }}</span>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center text-xs">
        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
          <span class="text-[10px] font-bold text-slate-400 block uppercase">Current</span>
          <span class="font-black text-slate-800 dark:text-slate-100 text-sm block">{{ formatMoney(reports.aged_receivables.current) }}</span>
        </div>
        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
          <span class="text-[10px] font-bold text-slate-400 block uppercase">1 - 30 Days</span>
          <span class="font-black text-emerald-700 dark:text-emerald-300 text-sm block">{{ formatMoney(reports.aged_receivables['1_30']) }}</span>
        </div>
        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
          <span class="text-[10px] font-bold text-slate-400 block uppercase">31 - 60 Days</span>
          <span class="font-black text-amber-700 dark:text-amber-300 text-sm block">{{ formatMoney(reports.aged_receivables['31_60']) }}</span>
        </div>
        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
          <span class="text-[10px] font-bold text-slate-400 block uppercase">61 - 90 Days</span>
          <span class="font-black text-rose-700 dark:text-rose-300 text-sm block">{{ formatMoney(reports.aged_receivables['61_90']) }}</span>
        </div>
        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
          <span class="text-[10px] font-bold text-slate-400 block uppercase">90+ Days</span>
          <span class="font-black text-rose-900 dark:text-rose-200 text-sm block">{{ formatMoney(reports.aged_receivables['90_plus']) }}</span>
        </div>
      </div>
      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase">
              <th class="py-3 px-4">Invoice #</th>
              <th class="py-3 px-4">Member Name</th>
              <th class="py-3 px-4">Date Issued</th>
              <th class="py-3 px-4">Aging Bucket</th>
              <th class="py-3 px-4 text-right">Amount</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold text-slate-700 dark:text-slate-200">
            <tr v-if="!reports.aged_receivables.items.length">
              <td colspan="5" class="py-6 text-center text-slate-400">No outstanding unpaid member invoices.</td>
            </tr>
            <tr v-for="inv in reports.aged_receivables.items" :key="inv.invoice_number" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
              <td class="py-2.5 px-4 font-mono font-bold text-slate-900 dark:text-white">{{ inv.invoice_number }}</td>
              <td class="py-2.5 px-4 font-bold text-slate-900 dark:text-white">{{ inv.recipient_name }}</td>
              <td class="py-2.5 px-4 text-slate-500 dark:text-slate-400">{{ inv.created_at }}</td>
              <td class="py-2.5 px-4">
                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800/60">
                  {{ inv.bucket }}
                </span>
                <span v-if="inv.status === 'pending_approval'" class="ml-1 px-2 py-0.5 rounded text-[10px] font-black bg-purple-50 dark:bg-purple-950/40 text-purple-800 dark:text-purple-200 border border-purple-200 dark:border-purple-800/60">
                  ⏳ Pending Approval
                </span>
              </td>
              <td class="py-2.5 px-4 text-right font-mono font-black text-slate-900 dark:text-white">{{ formatMoney(inv.amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
