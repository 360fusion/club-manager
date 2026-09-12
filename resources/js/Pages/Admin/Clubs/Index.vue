<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  clubs: {
    type: Array,
    default: () => [],
  },
});

const searchQuery = ref('');
const roleFilter = ref('');

const adminClubs = computed(() => {
  return props.clubs.filter(c => ['admin', 'owner'].includes(c.role));
});

const memberClubs = computed(() => {
  return props.clubs.filter(c => !['admin', 'owner'].includes(c.role));
});

const filteredAdminClubs = computed(() => {
  return adminClubs.value.filter(c => {
    const matchesSearch = c.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          c.type_name.toLowerCase().includes(searchQuery.value.toLowerCase());
    return matchesSearch;
  });
});

const filteredMemberClubs = computed(() => {
  return memberClubs.value.filter(c => {
    const matchesSearch = c.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          c.type_name.toLowerCase().includes(searchQuery.value.toLowerCase());
    return matchesSearch;
  });
});
</script>

<template>
  <AdminLayout title="Clubs" active-tab="clubs">
    
    <div class="space-y-8">
      
      <!-- Top Action & Summary Header -->
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-xl font-bold text-slate-900">My Clubs & Workspaces</h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-200">
              {{ clubs.length }} Joined Clubs
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">Manage and access all sports clubs, rowing boathouses, and community organizations linked to your account.</p>
        </div>

        <div class="flex items-center gap-3">
          <Link
            href="/"
            class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all flex items-center gap-1.5"
          >
            <span>+ Join or Register Club</span>
          </Link>
        </div>
      </div>

      <!-- Search Bar -->
      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80 flex flex-col sm:flex-row gap-3 items-center justify-between">
        <div class="relative w-full sm:w-80">
          <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search clubs by name or type..."
            class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 text-xs rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
          />
        </div>

        <div class="text-xs text-slate-500 font-semibold">
          Showing <strong class="text-slate-900">{{ filteredAdminClubs.length + filteredMemberClubs.length }}</strong> of <strong class="text-slate-900">{{ clubs.length }}</strong> clubs
        </div>
      </div>

      <!-- SECTION 1: CLUBS YOU MANAGE (ADMIN / OWNER) -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-indigo-600"></div>
            <h3 class="text-base font-bold text-slate-900">Clubs You Manage (Admin Role)</h3>
          </div>
          <span class="text-xs font-semibold text-slate-400">{{ filteredAdminClubs.length }} Clubs</span>
        </div>

        <div v-if="filteredAdminClubs.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="club in filteredAdminClubs"
            :key="club.id"
            class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 hover:border-indigo-300 transition-all flex flex-col justify-between space-y-6 group"
          >
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase bg-indigo-50 text-indigo-700 border border-indigo-200 tracking-wider">
                  {{ club.type_name }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-purple-50 text-purple-700 border border-purple-200">
                  {{ club.role }}
                </span>
              </div>

              <div>
                <h4 class="text-lg font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">{{ club.name }}</h4>
                <p class="text-xs text-slate-400 mt-1">Slug: <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-600 font-mono">{{ club.slug }}</code></p>
              </div>

              <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 text-xs">
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                  <div class="text-[10px] uppercase font-bold text-slate-400">Total Members</div>
                  <div class="text-sm font-black text-slate-900 mt-0.5">{{ club.members_count }}</div>
                </div>
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                  <div class="text-[10px] uppercase font-bold text-slate-400">Scheduled Events</div>
                  <div class="text-sm font-black text-slate-900 mt-0.5">{{ club.events_count }}</div>
                </div>
              </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center gap-2">
              <Link
                :href="route('admin.analytics', { slug: club.slug })"
                class="flex-1 text-center py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all"
              >
                Admin Portal &rarr;
              </Link>
              <Link
                :href="route('member.dashboard', { slug: club.slug })"
                class="flex-1 text-center py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-300 text-xs font-bold rounded-xl transition-all"
              >
                Member View &rarr;
              </Link>
            </div>
          </div>
        </div>

        <div v-else class="bg-white rounded-2xl p-8 text-center border border-slate-200/80 text-slate-400 text-xs">
          No clubs managed with administrative permissions found.
        </div>
      </div>

      <!-- SECTION 2: CLUBS YOU BELONG TO (MEMBER / COACH / TREASURER) -->
      <div class="space-y-4 pt-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
            <h3 class="text-base font-bold text-slate-900">Clubs You Belong To (Member / Athlete Role)</h3>
          </div>
          <span class="text-xs font-semibold text-slate-400">{{ filteredMemberClubs.length }} Clubs</span>
        </div>

        <div v-if="filteredMemberClubs.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="club in filteredMemberClubs"
            :key="club.id"
            class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 hover:border-emerald-300 transition-all flex flex-col justify-between space-y-6 group"
          >
            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200 tracking-wider">
                  {{ club.type_name }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-slate-100 text-slate-700 border border-slate-200">
                  {{ club.role }}
                </span>
              </div>

              <div>
                <h4 class="text-lg font-bold text-slate-900 group-hover:text-emerald-600 transition-colors">{{ club.name }}</h4>
                <div class="flex items-center gap-2 mt-1.5">
                  <span v-if="club.member_number" class="text-xs font-mono font-semibold px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md border border-slate-200">
                    {{ club.member_number }}
                  </span>
                  <span class="text-[11px] font-bold text-emerald-700 uppercase">
                    {{ club.status }}
                  </span>
                </div>
              </div>
            </div>

            <div class="pt-4 border-t border-slate-100">
              <Link
                :href="route('member.dashboard', { slug: club.slug })"
                class="block w-full text-center py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 transition-all"
              >
                Enter Member Portal &rarr;
              </Link>
            </div>
          </div>
        </div>

        <div v-else class="bg-white rounded-2xl p-8 text-center border border-slate-200/80 text-slate-400 text-xs">
          No additional member clubs found.
        </div>
      </div>

    </div>

  </AdminLayout>
</template>
