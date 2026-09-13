<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  meeting: Object,
  rsvps: Array,
  stats: Object,
});

const activeTab = ref('all');
const showApologiesModal = ref(false);

const filteredRsvps = computed(() => {
  if (activeTab.value === 'all') return props.rsvps;
  return props.rsvps.filter(r => r.attendance_status === activeTab.value);
});

const publishSummons = () => {
  if (confirm(`Publish summons and issue passwordless RSVP tokens to all ${props.stats.total_members} members?`)) {
    router.post(route('admin.meetings.publish', { clubSlug: props.club.slug, id: props.meeting.id }));
  }
};

const apologiesFormattedText = computed(() => {
  const list = props.rsvps.filter(r => r.attendance_status === 'apologies');
  if (!list.length) return 'No apologies have been received for this meeting.';
  
  const names = list.map(r => r.user ? r.user.name : 'Member').join(', ');
  return `APOLOGIES FOR ABSENCE:\nApologies were received and recorded from: ${names}.`;
});

const copyApologiesText = () => {
  navigator.clipboard.writeText(apologiesFormattedText.value);
  alert('Apologies text copied to clipboard! Ready to paste into meeting minutes.');
};
</script>

<template>
  <AdminLayout title="Secretary Meeting Dashboard" :club="club" active-tab="meetings">
    
    <div class="space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <div class="flex items-center gap-2">
            <span :class="['px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border', meeting.status === 'published' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200']">
              {{ meeting.status }}
            </span>
          </div>
          <h2 class="text-xl font-bold text-slate-900 mt-1">
            {{ meeting.title && !meeting.title.includes('Regular Meeting No.') ? meeting.title : 'Meeting - ' + meeting.meeting_date }} at {{ meeting.starts_at ? meeting.starts_at.substring(0, 5) : '18:30' }}
          </h2>
          <p class="text-xs text-slate-500">📍 {{ meeting.venue }} • Rehearsal: {{ meeting.rehearsal_starts_at }}</p>
        </div>

        <div class="flex items-center gap-3">
          <a :href="route('admin.meetings.pdf', { clubSlug: club.slug, id: meeting.id })" target="_blank" class="px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-xl border border-emerald-200 transition-all flex items-center gap-1">
            🖨️ PDF
          </a>
          <Link :href="route('admin.meetings.edit', { clubSlug: club.slug, id: meeting.id })" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition-all">
            📜 Edit Summons Builder
          </Link>
          <button @click="showApologiesModal = true" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition-all">
            📋 Copy Minutes Apologies
          </button>
          <button v-if="meeting.status !== 'published'" @click="publishSummons" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition-all">
            ✉️ Publish & Dispatch Summons
          </button>
        </div>
      </div>

      <!-- Live KPI Cards Grid -->
      <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Caterer Headcount</div>
          <div class="text-2xl font-black text-indigo-600">{{ stats.total_caterer_headcount }}</div>
          <div class="text-[11px] text-slate-500 font-medium">{{ stats.attending_dining }} Members + {{ stats.guest_meals }} Guests</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Attending (Dining)</div>
          <div class="text-2xl font-black text-emerald-600">{{ stats.attending_dining }}</div>
          <div class="text-[11px] text-slate-500 font-medium">Festive Board confirmed</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Meeting Only</div>
          <div class="text-2xl font-black text-sky-600">{{ stats.attending_meeting_only }}</div>
          <div class="text-[11px] text-slate-500 font-medium">Attending without dining</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Apologies Received</div>
          <div class="text-2xl font-black text-rose-600">{{ stats.apologies }}</div>
          <div class="text-[11px] text-slate-500 font-medium">Formally recorded absence</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Awaiting RSVP</div>
          <div class="text-2xl font-black text-amber-600">{{ stats.awaiting }}</div>
          <div class="text-[11px] text-slate-500 font-medium">Pending token responses</div>
        </div>
      </div>

      <!-- Filter Tabs & RSVP Grid -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
        <div class="flex items-center gap-2 border-b border-slate-100 pb-3 overflow-x-auto">
          <button @click="activeTab = 'all'" :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all', activeTab === 'all' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100']">
            All Responses ({{ rsvps.length }})
          </button>
          <button @click="activeTab = 'attending_dining'" :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all', activeTab === 'attending_dining' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100']">
            Attending Dining ({{ stats.attending_dining }})
          </button>
          <button @click="activeTab = 'attending_meeting_only'" :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all', activeTab === 'attending_meeting_only' ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-100']">
            Meeting Only ({{ stats.attending_meeting_only }})
          </button>
          <button @click="activeTab = 'apologies'" :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all', activeTab === 'apologies' ? 'bg-rose-600 text-white' : 'text-slate-600 hover:bg-slate-100']">
            Apologies ({{ stats.apologies }})
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-xs text-left text-slate-700">
            <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[10px] tracking-wider">
              <tr>
                <th class="p-3">Member Name</th>
                <th class="p-3">Attendance</th>
                <th class="p-3">Dietary Requirements</th>
                <th class="p-3">Registered Visitors / Guests</th>
                <th class="p-3">Payment Ref</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="r in filteredRsvps" :key="r.id" class="hover:bg-slate-50/80 transition-all">
                <td class="p-3 font-bold text-slate-900">{{ r.user ? r.user.name : 'Member' }}</td>
                <td class="p-3">
                  <span :class="['px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border', r.attendance_status === 'attending_dining' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : r.attendance_status === 'apologies' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-sky-50 text-sky-700 border-sky-200']">
                    {{ r.attendance_status.replace('_', ' ') }}
                  </span>
                </td>
                <td class="p-3 text-slate-600">{{ r.dietary_requirements || 'Standard' }}</td>
                <td class="p-3">
                  <div v-if="r.guests?.length" class="space-y-1">
                    <div v-for="g in r.guests" :key="g.id" class="text-[11px] font-medium text-slate-800">
                      👤 {{ g.guest_name }} <span class="text-slate-400">({{ g.home_club_lodge || 'Visitor' }})</span>
                    </div>
                  </div>
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td class="p-3 font-mono text-[11px] text-indigo-600">{{ r.payment_reference || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Caterer Breakdown Drawer -->
      <div v-if="stats.dietary_constraints?.length" class="bg-amber-50 border border-amber-200 rounded-2xl p-6 space-y-3">
        <h3 class="text-sm font-bold text-amber-900 flex items-center gap-2">
          <span>🍽️ Itemized Caterer Dietary Breakdown</span>
          <span class="px-2 py-0.5 bg-amber-200/80 text-amber-800 text-[10px] font-bold rounded-full">{{ stats.dietary_constraints.length }} Requests</span>
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          <div v-for="(d, idx) in stats.dietary_constraints" :key="idx" class="bg-white p-3 rounded-xl border border-amber-200 text-xs">
            <div class="font-bold text-slate-900">{{ d.person }}</div>
            <div class="text-amber-700 font-semibold mt-0.5">⚠️ {{ d.requirement }}</div>
          </div>
        </div>
      </div>

      <!-- Apologies Modal for Minutes -->
      <div v-if="showApologiesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-900">Formatted Minutes Apologies Export</h3>
            <button @click="showApologiesModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
          </div>

          <textarea :value="apologiesFormattedText" readonly rows="5" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono text-slate-800 focus:outline-none"></textarea>

          <div class="flex justify-end gap-3 pt-2">
            <button @click="showApologiesModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl">Close</button>
            <button @click="copyApologiesText" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md">
              📋 Copy to Clipboard
            </button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
