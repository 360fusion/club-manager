<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// The purchases section of Index.vue, in its own file so the page stays readable. State only this section uses lives here.
const props = defineProps({
    accounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    approveBill: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    bills: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    billsFyYears: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    billsYears: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    canManageBilling: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    getFyLabel: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    markBillPaid: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openActivityLog: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openAttachmentModal: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openEditBillModal: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    parseDate: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    publishBill: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const billsSearch     = ref('');

const billsYearFilter = ref('all');

const billsFyFilter   = ref('all');

const filteredBills = computed(() => {
  let list = props.bills || [];
  const q = billsSearch.value.trim().toLowerCase();
  if (q) {
    list = list.filter(b =>
      b.bill_number?.toLowerCase().includes(q) ||
      b.vendor_name?.toLowerCase().includes(q) ||
      b.category?.toLowerCase().includes(q)
    );
  }
  if (billsFyFilter.value !== 'all') {
    list = list.filter(b => props.getFyLabel(b.due_date) === billsFyFilter.value);
  } else if (billsYearFilter.value !== 'all') {
    list = list.filter(b => props.parseDate(b.due_date)?.getFullYear() === Number(billsYearFilter.value));
  }
  return list;
});
</script>

<template>
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden space-y-4 p-6">

      <!-- Header row -->
      <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
        <div>
          <h3 class="text-lg font-black text-slate-900 dark:text-white">Purchases & Vendor Bills</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">Track equipment purchases, facility bills, and accounts payable.</p>
        </div>
        <Link
          v-if="canManageBilling"
          :href="route('admin.accounting.bills.create', club.slug)"
          class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-sm flex items-center gap-1.5 transition-colors"
        >
          <span>📄 Record vendor bill</span>
        </Link>
      </div>

      <!-- Filter Bar -->
      <div class="flex flex-wrap items-center gap-2">
        <!-- Search -->
        <div class="relative flex-1 min-w-[200px]">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
          <input
            v-model="billsSearch"
            type="text"
            placeholder="Search bill #, vendor, category…"
            class="w-full pl-7 pr-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-medium text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800/50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400"
          />
        </div>

        <!-- Calendar year -->
        <select
          v-model="billsYearFilter"
          @change="billsFyFilter = 'all'"
          class="px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-800/50 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer"
        >
          <option value="all">All Years</option>
          <option v-for="yr in billsYears" :key="yr" :value="String(yr)">{{ yr }}</option>
        </select>

        <!-- Financial year quick-filters -->
        <div class="flex items-center gap-1 flex-wrap">
          <button
            type="button"
            @click="billsFyFilter = 'all'; billsYearFilter = 'all'"
            :class="['px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold transition-all cursor-pointer', billsFyFilter === 'all' && billsYearFilter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700']"
          >All</button>
          <button
            v-for="fy in billsFyYears"
            :key="fy"
            type="button"
            @click="billsFyFilter = fy; billsYearFilter = 'all'"
            :class="['px-2.5 py-1.5 rounded-xl text-[11px] font-extrabold transition-all cursor-pointer', billsFyFilter === fy ? 'bg-amber-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700']"
          >FY {{ fy }}</button>
        </div>

        <!-- Result count -->
        <span class="text-[11px] text-slate-400 font-semibold ml-auto whitespace-nowrap">
          {{ filteredBills.length }} of {{ (bills || []).length }} bills
        </span>
      </div>

      <!-- Empty state -->
      <div v-if="!filteredBills.length" class="text-center py-12 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl bg-slate-50 dark:bg-slate-800/50">
        <span class="text-3xl block mb-2">📄</span>
        <span class="text-xs font-bold text-slate-700 dark:text-slate-200 block">{{ (bills || []).length ? 'No results match your filters' : 'No Vendor Bills Recorded' }}</span>
        <span class="text-[11px] text-slate-400">{{ (bills || []).length ? 'Try adjusting your search or year filter.' : 'Click "Record Vendor Bill" to log payable vendor expenses.' }}</span>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800/80 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              <th class="py-3 px-4">Bill #</th>
              <th class="py-3 px-4">Vendor Name</th>
              <th class="py-3 px-4">Category</th>
              <th class="py-3 px-4">Due Date</th>
              <th class="py-3 px-4">Status</th>
              <th class="py-3 px-4 text-right">Amount</th>
              <th class="py-3 px-4 text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200">
            <tr v-for="b in filteredBills" :key="b.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
              <td class="py-3 px-4">
                <button
                  type="button"
                  @click="openEditBillModal(b)"
                  class="font-mono font-bold text-amber-700 dark:text-amber-300 hover:text-amber-900 dark:hover:text-amber-200 hover:underline cursor-pointer"
                >
                  {{ b.bill_number }}
                </button>
              </td>
              <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                <button
                  type="button"
                  @click="openEditBillModal(b)"
                  class="font-bold text-slate-900 dark:text-white hover:text-amber-700 dark:hover:text-amber-300 hover:underline text-left cursor-pointer"
                >
                  {{ b.vendor_name }}
                </button>
              </td>
              <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                <div class="flex items-center gap-2">
                  <span>{{ b.category }}</span>
                  <button
                    v-if="b.attachment"
                    type="button"
                    @click="openAttachmentModal(b, 'bill')"
                    title="View & Zoom Receipt Attachment"
                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-800 dark:text-amber-200 text-[10px] font-extrabold rounded-md border border-amber-200 dark:border-amber-800/60 transition-colors cursor-pointer"
                  >
                    📎 View receipt
                  </button>
                </div>
              </td>
              <td class="py-3 px-4 text-slate-500 dark:text-slate-400">{{ b.due_date }}</td>
              <td class="py-3 px-4">
                <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border', b.status === 'paid' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60' : b.status === 'draft' ? 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700' : b.status === 'pending_approval' ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/60' : 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800/60']">
                  {{ b.status === 'pending_approval' ? 'pending approval' : b.status }}
                </span>
                <span v-if="b.reconciled_at" title="Confirmed against a bank statement line" class="ml-1 px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/60">
                  🔗 Reconciled
                </span>
              </td>
              <td class="py-3 px-4 text-right font-black text-slate-900 dark:text-white font-mono">{{ b.formatted_amount }}</td>
              <td class="py-3 px-4 text-center">
                <div class="flex items-center justify-center gap-1.5">
                  <button
                    v-if="canManageBilling && b.status === 'draft'"
                    type="button"
                    @click="publishBill(b.id)"
                    title="Approve & Post Bill to Accounts Payable"
                    class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white border border-amber-700 text-[10px] font-extrabold rounded-xl transition-all cursor-pointer flex items-center gap-1 shadow-sm"
                  >
                    🚀 Approve
                  </button>
                  <button
                    v-else-if="canManageBilling && b.status === 'pending_approval'"
                    type="button"
                    :disabled="b.can_approve === false"
                    :title="b.can_approve === false ? 'You raised this bill, so you cannot also approve it — ask another admin or treasurer.' : 'Sign Off & Post Bill to Accounts Payable'"
                    @click="approveBill(b.id)"
                    class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white border border-blue-700 text-[10px] font-extrabold rounded-xl transition-all cursor-pointer flex items-center gap-1 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    ✅ Sign off
                  </button>
                  <button
                    v-else-if="canManageBilling && b.status === 'unpaid'"
                    type="button"
                    @click="markBillPaid(b.id)"
                    class="px-2.5 py-1 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800/60 text-[10px] font-extrabold rounded-xl transition-all cursor-pointer"
                  >
                    ✓ Pay bill
                  </button>
                  <span v-else-if="b.status === 'paid'" class="text-[10px] font-bold text-slate-400">
                    Paid {{ b.paid_at }}
                    <span v-if="!b.reconciled_at" class="block text-slate-400 dark:text-slate-500 normal-case font-semibold">Not yet reconciled</span>
                  </span>
                  <span v-else class="text-[10px] font-bold text-slate-400 uppercase">{{ b.status === 'pending_approval' ? 'pending approval' : b.status }}</span>
                  <button
                    type="button"
                    @click="openActivityLog('bill', b.id, `Bill ${b.bill_number}`)"
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
