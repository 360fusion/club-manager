<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MemberLayout from '@/Layouts/MemberLayout.vue';

const props = defineProps({
  club: { type: Object, required: true },
  memberRole: { type: String, default: 'member' },
  clubs: { type: Array, default: () => [] },
});

const searchQuery = ref('');

const filteredClubs = computed(() => {
  return props.clubs.filter(c => {
    return c.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
           c.type_name.toLowerCase().includes(searchQuery.value.toLowerCase());
  });
});
</script>

<template>
  <MemberLayout title="My Clubs" :club="club" :member-role="memberRole" active-tab="clubs">
    
    <div class="space-y-6">
      
      <!-- Top Action & Summary Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-xl font-bold text-slate-900">My Joined Clubs</h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
              {{ clubs.length }} Joined Clubs
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">Select any of your sports or community clubs below to view member events, RSVPs, and dues.</p>
        </div>

        <Link
          href="/"
          class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 transition-all text-center self-start sm:self-auto"
        >
          + Discover & Join Clubs
        </Link>
      </div>

      <!-- Search Input -->
      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80 flex flex-col sm:flex-row gap-3 items-center justify-between">
        <div class="relative w-full sm:w-80">
          <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search joined clubs..."
            class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 text-xs rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all"
          />
        </div>

        <div class="text-xs text-slate-500 font-semibold">
          Showing <strong class="text-slate-900">{{ filteredClubs.length }}</strong> of <strong class="text-slate-900">{{ clubs.length }}</strong> clubs
        </div>
      </div>

      <!-- Member Clubs Grid -->
      <div v-if="filteredClubs.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
          v-for="c in filteredClubs"
          :key="c.id"
          :class="[
            'bg-white rounded-2xl p-6 shadow-sm border transition-all flex flex-col justify-between space-y-6 group',
            c.slug === club.slug ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200/80 hover:border-emerald-300'
          ]"
        >
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200 tracking-wider">
                {{ c.type_name }}
              </span>
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-slate-100 text-slate-700 border border-slate-200">
                {{ c.role }}
              </span>
            </div>

            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-lg font-bold text-slate-900 group-hover:text-emerald-600 transition-colors">{{ c.name }}</h3>
                <span v-if="c.slug === club.slug" class="px-2 py-0.2 rounded text-[9px] font-extrabold uppercase bg-emerald-600 text-white">
                  Current
                </span>
              </div>
              <div class="flex items-center gap-2 mt-2">
                <span v-if="c.member_number" class="text-xs font-mono font-semibold px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md border border-slate-200">
                  {{ c.member_number }}
                </span>
                <span class="text-[11px] font-bold text-emerald-700 uppercase">
                  {{ c.status }}
                </span>
              </div>
            </div>
          </div>

          <div class="pt-4 border-t border-slate-100 flex items-center gap-2">
            <Link
              :href="route('member.dashboard', { slug: c.slug })"
              class="flex-1 text-center py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 transition-all"
            >
              Enter Member Portal &rarr;
            </Link>
            <Link
              v-if="['admin', 'owner'].includes(c.role)"
              :href="route('admin.analytics', { slug: c.slug })"
              class="py-2.5 px-3 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-bold rounded-xl transition-all"
            >
              Admin &rarr;
            </Link>
          </div>
        </div>
      </div>

      <div v-else class="bg-white rounded-2xl p-12 text-center border border-slate-200/80 text-slate-500 text-xs space-y-3">
        <p class="font-medium text-sm">No joined clubs found matching your search.</p>
        <Link href="/" class="inline-block px-4 py-2 bg-emerald-600 text-white font-bold rounded-xl">
          Browse Clubs Directory
        </Link>
      </div>

    </div>

  </MemberLayout>
</template>
