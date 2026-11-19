<script setup>
// Where the lodge's members have visited in the last year, from the visits they record themselves.
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';

defineProps({
  club: { type: Object, required: true },
  visits: { type: Array, required: true },
  visitors: { type: Number, required: true },
});
</script>

<template>
  <AdminLayout title="Visiting" :club="club" active-tab="meetings">
    <div class="mx-auto max-w-5xl space-y-5">
      <Card>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Visiting</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Visits members have recorded in the last 12 months: {{ visits.length }} by {{ visitors }} {{ visitors === 1 ? 'member' : 'members' }}. Members add them under My lodges.
            </p>
          </div>
          <Button variant="secondary" size="sm" :href="route('admin.meetings.attendance', { clubSlug: club.slug })">Attendance</Button>
        </div>
      </Card>

      <Card v-if="!visits.length">
        <p class="text-sm text-slate-600 dark:text-slate-300">No member has recorded a visit yet.</p>
      </Card>

      <Card v-else padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm text-slate-700 dark:text-slate-200">
            <thead class="bg-slate-50 text-xs text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
              <tr>
                <th scope="col" class="px-4 py-2 font-medium">Date</th>
                <th scope="col" class="px-4 py-2 font-medium">Member</th>
                <th scope="col" class="px-4 py-2 font-medium">Lodge visited</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="visit in visits" :key="visit.id">
                <td class="whitespace-nowrap px-4 py-2">{{ visit.visited_on }}</td>
                <td class="px-4 py-2 font-medium text-slate-900 dark:text-white">{{ visit.member }}</td>
                <td class="px-4 py-2">
                  <Link v-if="visit.lodge_slug" :href="route('lodges.show', visit.lodge_slug)" class="text-blue-600 hover:underline dark:text-blue-400">{{ visit.lodge }}</Link>
                  <span v-else>{{ visit.lodge }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  </AdminLayout>
</template>
