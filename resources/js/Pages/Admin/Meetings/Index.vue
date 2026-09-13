<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  meetings: Array,
  recurringRules: Array,
});

const showSeasonModal = ref(false);

const seasonForm = useForm({
  year: 2026,
  occurrence: '3rd',
  day_of_week: 'Tuesday',
  active_months: [1, 2, 3, 4, 5, 10, 11, 12], // 8 active meeting months (skipping recess)
});

const generateSeason = () => {
  seasonForm.post(route('admin.meetings.generate_season', { clubSlug: props.club.slug }), {
    onSuccess: () => {
      showSeasonModal.value = false;
    },
  });
};

const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const cleanStr = String(dateStr).split('T')[0];
  const parts = cleanStr.split('-');
  if (parts.length === 3) {
    const d = new Date(parts[0], parts[1] - 1, parts[2]);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
  }
  return dateStr;
};

const formatTime = (timeStr) => {
  if (!timeStr) return '';
  return timeStr.substring(0, 5);
};

const deleteMeeting = (id) => {
  if (confirm('Are you sure you want to remove this meeting record?')) {
    router.delete(route('admin.meetings.destroy', { clubSlug: props.club.slug, id }));
  }
};
</script>

<template>
  <AdminLayout title="Meetings & Summonses" :club="club" active-tab="meetings">
    
    <div class="space-y-6">
      
      <!-- Top Header & Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900">Meetings & Summons Management</h2>
          <p class="text-xs text-slate-500 mt-1">Generate season schedules using nth-weekday rules, publish summonses, and track passwordless RSVPs.</p>
        </div>
        
        <div class="flex items-center gap-3">
          <button @click="showSeasonModal = true" class="px-4 py-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-200 transition-all">
            ⚡ Generate Season / Masonic Year
          </button>
          <Link :href="route('admin.meetings.create', { clubSlug: club.slug })" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all flex items-center gap-1.5">
            📜 + Create Summons
          </Link>
        </div>
      </div>

      <!-- Meetings Roster Grid -->
      <div v-if="meetings.length" class="grid grid-cols-1 gap-4">
        <div v-for="meeting in meetings" :key="meeting.id" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 hover:border-indigo-200 transition-all flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2">
            <h3 class="text-lg font-bold text-slate-900">
              {{ meeting.title && !meeting.title.includes('Regular Meeting No.') ? meeting.title : 'Meeting - ' + formatDate(meeting.meeting_date) }} at {{ formatTime(meeting.starts_at) }}
            </h3>

            <div class="flex items-center gap-3 text-xs pt-1">
              <span :class="['px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border', meeting.status === 'published' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200']">
                {{ meeting.status }}
              </span>
              <span class="text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">🍽️ {{ meeting.dining_count }} Dining</span>
              <span class="text-rose-700 font-semibold bg-rose-50 px-2 py-0.5 rounded border border-rose-200">✉️ {{ meeting.apologies_count }} Apologies</span>
            </div>
          </div>

          <div class="flex items-center gap-2.5 self-start md:self-auto">
            <Link :href="route('admin.meetings.edit', { clubSlug: club.slug, id: meeting.id })" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition-all">
              📜 Edit Summons
            </Link>
            <Link :href="route('admin.meetings.show', { clubSlug: club.slug, id: meeting.id })" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition-all">
              📊 Secretary Dashboard
            </Link>
            <button @click="deleteMeeting(meeting.id)" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold rounded-xl border border-rose-200 transition-all">
              🗑️
            </button>
          </div>
        </div>
      </div>

      <!-- Blank Slate State -->
      <div v-else class="bg-white rounded-2xl p-12 text-center shadow-sm border border-slate-200/80">
        <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-4">📜</div>
        <h3 class="text-lg font-bold text-slate-900">No Meetings Scheduled Yet</h3>
        <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">Use the rule-based recurring engine to automatically calculate and generate your 8 annual meeting dates for the upcoming season.</p>
        <button @click="showSeasonModal = true" class="mt-6 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition-all">
          ⚡ Generate Season Meetings
        </button>
      </div>

      <!-- Batch Season Generator Modal -->
      <div v-if="showSeasonModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-6">
          <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div>
              <h3 class="text-lg font-bold text-slate-900">Generate Season Meeting Rules</h3>
              <p class="text-xs text-slate-500">Calculate exact dates using positional weekdays for active months.</p>
            </div>
            <button @click="showSeasonModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
          </div>

          <form @submit.prevent="generateSeason" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Masonic / Season Year</label>
                <input v-model="seasonForm.year" type="number" min="2025" max="2035" required class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Occurrence</label>
                <select v-model="seasonForm.occurrence" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                  <option value="1st">1st</option>
                  <option value="2nd">2nd</option>
                  <option value="3rd">3rd</option>
                  <option value="4th">4th</option>
                  <option value="last">Last</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Day of Week</label>
              <select v-model="seasonForm.day_of_week" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs">
                <option value="Monday">Monday</option>
                <option value="Tuesday">Tuesday</option>
                <option value="Wednesday">Wednesday</option>
                <option value="Thursday">Thursday</option>
                <option value="Friday">Friday</option>
                <option value="Saturday">Saturday</option>
                <option value="Sunday">Sunday</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase mb-2">Active Meeting Months (Recess Skipped)</label>
              <div class="grid grid-cols-4 gap-2 text-xs">
                <label v-for="(mName, idx) in ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']" :key="idx" class="flex items-center gap-1.5 p-2 bg-slate-50 rounded-lg border border-slate-200 cursor-pointer">
                  <input type="checkbox" :value="idx + 1" v-model="seasonForm.active_months" class="rounded text-indigo-600" />
                  <span>{{ mName }}</span>
                </label>
              </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
              <button type="button" @click="showSeasonModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl">Cancel</button>
              <button type="submit" :disabled="seasonForm.processing" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md">
                ⚡ Calculate & Create Dates
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
