<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  metrics: Object,
  upcomingMeetings: Array,
});

const inviteCopied = ref(false);

const copyInviteLink = () => {
  const url = `${window.location.origin}/register?club=${props.club.slug}`;
  navigator.clipboard.writeText(url);
  inviteCopied.value = true;
  setTimeout(() => {
    inviteCopied.value = false;
  }, 2000);
};
</script>

<template>
  <AdminLayout title="Dashboard" :club="club" active-tab="dashboard">
    
    <!-- Top Big Stat Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      
      <!-- 1. Active Users Card -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-col justify-between space-y-3">
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active users</div>
        <div class="text-4xl font-black text-slate-900 dark:text-white font-sans tracking-tight">
          {{ metrics.total_members || 300 }}
        </div>
        <div class="text-xs font-semibold text-slate-400">
          +100.00
        </div>
      </div>

      <!-- 2. Total Sales Card -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-col justify-between space-y-3">
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total sales</div>
        <div class="text-4xl font-black text-slate-900 dark:text-white font-sans tracking-tight">
          {{ $cs }}{{ metrics.monthly_dues_est || '2,500' }}
        </div>
        <div class="text-xs font-semibold text-slate-400">
          +20.00
        </div>
      </div>

    </div>

    <!-- Second Row Metric Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      
      <!-- Metric 1: Members -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-2">
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Active Members</div>
        <div class="text-2xl font-extrabold text-slate-900 dark:text-white">
          {{ metrics.total_members || 0 }}
        </div>
      </div>

      <!-- Metric 2: Pending users -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-2">
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Pending Users</div>
        <div class="text-2xl font-extrabold text-slate-900 dark:text-white">
          {{ metrics.pending_users_count || 0 }}
        </div>
      </div>

      <!-- Metric 3: Open invoices -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-2">
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">Open Invoices &amp; Bills</div>
        <div class="text-2xl font-extrabold text-slate-900 dark:text-white">
          {{ metrics.open_invoices_count || 0 }}
        </div>
      </div>

    </div>

    <!-- Bottom Section Dual Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      
      <!-- Left Panel: Today Agenda -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Today</h2>

        <div class="space-y-6 divide-y divide-slate-100 dark:divide-slate-800">
          
          <!-- Item 1: Club members meeting -->
          <div class="flex items-start gap-4 pt-2 first:pt-0">
            <div class="text-xs font-semibold text-slate-400 w-20 pt-1 space-y-1 text-right">
              <div>08:00am</div>
              <div>10:00am</div>
            </div>
            <div class="w-1 bg-blue-500 rounded-full self-stretch min-h-[48px]"></div>
            <div class="space-y-1.5 flex-1">
              <div class="text-sm font-bold text-slate-800 dark:text-slate-100">Club members meeting</div>
              <div class="flex items-center gap-2">
                <span class="w-10 h-3 bg-slate-200 dark:bg-slate-700 rounded-full inline-block"></span>
                <span class="w-16 h-3 bg-slate-200 dark:bg-slate-700 rounded-full inline-block"></span>
              </div>
            </div>
          </div>

          <!-- Item 2: Cheer & dance -->
          <div class="flex items-start gap-4 pt-6">
            <div class="text-xs font-semibold text-slate-400 w-20 pt-1 space-y-1 text-right">
              <div>11:00am</div>
              <div>01:00pm</div>
            </div>
            <div class="w-1 bg-rose-500 rounded-full self-stretch min-h-[48px]"></div>
            <div class="space-y-1.5 flex-1">
              <div class="text-sm font-bold text-slate-800 dark:text-slate-100">Cheer & dance</div>
              <div class="flex items-center gap-2">
                <span class="w-8 h-3 bg-slate-200 dark:bg-slate-700 rounded-full inline-block"></span>
                <span class="w-12 h-3 bg-slate-200 dark:bg-slate-700 rounded-full inline-block"></span>
              </div>
            </div>
          </div>

          <!-- Item 3: Conditioning -->
          <div class="flex items-start gap-4 pt-6">
            <div class="text-xs font-semibold text-slate-400 w-20 pt-1 space-y-1 text-right">
              <div>03:00pm</div>
              <div>08:00pm</div>
            </div>
            <div class="w-1 bg-amber-500 rounded-full self-stretch min-h-[48px]"></div>
            <div class="space-y-1.5 flex-1">
              <div class="text-sm font-bold text-slate-800 dark:text-slate-100">Conditioning</div>
              <div class="flex items-center gap-2">
                <span class="w-12 h-3 bg-slate-200 dark:bg-slate-700 rounded-full inline-block"></span>
                <span class="w-8 h-3 bg-slate-200 dark:bg-slate-700 rounded-full inline-block"></span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Right Panel: Meetings Summary -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Meetings</h2>
          <Link :href="route('admin.meetings.index', { clubSlug: club.slug })" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 flex items-center gap-1">
            View all meetings →
          </Link>
        </div>

        <div v-if="upcomingMeetings && upcomingMeetings.length" class="space-y-3">
          <div v-for="m in upcomingMeetings" :key="m.id" class="p-4 bg-slate-50/70 dark:bg-slate-800/50/70 hover:bg-slate-100/90 dark:hover:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 transition-all space-y-2.5">
            <div class="flex items-center justify-between gap-2">
              <Link :href="route('admin.meetings.show', { clubSlug: club.slug, id: m.id })" title="Open Secretary Dashboard" aria-label="Open Secretary Dashboard" class="font-bold text-slate-900 dark:text-white text-sm hover:text-blue-600 dark:hover:text-blue-400 transition-colors flex items-center gap-2 group">
                <span class="w-7 h-7 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 font-bold flex items-center justify-center text-xs border border-blue-200/60 dark:border-blue-800/60 group-hover:bg-blue-600 group-hover:text-white transition-all">
                  📜
                </span>
                <span class="hover:underline">{{ m.title }}</span>
              </Link>
              <span :class="['px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border', m.status === 'published' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60' : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60']">
                {{ m.status }}
              </span>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400 font-medium pt-1 border-t border-slate-200/50 dark:border-slate-800/50">
              <div class="flex items-center gap-3">
                <span class="flex items-center gap-1">📅 {{ m.meeting_date }} at {{ m.starts_at }}</span>
                <span v-if="m.venue" class="hidden sm:flex items-center gap-1 text-slate-400">📍 {{ m.venue }}</span>
              </div>
              <div class="flex items-center gap-2 text-[11px] font-bold">
                <span class="text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-lg border border-emerald-200 dark:border-emerald-800/60">🍽️ {{ m.dining_count }} Dining</span>
                <span class="text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/40 px-2 py-0.5 rounded-lg border border-rose-200 dark:border-rose-800/60">✉️ {{ m.apologies_count }} Apologies</span>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="p-8 text-center text-xs text-slate-400 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl space-y-3">
          <div class="text-2xl">📜</div>
          <div class="font-bold text-slate-700 dark:text-slate-200 text-sm">No Upcoming Meetings Scheduled</div>
          <p class="text-slate-400 max-w-xs mx-auto">Generate season schedules or create individual summonses for your club.</p>
          <Link :href="route('admin.meetings.create', { clubSlug: club.slug })" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-md transition-all">
            📜 + Add Meeting
          </Link>
        </div>
      </div>

    </div>

  </AdminLayout>
</template>
