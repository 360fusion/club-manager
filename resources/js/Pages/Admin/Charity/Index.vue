<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CharitySubNav from '@/Components/CharitySubNav.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Modal from '@/Components/Ui/Modal.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
  club: Object,
  target: Object,
  totalRaisedForFestival: Number,
  formattedTotalRaised: String,
  targetPercentage: Number,
  currentHonorTier: String,
  giftAidSummary: Object,
  reconciledDonations: Object,
  collections: Array,
  totalCollectionsAmount: Number,
  formattedTotalCollections: String,
  grants: Array,
  upcomingMeetings: {
    type: Array,
    default: () => [],
  },
  totalGrantsDisbursed: Number,
  formattedTotalGrants: String,
  activeMembers: Array,
  jewelHoldersCount: Number,
});

const showCollectionModal = ref(false);
const showGrantModal = ref(false);

const collectionForm = useForm({
  collection_type: 'alms_plate',
  cash_amount: '0.00',
  cheque_amount: '0.00',
  counted_by_member_id: null,
  witnessed_by_member_id: null,
  donor_member_id: null,
  donor_name: '',
  notes: '',
});

const grantForm = useForm({
  recipient_name: '',
  purpose: '',
  amount: '0.00',
  relief_chest_number: '',
  proposer_member_id: null,
  seconder_member_id: null,
  meeting_id: null,
});

const submitCollection = () => {
  collectionForm.post(route('admin.charity.collections.store', props.club.slug), {
    onSuccess: () => {
      showCollectionModal.value = false;
      collectionForm.reset();
    },
  });
};

const submitGrant = () => {
  grantForm.post(route('admin.charity.grants.store', props.club.slug), {
    onSuccess: () => {
      showGrantModal.value = false;
      grantForm.reset();
    },
  });
};

const updateGrantStatus = (grantId, status) => {
  useForm({ approval_status: status }).post(route('admin.charity.grants.update_status', { clubSlug: props.club.slug, grantId }));
};

// The member pickers stay native selects: their values are numeric ids or null, which the kit Select would turn into strings.
const fieldClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
const labelClass = 'block space-y-1.5';
const labelTextClass = 'block text-xs font-medium text-slate-700 dark:text-slate-300';
const secondaryButton = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:ring-offset-slate-950';
</script>

<template>
  <Head :title="`Charity dashboard — ${club.name}`" />

  <AdminLayout :club="club" title="Charity" active-tab="charity">
    <div class="max-w-7xl mx-auto space-y-6 pb-12">
      <CharitySubNav :clubSlug="club.slug" activeTab="dashboard" />

      <PageHeader title="Charity dashboard" description="Meeting collections counted under dual custody, grant voting and the Relief Chest.">
        <template #actions>
          <div class="flex flex-wrap items-center gap-2">
            <Button size="sm" @click="showCollectionModal = true">
              <template #icon><UiIcon name="cash" /></template>
              Record collection
            </Button>
            <Button variant="secondary" size="sm" @click="showGrantModal = true">
              <template #icon><UiIcon name="gift" /></template>
              Propose grant
            </Button>
            <a :href="route('admin.accounting.giftaid.export_schedule', club.slug)" target="_blank" :class="secondaryButton">
              <UiIcon name="download" />
              MCF Relief Chest CSV
            </a>
          </div>
        </template>
      </PageHeader>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Total raised to date" :value="formattedTotalRaised" caption="Collections and member giving" />
        <StatCard label="Meeting alms and raffles" :value="formattedTotalCollections" caption="Takings verified under dual custody" />
        <StatCard label="Grants disbursed" :value="formattedTotalGrants" caption="Approved relief grants paid" />
        <StatCard label="Festival jewel holders" :value="jewelHoldersCount" caption="Brethren qualifying for the Festival jewel" />
      </div>

      <Card>
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
          <div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Gift Aid and Relief Chest position</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
              Pending Gift Aid reclaim (25%): <strong class="font-semibold text-slate-900 dark:text-white">{{ giftAidSummary?.formatted_pending_gift_aid || ($cs + '0.00') }}</strong>
              ({{ giftAidSummary?.pending_claim_count || 0 }} collection batches pending)
              · Net Relief Chest balance: <strong class="font-semibold text-slate-900 dark:text-white">{{ giftAidSummary?.formatted_net_relief_chest_balance || ($cs + '0.00') }}</strong>
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <Button variant="secondary" size="sm" :href="route('admin.charity.giftaid.transactions_page', club.slug)">
              <template #icon><UiIcon name="receipt" /></template>
              View Gift Aid transactions
            </Button>
            <Button variant="secondary" size="sm" :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'reconciliation' })">
              <template #icon><UiIcon name="refresh" /></template>
              Open reconciliation workspace
            </Button>
          </div>
        </div>
      </Card>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <template #header>
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-base font-semibold text-slate-900 dark:text-white">Recent meeting collections</h3>
              <Button variant="ghost" size="sm" @click="showCollectionModal = true">
                <template #icon><UiIcon name="plus" /></template>
                New collection
              </Button>
            </div>
          </template>

          <div class="space-y-3">
            <div
              v-for="col in collections"
              :key="col.id"
              class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center justify-between gap-3 text-xs"
            >
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="font-semibold text-slate-900 dark:text-white">{{ col.collection_type }}</span>
                  <span class="text-[11px] text-slate-500 dark:text-slate-400 tabular-nums">{{ col.created_at }}</span>
                </div>
                <div class="text-slate-500 dark:text-slate-400">
                  <span>Counted by <strong class="font-medium text-slate-700 dark:text-slate-200">{{ col.counted_by }}</strong></span> · <span>Witnessed by <strong class="font-medium text-slate-700 dark:text-slate-200">{{ col.witnessed_by }}</strong></span>
                </div>
              </div>
              <div class="text-right whitespace-nowrap">
                <div class="font-semibold text-slate-900 dark:text-white text-sm tabular-nums">{{ col.formatted_total }}</div>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 block tabular-nums">Cash: {{ $cs }}{{ col.cash_amount }} · Cheque: {{ $cs }}{{ col.cheque_amount }}</span>
              </div>
            </div>
          </div>
        </Card>

        <Card>
          <template #header>
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-base font-semibold text-slate-900 dark:text-white">Charity grants and relief voting</h3>
              <Button variant="ghost" size="sm" @click="showGrantModal = true">
                <template #icon><UiIcon name="plus" /></template>
                Propose grant
              </Button>
            </div>
          </template>

          <div class="space-y-3">
            <div
              v-for="grant in grants"
              :key="grant.id"
              class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center justify-between gap-3 text-xs"
            >
              <div class="space-y-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-semibold text-slate-900 dark:text-white">{{ grant.recipient_name }}</span>
                  <Badge variant="info">{{ grant.status_label }}</Badge>
                  <Badge v-if="grant.meeting_title">
                    <UiIcon name="calendar" :size="12" />
                    {{ grant.meeting_title }}
                  </Badge>
                </div>
                <p class="text-slate-500 dark:text-slate-400 line-clamp-1">{{ grant.purpose }}</p>
              </div>
              <div class="text-right whitespace-nowrap space-y-1">
                <div class="font-semibold text-slate-900 dark:text-white text-sm tabular-nums">{{ grant.formatted_amount }}</div>
                <Button
                  v-if="grant.approval_status === 'proposed'"
                  variant="secondary"
                  size="sm"
                  @click="updateGrantStatus(grant.id, 'committee_approved')"
                >
                  Approve committee
                </Button>
                <Button
                  v-else-if="grant.approval_status === 'committee_approved'"
                  variant="secondary"
                  size="sm"
                  @click="updateGrantStatus(grant.id, 'lodge_voted')"
                >
                  Lodge voted
                </Button>
                <Button
                  v-else-if="grant.approval_status === 'lodge_voted'"
                  variant="secondary"
                  size="sm"
                  @click="updateGrantStatus(grant.id, 'disbursed')"
                >
                  Disburse payment
                </Button>
                <Badge v-else variant="success">
                  <UiIcon name="check" :size="12" />
                  Disbursed
                </Badge>
              </div>
            </div>
          </div>
        </Card>
      </div>
    </div>

    <Modal :open="showCollectionModal" title="Record a meeting collection" @close="showCollectionModal = false">
      <form id="charity-collection-form" class="space-y-4" @submit.prevent="submitCollection">
        <Select
          v-model="collectionForm.collection_type"
          label="Collection type"
          required
          :options="[
            { value: 'alms_plate', label: 'Alms plate collection' },
            { value: 'raffle', label: 'Meeting charity raffle' },
            { value: 'ladies_night', label: 'Festive board alms' },
            { value: 'individual_envelope', label: 'Gift Aid envelope' },
            { value: 'festival_donation', label: 'Festival direct donation' },
          ]"
        />

        <div class="grid grid-cols-2 gap-3">
          <Input v-model="collectionForm.cash_amount" type="number" step="0.01" :label="`Cash amount (${$cs})`" required />
          <Input v-model="collectionForm.cheque_amount" type="number" step="0.01" :label="`Cheque amount (${$cs})`" />
        </div>

        <label :class="labelClass">
          <span :class="labelTextClass">Counter (e.g. Steward) <span class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span></span>
          <select v-model="collectionForm.counted_by_member_id" :class="fieldClass">
            <option :value="null">Choose a counter</option>
            <option v-for="m in activeMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
          </select>
        </label>

        <label :class="labelClass">
          <span :class="labelTextClass">Witness <span class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span></span>
          <select v-model="collectionForm.witnessed_by_member_id" :class="fieldClass">
            <option :value="null">Choose a witness</option>
            <option v-for="m in activeMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
          </select>
        </label>

        <Textarea v-model="collectionForm.notes" label="Notes or donor details" :rows="2" placeholder="e.g. Gift Aid envelope #12, Bro. Smith" />
      </form>
      <template #footer>
        <Button variant="secondary" @click="showCollectionModal = false">Cancel</Button>
        <Button type="submit" form="charity-collection-form" :loading="collectionForm.processing">Record collection</Button>
      </template>
    </Modal>

    <Modal :open="showGrantModal" title="Propose a charity grant" @close="showGrantModal = false">
      <form id="charity-grant-form" class="space-y-4" @submit.prevent="submitGrant">
        <Input v-model="grantForm.recipient_name" label="Recipient name or cause" placeholder="e.g. Local children's hospice" required />

        <Input v-model="grantForm.amount" type="number" step="0.01" :label="`Grant amount (${$cs})`" required />

        <Textarea v-model="grantForm.purpose" label="Purpose and details" :rows="2" placeholder="Describe the cause and the committee's recommendation" required />

        <div class="grid grid-cols-2 gap-3">
          <label :class="labelClass">
            <span :class="labelTextClass">Proposer</span>
            <select v-model="grantForm.proposer_member_id" :class="fieldClass">
              <option :value="null">Choose a proposer</option>
              <option v-for="m in activeMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </label>
          <label :class="labelClass">
            <span :class="labelTextClass">Seconder</span>
            <select v-model="grantForm.seconder_member_id" :class="fieldClass">
              <option :value="null">Choose a seconder</option>
              <option v-for="m in activeMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </label>
        </div>

        <label :class="labelClass">
          <span :class="labelTextClass">Add to a meeting summons (optional)</span>
          <select v-model="grantForm.meeting_id" :class="fieldClass">
            <option :value="null">Do not add to a summons</option>
            <option v-for="m in upcomingMeetings" :key="m.id" :value="m.id">{{ m.label }}</option>
          </select>
          <span class="block text-xs text-slate-500 dark:text-slate-400">This adds a charity ballot item to that meeting's summons.</span>
        </label>
      </form>
      <template #footer>
        <Button variant="secondary" @click="showGrantModal = false">Cancel</Button>
        <Button type="submit" form="charity-grant-form" :loading="grantForm.processing">Save grant proposal</Button>
      </template>
    </Modal>
  </AdminLayout>
</template>
