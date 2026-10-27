<script setup>
import StatCard from '@/Components/Ui/StatCard.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
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
          <h3 class="text-xl font-semibold text-slate-900 dark:text-white">VAT return — {{ reports.vat_return?.quarter_label }}</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ reports.vat_return?.quarter_start }} to {{ reports.vat_return?.quarter_end }}</p>
        </div>
        <a :href="route('admin.accounting.vat_return.export', club.slug)" class="inline-flex items-center justify-center gap-2 self-start rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
          <UiIcon name="download" />
          Export CSV
        </a>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <StatCard label="Output VAT (on sales)" :value="formatMoney(reports.vat_return?.output_vat)" />
        <StatCard label="Input VAT (on purchases)" :value="formatMoney(reports.vat_return?.input_vat)" />
        <StatCard label="Net VAT due to HMRC" :value="formatMoney(reports.vat_return?.net_vat_due)" />
      </div>
      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="py-3 px-3 font-medium">Date</th>
              <th scope="col" class="py-3 px-3 font-medium">Type</th>
              <th scope="col" class="py-3 px-3 font-medium">Reference</th>
              <th scope="col" class="py-3 px-3 font-medium text-right">Net</th>
              <th scope="col" class="py-3 px-3 font-medium text-right">VAT</th>
              <th scope="col" class="py-3 px-3 font-medium text-right">Gross</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900">
            <tr v-for="(row, idx) in (reports.vat_return?.rows || [])" :key="idx">
              <td class="py-2 px-3 tabular-nums">{{ row.date }}</td>
              <td class="py-2 px-3">{{ row.type === 'sale' ? 'Sale' : 'Purchase' }}</td>
              <td class="py-2 px-3">{{ row.reference }} — {{ row.description }}</td>
              <td class="py-2 px-3 text-right tabular-nums">{{ formatMoney(row.net_amount) }}</td>
              <td class="py-2 px-3 text-right tabular-nums">{{ formatMoney(row.vat_amount) }}</td>
              <td class="py-2 px-3 text-right tabular-nums">{{ formatMoney(row.gross_amount) }}</td>
            </tr>
            <tr v-if="!(reports.vat_return?.rows || []).length">
              <td colspan="6" class="py-4 px-3 text-center text-slate-500 dark:text-slate-400">No VAT-inclusive transactions this quarter.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
