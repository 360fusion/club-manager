<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CharitySubNav from '@/Components/CharitySubNav.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Modal from '@/Components/Ui/Modal.vue';
import Input from '@/Components/Ui/Input.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
  club: Object,
  target: Object,
  totalRaisedForFestival: Number,
  formattedTotalRaised: String,
  targetPercentage: Number,
  currentHonorTier: String,
  activeMembers: Array,
});

const showTargetModal = ref(false);
const showGivingModal = ref(false);
const selectedMemberId = ref(null);

const targetForm = useForm({
  festival_name: props.target?.festival_name || '',
  relief_chest_ref: props.target?.relief_chest_ref || '',
  target_amount: props.target?.target_amount || '0.00',
  bronze_tier: props.target?.bronze_tier || '0.00',
  silver_tier: props.target?.silver_tier || '0.00',
  gold_tier: props.target?.gold_tier || '0.00',
  platinum_tier: props.target?.platinum_tier || '0.00',
});

const givingForm = useForm({
  regular_giving_amount: '0.00',
  total_donated_to_date: '0.00',
  qualifies_for_jewel: false,
  qualifies_for_bar: false,
});

const openGivingModal = (member) => {
  selectedMemberId.value = member.id;
  givingForm.regular_giving_amount = member.regular_giving || '0.00';
  givingForm.total_donated_to_date = member.total_donated || '0.00';
  givingForm.qualifies_for_jewel = member.qualifies_for_jewel;
  givingForm.qualifies_for_bar = member.qualifies_for_bar;
  showGivingModal.value = true;
};

const submitTarget = () => {
  targetForm.post(route('admin.charity.festival.target.update', props.club.slug), {
    onSuccess: () => {
      showTargetModal.value = false;
    },
  });
};

const submitGiving = () => {
  givingForm.post(route('admin.charity.festival.giving.update', { clubSlug: props.club.slug, memberId: selectedMemberId.value }), {
    onSuccess: () => {
      showGivingModal.value = false;
    },
  });
};

const tierClass = (reached) => [
  'p-3 rounded-xl border',
  reached
    ? 'bg-gold-50 border-gold-300 text-gold-900 dark:bg-gold-950/40 dark:border-gold-700/60 dark:text-gold-100'
    : 'bg-slate-50 border-slate-200 text-slate-500 dark:bg-slate-800/50 dark:border-slate-800 dark:text-slate-400',
];
</script>

<template>
  <Head :title="`Festival information — ${club.name}`" />

  <AdminLayout :club="club" title="Festival" active-tab="charity">
    <div class="max-w-7xl mx-auto space-y-6 pb-12">
      <CharitySubNav :clubSlug="club.slug" activeTab="festival" />

      <PageHeader title="Provincial Festival information" :description="`Relief Chest ref: ${target.relief_chest_ref} · target milestones and member honour qualifications`">
        <template #actions>
          <Button size="sm" @click="showTargetModal = true">
            <template #icon><UiIcon name="settings" /></template>
            Edit Festival milestones
          </Button>
        </template>
      </PageHeader>

      <Card>
        <template #header>
          <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ target.festival_name }}</h2>
                <Badge>Relief Chest ref: {{ target.relief_chest_ref }}</Badge>
              </div>
              <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Provincial honour milestone target: {{ $cs }}{{ Number(target.target_amount).toLocaleString('en-GB', { minimumFractionDigits: 2 }) }}</p>
            </div>

            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full border border-gold-300 bg-gold-50 text-[11px] font-medium text-gold-900 whitespace-nowrap dark:border-gold-700/60 dark:bg-gold-950/40 dark:text-gold-100">
              <UiIcon name="award" :size="12" />
              Honour tier: {{ currentHonorTier }}
            </span>
          </div>
        </template>

        <div class="space-y-2">
          <div class="flex items-center justify-between text-sm font-medium">
            <span class="text-slate-700 dark:text-slate-200">Total raised to date: {{ formattedTotalRaised }}</span>
            <span class="text-blue-700 dark:text-blue-300 tabular-nums">{{ targetPercentage }}% complete</span>
          </div>

          <div class="w-full bg-slate-100 dark:bg-slate-800 h-3 rounded-full overflow-hidden" role="progressbar" :aria-valuenow="targetPercentage" aria-valuemin="0" aria-valuemax="100" aria-label="Festival target progress">
            <div class="bg-blue-600 h-full rounded-full transition-all duration-500" :style="{ width: targetPercentage + '%' }"></div>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 text-xs font-medium text-center">
            <div :class="tierClass(totalRaisedForFestival >= target.bronze_tier)">
              <span>Bronze tier</span>
              <span class="block text-sm font-semibold tabular-nums">{{ $cs }}{{ Number(target.bronze_tier).toLocaleString('en-GB') }}</span>
              <span v-if="totalRaisedForFestival >= target.bronze_tier" class="mt-1 inline-flex items-center gap-1 text-[11px]"><UiIcon name="check" :size="12" />Reached</span>
            </div>
            <div :class="tierClass(totalRaisedForFestival >= target.silver_tier)">
              <span>Silver tier</span>
              <span class="block text-sm font-semibold tabular-nums">{{ $cs }}{{ Number(target.silver_tier).toLocaleString('en-GB') }}</span>
              <span v-if="totalRaisedForFestival >= target.silver_tier" class="mt-1 inline-flex items-center gap-1 text-[11px]"><UiIcon name="check" :size="12" />Reached</span>
            </div>
            <div :class="tierClass(totalRaisedForFestival >= target.gold_tier)">
              <span>Gold tier</span>
              <span class="block text-sm font-semibold tabular-nums">{{ $cs }}{{ Number(target.gold_tier).toLocaleString('en-GB') }}</span>
              <span v-if="totalRaisedForFestival >= target.gold_tier" class="mt-1 inline-flex items-center gap-1 text-[11px]"><UiIcon name="check" :size="12" />Reached</span>
            </div>
            <div :class="tierClass(totalRaisedForFestival >= target.platinum_tier)">
              <span>Platinum tier</span>
              <span class="block text-sm font-semibold tabular-nums">{{ $cs }}{{ Number(target.platinum_tier).toLocaleString('en-GB') }}</span>
              <span v-if="totalRaisedForFestival >= target.platinum_tier" class="mt-1 inline-flex items-center gap-1 text-[11px]"><UiIcon name="check" :size="12" />Reached</span>
            </div>
          </div>
        </div>
      </Card>

      <Card>
        <template #header>
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Member Festival giving and jewel qualifications</h3>
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Confidential stewardship roster</span>
          </div>
        </template>

        <div class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-x-auto">
          <table class="w-full text-left text-sm text-slate-700 dark:text-slate-200">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
              <tr>
                <th scope="col" class="py-3 px-4 font-medium">Member name and rank</th>
                <th scope="col" class="py-3 px-4 font-medium text-right">Regular giving / month</th>
                <th scope="col" class="py-3 px-4 font-medium text-right">Total donated to date</th>
                <th scope="col" class="py-3 px-4 font-medium">Jewel qualification</th>
                <th scope="col" class="py-3 px-4 font-medium text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="m in activeMembers" :key="m.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                <td class="py-3 px-4 font-medium text-slate-900 dark:text-white">{{ m.name }} ({{ m.rank }})</td>
                <td class="py-3 px-4 text-right tabular-nums">
                  {{ m.regular_giving > 0 ? $money(m.regular_giving) : '—' }}
                </td>
                <td class="py-3 px-4 text-right font-semibold text-slate-900 dark:text-white tabular-nums">{{ m.formatted_donated }}</td>
                <td class="py-3 px-4">
                  <div class="flex items-center gap-1.5">
                    <Badge v-if="m.qualifies_for_jewel" variant="success">
                      <UiIcon name="award" :size="12" />
                      Festival jewel
                    </Badge>
                    <Badge v-if="m.qualifies_for_bar" variant="info">Honour bar</Badge>
                    <span v-if="!m.qualifies_for_jewel && !m.qualifies_for_bar" class="text-slate-500 dark:text-slate-400 text-xs">In progress</span>
                  </div>
                </td>
                <td class="py-3 px-4 text-right">
                  <Button variant="secondary" size="sm" @click="openGivingModal(m)">Edit giving</Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>
    </div>

    <Modal :open="showTargetModal" title="Edit Festival target milestones" @close="showTargetModal = false">
      <form id="festival-target-form" class="space-y-4" @submit.prevent="submitTarget">
        <Input v-model="targetForm.festival_name" label="Festival name" required />

        <Input v-model="targetForm.relief_chest_ref" label="MCF Relief Chest reference" required />

        <div class="grid grid-cols-2 gap-3">
          <Input v-model="targetForm.bronze_tier" type="number" step="0.01" :label="`Bronze tier (${$cs})`" required />
          <Input v-model="targetForm.silver_tier" type="number" step="0.01" :label="`Silver tier (${$cs})`" required />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <Input v-model="targetForm.gold_tier" type="number" step="0.01" :label="`Gold tier (${$cs})`" required />
          <Input v-model="targetForm.platinum_tier" type="number" step="0.01" :label="`Platinum tier (${$cs})`" required />
        </div>
      </form>
      <template #footer>
        <Button variant="secondary" @click="showTargetModal = false">Cancel</Button>
        <Button type="submit" form="festival-target-form" :loading="targetForm.processing">Save milestones</Button>
      </template>
    </Modal>

    <Modal :open="showGivingModal" title="Edit member Festival giving" @close="showGivingModal = false">
      <form id="festival-giving-form" class="space-y-4" @submit.prevent="submitGiving">
        <Input v-model="givingForm.regular_giving_amount" type="number" step="0.01" :label="`Monthly regular giving (${$cs})`" />

        <Input v-model="givingForm.total_donated_to_date" type="number" step="0.01" :label="`Total donated to date (${$cs})`" required />

        <div class="space-y-2">
          <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200 cursor-pointer">
            <input type="checkbox" v-model="givingForm.qualifies_for_jewel" class="h-4 w-4 rounded accent-blue-600" />
            <span>Qualifies for the Festival jewel (at least {{ $cs }}250)</span>
          </label>

          <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200 cursor-pointer">
            <input type="checkbox" v-model="givingForm.qualifies_for_bar" class="h-4 w-4 rounded accent-blue-600" />
            <span>Qualifies for the honour bar (at least {{ $cs }}500)</span>
          </label>
        </div>
      </form>
      <template #footer>
        <Button variant="secondary" @click="showGivingModal = false">Cancel</Button>
        <Button type="submit" form="festival-giving-form" :loading="givingForm.processing">Save member giving</Button>
      </template>
    </Modal>
  </AdminLayout>
</template>
