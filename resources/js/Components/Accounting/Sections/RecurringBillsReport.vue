<script setup>
import { localDate } from '@/Utils/localDateTime';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

// The recurring_bills section of Index.vue, in its own file so the page stays readable. State only this section uses lives here.
const props = defineProps({
    bills: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    deleteRecurringBill: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    recurringBillTemplates: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    toggleRecurringBill: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const showAddRecurringBillForm = ref(false);

const recurringBillForm = useForm({
  vendor_name: '',
  category: 'General Expense',
  amount: '',
  frequency: 'monthly',
  next_run_date: localDate(),
  notes: '',
});

const submitRecurringBill = () => {
  recurringBillForm.post(route('admin.accounting.recurring_bills.store', props.club.slug), {
    preserveScroll: true,
    onSuccess: () => {
      recurringBillForm.reset();
      showAddRecurringBillForm.value = false;
    },
  });
};
</script>

<template>
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50 dark:bg-slate-800/50 p-6 rounded-2xl border border-slate-200 dark:border-slate-800">
        <div>
          <h3 class="text-xl font-black text-slate-900 dark:text-white">Recurring Vendor Bills</h3>
          <p class="text-xs text-slate-600 dark:text-slate-300 font-semibold mt-0.5">A daily scheduled task turns a due schedule into a real bill automatically.</p>
        </div>
        <button type="button" @click="showAddRecurringBillForm = !showAddRecurringBillForm" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-xl transition-all cursor-pointer">
          ➕ Add Schedule
        </button>
      </div>

      <form v-if="showAddRecurringBillForm" @submit.prevent="submitRecurringBill" class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
        <input v-model="recurringBillForm.vendor_name" placeholder="Vendor name" required class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl sm:col-span-2" />
        <input v-model="recurringBillForm.category" placeholder="Category" class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl" />
        <input v-model="recurringBillForm.amount" type="number" step="0.01" min="0.01" placeholder="Amount" required class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl" />
        <select v-model="recurringBillForm.frequency" class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl">
          <option value="monthly">Monthly</option>
          <option value="quarterly">Quarterly</option>
          <option value="annually">Annually</option>
        </select>
        <input v-model="recurringBillForm.next_run_date" type="date" required class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl" />
        <button type="submit" :disabled="recurringBillForm.processing" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold rounded-xl transition-all cursor-pointer disabled:opacity-50 sm:col-span-3">
          Schedule Recurring Bill
        </button>
      </form>

      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase text-[10px]">
            <tr><th class="py-2 px-3">Vendor</th><th class="py-2 px-3">Category</th><th class="py-2 px-3 text-right">Amount</th><th class="py-2 px-3">Frequency</th><th class="py-2 px-3">Next Run</th><th class="py-2 px-3 text-center">Status</th><th class="py-2 px-3"></th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold text-slate-800 dark:text-slate-100 bg-white dark:bg-slate-900">
            <tr v-for="t in recurringBillTemplates" :key="t.id">
              <td class="py-2 px-3 font-bold text-slate-900 dark:text-white">{{ t.vendor_name }}</td>
              <td class="py-2 px-3">{{ t.category }}</td>
              <td class="py-2 px-3 text-right font-mono">{{ t.formatted_amount }}</td>
              <td class="py-2 px-3 capitalize">{{ t.frequency }}</td>
              <td class="py-2 px-3 font-mono">{{ t.next_run_date }}</td>
              <td class="py-2 px-3 text-center">
                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-black uppercase', t.is_active ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-500']">{{ t.is_active ? 'Active' : 'Paused' }}</span>
              </td>
              <td class="py-2 px-3 text-right whitespace-nowrap">
                <button type="button" @click="toggleRecurringBill(t.id)" class="text-[10px] font-extrabold text-blue-700 dark:text-blue-300 hover:underline cursor-pointer mr-2">{{ t.is_active ? 'Pause' : 'Resume' }}</button>
                <button type="button" @click="deleteRecurringBill(t.id)" class="text-[10px] font-extrabold text-rose-700 dark:text-rose-300 hover:underline cursor-pointer">Remove</button>
              </td>
            </tr>
            <tr v-if="!recurringBillTemplates.length">
              <td colspan="7" class="py-4 px-3 text-center text-slate-400 italic">No recurring bills scheduled.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
