<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  club: Object,
  activeTab: {
    type: String,
    required: true, // 'lodge', 'committee'
  },
});
</script>

<template>
  <div class="flex items-center gap-2 p-1.5 bg-slate-200/80 rounded-2xl w-fit text-xs font-bold border border-slate-300/60 mb-6 shadow-inner">
    <Link
      :href="route('admin.meetings.index', { clubSlug: club.slug })"
      :class="[
        'px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer',
        activeTab === 'lodge' ? 'bg-slate-900 text-white shadow-md font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'
      ]"
    >
      <span>📅</span>
      <span>Lodge Meetings & Summonses</span>
    </Link>

    <a
      :href="route('admin.committee.index', { clubSlug: club.slug })"
      :class="[
        'px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer',
        activeTab === 'committee' ? 'bg-slate-900 text-white shadow-md font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'
      ]"
    >
      <span>🏛️</span>
      <span>Committee & Board Governance</span>
    </a>
  </div>
</template>
