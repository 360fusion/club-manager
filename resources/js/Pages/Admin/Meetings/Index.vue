<script setup>
import { localDate } from '@/Utils/localDateTime';
import { formatDate } from '@/Utils/lodgeDates';
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MeetingsTabs from '@/Components/MeetingsTabs.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Modal from '@/Components/Ui/Modal.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';

const props = defineProps({
  club: Object,
  meetings: Array,
  recurringRules: Array,
});

const showSeasonModal = ref(false);
const activeTab = ref('upcoming');
const selectedYear = ref('all');
const searchQuery = ref('');
const startDate = ref('');
const endDate = ref('');

const todayStr = localDate();

const upcomingMeetings = computed(() => {
  return (props.meetings || []).filter(m => {
    const d = String(m.meeting_date).split('T')[0];
    return d >= todayStr;
  });
});

const pastMeetings = computed(() => {
  return (props.meetings || []).filter(m => {
    const d = String(m.meeting_date).split('T')[0];
    return d < todayStr;
  }).sort((a, b) => String(b.meeting_date).localeCompare(String(a.meeting_date)));
});

const availableYears = computed(() => {
  const years = new Set();
  (props.meetings || []).forEach(m => {
    if (m.meeting_date) {
      const year = String(m.meeting_date).substring(0, 4);
      if (year && year.length === 4) years.add(year);
    }
  });
  return Array.from(years).sort();
});

const filteredMeetings = computed(() => {
  let list = [];
  if (activeTab.value === 'upcoming') {
    list = [...upcomingMeetings.value];
  } else if (activeTab.value === 'past') {
    list = [...pastMeetings.value];
  } else {
    list = [...(props.meetings || [])];
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    list = list.filter(m => 
      (m.title && m.title.toLowerCase().includes(q)) || 
      (m.venue && m.venue.toLowerCase().includes(q))
    );
  }

  if (selectedYear.value !== 'all') {
    list = list.filter(m => String(m.meeting_date).startsWith(selectedYear.value));
  }

  if (startDate.value) {
    list = list.filter(m => String(m.meeting_date).split('T')[0] >= startDate.value);
  }
  if (endDate.value) {
    list = list.filter(m => String(m.meeting_date).split('T')[0] <= endDate.value);
  }

  return list;
});

const clearFilters = () => {
  selectedYear.value = 'all';
  searchQuery.value = '';
  startDate.value = '';
  endDate.value = '';
};

const seasonForm = useForm({
  year: 2026,
  occurrence: '3rd',
  day_of_week: 'Tuesday',
  starts_at: '18:30',
  rehearsal_starts_at: '17:30',
  active_months: [1, 2, 3, 4, 5, 10, 11, 12], // 8 active meeting months (skipping recess)
});

const generateSeason = () => {
  seasonForm.post(route('admin.meetings.generate_season', { clubSlug: props.club.slug }), {
    onSuccess: () => {
      showSeasonModal.value = false;
    },
  });
};

const yearOptions = computed(() => [{ value: 'all', label: 'All years' }, ...availableYears.value.map((y) => ({ value: y, label: y }))]);

const OCCURRENCE_OPTIONS = [
  { value: '1st', label: '1st' },
  { value: '2nd', label: '2nd' },
  { value: '3rd', label: '3rd' },
  { value: '4th', label: '4th' },
  { value: 'last', label: 'Last' },
];

const DAY_OPTIONS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].map((day) => ({ value: day, label: day }));

const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const LIST_TABS = [['upcoming', 'Upcoming meetings'], ['past', 'Past meetings'], ['all', 'All meetings']];

const formatTime = (timeStr) => {
  if (!timeStr) return '';
  return timeStr.substring(0, 5);
};

const meetingToDelete = ref(null);
const deleting = ref(false);

const deleteMeeting = () => {
  deleting.value = true;
  router.delete(route('admin.meetings.destroy', { clubSlug: props.club.slug, id: meetingToDelete.value.id }), {
    onFinish: () => {
      deleting.value = false;
      meetingToDelete.value = null;
    },
  });
};

const duplicateMeeting = (id) => {
  router.post(route('admin.meetings.duplicate', { clubSlug: props.club.slug, id }));
};
</script>

<template>
  <AdminLayout title="Meetings and summonses" :club="club" active-tab="meetings">
    <div class="space-y-6">
      <MeetingsTabs :club="club" active-tab="lodge" />

      <Card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Meetings and summonses</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Generate season schedules using nth-weekday rules, publish summonses, and track passwordless RSVPs.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button variant="secondary" size="sm" @click="showSeasonModal = true">Generate season / Masonic year</Button>
            <Button size="sm" :href="route('admin.meetings.create', { clubSlug: club.slug })">Add a meeting</Button>
          </div>
        </div>
      </Card>

      <Card v-if="meetings.length">
        <div class="space-y-4">
          <div class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-4 dark:border-slate-800 sm:flex-row sm:items-center">
            <div class="flex items-center gap-1 overflow-x-auto" role="group" aria-label="Show">
              <button
                v-for="[key, label] in LIST_TABS"
                :key="key"
                type="button"
                :aria-pressed="activeTab === key"
                :class="['rounded-full px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors', activeTab === key ? 'bg-slate-900 text-white dark:bg-slate-700' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800']"
                @click="activeTab = key"
              >
                {{ label }} ({{ { upcoming: upcomingMeetings.length, past: pastMeetings.length, all: meetings.length }[key] }})
              </button>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">
              Showing <strong class="font-semibold text-slate-900 dark:text-white">{{ filteredMeetings.length }}</strong> of {{ meetings.length }} meetings
            </p>
          </div>

          <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 md:grid-cols-4">
            <Input v-model="searchQuery" type="search" label="Search title or venue" placeholder="Search" />
            <Select v-model="selectedYear" label="Year" :options="yearOptions" />
            <Input v-model="startDate" type="date" label="From" />
            <div class="flex items-end gap-2">
              <Input v-model="endDate" class="flex-1" type="date" label="To" />
              <Button v-if="searchQuery || selectedYear !== 'all' || startDate || endDate" variant="ghost" @click="clearFilters">Reset</Button>
            </div>
          </div>
        </div>
      </Card>

      <div v-if="filteredMeetings.length" class="space-y-4">
        <Card v-for="meeting in filteredMeetings" :key="meeting.id">
          <div class="flex flex-col justify-between gap-6 md:flex-row md:items-center">
            <div class="min-w-0 space-y-2">
              <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                {{ meeting.title && !meeting.title.includes('Regular Meeting No.') ? meeting.title : 'Meeting - ' + formatDate(meeting.meeting_date, { month: 'long' }) }} at {{ formatTime(meeting.starts_at) }}
              </h3>
              <div class="flex flex-wrap items-center gap-2">
                <Badge :variant="meeting.status === 'published' ? 'success' : 'warning'" class="capitalize">{{ meeting.status }}</Badge>
                <Badge>{{ meeting.dining_count }} dining</Badge>
                <Badge>{{ meeting.apologies_count }} apologies</Badge>
                <Badge>{{ meeting.visitors_count || 0 }} visitors</Badge>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 md:shrink-0">
              <Button
                v-if="String(meeting.meeting_date).split('T')[0] <= todayStr"
                variant="secondary"
                size="sm"
                :href="route('admin.meetings.financial_return.show', { clubSlug: club.slug, id: meeting.id })"
                title="Meeting financial return and dining calculator"
              >
                Financial return
              </Button>
              <Button variant="secondary" size="sm" :href="route('admin.meetings.edit', { clubSlug: club.slug, id: meeting.id })">Edit summons</Button>
              <Button variant="secondary" size="sm" :href="route('admin.meetings.dining', { clubSlug: club.slug, id: meeting.id })" title="Dining list and payments">Dining</Button>
              <Button size="sm" :href="route('admin.meetings.show', { clubSlug: club.slug, id: meeting.id })">Secretary dashboard</Button>
              <Button variant="secondary" size="sm" @click="duplicateMeeting(meeting.id)">Duplicate</Button>
              <Button variant="secondary" size="sm" @click="meetingToDelete = meeting">Delete</Button>
            </div>
          </div>
        </Card>
      </div>

      <Card v-else-if="meetings.length" class="text-center">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">No meetings match these filters</h3>
        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">Try switching tabs, clearing the search, or resetting the dates.</p>
        <Button class="mt-4" variant="secondary" @click="clearFilters">Reset all filters</Button>
      </Card>

      <Card v-else class="text-center">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">No meetings scheduled yet</h3>
        <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">Use the rule-based recurring engine to calculate and generate your annual meeting dates, or create a single meeting summons by hand.</p>
        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
          <Button :href="route('admin.meetings.create', { clubSlug: club.slug })">Add a meeting</Button>
          <Button variant="secondary" @click="showSeasonModal = true">Generate season meetings</Button>
        </div>
      </Card>
    </div>

    <Modal :open="showSeasonModal" title="Generate season meeting rules" subtitle="Calculate exact dates using positional weekdays for active months." size="lg" @close="showSeasonModal = false">
      <form id="season-form" class="space-y-4" @submit.prevent="generateSeason">
        <div class="grid grid-cols-2 gap-4">
          <Input v-model.number="seasonForm.year" type="number" min="2025" max="2035" required label="Masonic or season year" :error="seasonForm.errors.year" />
          <Select v-model="seasonForm.occurrence" label="Occurrence" :options="OCCURRENCE_OPTIONS" :error="seasonForm.errors.occurrence" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <Select v-model="seasonForm.day_of_week" label="Day of the week" :options="DAY_OPTIONS" :error="seasonForm.errors.day_of_week" />
          <Input v-model="seasonForm.starts_at" placeholder="18:30" required label="Meeting start time" :error="seasonForm.errors.starts_at" />
        </div>
        <Input v-model="seasonForm.rehearsal_starts_at" placeholder="17:30" label="Rehearsal start time" hint="Optional." :error="seasonForm.errors.rehearsal_starts_at" />

        <fieldset>
          <legend class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Active meeting months (recess skipped)</legend>
          <div class="grid grid-cols-4 gap-2 text-sm text-slate-700 dark:text-slate-200">
            <label v-for="(mName, idx) in MONTH_NAMES" :key="idx" class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 p-2 dark:border-slate-800 dark:bg-slate-800/50">
              <input v-model="seasonForm.active_months" type="checkbox" :value="idx + 1" class="h-4 w-4 rounded accent-blue-600" />
              <span>{{ mName }}</span>
            </label>
          </div>
          <p v-if="seasonForm.errors.active_months" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ seasonForm.errors.active_months }}</p>
        </fieldset>
      </form>
      <template #footer>
        <Button variant="secondary" @click="showSeasonModal = false">Cancel</Button>
        <Button type="submit" form="season-form" :loading="seasonForm.processing">Calculate and create dates</Button>
      </template>
    </Modal>

    <ConfirmDialog :open="Boolean(meetingToDelete)" title="Delete this meeting?" confirm-label="Delete meeting" :processing="deleting" @confirm="deleteMeeting" @close="meetingToDelete = null">
      This meeting record will be removed.
    </ConfirmDialog>
  </AdminLayout>
</template>
