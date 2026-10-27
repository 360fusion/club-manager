<script setup>
import { localDate } from '@/Utils/localDateTime';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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
      <div class="flex flex-col justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-6 sm:flex-row sm:items-center dark:border-slate-800 dark:bg-slate-800/50">
        <div>
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Recurring vendor bills</h3>
          <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">A daily scheduled task turns a due schedule into a real bill automatically.</p>
        </div>
        <Button size="sm" :aria-expanded="showAddRecurringBillForm" @click="showAddRecurringBillForm = !showAddRecurringBillForm">
          <template #icon><UiIcon name="plus" /></template>
          Add schedule
        </Button>
      </div>

      <form v-if="showAddRecurringBillForm" class="grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-3 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="submitRecurringBill">
        <Input v-model="recurringBillForm.vendor_name" class="sm:col-span-2" label="Vendor name" required :error="recurringBillForm.errors.vendor_name" />
        <Input v-model="recurringBillForm.category" label="Category" :error="recurringBillForm.errors.category" />
        <Input v-model="recurringBillForm.amount" type="number" step="0.01" min="0.01" label="Amount" :prefix="$cs" required :error="recurringBillForm.errors.amount" />
        <Select
          v-model="recurringBillForm.frequency"
          label="Frequency"
          :options="[{ value: 'monthly', label: 'Monthly' }, { value: 'quarterly', label: 'Quarterly' }, { value: 'annually', label: 'Annually' }]"
          :error="recurringBillForm.errors.frequency"
        />
        <Input v-model="recurringBillForm.next_run_date" type="date" label="Next run" required :error="recurringBillForm.errors.next_run_date" />
        <div class="sm:col-span-3">
          <Button type="submit" :loading="recurringBillForm.processing">Schedule recurring bill</Button>
        </div>
      </form>

      <div class="overflow-x-auto rounded-2xl border border-slate-200 shadow-sm dark:border-slate-800">
        <table class="w-full text-left text-sm">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="px-3 py-2.5 font-medium">Vendor</th>
              <th scope="col" class="px-3 py-2.5 font-medium">Category</th>
              <th scope="col" class="px-3 py-2.5 text-right font-medium">Amount</th>
              <th scope="col" class="px-3 py-2.5 font-medium">Frequency</th>
              <th scope="col" class="px-3 py-2.5 font-medium">Next run</th>
              <th scope="col" class="px-3 py-2.5 text-center font-medium">Status</th>
              <th scope="col" class="px-3 py-2.5"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white text-slate-800 dark:divide-slate-800 dark:bg-slate-900 dark:text-slate-100">
            <tr v-for="t in recurringBillTemplates" :key="t.id">
              <td class="px-3 py-2 font-medium text-slate-900 dark:text-white">{{ t.vendor_name }}</td>
              <td class="px-3 py-2">{{ t.category }}</td>
              <td class="px-3 py-2 text-right tabular-nums">{{ t.formatted_amount }}</td>
              <td class="px-3 py-2 capitalize">{{ t.frequency }}</td>
              <td class="px-3 py-2 tabular-nums">{{ t.next_run_date }}</td>
              <td class="px-3 py-2 text-center">
                <Badge :variant="t.is_active ? 'success' : 'neutral'">{{ t.is_active ? 'Active' : 'Paused' }}</Badge>
              </td>
              <td class="whitespace-nowrap px-3 py-2 text-right">
                <Button variant="ghost" size="sm" @click="toggleRecurringBill(t.id)">{{ t.is_active ? 'Pause' : 'Resume' }}</Button>
                <button type="button" class="cursor-pointer rounded-xl px-3 py-1.5 text-xs font-medium text-rose-700 transition-colors hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/40" @click="deleteRecurringBill(t.id)">Remove</button>
              </td>
            </tr>
            <tr v-if="!recurringBillTemplates.length">
              <td colspan="7" class="px-3 py-4 text-center text-slate-500 dark:text-slate-400">No recurring bills scheduled.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
