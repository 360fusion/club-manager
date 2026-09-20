<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  event: Object,
  attendees: Array,
});

const searchQuery = ref('');

const filteredAttendees = computed(() => {
  if (!searchQuery.value.trim()) return props.attendees;
  const q = searchQuery.value.toLowerCase();
  return props.attendees.filter(a => a.name.toLowerCase().includes(q) || a.email.toLowerCase().includes(q));
});

const checkedInCount = computed(() => {
  return props.attendees.filter(a => a.checked_in_at).length;
});

const toggleCheckIn = (userId, isCheckedIn) => {
  router.post(route('admin.events.checkin.store', { clubSlug: props.club.slug, id: props.event.id }), {
    user_id: userId,
    action: isCheckedIn ? 'undo' : 'checkin',
  }, {
    preserveScroll: true,
  });
};
</script>

<template>
  <AdminLayout title="Attendance Check-In Console" :club="club" active-tab="events">
    
    <div class="space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
              Live Attendance Roster
            </span>
            <span class="text-xs text-slate-400">📍 {{ event.location }}</span>
          </div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white mt-1">{{ event.title }}</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">🕒 {{ event.starts_at }}</p>
        </div>

        <Link :href="route('admin.events.index', { clubSlug: club.slug })" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl transition-all self-start sm:self-auto">
          &larr; Back to Events
        </Link>
      </div>

      <!-- Live Attendance Metrics Bar -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-1">
          <div class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total RSVPs</div>
          <div class="text-2xl font-black text-slate-900 dark:text-white">{{ attendees.length }}</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-1">
          <div class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Checked In Attendees</div>
          <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ checkedInCount }}</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-1">
          <div class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Turnout Rate</div>
          <div class="text-2xl font-black text-blue-600 dark:text-blue-400">
            {{ attendees.length > 0 ? round((checkedInCount / attendees.length) * 100, 1) : 0 }}%
          </div>
        </div>
      </div>

      <!-- Attendance Roster Section -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden space-y-4 p-6">
        
        <!-- Search input -->
        <div class="flex items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="🔍 Search attendee by name or email..." 
            class="w-full max-w-md px-4 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
          />
        </div>

        <!-- Roster Table -->
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider">
            <tr>
              <th class="p-3">Attendee Name</th>
              <th class="p-3">Email</th>
              <th class="p-3">RSVP Status</th>
              <th class="p-3">Dining Option</th>
              <th class="p-3">Check-in Status</th>
              <th class="p-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-for="a in filteredAttendees" :key="a.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50/50 transition-colors">
              <td class="p-3 font-bold text-slate-900 dark:text-white">{{ a.name }}</td>
              <td class="p-3 text-slate-500 dark:text-slate-400">{{ a.email }}</td>
              <td class="p-3">
                <span :class="['px-2 py-0.5 rounded text-[10px] font-bold border uppercase', a.attendance_status === 'attending' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-800']">
                  {{ a.attendance_status }}
                </span>
              </td>
              <td class="p-3 text-slate-500 dark:text-slate-400">
                <span v-if="a.attending_dining" class="text-blue-700 dark:text-blue-300 font-semibold">🍽️ Dining Reserved</span>
                <span v-else class="text-slate-400">None</span>
              </td>
              <td class="p-3">
                <span v-if="a.checked_in_at" class="text-emerald-700 dark:text-emerald-300 font-bold flex items-center gap-1">
                  ✓ Checked In @ {{ a.checked_in_at }}
                </span>
                <span v-else class="text-slate-400 font-medium">Not Checked In</span>
              </td>
              <td class="p-3 text-right">
                <button 
                  @click="toggleCheckIn(a.id, !!a.checked_in_at)" 
                  :class="[
                    'px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all',
                    a.checked_in_at 
                      ? 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700' 
                      : 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm'
                  ]"
                >
                  {{ a.checked_in_at ? 'Undo Check-In' : '✓ Check In' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>

      </div>

    </div>

  </AdminLayout>
</template>
