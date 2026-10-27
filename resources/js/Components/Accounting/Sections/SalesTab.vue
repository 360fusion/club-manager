<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// The sales section of Index.vue, in its own file so the page stays readable. State only this section uses lives here.
const props = defineProps({
    approveInvoice: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    canManageBilling: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    getFyLabel: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    invoices: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    markInvoicePaid: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openActivityLog: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openAttachmentModal: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openEditInvoiceModal: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    parseDate: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    publishInvoice: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    salesFyYears: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    salesYears: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const salesSearch     = ref('');

const salesYearFilter = ref('all');   // 'all' | '2024' | '2025' | ...

const salesFyFilter   = ref('all');   // 'all' | '2024-25' | ...

const filteredInvoices = computed(() => {
  let list = props.invoices || [];
  const q = salesSearch.value.trim().toLowerCase();
  if (q) {
    list = list.filter(i =>
      i.invoice_number?.toLowerCase().includes(q) ||
      i.recipient_name?.toLowerCase().includes(q) ||
      i.title?.toLowerCase().includes(q)
    );
  }
  if (salesFyFilter.value !== 'all') {
    list = list.filter(i => props.getFyLabel(i.created_at) === salesFyFilter.value);
  } else if (salesYearFilter.value !== 'all') {
    list = list.filter(i => props.parseDate(i.created_at)?.getFullYear() === Number(salesYearFilter.value));
  }
  return list;
});
</script>

<template>
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden space-y-4 p-6">

      <!-- Header row -->
      <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
        <div>
          <h3 class="text-lg font-black text-slate-900 dark:text-white">Sales Invoicing & Receivables</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">Track member dues, locker fees, and ticket invoices.</p>
        </div>
        <Link
          v-if="canManageBilling"
          :href="route('admin.accounting.invoices.create', club.slug)"
          class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer flex items-center gap-1.5"
        >
          <span>🧾 Create invoice</span>
        </Link>
      </div>

      <!-- Filter Bar -->
      <div class="flex flex-wrap items-center gap-2">
        <!-- Search -->
        <div class="relative flex-1 min-w-[200px]">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
          <input
            v-model="salesSearch"
            type="text"
            placeholder="Search invoice #, member, description…"
            class="w-full pl-7 pr-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-medium text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800/50 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-blue-400"
          />
        </div>

        <!-- Calendar year -->
        <select
          v-model="salesYearFilter"
          @change="salesFyFilter = 'all'"
          class="px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-800/50 focus:outline-none focus:ring-2 focus:ring-blue-400 cursor-pointer"
        >
          <option value="all">All Years</option>
          <option v-for="yr in salesYears" :key="yr" :value="String(yr)">{{ yr }}</option>
        </select>

        <!-- Financial year quick-filters -->
        <div class="flex items-center gap-1 flex-wrap">
          <button
            type="button"
            @click="salesFyFilter = 'all'; salesYearFilter = 'all'"
            :class="['px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold transition-all cursor-pointer', salesFyFilter === 'all' && salesYearFilter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700']"
          >All</button>
          <button
            v-for="fy in salesFyYears"
            :key="fy"
            type="button"
            @click="salesFyFilter = fy; salesYearFilter = 'all'"
            :class="['px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold transition-all cursor-pointer', salesFyFilter === fy ? 'bg-blue-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700']"
          >FY {{ fy }}</button>
        </div>

        <!-- Result count -->
        <span class="text-[11px] text-slate-400 font-semibold ml-auto whitespace-nowrap">
          {{ filteredInvoices.length }} of {{ (invoices || []).length }} invoices
        </span>
      </div>

      <!-- Empty state -->
      <div v-if="!filteredInvoices.length" class="text-center py-12 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl bg-slate-50 dark:bg-slate-800/50">
        <span class="text-3xl block mb-2">🧾</span>
        <span class="text-xs font-bold text-slate-700 dark:text-slate-200 block">{{ (invoices || []).length ? 'No results match your filters' : 'No Invoices Found' }}</span>
        <span class="text-[11px] text-slate-400">{{ (invoices || []).length ? 'Try adjusting your search or year filter.' : 'Click "Create Invoice" to issue a new one.' }}</span>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800/80 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              <th class="py-3 px-4">Invoice #</th>
              <th class="py-3 px-4">Member</th>
              <th class="py-3 px-4">Title / Description</th>
              <th class="py-3 px-4">Date Issued</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4 text-right">Amount</th>
              <th class="py-3 px-4 text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200">
            <tr v-for="inv in filteredInvoices" :key="inv.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
              <td class="py-3 px-4">
                <button
                  type="button"
                  @click="openEditInvoiceModal(inv)"
                  class="font-mono font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 hover:underline cursor-pointer"
                >
                  {{ inv.invoice_number }}
                </button>
              </td>
              <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">{{ inv.recipient_name }}</td>
              <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    @click="openEditInvoiceModal(inv)"
                    class="font-bold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 hover:underline text-left cursor-pointer"
                  >
                    {{ inv.title }}
                  </button>
                  <button
                    v-if="inv.attachment"
                    type="button"
                    @click="openAttachmentModal(inv, 'invoice')"
                    title="View & Zoom Attachment"
                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-extrabold rounded-md border border-blue-200 dark:border-blue-800/60 transition-colors cursor-pointer"
                  >
                    📎 View document
                  </button>
                </div>
              </td>
              <td class="py-3 px-4 text-slate-500 dark:text-slate-400">{{ inv.created_at }}</td>
              <td class="py-3 px-4">
                <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border', inv.status === 'paid' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60' : inv.status === 'draft' ? 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700' : inv.status === 'pending_approval' ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/60' : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60']">
                  {{ inv.status === 'pending_approval' ? 'pending approval' : inv.status }}
                </span>
                <span v-if="inv.reconciled_at" title="Confirmed against a bank statement line" class="ml-1 px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/60">
                  🔗 Reconciled
                </span>
              </td>
              <td class="py-3 px-4 text-right font-black text-slate-900 dark:text-white font-mono">{{ inv.formatted_amount }}</td>
              <td class="py-3 px-4 text-center">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    v-if="canManageBilling && inv.status === 'draft'"
                    type="button"
                    @click="publishInvoice(inv.id)"
                    title="Issue/Publish Invoice & Post to Ledger"
                    class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white border border-blue-700 text-[10px] font-extrabold rounded-xl transition-all cursor-pointer flex items-center gap-1 shadow-sm"
                  >
                    🚀 Issue
                  </button>
                  <button
                    v-else-if="canManageBilling && inv.status === 'pending_approval'"
                    type="button"
                    :disabled="inv.can_approve === false"
                    :title="inv.can_approve === false ? 'You raised this invoice, so you cannot also approve it — ask another admin or treasurer.' : 'Approve & Post Invoice to Accounts Receivable'"
                    @click="approveInvoice(inv.id)"
                    class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white border border-blue-700 text-[10px] font-extrabold rounded-xl transition-all cursor-pointer flex items-center gap-1 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    ✅ Approve
                  </button>
                  <button
                    v-else-if="canManageBilling && inv.status === 'unpaid'"
                    type="button"
                    @click="markInvoicePaid(inv.id)"
                    class="px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800/60 text-[10px] font-extrabold rounded-xl transition-all cursor-pointer"
                  >
                    ✓ Mark paid
                  </button>
                  <span v-else-if="inv.status === 'paid'" class="text-[10px] font-bold text-slate-400">
                    Paid {{ inv.paid_at }}
                    <span v-if="!inv.reconciled_at" class="block text-slate-400 dark:text-slate-500 normal-case font-semibold">Not yet reconciled</span>
                  </span>
                  <span v-else class="text-[10px] font-bold text-slate-400 uppercase">{{ inv.status === 'pending_approval' ? 'pending approval' : inv.status }}</span>
                  <button
                    type="button"
                    @click="openActivityLog('invoice', inv.id, `Invoice ${inv.invoice_number}`)"
                    title="View Activity Log"
                    class="px-2 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 text-[10px] font-extrabold rounded-xl transition-all cursor-pointer"
                  >
                    🕘
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
