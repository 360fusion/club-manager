<script setup>
// Adding a vendor bill and editing one: one page, so the two cannot drift apart. `bill` is null when adding.
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import AttachmentField from '@/Components/Accounting/AttachmentField.vue';
import { currencySymbol } from '@/Utils/currency';
import { localDate } from '@/Utils/localDateTime';

const props = defineProps({
  club: { type: Object, required: true },
  bill: { type: Object, default: null },
  initialVendor: { type: String, default: '' },
});

// The category is a label on the bill (the expense account is chosen when it is posted).
const CATEGORIES = [
  'Facility & Clubhouse Maintenance',
  'Boat Yard Supplies & Repair',
  'Bar & Dining Supplies',
  'Admin & Software Subscriptions',
  'Insurance & Legal Fees',
  'Utilities & Fuel',
  'Event & Regatta Supplies',
  'Other Operating Expenses',
];

const STATUS = {
  draft: { label: 'Draft', badge: 'neutral' },
  unpaid: { label: 'Unpaid', badge: 'warning' },
  pending_approval: { label: 'Pending approval', badge: 'info' },
  paid: { label: 'Paid', badge: 'success' },
};

const isEdit = computed(() => Boolean(props.bill));

// A bill saved with a category that is no longer in the list keeps it.
const categoryOptions = computed(() => {
  const names = props.bill?.category && !CATEGORIES.includes(props.bill.category) ? [props.bill.category, ...CATEGORIES] : CATEGORIES;
  return names.map((name) => ({ value: name, label: name }));
});

// Paid is reached with "Mark paid" on the bills list, and a bill over the approval threshold with "Approve", because
// both post to the ledger; the edit form only moves a bill between the states that do not.
const statusOptions = computed(() => {
  const status = props.bill?.status;
  if (status === 'paid') return [{ value: 'draft', label: 'Draft' }, { value: 'unpaid', label: 'Unpaid' }, { value: 'paid', label: 'Paid' }];
  if (status === 'pending_approval') return [{ value: 'draft', label: 'Draft' }, { value: 'pending_approval', label: 'Pending approval' }];
  return [{ value: 'draft', label: 'Draft' }, { value: 'unpaid', label: 'Unpaid (awaiting payment)' }];
});

const form = useForm({
  vendor_name: props.bill?.vendor_name ?? props.initialVendor,
  category: props.bill?.category ?? CATEGORIES[0],
  amount: props.bill?.amount ?? '',
  due_date: props.bill?.due_date ?? localDate(new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)),
  notes: props.bill?.notes ?? '',
  ...(props.bill ? { status: props.bill.status } : {}),
  attachment: null,
});

const purchasesUrl = computed(() => route('admin.accounting.index', { clubSlug: props.club.slug, tab: 'purchases' }));

// A file cannot be sent with a real PUT, so an edit posts with _method (Laravel's method spoofing).
const submit = (isDraft = false) => {
  if (isEdit.value) {
    form.transform((data) => ({ ...data, _method: 'put' })).post(route('admin.accounting.bills.update', { clubSlug: props.club.slug, id: props.bill.id }));
  } else {
    form.transform((data) => ({ ...data, is_draft: isDraft })).post(route('admin.accounting.bills.store', props.club.slug));
  }
};

const title = computed(() => (isEdit.value ? `Edit bill ${props.bill.bill_number}` : 'Add a vendor bill'));
</script>

<template>
  <AdminLayout :club="club" :title="title" active-tab="accounting">
    <Head :title="`${title} — ${club.name}`" />

    <div class="mx-auto max-w-3xl space-y-6">
      <PageHeader
        :title="title"
        :description="isEdit ? 'Change the vendor, category, amount, due date or status.' : 'Record a bill from a supplier, so the club knows what it owes.'"
        :breadcrumbs="[
          { label: 'Accounting', href: route('admin.accounting.index', club.slug) },
          { label: 'Purchases', href: purchasesUrl },
          { label: isEdit ? bill.bill_number : 'Add a bill' },
        ]"
      >
        <template v-if="isEdit" #badge>
          <Badge :variant="STATUS[bill.status]?.badge ?? 'neutral'">{{ STATUS[bill.status]?.label ?? bill.status }}</Badge>
        </template>
        <template #actions>
          <Button variant="ghost" size="sm" :href="purchasesUrl">Back to purchases</Button>
        </template>
      </PageHeader>

      <Alert v-if="!isEdit" variant="info" title="What a bill does">
        A bill is money the club owes. Once it is posted you can match it to a bank payment or mark it paid.
      </Alert>

      <form @submit.prevent="submit(false)">
        <Card>
          <div class="grid gap-5 sm:grid-cols-2">
            <Input v-model="form.vendor_name" label="Vendor or supplier" placeholder="Oxford Waterways Maintenance" required :error="form.errors.vendor_name" />
            <Select v-model="form.category" label="Category" :options="categoryOptions" required :error="form.errors.category" />
            <Input v-model="form.amount" type="number" step="0.01" :min="isEdit ? 0 : 0.01" inputmode="decimal" label="Amount" :prefix="currencySymbol()" placeholder="0.00" required :error="form.errors.amount" />
            <Input v-model="form.due_date" type="date" label="Due date" required :error="form.errors.due_date" />

            <div v-if="isEdit" class="space-y-1.5 sm:col-span-2">
              <Select v-model="form.status" label="Status" :options="statusOptions" required :error="form.errors.status" />
              <p class="text-xs text-slate-500 dark:text-slate-400">
                <template v-if="bill.status === 'pending_approval'">This bill is over the approval threshold: another admin or the treasurer approves it from the bills list, which posts it.</template>
                <template v-else>To record a payment, use "Mark paid" on the bills list: it posts the payment to the ledger.</template>
              </p>
            </div>

            <Textarea v-model="form.notes" class="sm:col-span-2" label="Notes" placeholder="Their invoice number, a purchase order or what the bill covers" :rows="3" :error="form.errors.notes" />
            <AttachmentField v-model="form.attachment" class="sm:col-span-2" label="Supplier's invoice or receipt" :current-url="bill?.attachment_url ?? ''" :error="form.errors.attachment" />
          </div>

          <template #footer>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
              <Button variant="ghost" :href="purchasesUrl">Cancel</Button>
              <div class="flex flex-col gap-3 sm:flex-row">
                <Button v-if="!isEdit" variant="secondary" :disabled="form.processing" @click="submit(true)">Save as draft</Button>
                <Button type="submit" :loading="form.processing">{{ isEdit ? 'Save changes' : 'Record and post bill' }}</Button>
              </div>
            </div>
          </template>
        </Card>
      </form>
    </div>
  </AdminLayout>
</template>
