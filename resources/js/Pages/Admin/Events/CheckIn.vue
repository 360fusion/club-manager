<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import { formatEventTime } from '@/Utils/localDateTime';

const props = defineProps({
  club: Object,
  event: Object,
  attendees: Array,
});

const searchQuery = ref('');

const filteredAttendees = computed(() => {
  if (!searchQuery.value.trim()) return props.attendees;
  const q = searchQuery.value.toLowerCase();
  return props.attendees.filter(a => a.name.toLowerCase().includes(q) || (a.email || '').toLowerCase().includes(q));
});

const checkedInCount = computed(() => {
  return props.attendees.filter(a => a.checked_in_at).length;
});

const turnout = computed(() => (props.attendees.length > 0 ? Math.round((checkedInCount.value / props.attendees.length) * 1000) / 10 : 0));

const walkInName = ref('');

const addWalkIn = () => {
  if (!walkInName.value.trim()) return;
  router.post(route('admin.events.checkin.walk_in', { clubSlug: props.club.slug, id: props.event.id }), { name: walkInName.value }, {
    preserveScroll: true,
    onSuccess: () => { walkInName.value = ''; },
  });
};

const toggleCheckIn = (attendeeId, isCheckedIn) => {
  router.post(route('admin.events.checkin.store', { clubSlug: props.club.slug, id: props.event.id }), {
    attendee_id: attendeeId,
    action: isCheckedIn ? 'undo' : 'checkin',
  }, {
    preserveScroll: true,
  });
};
</script>

<template>
  <AdminLayout title="Check-in" :club="club" active-tab="events">
    <div class="space-y-6">
      <Card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ event.title }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ [event.location, formatEventTime(event.starts_at)].filter(Boolean).join(' · ') }}</p>
          </div>
          <Button variant="secondary" size="sm" :href="route('admin.events.index', { clubSlug: club.slug })">Back to events</Button>
        </div>
      </Card>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard label="Booked in" :value="attendees.length" />
        <StatCard label="Checked in" :value="checkedInCount" />
        <StatCard label="Turnout" :value="`${turnout}%`" />
      </div>

      <Card>
        <div class="space-y-4">
          <form class="flex flex-wrap items-end gap-2 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50" @submit.prevent="addWalkIn">
            <Input v-model="walkInName" class="min-w-[14rem] flex-1" label="Walk-in" maxlength="150" placeholder="Name of someone without a booking" />
            <Button type="submit" size="sm">Add and check in</Button>
          </form>

          <Input v-model="searchQuery" class="max-w-md" type="search" aria-label="Search people" placeholder="Search by name or email" />

          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                  <th scope="col" class="p-3 font-medium">Name</th>
                  <th scope="col" class="p-3 font-medium">Email</th>
                  <th scope="col" class="p-3 font-medium">Booking</th>
                  <th scope="col" class="p-3 font-medium">Dining</th>
                  <th scope="col" class="p-3 font-medium">Checked in</th>
                  <th scope="col" class="p-3 text-right font-medium"><span class="sr-only">Action</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="a in filteredAttendees" :key="a.id">
                  <td class="p-3">
                    <span class="font-medium text-slate-900 dark:text-white">{{ a.name }}</span>
                    <Badge v-if="a.is_guest" variant="warning" class="ml-1">Guest</Badge>
                    <div v-if="a.booked_by" class="text-xs text-slate-500 dark:text-slate-400">Guest of {{ a.booked_by }}</div>
                    <div v-if="a.dietary_requirements" class="text-xs font-medium text-rose-600 dark:text-rose-400">Dietary: {{ a.dietary_requirements }}</div>
                  </td>
                  <td class="p-3 text-slate-500 dark:text-slate-400">{{ a.email }}</td>
                  <td class="p-3">
                    <Badge :variant="a.attendance_status === 'attending' ? 'success' : 'neutral'" class="capitalize">{{ a.attendance_status }}</Badge>
                  </td>
                  <td class="p-3 text-slate-600 dark:text-slate-300">
                    <template v-if="a.attending_dining">{{ [a.meal?.starter, a.meal?.main, a.meal?.dessert].filter(Boolean).join(' · ') || 'Dining' }}</template>
                    <span v-else class="text-slate-400">None</span>
                  </td>
                  <td class="p-3">
                    <span v-if="a.checked_in_at" class="font-medium text-emerald-700 dark:text-emerald-300">Yes, {{ a.checked_in_at.slice(0, 5) }}</span>
                    <span v-else class="text-slate-500 dark:text-slate-400">Not yet</span>
                  </td>
                  <td class="p-3 text-right">
                    <Button :variant="a.checked_in_at ? 'secondary' : 'primary'" size="sm" @click="toggleCheckIn(a.id, !!a.checked_in_at)">
                      {{ a.checked_in_at ? 'Undo' : 'Check in' }}
                    </Button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </Card>
    </div>
  </AdminLayout>
</template>
