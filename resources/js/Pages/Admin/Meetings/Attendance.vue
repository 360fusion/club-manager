<script setup>
// Each member's attendance over the last year, from the signing-in book, longest absent first: the Almoner's list.
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';

const props = defineProps({
  club: { type: Object, required: true },
  attendance: { type: Object, required: true },
});

const NOT_SEEN_AFTER = 3;
const notSeen = computed(() => props.attendance.members.filter((member) => member.missed_in_a_row >= NOT_SEEN_AFTER));
</script>

<template>
  <AdminLayout title="Attendance" :club="club" active-tab="meetings">
    <div class="mx-auto max-w-5xl space-y-5">
      <Card>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Attendance</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              The last 12 months, from the signing-in book: {{ attendance.meetings }} {{ attendance.meetings === 1 ? 'meeting' : 'meetings' }} where it was used.
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button variant="secondary" size="sm" :href="route('admin.meetings.visiting', { clubSlug: club.slug })">Visiting</Button>
            <Button variant="secondary" size="sm" :href="route('admin.meetings.index', { clubSlug: club.slug })">All meetings</Button>
          </div>
        </div>
      </Card>

      <Card v-if="!attendance.meetings">
        <p class="text-sm text-slate-600 dark:text-slate-300">No meeting has used the signing-in book yet. Open a meeting and choose Signing-in book to tick people in at the door.</p>
      </Card>

      <template v-else>
        <Card v-if="notSeen.length">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Not seen in the last {{ NOT_SEEN_AFTER }} meetings</h3>
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Worth a call from the Almoner or a friend in the lodge.</p>
          <ul class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
            <li v-for="member in notSeen" :key="member.user_id" class="flex flex-wrap items-center justify-between gap-2 py-2">
              <span class="font-medium text-slate-800 dark:text-slate-100">{{ member.name }}</span>
              <span class="text-xs text-slate-500 dark:text-slate-400">
                Last here {{ member.last_attended || 'not in the last year' }}
                <template v-if="member.phone"> · <a :href="`tel:${member.phone}`" class="text-blue-600 hover:underline dark:text-blue-400">{{ member.phone }}</a></template>
                <template v-if="member.email"> · <a :href="`mailto:${member.email}`" class="text-blue-600 hover:underline dark:text-blue-400">Email</a></template>
              </span>
            </li>
          </ul>
        </Card>

        <Card padding="none" class="overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-200">
              <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                  <th scope="col" class="px-4 py-2 font-medium">Member</th>
                  <th scope="col" class="px-4 py-2 font-medium">Attended</th>
                  <th scope="col" class="px-4 py-2 font-medium">Last here</th>
                  <th scope="col" class="px-4 py-2 font-medium">Missed in a row</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="member in attendance.members" :key="member.user_id">
                  <td class="px-4 py-2 font-medium text-slate-900 dark:text-white">{{ member.name }}</td>
                  <td class="px-4 py-2">{{ member.attended }} of {{ attendance.meetings }}</td>
                  <td class="px-4 py-2">{{ member.last_attended || '-' }}</td>
                  <td class="px-4 py-2">
                    <Badge v-if="member.missed_in_a_row >= NOT_SEEN_AFTER" variant="warning">{{ member.missed_in_a_row }}</Badge>
                    <span v-else>{{ member.missed_in_a_row }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </template>
    </div>
  </AdminLayout>
</template>
