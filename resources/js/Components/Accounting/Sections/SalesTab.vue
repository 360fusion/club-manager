<script setup>
import { computed, ref } from 'vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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

const STATUS = {
  draft: { label: 'Draft', badge: 'neutral' },
  pending_approval: { label: 'Pending approval', badge: 'info' },
  unpaid: { label: 'Unpaid', badge: 'warning' },
  paid: { label: 'Paid', badge: 'success' },
  void: { label: 'Void', badge: 'danger' },
  refunded: { label: 'Refunded', badge: 'neutral' },
};

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
    <Card class="space-y-4 overflow-hidden">

      <!-- Header row -->
      <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
        <div>
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Sales invoices and receivables</h3>
          <p class="text-sm text-slate-500 dark:text-slate-400">Track member dues, locker fees and ticket invoices.</p>
        </div>
        <Button v-if="canManageBilling" size="sm" :href="route('admin.accounting.invoices.create', club.slug)">
          <template #icon><UiIcon name="plus" /></template>
          Create invoice
        </Button>
      </div>

      <!-- Filter bar -->
      <div class="flex flex-wrap items-center gap-2">
        <Input
          v-model="salesSearch"
          class="min-w-[200px] flex-1"
          type="search"
          placeholder="Search invoice #, member, description…"
          aria-label="Search invoices"
        />

        <!-- Calendar year -->
        <select
          v-model="salesYearFilter"
          aria-label="Calendar year"
          class="cursor-pointer rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
          @change="salesFyFilter = 'all'"
        >
          <option value="all">All years</option>
          <option v-for="yr in salesYears" :key="yr" :value="String(yr)">{{ yr }}</option>
        </select>

        <!-- Financial year quick-filters -->
        <div class="flex flex-wrap items-center gap-1">
          <button
            type="button"
            :aria-pressed="salesFyFilter === 'all' && salesYearFilter === 'all'"
            :class="['cursor-pointer rounded-full px-3 py-1.5 text-xs font-medium transition-colors', salesFyFilter === 'all' && salesYearFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700']"
            @click="salesFyFilter = 'all'; salesYearFilter = 'all'"
          >All</button>
          <button
            v-for="fy in salesFyYears"
            :key="fy"
            type="button"
            :aria-pressed="salesFyFilter === fy"
            :class="['cursor-pointer rounded-full px-3 py-1.5 text-xs font-medium transition-colors', salesFyFilter === fy ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700']"
            @click="salesFyFilter = fy; salesYearFilter = 'all'"
          >FY {{ fy }}</button>
        </div>

        <!-- Result count -->
        <span class="ml-auto whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
          {{ filteredInvoices.length }} of {{ (invoices || []).length }} invoices
        </span>
      </div>

      <!-- Empty state -->
      <div v-if="!filteredInvoices.length" class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 py-12 text-center dark:border-slate-800 dark:bg-slate-800/50">
        <span class="mb-2 inline-flex text-slate-400 dark:text-slate-500"><UiIcon name="receipt" :size="20" /></span>
        <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">{{ (invoices || []).length ? 'No results match your filters' : 'No invoices yet' }}</span>
        <span class="text-xs text-slate-500 dark:text-slate-400">{{ (invoices || []).length ? 'Try adjusting your search or year filter.' : 'Use "Create invoice" to issue a new one.' }}</span>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800">
        <table class="w-full border-collapse text-left">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="px-4 py-3 font-medium">Invoice #</th>
              <th scope="col" class="px-4 py-3 font-medium">Member</th>
              <th scope="col" class="px-4 py-3 font-medium">Title or description</th>
              <th scope="col" class="px-4 py-3 font-medium">Date issued</th>
              <th scope="col" class="px-4 py-3 font-medium">Status</th>
              <th scope="col" class="px-4 py-3 text-right font-medium">Amount</th>
              <th scope="col" class="px-4 py-3 text-center font-medium">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-sm text-slate-700 dark:divide-slate-800 dark:text-slate-200">
            <tr v-for="inv in filteredInvoices" :key="inv.id" class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/80">
              <td class="px-4 py-3">
                <button
                  type="button"
                  class="cursor-pointer whitespace-nowrap font-mono font-medium text-blue-600 hover:text-blue-800 hover:underline dark:text-blue-400 dark:hover:text-blue-200"
                  @click="openEditInvoiceModal(inv)"
                >
                  {{ inv.invoice_number }}
                </button>
              </td>
              <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ inv.recipient_name }}</td>
              <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="cursor-pointer text-left font-medium text-slate-900 hover:text-blue-600 hover:underline dark:text-white dark:hover:text-blue-400"
                    @click="openEditInvoiceModal(inv)"
                  >
                    {{ inv.title }}
                  </button>
                  <Button
                    v-if="inv.attachment"
                    variant="ghost"
                    size="sm"
                    title="View and zoom the attachment"
                    @click="openAttachmentModal(inv, 'invoice')"
                  >
                    <template #icon><UiIcon name="document" /></template>
                    View document
                  </Button>
                </div>
              </td>
              <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ inv.created_at }}</td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap items-center gap-1">
                  <Badge :variant="STATUS[inv.status]?.badge ?? 'warning'">{{ STATUS[inv.status]?.label ?? inv.status }}</Badge>
                  <Badge v-if="inv.reconciled_at" variant="success" title="Confirmed against a bank statement line">Reconciled</Badge>
                </div>
              </td>
              <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ inv.formatted_amount }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1.5">
                  <Button
                    v-if="canManageBilling && inv.status === 'draft'"
                    variant="secondary"
                    size="sm"
                    title="Issue the invoice and post it to the ledger"
                    @click="publishInvoice(inv.id)"
                  >
                    Issue
                  </Button>
                  <Button
                    v-else-if="canManageBilling && inv.status === 'pending_approval'"
                    variant="secondary"
                    size="sm"
                    :disabled="inv.can_approve === false"
                    :title="inv.can_approve === false ? 'You raised this invoice, so you cannot also approve it — ask another admin or treasurer.' : 'Approve and post the invoice to accounts receivable'"
                    @click="approveInvoice(inv.id)"
                  >
                    Approve
                  </Button>
                  <Button
                    v-else-if="canManageBilling && inv.status === 'unpaid'"
                    variant="secondary"
                    size="sm"
                    @click="markInvoicePaid(inv.id)"
                  >
                    Mark paid
                  </Button>
                  <span v-else-if="inv.status === 'paid'" class="text-xs text-slate-500 dark:text-slate-400">
                    Paid {{ inv.paid_at }}
                    <span v-if="!inv.reconciled_at" class="block text-slate-400 dark:text-slate-500">Not yet reconciled</span>
                  </span>
                  <span v-else class="text-xs text-slate-500 dark:text-slate-400">{{ STATUS[inv.status]?.label ?? inv.status }}</span>
                  <Button
                    variant="ghost"
                    size="sm"
                    title="View the activity log"
                    aria-label="View the activity log"
                    @click="openActivityLog('invoice', inv.id, `Invoice ${inv.invoice_number}`)"
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
