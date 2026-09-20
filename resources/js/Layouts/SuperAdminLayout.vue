<script setup>
import { ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
  title: {
    type: String,
    default: 'Superadmin Console',
  },
});

const page = usePage();
const user = page.props.auth?.user;
</script>

<template>
  <Head :title="`${title} - Superadmin`" />

  <div class="min-h-screen bg-slate-950 text-slate-100 font-sans flex flex-col md:flex-row">
    
    <!-- Sidebar Navigation -->
    <aside class="w-full md:w-64 bg-slate-900 border-r border-slate-800 flex flex-col justify-between p-4 flex-shrink-0">
      <div class="space-y-6">
        <!-- Logo Header -->
        <div class="flex items-center gap-3 px-3 py-2 border-b border-slate-800 pb-4">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-500 flex items-center justify-center text-white font-black text-sm shadow-lg shadow-purple-500/20">
            SA
          </div>
          <div>
            <span class="font-extrabold tracking-wider text-sm uppercase text-white block">SUPERADMIN</span>
            <span class="text-[10px] text-purple-400 font-semibold block">Platform Control Center</span>
          </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="space-y-1 text-xs font-bold">
          <Link
            :href="route('superadmin.dashboard')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url === '/superadmin' ? 'bg-purple-600 text-white shadow-md shadow-purple-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span class="text-base">📊</span>
            <span>Platform Overview</span>
          </Link>

          <Link
            :href="route('superadmin.club_types.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/club-types') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span class="text-base">🏛️</span>
            <span>Club Types &amp; Orders</span>
          </Link>

          <Link
            :href="route('superadmin.grand_lodges.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/grand-lodges') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span class="text-base">👑</span>
            <span>Grand Lodges &amp; Country Heads</span>
          </Link>

          <Link
            :href="route('superadmin.provinces.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/provinces') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span class="text-base">🗺️</span>
            <span>Provinces UK&amp;I</span>
          </Link>

          <Link
            :href="route('superadmin.districts.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/districts') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span class="text-base">🌍</span>
            <span>Districts &amp; Groups</span>
          </Link>

          <Link
            :href="route('superadmin.australian_grand_lodges')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/australian-grand-lodges') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span class="text-base">🦘</span>
            <span>Australian Grand Lodges</span>
          </Link>

          <Link
            :href="route('superadmin.us_grand_lodges')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/us-grand-lodges') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span class="text-base">🇺🇸</span>
            <span>US Grand Lodges</span>
          </Link>

          <Link
            :href="route('superadmin.email_templates.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/email-templates') ? 'bg-purple-600 text-white shadow-md shadow-purple-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span class="text-base">✉️</span>
            <span>Default Email Templates</span>
          </Link>
        </nav>
      </div>

      <!-- User Profile Footer -->
      <div class="pt-4 border-t border-slate-800 space-y-3">
        <div class="px-3">
          <div class="text-xs font-bold text-white truncate">{{ user?.name || 'Superadmin' }}</div>
          <div class="text-[10px] text-purple-400 font-medium truncate">{{ user?.email }}</div>
        </div>

        <div class="space-y-2">
          <Link
            :href="route('home')"
            class="flex items-center justify-center gap-2 w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl transition border border-slate-700"
          >
            <span>← Return to App</span>
          </Link>
          <form method="POST" action="/logout" class="block">
            <button
              type="submit"
              class="flex items-center justify-center gap-2 w-full py-2 bg-rose-950/60 hover:bg-rose-900 text-rose-300 font-bold text-xs rounded-xl transition border border-rose-800/60"
            >
              <span>🚪 Log Out</span>
            </button>
          </form>
        </div>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 min-w-0 p-6 md:p-10 space-y-8 overflow-y-auto">
      <slot />
    </main>
  </div>
</template>
