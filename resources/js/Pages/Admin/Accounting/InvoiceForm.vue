<script setup>
// Creating an invoice and editing one: one page, so the two cannot drift apart. `invoice` is null when creating.
import { computed, ref } from 'vue';
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
import { currencySymbol, formatMoney } from '@/Utils/currency';
import { localDate } from '@/Utils/localDateTime';

const props = defineProps({
  club: { type: Object, required: true },
  invoice: { type: Object, default: null },
  members: { type: Array, default: () => [] },
  initialUserId: { type: Number, default: null },
});

const STATUS = {
  draft: { label: 'Draft', badge: 'neutral' },
  unpaid: { label: 'Unpaid', badge: 'warning' },
  pending_approval: { label: 'Pending approval', badge: 'info' },
  paid: { label: 'Paid', badge: 'success' },
};

const isEdit = computed(() => Boolean(props.invoice));

// Paid is reached with "Mark paid" on the invoices list, and an invoice over the approval threshold with "Approve",
// because both post to the ledger; the edit form only moves an invoice between the states that do not.
const statusOptions = computed(() => {
  const status = props.invoice?.status;
  if (status === 'paid') return [{ value: 'draft', label: 'Draft' }, { value: 'unpaid', label: 'Unpaid' }, { value: 'paid', label: 'Paid' }];
  if (status === 'pending_approval') return [{ value: 'draft', label: 'Draft' }, { value: 'pending_approval', label: 'Pending approval' }];
  return [{ value: 'draft', label: 'Draft' }, { value: 'unpaid', label: 'Unpaid (issued)' }];
});

const form = useForm({
  user_id: props.invoice?.user_id ?? props.initialUserId ?? '',
  title: props.invoice?.title ?? '',
  amount: props.invoice?.amount ?? '',
  due_date: props.invoice ? props.invoice.due_date : localDate(new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)),
  notes: props.invoice?.notes ?? '',
  ...(props.invoice ? { status: props.invoice.status } : {}),
  attachment: null,
});

const memberSearch = ref('');
const filteredMembers = computed(() => {
  const q = memberSearch.value.trim().toLowerCase();
  if (!q) return props.members;
  return props.members.filter((m) => m.name.toLowerCase().includes(q) || m.email?.toLowerCase().includes(q));
});
const selectedMember = computed(() => props.members.find((m) => m.id === Number(form.user_id)) ?? null);

const salesUrl = computed(() => route('admin.accounting.index', { clubSlug: props.club.slug, tab: 'sales' }));

// A draft may be saved without a title or amount. A file cannot be sent with a real PUT, so an edit posts with _method
// (Laravel's method spoofing).
const submit = (isDraft = false) => {
  if (isEdit.value) {
    form.transform((data) => ({ ...data, _method: 'put' })).post(route('admin.accounting.invoices.update', { clubSlug: props.club.slug, id: props.invoice.id }));
  } else {
    form.transform((data) => ({
      ...data,
      title: data.title || (isDraft ? 'Draft Invoice' : ''),
      amount: data.amount === '' || data.amount === null ? (isDraft ? 0 : data.amount) : data.amount,
      is_draft: isDraft,
    })).post(route('admin.accounting.invoices.store', props.club.slug));
  }
};

const title = computed(() => (isEdit.value ? `Edit invoice ${props.invoice.invoice_number}` : 'Create an invoice'));
</script>

<template>
  <AdminLayout :club="club" :title="title" active-tab="accounting">
    <Head :title="`${title} — ${club.name}`" />

    <div class="mx-auto max-w-3xl space-y-6">
      <PageHeader
        :title="title"
        :description="isEdit ? 'Change who it is for, what it is for, the amount or the status.' : 'Ask a member to pay dues, fees or another charge.'"
        :breadcrumbs="[
          { label: 'Accounting', href: route('admin.accounting.index', club.slug) },
          { label: 'Sales', href: salesUrl },
          { label: isEdit ? invoice.invoice_number : 'Create an invoice' },
        ]"
      >
        <template v-if="isEdit" #badge>
          <Badge :variant="STATUS[invoice.status]?.badge ?? 'neutral'">{{ STATUS[invoice.status]?.label ?? invoice.status }}</Badge>
        </template>
        <template #actions>
          <Button variant="ghost" size="sm" :href="salesUrl">Back to sales</Button>
        </template>
      </PageHeader>

      <Alert v-if="!isEdit" variant="info" title="Invoice or journal entry?">
        An invoice asks a member to pay (annual subscription, a dining fee) and tracks whether they have. A journal entry is
        an adjustment inside the books and is never sent to anyone.
      </Alert>

      <form @submit.prevent="submit(false)">
        <Card>
          <div class="space-y-6">
            <fieldset class="space-y-2">
              <legend class="text-xs font-medium text-slate-700 dark:text-slate-300">Member <span class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span></legend>
              <Input v-model="memberSearch" type="search" placeholder="Search by name or email" aria-label="Search members" @keydown.enter.prevent />
              <div class="max-h-56 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800">
                <label
                  v-for="m in filteredMembers"
                  :key="m.id"
                  :class="['flex cursor-pointer items-center gap-3 px-4 py-2.5 text-sm transition-colors', Number(form.user_id) === m.id ? 'bg-blue-50 dark:bg-blue-950/40' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50']"
                >
                  <input v-model="form.user_id" type="radio" name="user_id" :value="m.id" required class="accent-blue-600" />
                  <span class="font-medium text-slate-900 dark:text-white">{{ m.name }}</span>
                  <span class="truncate text-xs text-slate-500 dark:text-slate-400">{{ m.email }}</span>
                </label>
                <p v-if="!filteredMembers.length" class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">No members match "{{ memberSearch }}".</p>
              </div>
              <p v-if="selectedMember" class="text-xs text-slate-600 dark:text-slate-300">Invoice for <strong class="font-medium text-slate-900 dark:text-white">{{ selectedMember.name }}</strong></p>
              <p v-if="form.errors.user_id" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.user_id }}</p>
            </fieldset>

            <div class="grid gap-5 sm:grid-cols-2">
              <Input v-model="form.title" class="sm:col-span-2" label="What it is for" placeholder="Annual subscription 2026-27" :hint="isEdit ? '' : 'The invoice number is given when it is saved.'" required :error="form.errors.title" />
              <Input v-model="form.amount" type="number" step="0.01" :min="isEdit ? 0 : 0.01" inputmode="decimal" label="Amount" :prefix="currencySymbol()" placeholder="0.00" required :error="form.errors.amount" />
              <Input v-model="form.due_date" type="date" label="Due date" hint="Leave empty if there is no date to pay by." :error="form.errors.due_date" />

              <div v-if="isEdit" class="space-y-1.5">
                <Select v-model="form.status" label="Status" :options="statusOptions" required :error="form.errors.status" />
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  <template v-if="invoice.status === 'pending_approval'">Over the approval threshold: another admin or the treasurer approves it from the invoices list.</template>
                  <template v-else>To record a payment, use "Mark paid" on the invoices list.</template>
                </p>
              </div>

              <Textarea v-model="form.notes" class="sm:col-span-2" label="Notes" placeholder="How to pay, such as the club's bank details, or what the charge covers" :rows="3" :error="form.errors.notes" />
              <AttachmentField v-model="form.attachment" class="sm:col-span-2" :current-url="invoice?.attachment_url ?? ''" :error="form.errors.attachment" />
            </div>

            <div v-if="!isEdit" class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
              <p class="mb-1 font-medium text-slate-900 dark:text-white">When you issue it, the ledger records:</p>
              <div class="flex justify-between font-mono"><span>Debit Accounts receivable (1200)</span><span>{{ formatMoney(form.amount) }}</span></div>
              <div class="flex justify-between font-mono"><span>Credit Membership income (4000)</span><span>{{ formatMoney(form.amount) }}</span></div>
            </div>
          </div>

          <template #footer>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
              <Button variant="ghost" :href="salesUrl">Cancel</Button>
              <div class="flex flex-col gap-3 sm:flex-row">
                <Button v-if="!isEdit" variant="secondary" :disabled="form.processing" @click="submit(true)">Save as draft</Button>
                <Button type="submit" :loading="form.processing">{{ isEdit ? 'Save changes' : 'Issue invoice' }}</Button>
              </div>
            </div>
          </template>
        </Card>
      </form>
    </div>
  </AdminLayout>
</template>
