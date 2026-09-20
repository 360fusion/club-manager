<script setup>
import { Head, Link } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

defineProps({
  metrics: Object,
  clubsByType: Array,
  clubs: Array,
});
</script>

<template>
  <SuperAdminLayout title="System Analytics Dashboard">
    <div class="space-y-8">
      
      <!-- Top Header Banner -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-white via-blue-50 to-blue-50 p-8 rounded-3xl border border-blue-200 shadow-2xl text-slate-900 dark:from-slate-900 dark:via-blue-950 dark:to-blue-950 dark:border-blue-800/60 dark:text-white">
        <div>
          <span class="px-3 py-1 bg-blue-50 text-blue-700 text-[10px] font-black uppercase tracking-wider rounded-full border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/60">
            ⚡ Platform Superadmin Console
          </span>
          <h1 class="text-3xl font-black tracking-tight mt-2">System Analytics &amp; Metrics</h1>
          <p class="text-xs text-slate-700 mt-1 font-medium dark:text-slate-300">Real-time overview of registered clubs, orders, user accounts, and platform volume.</p>
        </div>

        <div class="flex items-center gap-3">
          <Link
            :href="route('superadmin.club_types.index')"
            class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-black text-xs rounded-xl shadow-sm transition flex items-center gap-2"
          >
            <span>⚙️</span>
            <span>Manage Club Types &amp; Presets</span>
          </Link>
        </div>
      </div>

      <!-- KPI Metrics Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white/90 p-6 rounded-3xl border border-slate-200 shadow-lg space-y-2 dark:bg-slate-900/90 dark:border-slate-800">
          <span class="text-[10px] font-extrabold text-blue-600 uppercase tracking-wider block dark:text-blue-400">Registered Clubs</span>
          <span class="text-3xl font-black text-slate-900 block dark:text-white">{{ metrics?.totalClubsCount || 0 }}</span>
          <span class="text-xs text-slate-500 block font-medium dark:text-slate-400">Across all order types</span>
        </div>

        <div class="bg-white/90 p-6 rounded-3xl border border-slate-200 shadow-lg space-y-2 dark:bg-slate-900/90 dark:border-slate-800">
          <span class="text-[10px] font-extrabold text-blue-600 uppercase tracking-wider block dark:text-blue-400">Total User Accounts</span>
          <span class="text-3xl font-black text-slate-900 block dark:text-white">{{ metrics?.totalUsersCount || 0 }}</span>
          <span class="text-xs text-slate-500 block font-medium dark:text-slate-400">Brethren, officers &amp; members</span>
        </div>

        <div class="bg-white/90 p-6 rounded-3xl border border-slate-200 shadow-lg space-y-2 dark:bg-slate-900/90 dark:border-slate-800">
          <span class="text-[10px] font-extrabold text-amber-700 uppercase tracking-wider block dark:text-amber-400">Meeting Summonses</span>
          <span class="text-3xl font-black text-slate-900 block dark:text-white">{{ metrics?.totalMeetingsCount || 0 }}</span>
          <span class="text-xs text-slate-500 block font-medium dark:text-slate-400">Circulars &amp; convocations</span>
        </div>

        <div class="bg-white/90 p-6 rounded-3xl border border-slate-200 shadow-lg space-y-2 dark:bg-slate-900/90 dark:border-slate-800">
          <span class="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider block dark:text-emerald-400">Charity Grants Disbursed</span>
          <span class="text-3xl font-black text-slate-900 block dark:text-white">{{ metrics?.totalGrantsDisbursed || '£0.00' }}</span>
          <span class="text-xs text-slate-500 block font-medium dark:text-slate-400">Total relief chest payouts</span>
        </div>
      </div>

      <!-- Breakdown by Club Type / Order -->
      <div class="bg-white/90 border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900/90 dark:border-slate-800">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-base font-black text-slate-900 flex items-center gap-2 dark:text-white">
              <span>🏛️</span>
              <span>Clubs Breakdown by Order / Type</span>
            </h3>
            <p class="text-xs text-slate-500 mt-0.5 dark:text-slate-400">Click any Masonic Order to view its dedicated specification page, offices, registered clubs, and official website.</p>
          </div>
          <span class="text-xs text-slate-500 font-medium dark:text-slate-400">Click card to view page →</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <Link
            v-for="ct in clubsByType"
            :key="ct.id"
            :href="route('superadmin.club_types.show', ct.id)"
            class="p-4 bg-slate-100 hover:bg-slate-100 rounded-2xl border border-slate-300 hover:border-blue-400 transition-all duration-200 group flex items-center justify-between dark:bg-slate-800 dark:hover:bg-slate-800 dark:border-slate-700"
          >
            <div>
              <div class="font-extrabold text-sm text-slate-900 group-hover:text-blue-700 transition-colors flex items-center gap-1.5 dark:text-white dark:group-hover:text-blue-300">
                <span>{{ ct.name }}</span>
                <span class="text-xs text-blue-600 group-hover:translate-x-0.5 transition-transform dark:text-blue-400">→</span>
              </div>
              <div class="text-[10px] text-blue-600 font-mono mt-0.5 dark:text-blue-400">Code: {{ ct.code }}</div>
            </div>
            <span class="px-3 py-1 bg-blue-50 group-hover:bg-blue-100 text-blue-700 font-black text-xs rounded-full border border-blue-200 dark:bg-blue-950/60 dark:group-hover:bg-blue-900/40 dark:text-blue-300 dark:border-blue-800/60">
              {{ ct.count }}
            </span>
          </Link>
        </div>
      </div>

      <!-- Global Registered Clubs Directory -->
      <div class="bg-white/90 border border-slate-200 rounded-3xl p-6 shadow-xl space-y-4 dark:bg-slate-900/90 dark:border-slate-800">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800">
          <div>
            <h3 class="text-base font-black text-slate-900 dark:text-white">Global Club Directory</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">All clubs registered on the platform with member counts and order types.</p>
          </div>
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Total {{ clubs.length }} club(s)</span>
        </div>

        <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-2xs dark:border-slate-800">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="bg-slate-100 text-slate-500 text-[10px] font-black uppercase tracking-wider border-b border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-800">
                <th class="py-3 px-4">Club Name &amp; No.</th>
                <th class="py-3 px-4">Order / Type</th>
                <th class="py-3 px-4">Masonic Province</th>
                <th class="py-3 px-4 text-center">Active Members</th>
                <th class="py-3 px-4">Created Date</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
              <tr v-for="c in clubs" :key="c.id" class="hover:bg-slate-50 transition-colors dark:hover:bg-slate-800/40">
                <td class="py-3.5 px-4 font-bold text-slate-900 dark:text-white">
                  {{ c.name }} <span v-if="c.lodge_number !== '—'" class="text-slate-500 font-normal dark:text-slate-400">No. {{ c.lodge_number }}</span>
                </td>
                <td class="py-3.5 px-4">
                  <span class="px-2.5 py-1 bg-blue-50 text-blue-700 text-[10px] font-black rounded-lg border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/60">
                    {{ c.club_type }}
                  </span>
                </td>
                <td class="py-3.5 px-4">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800/60">
                    🏛️ {{ c.province }}
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center font-bold text-slate-900 dark:text-white">{{ c.members_count }}</td>
                <td class="py-3.5 px-4 text-slate-500 font-medium dark:text-slate-400">{{ c.created_at }}</td>
                <td class="py-3.5 px-4 text-right">
                  <Link
                    :href="route('admin.club_acc.charity.index', c.slug)"
                    class="px-3 py-1 bg-blue-600 hover:bg-blue-500 text-white font-bold text-[10px] rounded-lg transition"
                  >
                    View Workspace →
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </SuperAdminLayout>
</template>
