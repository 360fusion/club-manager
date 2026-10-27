<script setup>
import { computed, ref } from 'vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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

const STATUS = {
  draft: { label: 'Draft', badge: 'neutral' },
  pending_approval: { label: 'Pending approval', badge: 'info' },
  unpaid: { label: 'Unpaid', badge: 'warning' },
  paid: { label: 'Paid', badge: 'success' },
  void: { label: 'Void', badge: 'danger' },
  refunded: { label: 'Refunded', badge: 'neutral' },
};

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
    <Card class="space-y-4 overflow-hidden">

      <!-- Header row -->
      <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
        <div>
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Purchases and vendor bills</h3>
          <p class="text-sm text-slate-500 dark:text-slate-400">Track equipment purchases, facility bills and accounts payable.</p>
        </div>
        <Button v-if="canManageBilling" size="sm" :href="route('admin.accounting.bills.create', club.slug)">
          <template #icon><UiIcon name="plus" /></template>
          Record vendor bill
        </Button>
      </div>

      <!-- Filter bar -->
      <div class="flex flex-wrap items-center gap-2">
        <Input
          v-model="billsSearch"
          class="min-w-[200px] flex-1"
          type="search"
          placeholder="Search bill #, vendor, category…"
          aria-label="Search bills"
        />

        <!-- Calendar year -->
        <select
          v-model="billsYearFilter"
          aria-label="Calendar year"
          class="cursor-pointer rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
          @change="billsFyFilter = 'all'"
        >
          <option value="all">All years</option>
          <option v-for="yr in billsYears" :key="yr" :value="String(yr)">{{ yr }}</option>
        </select>

        <!-- Financial year quick-filters -->
        <div class="flex flex-wrap items-center gap-1">
          <button
            type="button"
            :aria-pressed="billsFyFilter === 'all' && billsYearFilter === 'all'"
            :class="['cursor-pointer rounded-full px-3 py-1.5 text-xs font-medium transition-colors', billsFyFilter === 'all' && billsYearFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700']"
            @click="billsFyFilter = 'all'; billsYearFilter = 'all'"
          >All</button>
          <button
            v-for="fy in billsFyYears"
            :key="fy"
            type="button"
            :aria-pressed="billsFyFilter === fy"
            :class="['cursor-pointer rounded-full px-3 py-1.5 text-xs font-medium transition-colors', billsFyFilter === fy ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700']"
            @click="billsFyFilter = fy; billsYearFilter = 'all'"
          >FY {{ fy }}</button>
        </div>

        <!-- Result count -->
        <span class="ml-auto whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
          {{ filteredBills.length }} of {{ (bills || []).length }} bills
        </span>
      </div>

      <!-- Empty state -->
      <div v-if="!filteredBills.length" class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 py-12 text-center dark:border-slate-800 dark:bg-slate-800/50">
        <span class="mb-2 inline-flex text-slate-400 dark:text-slate-500"><UiIcon name="document" :size="20" /></span>
        <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ (bills || []).length ? 'No results match your filters' : 'No vendor bills recorded' }}</span>
        <span class="text-xs text-slate-500 dark:text-slate-400">{{ (bills || []).length ? 'Try adjusting your search or year filter.' : 'Use "Record vendor bill" to log what the club owes a supplier.' }}</span>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800">
        <table class="w-full border-collapse text-left">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="px-4 py-3 font-medium">Bill #</th>
              <th scope="col" class="px-4 py-3 font-medium">Vendor name</th>
              <th scope="col" class="px-4 py-3 font-medium">Category</th>
              <th scope="col" class="px-4 py-3 font-medium">Due date</th>
              <th scope="col" class="px-4 py-3 font-medium">Status</th>
              <th scope="col" class="px-4 py-3 text-right font-medium">Amount</th>
              <th scope="col" class="px-4 py-3 text-center font-medium">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-sm text-slate-700 dark:divide-slate-800 dark:text-slate-200">
            <tr v-for="b in filteredBills" :key="b.id" class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/80">
              <td class="px-4 py-3">
                <button
                  type="button"
                  class="cursor-pointer whitespace-nowrap font-mono font-medium text-blue-600 hover:text-blue-800 hover:underline dark:text-blue-400 dark:hover:text-blue-200"
                  @click="openEditBillModal(b)"
                >
                  {{ b.bill_number }}
                </button>
              </td>
              <td class="px-4 py-3">
                <button
                  type="button"
                  class="cursor-pointer text-left font-medium text-slate-900 hover:text-blue-600 hover:underline dark:text-white dark:hover:text-blue-400"
                  @click="openEditBillModal(b)"
                >
                  {{ b.vendor_name }}
                </button>
              </td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                <div class="flex items-center gap-2">
                  <span>{{ b.category }}</span>
                  <Button
                    v-if="b.attachment"
                    variant="ghost"
                    size="sm"
                    title="View and zoom the receipt"
                    @click="openAttachmentModal(b, 'bill')"
                  >
                    <template #icon><UiIcon name="document" /></template>
                    View receipt
                  </Button>
                </div>
              </td>
              <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ b.due_date }}</td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap items-center gap-1">
                  <Badge :variant="STATUS[b.status]?.badge ?? 'danger'">{{ STATUS[b.status]?.label ?? b.status }}</Badge>
                  <Badge v-if="b.reconciled_at" variant="success" title="Confirmed against a bank statement line">Reconciled</Badge>
                </div>
              </td>
              <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ b.formatted_amount }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1.5">
                  <Button
                    v-if="canManageBilling && b.status === 'draft'"
                    variant="secondary"
                    size="sm"
                    title="Approve the bill and post it to accounts payable"
                    @click="publishBill(b.id)"
                  >
                    Approve
                  </Button>
                  <Button
                    v-else-if="canManageBilling && b.status === 'pending_approval'"
                    variant="secondary"
                    size="sm"
                    :disabled="b.can_approve === false"
                    :title="b.can_approve === false ? 'You raised this bill, so you cannot also approve it — ask another admin or treasurer.' : 'Sign off and post the bill to accounts payable'"
                    @click="approveBill(b.id)"
                  >
                    Sign off
                  </Button>
                  <Button
                    v-else-if="canManageBilling && b.status === 'unpaid'"
                    variant="secondary"
                    size="sm"
                    @click="markBillPaid(b.id)"
                  >
                    Pay bill
                  </Button>
                  <span v-else-if="b.status === 'paid'" class="text-xs text-slate-500 dark:text-slate-400">
                    Paid {{ b.paid_at }}
                    <span v-if="!b.reconciled_at" class="block text-slate-400 dark:text-slate-500">Not yet reconciled</span>
                  </span>
                  <span v-else class="text-xs text-slate-500 dark:text-slate-400">{{ STATUS[b.status]?.label ?? b.status }}</span>
                  <Button
                    variant="ghost"
                    size="sm"
                    title="View the activity log"
                    aria-label="View the activity log"
                    @click="openActivityLog('bill', b.id, `Bill ${b.bill_number}`)"
                  >
                    <UiIcon name="clock" />
                  </Button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>
</template>
