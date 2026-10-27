<script setup>
// Posting a manual journal entry and editing one: one page, so the two cannot drift apart. `journalEntry` is null when
// posting a new one.
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import { localDate } from '@/Utils/localDateTime';

const props = defineProps({
  club: { type: Object, required: true },
  accounts: { type: Array, default: () => [] },
  journalEntry: { type: Object, default: null },
});

const isEdit = computed(() => Boolean(props.journalEntry));

const blankLine = (account = props.accounts[0]) => ({ account_id: account?.id || '', debit: '', credit: '', memo: '' });

const form = useForm({
  description: props.journalEntry?.description ?? '',
  entry_date: props.journalEntry?.entry_date ?? localDate(),
  // A zero on a saved line shows as an empty box, the same as on a new line.
  items: props.journalEntry
    ? props.journalEntry.items.map((item) => ({ account_id: item.account_id, debit: item.debit || '', credit: item.credit || '', memo: item.memo ?? '' }))
    : [blankLine(props.accounts[0]), blankLine(props.accounts[1])],
});

// Each line is rounded to the penny first, as the ledger stores it, so this agrees with the server's check.
const pence = (value) => Math.round((Number(value) || 0) * 100);
const totalDebit = computed(() => form.items.reduce((sum, item) => sum + pence(item.debit), 0) / 100);
const totalCredit = computed(() => form.items.reduce((sum, item) => sum + pence(item.credit), 0) / 100);
const isBalanced = computed(() => totalDebit.value > 0 && Math.round(totalDebit.value * 100) === Math.round(totalCredit.value * 100));

const addLine = () => form.items.push(blankLine());
const removeLine = (index) => {
  if (form.items.length > 2) form.items.splice(index, 1);
};

// A line is a debit or a credit, never both: typing one clears the other.
const setAmount = (item, side, value) => {
  item[side] = value;
  if (value !== '') item[side === 'debit' ? 'credit' : 'debit'] = '';
};

const ledgerUrl = computed(() => route('admin.accounting.index', { clubSlug: props.club.slug, tab: 'accounting' }));

const submit = () => {
  if (!isBalanced.value) return;
  if (isEdit.value) {
    form.put(route('admin.accounting.journal.update', { clubSlug: props.club.slug, id: props.journalEntry.id }));
  } else {
    form.post(route('admin.accounting.journal.store', props.club.slug));
  }
};

const title = computed(() => (isEdit.value ? `Edit journal entry ${props.journalEntry.reference_number}` : 'Post a journal entry'));
const cell = 'w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
</script>

<template>
  <AdminLayout :club="club" :title="title" active-tab="accounting">
    <Head :title="`${title} — ${club.name}`" />

    <div class="mx-auto max-w-4xl space-y-6">
      <PageHeader
        :title="title"
        :description="isEdit ? 'Change the lines and accounts of this entry.' : 'A double-entry adjustment: the debits must equal the credits.'"
        :breadcrumbs="[
          { label: 'Accounting', href: route('admin.accounting.index', club.slug) },
          { label: 'General ledger', href: ledgerUrl },
          { label: isEdit ? journalEntry.reference_number : 'Post a journal entry' },
        ]"
      >
        <template #actions>
          <Button variant="ghost" size="sm" :href="ledgerUrl">Back to the ledger</Button>
        </template>
      </PageHeader>

      <form @submit.prevent="submit">
        <Card>
          <div class="space-y-6">
            <div class="grid gap-5 sm:grid-cols-3">
              <Input v-model="form.description" class="sm:col-span-2" label="Description" placeholder="Monthly depreciation adjustment" required :error="form.errors.description" />
              <Input v-model="form.entry_date" type="date" label="Date" required :error="form.errors.entry_date" />
            </div>

            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <h2 class="text-xs font-medium text-slate-700 dark:text-slate-300">Lines</h2>
                <Button variant="ghost" size="sm" @click="addLine">Add a line</Button>
              </div>

              <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="w-full min-w-[640px] text-left text-xs">
                  <thead class="border-b border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                    <tr>
                      <th scope="col" class="w-1/3 px-3 py-2.5 font-medium">Account</th>
                      <th scope="col" class="px-3 py-2.5 font-medium">Memo</th>
                      <th scope="col" class="w-32 px-3 py-2.5 text-right font-medium">Debit ({{ $cs }})</th>
                      <th scope="col" class="w-32 px-3 py-2.5 text-right font-medium">Credit ({{ $cs }})</th>
                      <th scope="col" class="w-10 px-2 py-2.5"><span class="sr-only">Remove</span></th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr v-for="(item, index) in form.items" :key="index">
                      <td class="p-2">
                        <select v-model="item.account_id" required :aria-label="`Account, line ${index + 1}`" :class="cell">
                          <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.code }} — {{ account.name }} ({{ account.type }})</option>
                        </select>
                      </td>
                      <td class="p-2"><input v-model="item.memo" type="text" placeholder="Optional" :aria-label="`Memo, line ${index + 1}`" :class="cell" /></td>
                      <td class="p-2"><input :value="item.debit" type="number" step="0.01" min="0" placeholder="0.00" inputmode="decimal" :aria-label="`Debit, line ${index + 1}`" :class="[cell, 'text-right font-mono']" @input="setAmount(item, 'debit', $event.target.value)" /></td>
                      <td class="p-2"><input :value="item.credit" type="number" step="0.01" min="0" placeholder="0.00" inputmode="decimal" :aria-label="`Credit, line ${index + 1}`" :class="[cell, 'text-right font-mono']" @input="setAmount(item, 'credit', $event.target.value)" /></td>
                      <td class="p-2 text-center">
                        <button type="button" :disabled="form.items.length <= 2" :aria-label="`Remove line ${index + 1}`" class="rounded-lg p-1 text-slate-400 hover:text-rose-600 disabled:opacity-30 dark:hover:text-rose-400" @click="removeLine(index)">
                          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                  <tfoot class="border-t border-slate-200 bg-slate-50 font-medium dark:border-slate-800 dark:bg-slate-800/50">
                    <tr>
                      <td colspan="2" class="px-3 py-2.5 text-right text-slate-600 dark:text-slate-300">Totals</td>
                      <td class="px-3 py-2.5 text-right font-mono text-slate-900 dark:text-white">{{ $money(totalDebit) }}</td>
                      <td class="px-3 py-2.5 text-right font-mono text-slate-900 dark:text-white">{{ $money(totalCredit) }}</td>
                      <td></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <p v-if="form.errors.items" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.items }}</p>

              <Alert :variant="isBalanced ? 'success' : 'warning'" :title="isBalanced ? 'Balanced' : 'Not balanced yet'">
                <template v-if="isBalanced">The debits equal the credits, so the entry can be saved.</template>
                <template v-else-if="totalDebit === 0 && totalCredit === 0">Enter the amounts on each line.</template>
                <template v-else>Debits {{ $money(totalDebit) }} and credits {{ $money(totalCredit) }} differ by {{ $money(Math.abs(totalDebit - totalCredit)) }}.</template>
              </Alert>
            </div>
          </div>

          <template #footer>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
              <Button variant="ghost" :href="ledgerUrl">Cancel</Button>
              <Button type="submit" :disabled="!isBalanced" :loading="form.processing">{{ isEdit ? 'Save journal entry' : 'Post journal entry' }}</Button>
            </div>
          </template>
        </Card>
      </form>
    </div>
  </AdminLayout>
</template>
