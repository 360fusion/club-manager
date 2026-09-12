<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';

const props = defineProps({
  title: {
    type: String,
    default: 'Member Portal',
  },
  club: {
    type: Object,
    default: null,
  },
  activeTab: {
    type: String,
    default: 'dashboard',
  },
  memberRole: {
    type: String,
    default: 'member',
  },
});

const page = usePage();
const clubSlug = props.club?.slug || page.props.club?.slug || 'oxford-boating';
const clubName = props.club?.name || page.props.club?.name || 'Oxford University Boat Club';

const sidebarOpen = ref(false);

const userClubs = computed(() => page.props.auth?.clubs || []);
const adminClubs = computed(() => userClubs.value.filter(c => ['admin', 'owner'].includes(c.role)));
const memberClubs = computed(() => userClubs.value.filter(c => !['admin', 'owner'].includes(c.role)));

const isUserAdmin = computed(() => {
  const role = String(props.memberRole || '').toLowerCase();
  if (role && role !== 'member') {
    return true;
  }
  if (Array.isArray(userClubs.value) && userClubs.value.length > 0) {
    if (userClubs.value.some(c => String(c.role || '').toLowerCase() !== 'member')) {
      return true;
    }
  }
  return !!page.props.auth?.user;
});

const adminTargetSlug = computed(() => {
  const currentClubAuth = userClubs.value.find(c => c.slug === clubSlug);
  if (currentClubAuth && String(currentClubAuth.role || '').toLowerCase() !== 'member') {
    return clubSlug;
  }
  const firstAdmin = userClubs.value.find(c => String(c.role || '').toLowerCase() !== 'member');
  return firstAdmin ? firstAdmin.slug : clubSlug;
});

const switchWorkspace = (targetClub) => {
  router.visit(route('member.dashboard', { slug: targetClub.slug }));
};
</script>

<template>
  <Head :title="`${title} - ${clubName}`" />

  <div class="min-h-screen bg-[#f1f5f9] text-slate-800 font-sans flex flex-col md:flex-row">
    
    <!-- Mobile Sidebar Toggle Header -->
    <div class="md:hidden bg-[#1e293b] text-white p-4 flex items-center justify-between border-b border-slate-700">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center font-black text-white text-xs shadow-md">
          MP
        </div>
        <span class="font-extrabold tracking-wider text-base uppercase">CLUBMEMBER</span>
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
        <!-- Brand Header -->
        <div class="p-6 border-b border-slate-800 flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center font-black text-white text-sm shadow-lg shadow-emerald-500/20">
            MP
          </div>
          <div>
            <div class="font-black text-lg tracking-wider text-white uppercase leading-none">CLUBMEMBER</div>
            <div class="text-[10px] text-emerald-400 font-semibold mt-1 tracking-wide truncate max-w-[140px]">{{ clubName }}</div>
          </div>
        </div>

        <!-- Navigation Links -->
        <nav class="p-4 space-y-1 text-sm font-medium">
          <!-- 1. Member Dashboard -->
          <Link 
            :href="route('member.dashboard', clubSlug)" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'dashboard' 
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-emerald-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span>Member Dashboard</span>
          </Link>

          <!-- 1b. My Clubs -->
          <Link 
            :href="route('member.clubs', { slug: clubSlug })" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'clubs' 
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-emerald-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V11m0 0V7m0 4h4m-4 0H7" />
            </svg>
            <span>My Clubs</span>
          </Link>

          <!-- 2. Events & RSVPs -->
          <Link 
            :href="route('member.events', { slug: clubSlug })" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'events' 
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-emerald-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>My Events & RSVPs</span>
          </Link>

          <!-- 3. My Dues -->
          <Link 
            :href="route('member.dues', { slug: clubSlug })" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'dues' 
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-emerald-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
            </svg>
            <span>My Dues & Receipts</span>
          </Link>

          <!-- MY CLUBS & WORKSPACES SECTION -->
          <div v-if="userClubs.length > 0" class="pt-4 pb-2 border-t border-slate-800 space-y-2">
            <div class="px-4 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
              My Clubs ({{ userClubs.length }})
            </div>

            <!-- Admin Clubs -->
            <div v-if="adminClubs.length > 0" class="space-y-1">
              <div class="px-4 text-[9px] font-bold uppercase tracking-wider text-indigo-400">Admin Role</div>
              <button
                v-for="c in adminClubs"
                :key="c.id"
                @click="switchWorkspace(c)"
                :class="[
                  'w-full flex items-center justify-between px-4 py-2 rounded-xl text-left transition-all text-xs',
                  c.slug === clubSlug 
                    ? 'bg-indigo-600/30 text-white font-bold border-l-2 border-indigo-400' 
                    : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'
                ]"
              >
                <div class="flex items-center gap-2.5 overflow-hidden">
                  <div class="w-5 h-5 rounded bg-indigo-500/20 text-indigo-300 font-bold text-[9px] flex items-center justify-center uppercase flex-shrink-0">
                    {{ c.name.substring(0, 2) }}
                  </div>
                  <span class="truncate">{{ c.name }}</span>
                </div>
                <span class="text-[9px] px-1.5 py-0.2 rounded font-extrabold uppercase bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                  Admin
                </span>
              </button>
            </div>

            <!-- Member Clubs -->
            <div v-if="memberClubs.length > 0" class="space-y-1 pt-1">
              <div class="px-4 text-[9px] font-bold uppercase tracking-wider text-emerald-400">Member Role</div>
              <button
                v-for="c in memberClubs"
                :key="c.id"
                @click="switchWorkspace(c)"
                :class="[
                  'w-full flex items-center justify-between px-4 py-2 rounded-xl text-left transition-all text-xs',
                  c.slug === clubSlug 
                    ? 'bg-emerald-600/30 text-white font-bold border-l-2 border-emerald-400' 
                    : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'
                ]"
              >
                <div class="flex items-center gap-2.5 overflow-hidden">
                  <div class="w-5 h-5 rounded bg-emerald-500/20 text-emerald-300 font-bold text-[9px] flex items-center justify-center uppercase flex-shrink-0">
                    {{ c.name.substring(0, 2) }}
                  </div>
                  <span class="truncate">{{ c.name }}</span>
                </div>
                <span class="text-[9px] px-1.5 py-0.2 rounded font-extrabold uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                  {{ c.role }}
                </span>
              </button>
            </div>
          </div>

          <!-- 4. Settings & Profile -->
          <Link 
            :href="route('member.profile', { slug: clubSlug })" 
            :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl transition-all',
              activeTab === 'profile' 
                ? 'bg-slate-800/90 text-white font-bold shadow-sm border-l-4 border-emerald-500' 
                : 'hover:bg-slate-800/50 hover:text-white text-slate-300'
            ]"
          >
            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span>Profile & Security</span>
          </Link>
        </nav>
      </div>

      <!-- Footer Quick Action / User Profile -->
      <div class="p-4 border-t border-slate-800 space-y-3">
        <!-- Switch to Admin Dashboard if Admin/Coach -->
        <Link 
          v-if="isUserAdmin"
          :href="route('admin.analytics', { slug: adminTargetSlug })" 
          class="block text-center py-2.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all"
        >
          🔄 Switch to Admin Portal
        </Link>

        <div class="flex items-center gap-3 p-2 rounded-xl bg-slate-900/60 border border-slate-800">
          <div class="w-8 h-8 rounded-full bg-emerald-500/20 text-emerald-400 font-bold flex items-center justify-center text-xs">
            MP
          </div>
          <div class="overflow-hidden">
            <div class="text-xs font-bold text-white truncate">{{ page.props.auth?.user?.name || 'Club Member' }}</div>
            <div class="text-[10px] text-slate-400 truncate">{{ page.props.auth?.user?.email || 'member@oxford.edu' }}</div>
          </div>
        </div>

        <form method="POST" action="/logout" class="block">
          <button type="submit" class="w-full text-center py-2 px-3 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-lg transition-all">
            Log Out
          </button>
        </form>
      </div>
    </aside>

    <!-- Main Right Content Area -->
    <main class="flex-1 overflow-y-auto flex flex-col min-w-0">
      
      <!-- Top Title Bar -->
      <header class="bg-white border-b border-slate-200 px-6 md:px-10 py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
        <div>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ title }}</h1>
          <p class="text-xs text-slate-500 font-medium mt-0.5">{{ clubName }} Member Dashboard</p>
        </div>

        <!-- Quick Actions & Links -->
        <div class="flex items-center gap-3">
          <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
            {{ memberRole }}
          </span>
          <Link 
            :href="route('admin.analytics', { slug: adminTargetSlug })" 
            class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all flex items-center gap-1.5"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
            <span>Switch to Admin Portal</span>
          </Link>
        </div>
      </header>

      <!-- Main Slot Content -->
      <div class="p-6 md:p-10 max-w-7xl w-full mx-auto space-y-8">
        <slot />
      </div>

    </main>

  </div>
</template>
