<script setup>
import { localDate } from '@/Utils/localDateTime';
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Input from '@/Components/Ui/Input.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import { formatMeetingDate } from '@/Utils/lodgeDates';
import { formatMoney } from '@/Utils/currency';
const props = defineProps({
  club: {
    type: Object,
    required: true,
  },
  meeting: {
    type: Object,
    required: true,
  },
  confirmedDiningCount: {
    type: Number,
    default: 0,
  },
  financialReturn: {
    type: Object,
    default: null,
  },
});

const defaultDate = computed(() => {
  let d = null;
  if (props.financialReturn?.return_date) {
    d = String(props.financialReturn.return_date);
  } else if (props.meeting?.meeting_date) {
    d = String(props.meeting.meeting_date);
  }
  if (d) {
    return d.includes('T') ? d.split('T')[0] : d.substring(0, 10);
  }
  return localDate();
});

const form = useForm({
  return_date: defaultDate.value,
  kitchen_vendor_name: props.financialReturn?.kitchen_vendor_name || 'Masonic Hall Catering Ltd',
  paid_diners_count: props.financialReturn ? props.financialReturn.paid_diners_count : (props.confirmedDiningCount || 0),
  dining_fee_per_head: props.financialReturn ? parseFloat(props.financialReturn.dining_fee_per_head) : (parseFloat(props.meeting.dining_cost_member) || 35.00),
  kitchen_cost_per_head: props.financialReturn ? parseFloat(props.financialReturn.kitchen_cost_per_head) : 28.00,
  waived_diners_count: props.financialReturn ? props.financialReturn.waived_diners_count : 0,
  waived_reason: props.financialReturn?.waived_reason || 'Official Guests / Visiting Speakers',
  raffle_amount: props.financialReturn ? parseFloat(props.financialReturn.raffle_amount) : 0,
  alms_amount: props.financialReturn ? parseFloat(props.financialReturn.alms_amount) : 0,
  donations_amount: props.financialReturn ? parseFloat(props.financialReturn.donations_amount) : 0,
  bequest_amount: props.financialReturn ? parseFloat(props.financialReturn.bequest_amount) : 0,
  notes: props.financialReturn?.notes || '',
});

// Computed Calculations
const calcTotalMeals = computed(() => {
  return (parseInt(form.paid_diners_count) || 0) + (parseInt(form.waived_diners_count) || 0);
});

const calcDiningRevenue = computed(() => {
  return (parseInt(form.paid_diners_count) || 0) * (parseFloat(form.dining_fee_per_head) || 0);
});

const calcKitchenBill = computed(() => {
  return calcTotalMeals.value * (parseFloat(form.kitchen_cost_per_head) || 0);
});

const calcDiningSurplus = computed(() => {
  return calcDiningRevenue.value - calcKitchenBill.value;
});

const calcTotalCharity = computed(() => {
  return (parseFloat(form.raffle_amount) || 0) +
         (parseFloat(form.alms_amount) || 0) +
         (parseFloat(form.donations_amount) || 0) +
         (parseFloat(form.bequest_amount) || 0);
});

const calcBankDeposit = computed(() => {
  return calcDiningRevenue.value + calcTotalCharity.value;
});

const meetingName = computed(() => props.meeting.title || ('Meeting - ' + formatMeetingDate(props.meeting.meeting_date)));

const saveDraft = () => {
  form.transform((data) => ({
    ...data,
    is_draft: true,
  })).post(route('admin.meetings.financial_return.store', { clubSlug: props.club.slug, id: props.meeting.id }));
};

const submit = () => {
  form.transform((data) => ({
    ...data,
    is_draft: false,
  })).post(route('admin.meetings.financial_return.store', { clubSlug: props.club.slug, id: props.meeting.id }));
};
</script>

<template>
  <AdminLayout :club="club" title="Meeting financial return" active-tab="meetings">
    <Head :title="`Meeting Financial Return - ${club.name}`" />

    <div class="mx-auto max-w-5xl space-y-6">
      <PageHeader
        title="Meeting financial return and dining calculator"
        description="Record dining receipts, caterer kitchen expenses and meeting charity collections to post them automatically to accounts payable and the general ledger."
        :breadcrumbs="[
          { label: 'Meetings', href: route('admin.meetings.index', club.slug) },
          { label: meetingName, href: route('admin.meetings.show', { clubSlug: club.slug, id: meeting.id }) },
          { label: 'Financial return' },
        ]"
      >
        <template #badge>
          <Badge v-if="financialReturn?.is_draft" variant="warning">Draft saved</Badge>
        </template>
        <template #actions>
          <Button variant="secondary" size="sm" :href="route('admin.meetings.show', { clubSlug: club.slug, id: meeting.id })">Back to meeting dashboard</Button>
        </template>
      </PageHeader>

      <Card>
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
          <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
              <Badge class="capitalize">{{ meeting.status }}</Badge>
              <Badge v-if="financialReturn" variant="success">Posted to ledger</Badge>
            </div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ meetingName }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ meeting.venue || 'Masonic Hall' }} · {{ formatMeetingDate(meeting.meeting_date) }}</p>
          </div>

          <dl class="flex gap-6 border-t border-slate-200 pt-3 text-sm md:border-l md:border-t-0 md:pl-6 md:pt-0 dark:border-slate-800">
            <div>
              <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Confirmed RSVPs</dt>
              <dd class="text-base font-semibold text-slate-900 dark:text-white">{{ confirmedDiningCount }} diners</dd>
            </div>
            <div>
              <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Dining fee per head</dt>
              <dd class="text-base font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatMoney(form.dining_fee_per_head) }}</dd>
            </div>
          </dl>
        </div>
      </Card>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <template #header>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Return date and catering supplier</h2>
          </template>
          <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <Input v-model="form.return_date" type="date" label="Accounting return date" required :error="form.errors.return_date" />
            <Input v-model="form.kitchen_vendor_name" label="Kitchen supplier (accounts payable)" placeholder="e.g. Masonic Hall Catering Ltd" required :error="form.errors.kitchen_vendor_name" />
          </div>
        </Card>

        <Card>
          <template #header>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Dining fees and kitchen caterer calculator</h2>
          </template>
          <div class="space-y-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
              <Input v-model.number="form.paid_diners_count" type="number" min="0" label="Paid diners" hint="Number of paying diners" required :error="form.errors.paid_diners_count" />
              <Input v-model.number="form.dining_fee_per_head" type="number" step="0.01" min="0" label="Dining fee per head" :prefix="$cs" hint="Price charged per diner" required :error="form.errors.dining_fee_per_head" />
              <Input v-model.number="form.kitchen_cost_per_head" type="number" step="0.01" min="0" label="Kitchen cost per head" :prefix="$cs" hint="Caterer fee per meal prepared" required :error="form.errors.kitchen_cost_per_head" />
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-5">
              <Input v-model.number="form.waived_diners_count" class="sm:col-span-2" type="number" min="0" label="Waived diners (guests or speakers)" :error="form.errors.waived_diners_count" />
              <Input v-model="form.waived_reason" class="sm:col-span-3" label="Reason for waived fee" placeholder="e.g. Official guests, visiting speakers" :error="form.errors.waived_reason" />
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
              <StatCard label="Total meals prepared" :value="`${calcTotalMeals} ${calcTotalMeals === 1 ? 'meal' : 'meals'}`" />
              <StatCard label="Gross dining revenue" :value="formatMoney(calcDiningRevenue)" />
              <StatCard label="Kitchen caterer bill" :value="formatMoney(calcKitchenBill)" />
              <StatCard label="Net dining surplus" :value="formatMoney(calcDiningSurplus)" />
            </div>
          </div>
        </Card>

        <Card>
          <template #header>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Meeting charity and almoner collections</h2>
          </template>
          <div class="space-y-5">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
              <Input v-model.number="form.raffle_amount" type="number" step="0.01" min="0" label="Raffle collection" :prefix="$cs" :error="form.errors.raffle_amount" />
              <Input v-model.number="form.alms_amount" type="number" step="0.01" min="0" label="Alms box collection" :prefix="$cs" :error="form.errors.alms_amount" />
              <Input v-model.number="form.donations_amount" type="number" step="0.01" min="0" label="Donations" :prefix="$cs" :error="form.errors.donations_amount" />
              <Input v-model.number="form.bequest_amount" type="number" step="0.01" min="0" label="Bequests" :prefix="$cs" :error="form.errors.bequest_amount" />
            </div>

            <Textarea v-model="form.notes" label="Treasury notes or remarks" :rows="2" placeholder="e.g. Raffle receipts to be remitted to Masonic Charity Foundation. Catering invoice matched to kitchen cost." :error="form.errors.notes" />
          </div>
        </Card>

        <Card>
          <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
              <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Net bank deposit expected</p>
              <p class="mt-1 text-3xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatMoney(calcBankDeposit) }}</p>
              <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Gross dining income ({{ formatMoney(calcDiningRevenue) }}) plus total charity collections ({{ formatMoney(calcTotalCharity) }})</p>
            </div>
            <dl class="space-y-1 border-t border-slate-200 pt-3 text-sm md:border-l md:border-t-0 md:pl-6 md:pt-0 md:text-right dark:border-slate-800">
              <div>
                <dt class="inline text-slate-500 dark:text-slate-400">Kitchen bill for accounts payable:</dt>
                <dd class="inline font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatMoney(calcKitchenBill) }}</dd>
              </div>
              <div>
                <dt class="inline text-slate-500 dark:text-slate-400">Total charity collected:</dt>
                <dd class="inline font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatMoney(calcTotalCharity) }}</dd>
              </div>
            </dl>
          </div>

          <template #footer>
            <div class="flex flex-col-reverse items-stretch justify-between gap-3 sm:flex-row sm:items-center">
              <Button variant="ghost" :href="route('admin.meetings.show', { clubSlug: club.slug, id: meeting.id })">Cancel and return to meeting</Button>
              <div class="flex flex-col gap-3 sm:flex-row">
                <Button variant="secondary" :disabled="form.processing" @click="saveDraft">Save draft</Button>
                <Button type="submit" :disabled="form.processing">Post financial return and ledger entry</Button>
              </div>
            </div>
          </template>
        </Card>
      </form>
    </div>
  </AdminLayout>
</template>
