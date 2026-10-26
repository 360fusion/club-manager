<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import { formatEventTime } from '@/Utils/localDateTime';

const props = defineProps({
  club: Object,
  events: Array,
});

const eventToDelete = ref(null);
const deleting = ref(false);

const deleteEvent = () => {
  deleting.value = true;
  router.delete(route('admin.events.destroy', { clubSlug: props.club.slug, id: eventToDelete.value.id }), {
    onFinish: () => {
      deleting.value = false;
      eventToDelete.value = null;
    },
  });
};
</script>

<template>
  <AdminLayout title="Events" :club="club" active-tab="events">
    <div class="space-y-6">
      <Card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Events and tickets</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Create events with ticket types and dining menus, and see who has booked.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button variant="secondary" size="sm" :href="route('admin.payment_options.index', { clubSlug: club.slug })">Payment options</Button>
            <Button variant="secondary" size="sm" :href="route('admin.email_templates.index', { clubSlug: club.slug })">Email wording</Button>
            <Button size="sm" :href="route('admin.events.create', { clubSlug: club.slug })">Create an event</Button>
          </div>
        </div>
      </Card>

      <div v-if="events.length" class="space-y-4">
        <Card v-for="event in events" :key="event.id">
          <div class="flex flex-col justify-between gap-6 md:flex-row md:items-center">
            <div class="min-w-0 space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <Badge :variant="event.status === 'upcoming' ? 'success' : 'neutral'" class="capitalize">{{ event.status }}</Badge>
                <Badge v-if="event.ticket_tiers?.length" variant="info">{{ event.ticket_tiers.length }} ticket types</Badge>
                <Badge v-if="event.has_dining" variant="info">Dining</Badge>
                <Badge v-if="event.booking_cutoff_days" variant="warning">Booking closes {{ event.booking_cutoff_days }} days before</Badge>
              </div>
              <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ event.title }}</h3>
              <p class="text-sm text-slate-500 dark:text-slate-400">{{ [event.location, formatEventTime(event.starts_at)].filter(Boolean).join(' · ') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 md:shrink-0">
              <Button variant="secondary" size="sm" :href="route('admin.events.checkin', { clubSlug: club.slug, id: event.id })">Check-in</Button>
              <Button variant="secondary" size="sm" :href="route('admin.events.subscribers', { clubSlug: club.slug, id: event.id })">Bookings ({{ event.attendees_count || 0 }})</Button>
              <Button variant="secondary" size="sm" :href="route('admin.events.edit', { clubSlug: club.slug, id: event.id })">Edit</Button>
              <Button variant="secondary" size="sm" @click="eventToDelete = event">Delete</Button>
            </div>
          </div>
        </Card>
      </div>

      <Card v-else class="text-center">
        <p class="text-sm text-slate-500 dark:text-slate-400">No events yet.</p>
        <Button class="mt-4" :href="route('admin.events.create', { clubSlug: club.slug })">Create your first event</Button>
      </Card>
    </div>

    <ConfirmDialog :open="Boolean(eventToDelete)" title="Delete this event?" confirm-label="Delete event" :processing="deleting" @confirm="deleteEvent" @close="eventToDelete = null">
      "{{ eventToDelete?.title }}" will be deleted.
    </ConfirmDialog>
  </AdminLayout>
</template>
