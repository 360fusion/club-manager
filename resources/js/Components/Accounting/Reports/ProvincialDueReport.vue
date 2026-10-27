<script setup>
import { formatMoney } from '@/Utils/currency';

// provincial_due section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    members: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div>
        <h4 class="text-base font-extrabold text-slate-900 dark:text-white">What's Due to Province</h4>
        <p v-if="reports.provincial_due?.province_name" class="text-xs text-slate-500 dark:text-slate-400">{{ reports.provincial_due.province_name }}</p>
      </div>
      <p v-if="!reports.provincial_due?.has_province" class="text-xs text-slate-500 dark:text-slate-400">This lodge is not linked to a Province, so no rates apply.</p>
      <template v-else>
        <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase">
                <th class="py-3 px-4">Item</th>
                <th class="py-3 px-4 text-right">Members</th>
                <th class="py-3 px-4 text-right">Rate</th>
                <th class="py-3 px-4 text-right">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold text-slate-700 dark:text-slate-200">
              <tr>
                <td class="py-2.5 px-4">Per-capita dues</td>
                <td class="py-2.5 px-4 text-right font-mono">{{ reports.provincial_due.member_count }}</td>
                <td class="py-2.5 px-4 text-right font-mono">{{ reports.provincial_due.per_capita_rate != null ? formatMoney(reports.provincial_due.per_capita_rate) : 'Rate not set' }}</td>
                <td class="py-2.5 px-4 text-right font-mono font-black">{{ reports.provincial_due.per_capita_amount != null ? formatMoney(reports.provincial_due.per_capita_amount) : '—' }}</td>
              </tr>
              <tr>
                <td class="py-2.5 px-4">Festival contribution</td>
                <td class="py-2.5 px-4 text-right font-mono">{{ reports.provincial_due.member_count }}</td>
                <td class="py-2.5 px-4 text-right font-mono">{{ reports.provincial_due.festival_rate != null ? formatMoney(reports.provincial_due.festival_rate) : 'Rate not set' }}</td>
                <td class="py-2.5 px-4 text-right font-mono font-black">{{ reports.provincial_due.festival_amount != null ? formatMoney(reports.provincial_due.festival_amount) : '—' }}</td>
              </tr>
              <tr class="bg-slate-50 dark:bg-slate-800/50">
                <td colspan="3" class="py-2.5 px-4 font-black">Total due to Province</td>
                <td class="py-2.5 px-4 text-right font-mono font-black">{{ reports.provincial_due.total_due != null ? formatMoney(reports.provincial_due.total_due) : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="reports.provincial_due.per_capita_rate == null || reports.provincial_due.festival_rate == null" class="text-xs text-amber-700 dark:text-amber-300 font-semibold">Where a rate shows "Rate not set", your Province has not published one here. Contact your Province for the figure.</p>
      </template>
      <p class="text-[11px] text-slate-400">Counted on {{ reports.provincial_due?.counted_on }} from current active members. Lodges keep no leave date for members, so this is today's count, not a snapshot at a past date.</p>
    </div>
</template>
