<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  metrics: Object,
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
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 flex flex-col justify-between space-y-3">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Active users</div>
        <div class="text-4xl font-black text-slate-900 font-sans tracking-tight">
          {{ metrics.total_members || 300 }}
        </div>
        <div class="text-xs font-semibold text-slate-400">
          +100.00
        </div>
      </div>

      <!-- 2. Total Sales Card -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 flex flex-col justify-between space-y-3">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total sales</div>
        <div class="text-4xl font-black text-slate-900 font-sans tracking-tight">
          £{{ metrics.monthly_dues_est || '2,500' }}
        </div>
        <div class="text-xs font-semibold text-slate-400">
          +20.00
        </div>
      </div>

    </div>

    <!-- Second Row Metric Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      
      <!-- Metric 1: Active users -->
      <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80 space-y-2">
        <div class="text-xs font-semibold text-slate-500">Active users</div>
        <div class="text-2xl font-extrabold text-slate-900">
          {{ metrics.total_members || 300 }}
        </div>
      </div>

      <!-- Metric 2: Pending users -->
      <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80 space-y-2">
        <div class="text-xs font-semibold text-slate-500">Pending users</div>
        <div class="text-2xl font-extrabold text-slate-900">10</div>
      </div>

      <!-- Metric 3: Open invoices -->
      <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80 space-y-2">
        <div class="text-xs font-semibold text-slate-500">Open invoices</div>
        <div class="text-2xl font-extrabold text-slate-900">5</div>
      </div>

      <!-- Metric 4: Invite users to register -->
      <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80 space-y-2 flex flex-col justify-between">
        <div class="text-xs font-semibold text-slate-500">Invite users to register</div>
        <div class="flex items-center gap-1.5 pt-1">
          <input 
            type="text" 
            readonly 
            :value="`https://club-manager.test/register?club=${club.slug}`" 
            class="w-full px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-[11px] font-mono text-slate-600 truncate focus:outline-none"
          />
          <button 
            @click="copyInviteLink" 
            class="p-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg transition-all flex-shrink-0"
            title="Copy Invite Link"
          >
            <svg v-if="!inviteCopied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
            </svg>
            <svg v-else class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </button>
        </div>
      </div>

    </div>

    <!-- Bottom Section Dual Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      
      <!-- Left Panel: Today Agenda -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-6">
        <h2 class="text-xl font-bold text-slate-900">Today</h2>

        <div class="space-y-6 divide-y divide-slate-100">
          
          <!-- Item 1: Club members meeting -->
          <div class="flex items-start gap-4 pt-2 first:pt-0">
            <div class="text-xs font-semibold text-slate-400 w-20 pt-1 space-y-1 text-right">
              <div>08:00am</div>
              <div>10:00am</div>
            </div>
            <div class="w-1 bg-blue-500 rounded-full self-stretch min-h-[48px]"></div>
            <div class="space-y-1.5 flex-1">
              <div class="text-sm font-bold text-slate-800">Club members meeting</div>
              <div class="flex items-center gap-2">
                <span class="w-10 h-3 bg-slate-200 rounded-full inline-block"></span>
                <span class="w-16 h-3 bg-slate-200 rounded-full inline-block"></span>
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
              <div class="text-sm font-bold text-slate-800">Cheer & dance</div>
              <div class="flex items-center gap-2">
                <span class="w-8 h-3 bg-slate-200 rounded-full inline-block"></span>
                <span class="w-12 h-3 bg-slate-200 rounded-full inline-block"></span>
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
              <div class="text-sm font-bold text-slate-800">Conditioning</div>
              <div class="flex items-center gap-2">
                <span class="w-12 h-3 bg-slate-200 rounded-full inline-block"></span>
                <span class="w-8 h-3 bg-slate-200 rounded-full inline-block"></span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Right Panel: Events Summary -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-bold text-slate-900">Events</h2>
          <Link :href="route('admin.events.index', { clubSlug: club.slug })" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
            View all →
          </Link>
        </div>

        <div class="space-y-4">
          
          <!-- Event Card 1 -->
          <div class="p-4 bg-slate-50/60 rounded-xl border border-slate-200/80 space-y-3">
            <div class="font-bold text-slate-800 text-sm">Club members meeting</div>
            <div class="flex items-center gap-4 text-xs text-slate-400">
              <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span class="w-20 h-2.5 bg-slate-200 rounded-full inline-block"></span>
              </div>
              <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="w-24 h-2.5 bg-slate-200 rounded-full inline-block"></span>
              </div>
            </div>
          </div>

          <!-- Event Card 2 -->
          <div class="p-4 bg-slate-50/60 rounded-xl border border-slate-200/80 space-y-3">
            <div class="font-bold text-slate-800 text-sm">Cheer & dance</div>
            <div class="flex items-center gap-4 text-xs text-slate-400">
              <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span class="w-20 h-2.5 bg-slate-200 rounded-full inline-block"></span>
              </div>
              <div class="flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="w-24 h-2.5 bg-slate-200 rounded-full inline-block"></span>
              </div>
            </div>
          </div>

        </div>
      </div>

    </div>

  </AdminLayout>
</template>
