<script setup>
import { computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
  club: {
    type: Object,
    required: true,
  },
  contact: {
    type: Object,
    default: null,
  },
  memberSummary: {
    type: Object,
    default: null,
  },
});

const isEditing = computed(() => !!props.contact);
const isMember  = computed(() => props.contact?.is_member === true);

// The values are what is stored on the contact; only the labels are worded for people.
const ROLE_OPTIONS = [
  { value: 'Contractor / Coach', label: 'Contractor or coach' },
  { value: 'Volunteer / Staff', label: 'Volunteer or staff' },
  { value: 'Vendor Representative', label: 'Vendor representative' },
  { value: 'Client / Customer', label: 'Client or customer' },
  { value: 'Club Member', label: 'Club member' },
];

const form = useForm({
  first_name:     props.contact?.first_name     || '',
  middle_names:   props.contact?.middle_names   || '',
  last_name:      props.contact?.last_name      || '',
  preferred_name: props.contact?.preferred_name || '',
  email:          props.contact?.email          || '',
  phone:          props.contact?.phone          || '',
  role:           props.contact?.role           || 'Contractor / Coach',
  tax_id:         props.contact?.tax_id         || '',
  address_line_1: props.contact?.address_line_1 || '',
  address_line_2: props.contact?.address_line_2 || '',
  city:           props.contact?.city           || '',
  postcode:       props.contact?.postcode       || '',
  country:        props.contact?.country        || 'United Kingdom',
  notes:          props.contact?.notes          || '',
});

// Members are always "Club Member" – lock the role on submit
if (isMember.value) {
  form.role = 'Club Member';
}

const submitForm = () => {
  if (isMember.value) form.role = 'Club Member';
  if (isEditing.value) {
    form.put(route('admin.accounting.contacts.update', [props.club.slug, props.contact.id]));
  } else {
    form.post(route('admin.accounting.contacts.store', props.club.slug));
  }
};

const deleteContact = () => {
  if (!isEditing.value) return;
  const displayName = [props.contact?.first_name, props.contact?.last_name].filter(Boolean).join(' ') || props.contact?.name || 'this contact';
  if (confirm(`Are you sure you want to remove "${displayName}" from the contact directory?`)) {
    router.delete(route('admin.accounting.contacts.destroy', [props.club.slug, props.contact.id]));
  }
};
</script>

<template>
  <AdminLayout :club="club" :title="isEditing ? 'Edit contact details' : 'Add a contact'" active-tab="accounting">
    <Head :title="`${isEditing ? 'Edit contact details' : 'Add a contact'} - ${club.name}`" />

    <div class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <PageHeader
        :title="isEditing ? 'Edit contact details' : 'Add a directory contact'"
        :description="isMember ? 'Update billing address, tax info and notes for this club member.' : isEditing ? 'Update address, tax information, role and notes for this contact.' : 'Create a new contractor, client or sponsor in your accounting directory.'"
        :breadcrumbs="[
          { label: 'Accounting', href: route('admin.accounting.index', club.slug) },
          { label: 'Contacts directory', href: route('admin.accounting.index', { clubSlug: club.slug, tab: 'contacts' }) },
          { label: isEditing ? 'Edit contact' : 'New contact' },
        ]"
      >
        <template #actions>
          <Button variant="ghost" size="sm" :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'contacts' })">
            <template #icon><UiIcon name="arrow-left" /></template>
            Back to directory
          </Button>
          <Button v-if="isEditing && !isMember" variant="danger" size="sm" @click="deleteContact">
            <template #icon><UiIcon name="trash" /></template>
            Delete
          </Button>
        </template>
      </PageHeader>

      <!-- Member financial overview -->
      <div v-if="isMember && memberSummary" class="space-y-4">

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <StatCard
            label="Amount owed"
            :value="memberSummary.amount_owed_formatted"
            :caption="memberSummary.amount_owed > 0 ? 'Outstanding subs or invoices' : 'All paid up'"
          />
          <StatCard label="Credit or advance" :value="memberSummary.credit_balance_formatted" caption="Paid in advance or refunds" />
          <StatCard label="Total paid" :value="memberSummary.total_paid_formatted" caption="Lifetime contributions" />
          <StatCard label="Last payment" :value="memberSummary.last_payment_amount ?? '—'" :caption="memberSummary.last_payment_date ?? 'No payments yet'" />
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Card padding="sm">
            <div class="flex items-start gap-3">
              <span class="mt-0.5 text-slate-400 dark:text-slate-500"><UiIcon name="tag" :size="20" /></span>
              <div>
                <p class="mb-0.5 text-xs font-medium text-slate-500 dark:text-slate-400">Membership plan</p>
                <p v-if="memberSummary.membership_plan" class="text-sm font-semibold text-slate-900 dark:text-white">
                  {{ memberSummary.membership_plan }}
                </p>
                <p v-else class="text-sm text-slate-500 dark:text-slate-400">No active plan</p>
                <p v-if="memberSummary.membership_plan" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                  {{ memberSummary.membership_price }} / {{ memberSummary.membership_period }}
                  <span v-if="memberSummary.membership_renews"> · Renews {{ memberSummary.membership_renews }}</span>
                </p>
              </div>
            </div>
          </Card>

          <Card padding="sm">
            <div class="flex items-start gap-3">
              <span class="mt-0.5 text-slate-400 dark:text-slate-500"><UiIcon name="calendar" :size="20" /></span>
              <div>
                <p class="mb-0.5 text-xs font-medium text-slate-500 dark:text-slate-400">Member since</p>
                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ memberSummary.member_since ?? 'Unknown' }}</p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ memberSummary.invoice_count }} invoice(s) on record</p>
              </div>
            </div>
          </Card>
        </div>

        <!-- Unpaid invoices -->
        <div v-if="memberSummary.unpaid_invoices?.length" class="overflow-hidden rounded-2xl border border-rose-200 bg-rose-50 dark:border-rose-800/50 dark:bg-rose-950/40">
          <div class="flex items-center gap-2 border-b border-rose-200 px-4 py-3 text-rose-700 dark:border-rose-800/50 dark:text-rose-300">
            <UiIcon name="warning" />
            <h3 class="text-sm font-semibold">Outstanding invoices</h3>
          </div>
          <table class="w-full text-left">
            <thead class="border-b border-rose-100 text-xs text-rose-700 dark:border-rose-900/40 dark:text-rose-300">
              <tr>
                <th scope="col" class="px-4 py-2 font-medium">Invoice</th>
                <th scope="col" class="px-4 py-2 font-medium">Description</th>
                <th scope="col" class="px-4 py-2 font-medium">Issued</th>
                <th scope="col" class="px-4 py-2 text-right font-medium">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-rose-100 dark:divide-rose-900/40">
              <tr v-for="inv in memberSummary.unpaid_invoices" :key="inv.id" class="text-sm text-slate-700 dark:text-slate-200">
                <td class="px-4 py-2.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ inv.invoice_number }}</td>
                <td class="px-4 py-2.5">{{ inv.title }}</td>
                <td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ inv.created_at }}</td>
                <td class="px-4 py-2.5 text-right font-semibold tabular-nums text-rose-700 dark:text-rose-300">{{ inv.amount }}</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>

      <!-- Contact form -->
      <form @submit.prevent="submitForm">
        <Card padding="lg">
          <div class="space-y-8">

            <section class="space-y-4">
              <h3 class="border-b border-slate-200 pb-2 text-base font-semibold text-slate-900 dark:border-slate-800 dark:text-white">Name and classification</h3>

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Input v-model="form.first_name" label="First name" placeholder="e.g. Robert" required autocomplete="given-name" :error="form.errors.first_name" />
                <Input v-model="form.middle_names" label="Middle names" placeholder="e.g. James" autocomplete="additional-name" :error="form.errors.middle_names" />
                <Input v-model="form.last_name" label="Surname" placeholder="e.g. Sterling" autocomplete="family-name" :error="form.errors.last_name" />
              </div>

              <Input
                v-model="form.preferred_name"
                class="sm:w-1/2"
                label="Preferred or display name"
                hint="A nickname, or how they like to be addressed."
                placeholder="e.g. Bob"
                autocomplete="nickname"
                :error="form.errors.preferred_name"
              />

              <div v-if="isMember" class="space-y-1.5">
                <p class="text-xs font-medium text-slate-700 dark:text-slate-300">Relationship or role</p>
                <Badge variant="success">Club member</Badge>
              </div>
              <Select
                v-else
                v-model="form.role"
                class="sm:w-1/2"
                label="Relationship or role"
                :options="ROLE_OPTIONS"
                required
                :error="form.errors.role"
              />
            </section>

            <section class="space-y-4">
              <h3 class="border-b border-slate-200 pb-2 text-base font-semibold text-slate-900 dark:border-slate-800 dark:text-white">Contact details</h3>

              <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <Input v-model="form.email" type="email" label="Email address" placeholder="robert@example.co.uk" autocomplete="email" :error="form.errors.email" />
                <Input v-model="form.phone" label="Phone number" placeholder="+44 7700 900123" autocomplete="tel" :error="form.errors.phone" />
              </div>
            </section>

            <section class="space-y-4">
              <h3 class="border-b border-slate-200 pb-2 text-base font-semibold text-slate-900 dark:border-slate-800 dark:text-white">Billing address</h3>

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input v-model="form.address_line_1" label="Address line 1" placeholder="14 Boathouse Lane" autocomplete="address-line1" :error="form.errors.address_line_1" />
                <Input v-model="form.address_line_2" label="Address line 2" placeholder="Abingdon Road" autocomplete="address-line2" :error="form.errors.address_line_2" />
              </div>

              <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Input v-model="form.city" label="City or town" placeholder="Oxford" autocomplete="address-level2" :error="form.errors.city" />
                <Input v-model="form.postcode" label="Postcode or ZIP" placeholder="OX2 0ES" autocomplete="postal-code" :error="form.errors.postcode" />
                <Input v-model="form.country" label="Country" placeholder="United Kingdom" autocomplete="country-name" :error="form.errors.country" />
              </div>
            </section>

            <section class="space-y-4">
              <h3 class="border-b border-slate-200 pb-2 text-base font-semibold text-slate-900 dark:border-slate-800 dark:text-white">Internal bookkeeping notes and payment terms</h3>

              <Textarea
                v-model="form.notes"
                label="Notes and terms"
                :rows="3"
                placeholder="e.g. Membership since 2021. Direct debit set up. Payment terms Net 30."
                :error="form.errors.notes"
              />
            </section>
          </div>

          <template #footer>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
              <Button variant="ghost" :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'contacts' })">Cancel</Button>
              <Button type="submit" :loading="form.processing">{{ isEditing ? 'Update contact details' : 'Save contact to directory' }}</Button>
            </div>
          </template>
        </Card>
      </form>
    </div>
  </AdminLayout>
</template>
