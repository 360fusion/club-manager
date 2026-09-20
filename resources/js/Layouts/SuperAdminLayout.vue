<script setup>
import { ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';

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

  <div class="min-h-screen bg-slate-100 text-slate-900 font-sans flex flex-col md:flex-row dark:bg-slate-950 dark:text-white">
    
    <!-- Sidebar Navigation -->
    <aside class="w-full md:w-64 bg-white border-r border-slate-200 flex flex-col justify-between p-4 flex-shrink-0 dark:bg-slate-900 dark:border-slate-800">
      <div class="space-y-6">
        <!-- Logo Header -->
        <div class="flex items-center gap-3 px-3 py-2 border-b border-slate-200 pb-4 dark:border-slate-800">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-blue-500 flex items-center justify-center text-slate-900 font-black text-sm shadow-sm dark:text-white">
            SA
          </div>
          <div>
            <span class="font-extrabold tracking-wider text-sm uppercase text-slate-900 block dark:text-white">SUPERADMIN</span>
            <span class="text-[10px] text-blue-600 font-semibold block dark:text-blue-400">Platform Control Center</span>
          </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="space-y-1 text-xs font-bold">
          <Link
            :href="route('superadmin.dashboard')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url === '/superadmin' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <span class="text-base">📊</span>
            <span>Platform Overview</span>
          </Link>

          <Link
            :href="route('superadmin.club_types.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/club-types') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <span class="text-base">🏛️</span>
            <span>Club Types &amp; Orders</span>
          </Link>

          <Link
            :href="route('superadmin.grand_lodges.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/grand-lodges') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <span class="text-base">👑</span>
            <span>Grand Lodges &amp; Country Heads</span>
          </Link>

          <Link
            :href="route('superadmin.provinces.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/provinces') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <span class="text-base">🗺️</span>
            <span>Provinces UK&amp;I</span>
          </Link>

          <Link
            :href="route('superadmin.districts.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/districts') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <span class="text-base">🌍</span>
            <span>Districts &amp; Groups</span>
          </Link>

          <Link
            :href="route('superadmin.australian_grand_lodges')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/australian-grand-lodges') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <span class="text-base">🦘</span>
            <span>Australian Grand Lodges</span>
          </Link>

          <Link
            :href="route('superadmin.us_grand_lodges')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/us-grand-lodges') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <span class="text-base">🇺🇸</span>
            <span>US Grand Lodges</span>
          </Link>

          <Link
            :href="route('superadmin.email_templates.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/email-templates') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <span class="text-base">✉️</span>
            <span>Default Email Templates</span>
          </Link>
        </nav>
      </div>

      <!-- User Profile Footer -->
      <div class="pt-4 border-t border-slate-200 space-y-3 dark:border-slate-800">
        <div class="px-3">
          <div class="text-xs font-bold text-slate-900 truncate dark:text-white">{{ user?.name || 'Superadmin' }}</div>
          <div class="text-[10px] text-blue-600 font-medium truncate dark:text-blue-400">{{ user?.email }}</div>
        </div>

        <div class="space-y-2">
          <Link
            :href="route('home')"
            class="flex items-center justify-center gap-2 w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs rounded-xl transition border border-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700"
          >
            <span>← Return to App</span>
          </Link>
          <form method="POST" action="/logout" class="block">
            <button
              type="submit"
              class="flex items-center justify-center gap-2 w-full py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs rounded-xl transition border border-rose-200 dark:bg-rose-950/50 dark:hover:bg-rose-900/40 dark:text-rose-400 dark:border-rose-800/50"
            >
              <span>🚪 Log Out</span>
            </button>
          </form>
        </div>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 min-w-0 p-6 md:p-10 space-y-8 overflow-y-auto">
      <div class="flex justify-end -mb-4">
        <ThemeToggle />
      </div>
      <slot />
    </main>
  </div>
</template>
