<script setup>
import { formatMoney } from '@/Utils/currency';

// vat_return section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50 dark:bg-slate-800/50 p-6 rounded-2xl border border-slate-200 dark:border-slate-800">
        <div>
          <h3 class="text-xl font-black text-slate-900 dark:text-white">VAT Return — {{ reports.vat_return?.quarter_label }}</h3>
          <p class="text-xs text-slate-600 dark:text-slate-300 font-semibold mt-0.5">{{ reports.vat_return?.quarter_start }} to {{ reports.vat_return?.quarter_end }}</p>
        </div>
        <a :href="route('admin.accounting.vat_return.export', club.slug)" class="px-4 py-2 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-extrabold text-xs rounded-xl transition-all cursor-pointer shadow-sm flex items-center gap-1.5">
          ⬇️ Export CSV
        </a>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800">
          <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block">Output VAT (on sales)</span>
          <span class="text-lg font-black text-emerald-700 dark:text-emerald-300">{{ formatMoney(reports.vat_return?.output_vat) }}</span>
        </div>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800">
          <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block">Input VAT (on purchases)</span>
          <span class="text-lg font-black text-rose-700 dark:text-rose-300">{{ formatMoney(reports.vat_return?.input_vat) }}</span>
        </div>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800">
          <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 block">Net VAT Due to HMRC</span>
          <span class="text-lg font-black text-slate-900 dark:text-white">{{ formatMoney(reports.vat_return?.net_vat_due) }}</span>
        </div>
      </div>
      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase text-[10px]">
            <tr>
              <th class="py-2 px-3">Date</th>
              <th class="py-2 px-3">Type</th>
              <th class="py-2 px-3">Reference</th>
              <th class="py-2 px-3 text-right">Net</th>
              <th class="py-2 px-3 text-right">VAT</th>
              <th class="py-2 px-3 text-right">Gross</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold text-slate-800 dark:text-slate-100 bg-white dark:bg-slate-900">
            <tr v-for="(row, idx) in (reports.vat_return?.rows || [])" :key="idx">
              <td class="py-2 px-3 font-mono">{{ row.date }}</td>
              <td class="py-2 px-3">{{ row.type === 'sale' ? 'Sale' : 'Purchase' }}</td>
              <td class="py-2 px-3">{{ row.reference }} — {{ row.description }}</td>
              <td class="py-2 px-3 text-right font-mono">{{ formatMoney(row.net_amount) }}</td>
              <td class="py-2 px-3 text-right font-mono">{{ formatMoney(row.vat_amount) }}</td>
              <td class="py-2 px-3 text-right font-mono">{{ formatMoney(row.gross_amount) }}</td>
            </tr>
            <tr v-if="!(reports.vat_return?.rows || []).length">
              <td colspan="6" class="py-4 px-3 text-center text-slate-400 italic">No VAT-inclusive transactions this quarter.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
