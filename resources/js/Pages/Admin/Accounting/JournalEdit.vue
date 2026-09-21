<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed } from 'vue';

const props = defineProps({
  club: { type: Object, required: true },
  journalEntry: { type: Object, required: true },
  accounts: { type: Array, default: () => [] },
});

const form = useForm({
  description: props.journalEntry.description,
  entry_date: props.journalEntry.entry_date,
  items: props.journalEntry.items.map(item => ({
    account_id: item.account_id,
    debit: item.debit,
    credit: item.credit,
    memo: item.memo,
  })),
});

const addLine = () => {
  form.items.push({
    account_id: props.accounts[0]?.id || '',
    debit: 0,
    credit: 0,
    memo: '',
  });
};

const removeLine = (index) => {
  if (form.items.length > 2) {
    form.items.splice(index, 1);
  }
};

const totalDebit = computed(() =>
  form.items.reduce((sum, item) => sum + (Number(item.debit) || 0), 0)
);

const totalCredit = computed(() =>
  form.items.reduce((sum, item) => sum + (Number(item.credit) || 0), 0)
);

const isBalanced = computed(() =>
  Math.abs(totalDebit.value - totalCredit.value) < 0.001
);

const submit = () => {
  if (!isBalanced.value) return;
  form.put(route('admin.accounting.journal.update', { clubSlug: props.club.slug, id: props.journalEntry.id }));
};
</script>

<template>
  <AdminLayout :club="club" :title="`Edit Journal Entry ${journalEntry.reference_number}`" active-tab="accounting">
    <Head :title="`Edit ${journalEntry.reference_number} — ${club.name}`" />

    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">

      <!-- Breadcrumb & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1" aria-label="Breadcrumb">
            <Link :href="route('admin.accounting.index', club.slug)" class="hover:text-slate-900 dark:hover:text-white transition-colors">
              Accounting ERP
            </Link>
            <span>/</span>
            <Link :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'general_ledger' })" class="hover:text-slate-900 dark:hover:text-white transition-colors">
              General Ledger
            </Link>
            <span>/</span>
            <span class="text-slate-900 dark:text-white font-bold" aria-current="page">Edit {{ journalEntry.reference_number }}</span>
          </nav>
          <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Edit Journal Entry {{ journalEntry.reference_number }}</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Modify multi-line double-entry journal items and ledger allocations.</p>
        </div>
        <Link
          :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'general_ledger' })"
          class="inline-flex items-center gap-1 px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition-colors"
        >
          ← Back to General Ledger
        </Link>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="p-6 sm:p-8 space-y-6">

          <!-- Entry Details Header -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="space-y-1 sm:col-span-2">
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Description / Memo <span class="text-rose-500">*</span></label>
              <input
                v-model="form.description"
                type="text"
                required
                placeholder="e.g. End of month depreciation adjustment"
                class="w-full px-4 py-2.5 text-sm bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400"
              />
              <p v-if="form.errors.description" class="text-xs text-rose-500 font-semibold">{{ form.errors.description }}</p>
            </div>

            <div class="space-y-1">
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Entry Date <span class="text-rose-500">*</span></label>
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

          <!-- Line Items Table -->
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="text-xs font-black text-slate-400 uppercase tracking-wider">Journal Line Items</h3>
              <button
                type="button"
                @click="addLine"
                class="inline-flex items-center gap-1 text-xs font-extrabold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 transition-colors"
              >
                + Add Line Item
              </button>
            </div>

            <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
              <table class="w-full text-left border-collapse min-w-[640px]">
                <thead>
                  <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-[11px] font-black uppercase text-slate-500 dark:text-slate-400 tracking-wider">
                    <th class="p-3 w-1/3">Account</th>
                    <th class="p-3 w-1/4">Memo</th>
                    <th class="p-3 w-1/6 text-right">Debit ({{ $cs }})</th>
                    <th class="p-3 w-1/6 text-right">Credit ({{ $cs }})</th>
                    <th class="p-3 w-12 text-center"></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
                  <tr v-for="(item, idx) in form.items" :key="idx" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50/50">
                    <td class="p-2">
                      <select
                        v-model="item.account_id"
                        required
                        class="w-full px-3 py-1.5 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400 font-medium"
                      >
                        <option v-for="a in accounts" :key="a.id" :value="a.id">
                          {{ a.code }} — {{ a.name }} ({{ a.type }})
                        </option>
                      </select>
                    </td>
                    <td class="p-2">
                      <input
                        v-model="item.memo"
                        type="text"
                        placeholder="Line memo..."
                        class="w-full px-3 py-1.5 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400"
                      />
                    </td>
                    <td class="p-2">
                      <input
                        v-model.number="item.debit"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        class="w-full px-3 py-1.5 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400 text-right font-mono"
                      />
                    </td>
                    <td class="p-2">
                      <input
                        v-model.number="item.credit"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        class="w-full px-3 py-1.5 text-xs bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400 text-right font-mono"
                      />
                    </td>
                    <td class="p-2 text-center">
                      <button
                        type="button"
                        @click="removeLine(idx)"
                        :disabled="form.items.length <= 2"
                        class="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 disabled:opacity-20 transition-colors p-1"
                      >
                        ✕
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="bg-slate-50 dark:bg-slate-800/50 font-bold text-xs border-t border-slate-200 dark:border-slate-800">
                    <td colspan="2" class="p-3 text-right text-slate-600 dark:text-slate-300 uppercase tracking-wider">Total:</td>
                    <td class="p-3 text-right font-mono text-slate-900 dark:text-white">{{ $cs }}{{ totalDebit.toFixed(2) }}</td>
                    <td class="p-3 text-right font-mono text-slate-900 dark:text-white">{{ $cs }}{{ totalCredit.toFixed(2) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <p v-if="form.errors.items" class="text-xs text-rose-500 font-semibold">{{ form.errors.items }}</p>

            <!-- Balance Verification Banner -->
            <div
              class="p-4 rounded-2xl border text-xs flex items-center justify-between"
              :class="{
                'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/60 text-emerald-900 dark:text-emerald-200': isBalanced,
                'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800/60 text-rose-900 dark:text-rose-200': !isBalanced
              }"
            >
              <div class="flex items-center gap-2">
                <span class="text-base">{{ isBalanced ? '✓' : '⚠️' }}</span>
                <div>
                  <p class="font-extrabold">{{ isBalanced ? 'Balanced Entry' : 'Entry Out of Balance' }}</p>
                  <p v-if="!isBalanced">Total Debits ({{ $cs }}{{ totalDebit.toFixed(2) }}) must equal Total Credits ({{ $cs }}{{ totalCredit.toFixed(2) }}). Difference: {{ $cs }}{{ Math.abs(totalDebit - totalCredit).toFixed(2) }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Submit Footer -->
        <div class="p-6 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <Link
            :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'general_ledger' })"
            class="px-5 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors"
          >
            Cancel
          </Link>

          <button
            type="submit"
            :disabled="form.processing || !isBalanced"
            class="px-6 py-2.5 text-sm font-black text-white bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 rounded-xl shadow-md transition-colors disabled:opacity-50"
          >
            Save Journal Entry
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
