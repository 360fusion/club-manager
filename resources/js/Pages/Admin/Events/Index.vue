<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  events: Array,
});

const deleteEvent = (eventId) => {
  if (confirm('Are you sure you want to delete this event?')) {
    router.delete(route('admin.events.destroy', { clubSlug: props.club.slug, id: eventId }));
  }
};
</script>

<template>
  <AdminLayout title="Events" :club="club" active-tab="events">
    
    <div class="space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900">Manage Club Events & Ticketing</h2>
          <p class="text-xs text-slate-500 mt-1">Create and manage event ticketing tiers, promo codes, and 3-course dining menus.</p>
        </div>
        <Link :href="route('admin.events.create', { clubSlug: club.slug })" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all text-center">
          + Create New Event
        </Link>
      </div>

      <!-- Events List Grid -->
      <div v-if="events.length" class="grid grid-cols-1 gap-4">
        <div v-for="event in events" :key="event.id" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 hover:border-slate-300 transition-all flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-3">
            <div class="flex items-center gap-2">
              <span :class="['px-2.5 py-0.5 rounded text-xs font-bold border uppercase tracking-wider', event.status === 'upcoming' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200']">
                {{ event.status }}
              </span>
              <span v-if="event.ticket_tiers?.length" class="px-2 py-0.5 rounded text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                🎟️ {{ event.ticket_tiers.length }} Tiers
              </span>
              <span v-if="event.has_dining" class="px-2 py-0.5 rounded text-xs font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                🍽️ 3-Course Dining
              </span>
              <span v-if="event.booking_cutoff_days" class="px-2 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                ⏰ Cutoff: {{ event.booking_cutoff_days }} days before
              </span>
            </div>

            <h3 class="text-lg font-bold text-slate-900">{{ event.title }}</h3>
            <p class="text-xs text-slate-500">📍 {{ event.location }} • 🕒 {{ event.starts_at }}</p>
          </div>

          <div class="flex items-center gap-3 self-start md:self-auto">
            <Link :href="route('admin.events.subscribers', { clubSlug: club.slug, id: event.id })" class="px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-semibold rounded-xl border border-blue-200 transition-all">
              👥 View Subscriptions ({{ event.attendees_count || 0 }})
            </Link>
            <Link :href="route('admin.events.edit', { clubSlug: club.slug, id: event.id })" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition-all">
              ✏️ Edit Event & Tiers
            </Link>
            <button @click="deleteEvent(event.id)" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold rounded-xl border border-rose-200 transition-all">
              🗑️ Delete
            </button>
          </div>
        </div>
      </div>

      <div v-else class="bg-white rounded-2xl p-12 text-center shadow-sm border border-slate-200/80 space-y-4">
        <p class="text-slate-500 text-sm">No events found for this club.</p>
        <Link :href="route('admin.events.create', { clubSlug: club.slug })" class="inline-block px-5 py-2.5 bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20">
          Create First Event
        </Link>
      </div>

    </div>

  </AdminLayout>
</template>
