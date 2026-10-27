<script setup>
import Badge from '@/Components/Ui/Badge.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import { formatMoney } from '@/Utils/currency';

// aged_payables section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    bills: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div class="flex items-center justify-between gap-2">
        <h4 class="text-base font-semibold text-slate-900 dark:text-white">Aged payables</h4>
        <span class="text-xs font-medium text-slate-600 dark:text-slate-300 tabular-nums">Total outstanding: {{ formatMoney(reports.aged_payables.total) }}</span>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <StatCard label="Current" :value="formatMoney(reports.aged_payables.current)" />
        <StatCard label="1 - 30 days" :value="formatMoney(reports.aged_payables['1_30'])" />
        <StatCard label="31 - 60 days" :value="formatMoney(reports.aged_payables['31_60'])" />
        <StatCard label="61 - 90 days" :value="formatMoney(reports.aged_payables['61_90'])" />
        <StatCard label="90+ days" :value="formatMoney(reports.aged_payables['90_plus'])" />
      </div>
      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
        <table class="w-full text-left border-collapse text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="py-3 px-4 font-medium">Bill #</th>
              <th scope="col" class="py-3 px-4 font-medium">Vendor name</th>
              <th scope="col" class="py-3 px-4 font-medium">Due date</th>
              <th scope="col" class="py-3 px-4 font-medium">Aging bucket</th>
              <th scope="col" class="py-3 px-4 font-medium text-right">Amount</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200">
            <tr v-if="!reports.aged_payables.items.length">
              <td colspan="5" class="py-6 text-center text-slate-500 dark:text-slate-400">No outstanding unpaid vendor bills.</td>
            </tr>
            <tr v-for="b in reports.aged_payables.items" :key="b.bill_number" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
              <td class="py-2.5 px-4 tabular-nums font-medium text-slate-900 dark:text-white">{{ b.bill_number }}</td>
              <td class="py-2.5 px-4 font-medium text-slate-900 dark:text-white">{{ b.vendor_name }}</td>
              <td class="py-2.5 px-4 text-slate-500 dark:text-slate-400">{{ b.due_date }}</td>
              <td class="py-2.5 px-4">
                <Badge variant="warning">{{ b.bucket }}</Badge>
                <Badge v-if="b.status === 'pending_approval'" variant="info" class="ml-1">Pending approval</Badge>
              </td>
              <td class="py-2.5 px-4 text-right tabular-nums font-semibold text-slate-900 dark:text-white">{{ formatMoney(b.amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
