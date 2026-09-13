<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';

const props = defineProps({
  title: {
    type: String,
    default: 'Dashboard',
  },
  club: {
    type: Object,
    default: null,
  },
  activeTab: {
    type: String,
    default: 'dashboard',
  },
});

const page = usePage();
const clubSlug = props.club?.slug || page.props.club?.slug || 'oxford-boating';
const clubName = props.club?.name || page.props.club?.name || 'Oxford University Boat Club';

const sidebarOpen = ref(false);
const switcherOpen = ref(false);

const userClubs = computed(() => page.props.auth?.clubs || []);
const adminClubs = computed(() => userClubs.value.filter(c => ['admin', 'owner'].includes(c.role)));
const memberClubs = computed(() => userClubs.value.filter(c => !['admin', 'owner'].includes(c.role)));

const currentClubRole = computed(() => {
  const c = userClubs.value.find(item => item.slug === clubSlug);
  return c?.role || 'admin';
});

const switchWorkspace = (targetClub) => {
  switcherOpen.value = false;
  if (['admin', 'owner'].includes(targetClub.role)) {
    router.visit(route('admin.analytics', { slug: targetClub.slug }));
  } else {
    router.visit(route('member.dashboard', { slug: targetClub.slug }));
  }
};

const copyInviteLink = () => {
  const inviteUrl = `${window.location.origin}/register?club=${clubSlug}`;
  navigator.clipboard.writeText(inviteUrl);
  alert('Invite link copied to clipboard: ' + inviteUrl);
};
</script>

<template>
  <Head :title="`${title} - ClubAdmin`" />

  <div class="min-h-screen bg-[#f1f5f9] text-slate-800 font-sans flex flex-col md:flex-row">
    
    <!-- Mobile Sidebar Toggle Header -->
    <div class="md:hidden bg-[#1e293b] text-white p-4 flex items-center justify-between border-b border-slate-700">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center font-black text-white text-xs shadow-md">
          CA
        </div>
        <span class="font-extrabold tracking-wider text-base uppercase">CLUBADMIN</span>
      </div>
      <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-lg bg-slate-800 text-slate-300 hover:text-white">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
    </div>

    <!-- Left Sidebar Navigation -->
    <aside 
      :class="[
        'w-full md:w-64 bg-[#1e293b] text-slate-300 flex-shrink-0 flex flex-col justify-between transition-all duration-200 z-30',
        sidebarOpen ? 'block' : 'hidden md:flex'
      ]"
    >
      <div>
        <!-- Interactive Workspace Switcher Header -->
        <div class="relative p-4 border-b border-slate-800">
          <button 
            @click="switcherOpen = !switcherOpen" 
            class="w-full flex items-center justify-between p-2.5 rounded-xl bg-slate-800/80 hover:bg-slate-800 border border-slate-700/80 transition-all group"
          >
            <div class="flex items-center gap-3 overflow-hidden">
              <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-indigo-500 to-sky-400 text-white font-black flex items-center justify-center text-xs shadow-md uppercase flex-shrink-0">
                {{ clubName.substring(0, 2) }}
              </div>
              <div class="text-left overflow-hidden">
                <div class="text-xs font-bold text-white truncate group-hover:text-indigo-300 transition-colors">{{ clubName }}</div>
                <div class="flex items-center gap-1.5 mt-0.5">
                  <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold uppercase bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                    {{ currentClubRole }}
                  </span>
                </div>
              </div>
            </div>
            <svg class="w-4 h-4 text-slate-400 group-hover:text-white transition-all transform" :class="{ 'rotate-180': switcherOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <!-- Dropdown Menu -->
          <div 
            v-if="switcherOpen" 
            class="absolute top-full left-4 right-4 mt-2 bg-slate-900 border border-slate-700/90 rounded-2xl shadow-2xl z-50 p-2 space-y-1 backdrop-blur-md"
          >
            <div class="px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400 border-b border-slate-800">
              Switch Workspace ({{ userClubs.length || 1 }})
            </div>

            <div class="max-h-60 overflow-y-auto space-y-1">
              <button
                v-for="c in userClubs"
                :key="c.id"
                @click="switchWorkspace(c)"
                :class="[
                  'w-full flex items-center justify-between p-2.5 rounded-xl text-left transition-all text-xs',
                  c.slug === clubSlug ? 'bg-indigo-600/20 border border-indigo-500/40 text-white font-bold' : 'hover:bg-slate-800 text-slate-300'
                ]"
              >
                <div class="flex items-center gap-2.5 overflow-hidden">
                  <div class="w-6 h-6 rounded-md bg-slate-800 border border-slate-700 font-bold text-[10px] text-indigo-400 flex items-center justify-center uppercase">
                    {{ c.name.substring(0, 2) }}
                  </div>
                  <div class="truncate">
                    <div class="truncate font-semibold">{{ c.name }}</div>
                    <div class="text-[10px] text-slate-400 capitalize">{{ c.role }}</div>
                  </div>
                </div>
                <span v-if="c.slug === clubSlug" class="text-indigo-400 text-xs">✓</span>
              </button>
            </div>

            <div class="pt-2 border-t border-slate-800 space-y-1">
              <Link :href="route('admin.clubs.index')" class="block w-full text-center py-2 px-3 bg-slate-800 hover:bg-slate-700 text-xs font-bold text-indigo-300 rounded-xl transition-all">
                + View All Joined Clubs
              </Link>
            </div>
          </div>
        </div>

        <!-- Navigation Links -->
        <nav class="p-4 space-y-1 text-sm font-medium">
          <!-- 1. Dashboard / Analytics -->
          <Link 
            :href="route('admin.analytics', clubSlug)" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'dashboard' 
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-indigo-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
            <span>Dashboard</span>
          </Link>

          <!-- 1b. Clubs -->
          <Link 
            :href="route('admin.clubs.index')" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'clubs' 
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-indigo-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V11m0 0V7m0 4h4m-4 0H7" />
            </svg>
            <span>Clubs</span>
          </Link>

          <!-- 2. Classes & Events -->
          <Link 
            :href="route('admin.events.index', { clubSlug })" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'events' 
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-indigo-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>Classes & Events</span>
          </Link>

          <!-- 3. Subscriptions -->
          <Link 
            :href="route('admin.memberships.index', { clubSlug })" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'memberships' 
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-indigo-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span>Subscriptions</span>
          </Link>

          <!-- 4. Members -->
          <Link 
            :href="route('admin.users.index', { clubSlug })" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'users' || activeTab === 'members'
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-indigo-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span>Members</span>
          </Link>

          <!-- 5. Billing -->
          <Link 
            :href="route('billing.index')" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'billing' 
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-indigo-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <span>Billing</span>
          </Link>

          <!-- 6. Communications & Posts -->
          <Link 
            :href="route('admin.posts.index', { clubSlug })" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'posts' 
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-indigo-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
            </svg>
            <span>Communications</span>
          </Link>

          <!-- Newsletters -->
          <Link 
            :href="route('admin.newsletters.index', { clubSlug })" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'newsletters' 
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-indigo-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <span>Newsletters</span>
          </Link>

          <!-- 7. CMS Pages -->
          <Link 
            :href="route('admin.pages.index', { clubSlug })" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'pages' 
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-indigo-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>Forms & CMS Pages</span>
          </Link>

          <!-- 8. Club Settings -->
          <Link 
            :href="route('admin.settings.show', { clubSlug })" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'settings' || activeTab === 'profile'
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-indigo-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>Club Settings</span>
          </Link>
        </nav>
      </div>

      <!-- Footer Quick Action / User Profile -->
      <div class="p-4 border-t border-slate-800 space-y-3">
        <div class="flex items-center gap-3 p-2 rounded-xl bg-slate-900/60 border border-slate-800">
          <div class="w-8 h-8 rounded-full bg-indigo-500/20 text-indigo-400 font-bold flex items-center justify-center text-xs">
            OU
          </div>
          <div class="overflow-hidden">
            <div class="text-xs font-bold text-white truncate">{{ page.props.auth?.user?.name || 'Club Administrator' }}</div>
            <div class="text-[10px] text-slate-400 truncate">{{ page.props.auth?.user?.email || 'admin@oxford.edu' }}</div>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Right Content Area -->
    <main class="flex-1 overflow-y-auto flex flex-col min-w-0">
      
      <!-- Top Title Bar -->
      <header class="bg-white border-b border-slate-200 px-6 md:px-10 py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ title }}</h1>
          <p class="text-xs text-slate-500 font-medium mt-0.5">Managing {{ clubName }}</p>
        </div>

        <!-- Quick Actions & Links -->
        <div class="flex items-center gap-3">
          <Link :href="route('member.dashboard', clubSlug)" class="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 text-emerald-700 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 shadow-sm">
            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
            <span>Switch to Member Portal</span>
          </Link>
          <Link :href="route('clubs.show', clubSlug)" target="_blank" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl transition-all flex items-center gap-1.5">
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
            <span>Live Website</span>
          </Link>
          <button @click="copyInviteLink" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all flex items-center gap-1.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            <span>Invite Members</span>
          </button>
        </div>
      </header>

      <!-- Main Slot Content -->
      <div class="p-6 md:p-10 max-w-7xl w-full mx-auto space-y-8">
        <slot />
      </div>

    </main>

  </div>
</template>
