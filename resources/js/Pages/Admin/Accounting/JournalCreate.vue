<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: { type: Object, required: true },
  accounts: { type: Array, default: () => [] },
});

const form = useForm({
  description: '',
  entry_date: new Date().toISOString().split('T')[0],
  items: [
    { account_id: props.accounts[0]?.id || '', debit: '', credit: '', memo: '' },
    { account_id: props.accounts[1]?.id || '', debit: '', credit: '', memo: '' },
  ],
});

const totalDebit = computed(() => {
  return form.items.reduce((sum, item) => sum + (parseFloat(item.debit) || 0), 0);
});

const totalCredit = computed(() => {
  return form.items.reduce((sum, item) => sum + (parseFloat(item.credit) || 0), 0);
});

const isBalanced = computed(() => {
  const diff = Math.abs(totalDebit.value - totalCredit.value);
  return diff < 0.001 && totalDebit.value > 0;
});

const addLine = () => {
  form.items.push({ account_id: props.accounts[0]?.id || '', debit: '', credit: '', memo: '' });
};

const removeLine = (index) => {
  if (form.items.length > 2) {
    form.items.splice(index, 1);
  }
};

const submit = () => {
  if (!isBalanced.value) return;
  form.post(route('admin.accounting.journal.store', props.club.slug));
};
</script>

<template>
  <AdminLayout :club="club" title="Post Journal Entry" active-tab="accounting">
    <Head :title="`Post Journal Entry — ${club.name}`" />

    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">

      <!-- Breadcrumb & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1" aria-label="Breadcrumb">
            <Link :href="route('admin.accounting.index', club.slug)" class="hover:text-slate-900 dark:hover:text-white transition-colors">
              Accounting ERP
            </Link>
            <span>/</span>
            <Link :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'accounting' })" class="hover:text-slate-900 dark:hover:text-white transition-colors">
              General Ledger
            </Link>
            <span>/</span>
            <span class="text-slate-900 dark:text-white font-bold" aria-current="page">Post Journal Entry</span>
          </nav>
          <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Post Manual Journal Entry</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Create double-entry ledger postings with balanced debits and credits.</p>
        </div>
        <Link
          :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'accounting' })"
          class="inline-flex items-center gap-1 px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition-colors shrink-0"
        >
          ← Back to General Ledger
        </Link>
      </div>

      <!-- Balance Status Bar -->
      <div
        class="rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3 border transition-colors"
        :class="isBalanced ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/60 text-emerald-900 dark:text-emerald-200' : 'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/60 text-amber-900 dark:text-amber-200'"
      >
        <div class="flex items-center gap-2">
          <span class="text-xl">{{ isBalanced ? '✅' : '⚖️' }}</span>
          <div>
            <p class="text-xs font-black uppercase tracking-wider">
              {{ isBalanced ? 'Journal Entry Balanced' : 'Unbalanced Journal Entry' }}
            </p>
            <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5">
              {{ isBalanced ? 'Total Debits match Total Credits.' : 'Total Debits must equal Total Credits before posting.' }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-4 text-xs font-bold shrink-0">
          <div>Debits: <span class="font-mono text-sm text-slate-900 dark:text-white">{{ $cs }}{{ totalDebit.toFixed(2) }}</span></div>
          <div>Credits: <span class="font-mono text-sm text-slate-900 dark:text-white">{{ $cs }}{{ totalCredit.toFixed(2) }}</span></div>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="p-6 sm:p-8 space-y-6">

          <!-- Entry Header -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-2 space-y-1">
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Journal Description / Memo <span class="text-rose-500">*</span></label>
              <input
                v-model="form.description"
                type="text"
                required
                placeholder="e.g. Monthly Depreciation Adjustment / Reclassification"
                class="w-full px-4 py-2.5 text-sm bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400"
              />
              <p v-if="form.errors.description" class="text-xs text-rose-500 font-semibold">{{ form.errors.description }}</p>
            </div>

            <div class="space-y-1">
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Posting Date <span class="text-rose-500">*</span></label>
              <input
                v-model="form.entry_date"
                type="date"
                required
                class="w-full px-4 py-2.5 text-sm bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400"
              />
              <p v-if="form.errors.entry_date" class="text-xs text-rose-500 font-semibold">{{ form.errors.entry_date }}</p>
            </div>
          </div>

          <hr class="border-slate-100 dark:border-slate-800" />

          <!-- Journal Lines Table -->
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <h3 class="text-xs font-black text-slate-400 uppercase tracking-wider">Journal Line Items</h3>
              <button
                type="button"
                @click="addLine"
                class="inline-flex items-center gap-1 text-xs font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 transition-colors"
              >
                + Add Line Item
              </button>
            </div>

            <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
              <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider">
                  <tr>
                    <th class="py-3 px-4 w-1/3">Account</th>
                    <th class="py-3 px-4">Line Memo</th>
                    <th class="py-3 px-4 w-28 text-right">Debit ({{ $cs }})</th>
                    <th class="py-3 px-4 w-28 text-right">Credit ({{ $cs }})</th>
                    <th class="py-3 px-3 w-10 text-center"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 bg-white dark:bg-slate-900">
                  <tr v-for="(item, index) in form.items" :key="index" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                    <td class="p-3">
                      <select
                        v-model="item.account_id"
                        required
                        class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-slate-900 dark:focus:ring-slate-400"
                      >
                        <option v-for="acct in accounts" :key="acct.id" :value="acct.id">
                          {{ acct.code }} — {{ acct.name }} ({{ acct.type }})
                        </option>
                      </select>
                    </td>
                    <td class="p-3">
                      <input
                        v-model="item.memo"
                        type="text"
                        placeholder="Optional memo"
                        class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-lg text-xs focus:outline-none focus:ring-1 focus:ring-slate-900 dark:focus:ring-slate-400"
                      />
                    </td>
                    <td class="p-3">
                      <input
                        v-model="item.debit"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        @input="item.credit = item.debit ? '' : item.credit"
                        class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-lg text-xs text-right font-mono focus:outline-none focus:ring-1 focus:ring-slate-900 dark:focus:ring-slate-400"
                      />
                    </td>
                    <td class="p-3">
                      <input
                        v-model="item.credit"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        @input="item.debit = item.credit ? '' : item.debit"
                        class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-lg text-xs text-right font-mono focus:outline-none focus:ring-1 focus:ring-slate-900 dark:focus:ring-slate-400"
                      />
                    </td>
                    <td class="p-3 text-center">
                      <button
                        type="button"
                        :disabled="form.items.length <= 2"
                        @click="removeLine(index)"
                        class="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 disabled:opacity-30 transition-colors"
                      >
                        ✕
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot class="bg-slate-50 dark:bg-slate-800/50 font-bold border-t border-slate-200 dark:border-slate-800">
                  <tr>
                    <td colspan="2" class="py-3 px-4 text-right text-slate-600 dark:text-slate-300">Totals:</td>
                    <td class="py-3 px-4 text-right font-mono text-slate-900 dark:text-white">{{ $cs }}{{ totalDebit.toFixed(2) }}</td>
                    <td class="py-3 px-4 text-right font-mono text-slate-900 dark:text-white">{{ $cs }}{{ totalCredit.toFixed(2) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <p v-if="form.errors.items" class="text-xs text-rose-500 font-semibold">{{ form.errors.items }}</p>
          </div>

        </div>

        <!-- Footer Actions -->
        <div class="p-6 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
          <Link
            :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'accounting' })"
            class="px-5 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors"
          >
            Cancel
          </Link>
          <button
            type="submit"
            :disabled="!isBalanced || form.processing"
            class="px-6 py-2.5 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold text-sm rounded-xl transition-all shadow-md shadow-slate-900/10 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="form.processing">Posting...</span>
            <span v-else>Post Double-Entry Journal</span>
          </button>
        </div>
      </form>

    </div>
  </AdminLayout>
</template>
