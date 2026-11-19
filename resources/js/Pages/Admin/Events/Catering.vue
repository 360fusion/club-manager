<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import CateringCourse from '@/Components/Events/Organiser/CateringCourse.vue';

const props = defineProps({
  club: Object,
  event: Object,
  summary: Object,
});

const COURSES = [['starter', 'Starter'], ['main', 'Main'], ['dessert', 'Dessert']];

const split = computed(() => {
  const { members, guests, visitors } = props.summary.split;
  return [[members, 'member', 'members'], [guests, 'guest', 'guests'], [visitors, 'visitor', 'visitors']]
    .filter(([n]) => n > 0)
    .map(([n, one, many]) => `${n} ${n === 1 ? one : many}`)
    .join(' · ');
});

const flags = computed(() => [['vegetarian', 'vegetarian'], ['vegan', 'vegan'], ['gluten_free', 'gluten free']]
  .filter(([key]) => props.summary.flags[key] > 0)
  .map(([key, label]) => `${props.summary.flags[key]} ${label}`)
  .join(' · '));
</script>

<template>
  <AdminLayout title="Catering" :club="club" active-tab="events">
    <Head :title="`Catering - ${event.title}`" />

    <div class="mx-auto max-w-2xl space-y-3 pb-28 sm:pb-6">
      <div class="flex items-center gap-2">
        <Button variant="ghost" size="sm" :href="route('admin.events.checkin', { clubSlug: club.slug, id: event.id })" aria-label="Back to event night">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
        </Button>
        <div class="min-w-0">
          <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Catering</h1>
          <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ [event.title, event.starts_at].filter(Boolean).join(' · ') }}</p>
        </div>
      </div>

      <Card padding="sm">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Covers</p>
            <p class="text-3xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ summary.covers }}</p>
          </div>
          <div class="space-y-0.5 text-right text-xs text-slate-500 dark:text-slate-400">
            <p v-if="split">{{ split }}</p>
            <p v-if="summary.not_dining">{{ summary.not_dining }} booked but not dining</p>
            <p v-if="event.closes_at">{{ event.is_closed ? 'Bookings closed' : 'Bookings close' }} {{ event.closes_at }}</p>
          </div>
        </div>
        <p v-if="flags" class="mt-2 text-xs text-slate-500 dark:text-slate-400">Dishes chosen: {{ flags }}</p>
      </Card>

      <template v-for="[key, label] in COURSES" :key="key">
        <CateringCourse v-if="summary.courses[key]?.length" :label="label" :dishes="summary.courses[key]" :covers="summary.covers" />
      </template>

      <Card v-if="!summary.covers" padding="sm">
        <p class="text-sm text-slate-500 dark:text-slate-400">No one is down for dinner yet.</p>
      </Card>

      <Card v-if="summary.dietary.length" padding="sm">
        <div class="mb-2 flex items-center justify-between gap-2">
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Dietary notes</p>
          <Badge variant="warning">{{ summary.dietary.length }} {{ summary.dietary.length === 1 ? 'note' : 'notes' }}</Badge>
        </div>
        <ul class="space-y-1 text-sm text-slate-700 dark:text-slate-300">
          <li v-for="note in summary.dietary" :key="note.name + note.notes"><span class="font-medium text-slate-900 dark:text-white">{{ note.name }}:</span> {{ note.notes }}</li>
        </ul>
      </Card>

      <div class="fixed inset-x-0 bottom-0 z-30 flex gap-2 border-t border-slate-200 bg-white px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:static sm:border-0 sm:bg-transparent sm:p-0 dark:border-slate-800 dark:bg-slate-900 sm:dark:bg-transparent">
        <Button variant="secondary" class="flex-1 sm:flex-none" external :href="route('admin.events.catering', { clubSlug: club.slug, id: event.id })">Print version</Button>
        <Button class="flex-1 sm:flex-none" external :href="route('admin.events.catering', { clubSlug: club.slug, id: event.id, download: 1 })">Download PDF</Button>
      </div>
    </div>
  </AdminLayout>
</template>
