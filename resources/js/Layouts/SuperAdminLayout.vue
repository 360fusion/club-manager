<script setup>
import { ref } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import FlashMessages from '@/Components/Ui/FlashMessages.vue';
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const platformLinks = [
  { route: 'superadmin.users.index', path: '/superadmin/users', icon: 'users', label: 'Users' },
  { route: 'superadmin.clubs.index', path: '/superadmin/clubs', icon: 'building', label: 'Clubs and plans' },
  { route: 'superadmin.stripe_events.index', path: '/superadmin/stripe-events', icon: 'receipt', label: 'Stripe events' },
  { route: 'superadmin.health.index', path: '/superadmin/system-health', icon: 'shield', label: 'System health' },
];

defineProps({
  title: {
    type: String,
    default: 'Superadmin console',
  },
});

const page = usePage();
const user = page.props.auth?.user;

// Phones: the menu folds away behind a button so the page itself comes first (each page opens with it closed).
const menuOpen = ref(false);
</script>

<template>
  <Head :title="`${title} - Superadmin`" />

  <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[200] focus:px-4 focus:py-2 focus:rounded-xl focus:bg-blue-600 focus:text-white focus:font-bold focus:text-sm focus:shadow-lg">
    Skip to main content
  </a>

  <div class="min-h-dvh bg-slate-100 text-slate-900 font-sans flex flex-col md:flex-row dark:bg-slate-950 dark:text-white">
    
    <!-- Sidebar Navigation -->
    <aside class="w-full md:w-64 bg-white border-r border-slate-200 flex flex-col justify-between p-4 flex-shrink-0 dark:bg-slate-900 dark:border-slate-800">
      <div class="space-y-6">
        <!-- Logo Header -->
        <div class="flex items-center gap-3 px-3 py-2 border-b border-slate-200 pb-4 dark:border-slate-800">
          <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white font-semibold text-sm shadow-sm">
            SA
          </div>
          <div class="min-w-0 flex-1">
            <span class="font-semibold tracking-wider text-sm uppercase text-slate-900 block dark:text-white">Superadmin</span>
            <span class="text-[11px] text-slate-500 font-medium block dark:text-slate-400">Platform control centre</span>
          </div>
          <Button variant="secondary" size="sm" class="md:hidden" :aria-expanded="menuOpen" aria-controls="superadmin-menu" @click="menuOpen = !menuOpen">{{ menuOpen ? 'Close' : 'Menu' }}</Button>
        </div>

        <!-- Navigation Menu -->
        <nav id="superadmin-menu" aria-label="Superadmin" :class="menuOpen ? 'block' : 'hidden md:block'" class="space-y-1 text-xs font-medium">
          <Link
            :href="route('superadmin.dashboard')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url === '/superadmin' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="chart" />
            <span>Platform overview</span>
          </Link>

          <Link
            :href="route('superadmin.club_types.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/club-types') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="tag" />
            <span>Club types &amp; orders</span>
          </Link>

          <Link
            :href="route('superadmin.grand_lodges.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/grand-lodges') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="bank" />
            <span>Grand Lodges &amp; country heads</span>
          </Link>

          <Link
            :href="route('superadmin.provinces.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/provinces') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="shield" />
            <span>Provinces UK&amp;I</span>
          </Link>

          <Link
            :href="route('superadmin.districts.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/districts') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="globe" />
            <span>Districts &amp; groups</span>
          </Link>

          <Link
            :href="route('superadmin.lodge_claims.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/lodge-claims') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="key" />
            <span>Lodge claims</span>
            <span v-if="$page.props.pendingLodgeClaims" class="ml-auto rounded-full bg-amber-500 px-2 py-0.5 text-[11px] font-bold text-white">{{ $page.props.pendingLodgeClaims }}</span>
          </Link>

          <Link
            :href="route('superadmin.lodge_corrections.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/lodge-corrections') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="pencil" />
            <span>Suggested fixes</span>
            <span v-if="$page.props.pendingLodgeCorrections" class="ml-auto rounded-full bg-amber-500 px-2 py-0.5 text-[11px] font-bold text-white">{{ $page.props.pendingLodgeCorrections }}</span>
          </Link>

          <Link
            :href="route('superadmin.lodges.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/lodges') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="document" />
            <span>Lodge directory</span>
          </Link>

          <Link
            :href="route('superadmin.masonic_halls.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/masonic-halls') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="building" />
            <span>Masonic halls</span>
          </Link>

          <Link
            :href="route('superadmin.australian_grand_lodges')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/australian-grand-lodges') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="award" />
            <span>Australian Grand Lodges</span>
          </Link>

          <Link
            :href="route('superadmin.us_grand_lodges')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/us-grand-lodges') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="award" />
            <span>US Grand Lodges</span>
          </Link>

          <Link
            :href="route('superadmin.email_templates.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/email-templates') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="mail" />
            <span>Default email templates</span>
          </Link>

          <Link
            :href="route('superadmin.platform_payments.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/platform-payments') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="cash" />
            <span>Platform payments</span>
          </Link>

          <Link
            v-for="item in platformLinks"
            :key="item.route"
            :href="route(item.route)"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith(item.path) ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon :name="item.icon" />
            <span>{{ item.label }}</span>
          </Link>

          <Link
            :href="route('superadmin.audit_log.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/audit-log') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="clipboard" />
            <span>Audit log</span>
          </Link>

          <Link
            :href="route('superadmin.page_speed.index')"
            :class="[
              'flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all',
              $page.url.startsWith('/superadmin/page-speed') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800'
            ]"
          >
            <UiIcon name="lightning" />
            <span>Page speed</span>
          </Link>
        </nav>

        <div :class="menuOpen ? 'block' : 'hidden md:block'" class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-800">
          <ThemeToggle />
        </div>
      </div>

      <!-- User Profile Footer -->
      <div :class="menuOpen ? 'block' : 'hidden md:block'" class="pt-4 border-t border-slate-200 space-y-3 dark:border-slate-800">
        <div class="px-3">
          <div class="text-xs font-semibold text-slate-900 truncate dark:text-white">{{ user?.name || 'Superadmin' }}</div>
          <div class="text-[11px] text-slate-500 truncate dark:text-slate-400">{{ user?.email }}</div>
        </div>

        <div class="space-y-2">
          <Button variant="secondary" size="sm" block :href="route('home')">
            <template #icon><UiIcon name="arrow-left" :size="14" /></template>
            Return to app
          </Button>
          <Button variant="ghost" size="sm" block @click="router.post('/logout')">
            <template #icon><UiIcon name="logout" :size="14" /></template>
            Log out
          </Button>
        </div>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main id="main-content" tabindex="-1" class="flex-1 min-w-0 px-3 py-5 sm:p-6 md:p-10 space-y-6 sm:space-y-8 overflow-y-auto focus:outline-none">
      <FlashMessages />
      <slot />
    </main>
  </div>
</template>
