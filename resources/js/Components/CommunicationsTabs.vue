<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  club: Object,
  activeTab: {
    type: String,
    required: true, // 'posts', 'newsletters', 'updates'
  },
});
</script>

<template>
  <div class="flex items-center gap-2 p-1.5 bg-slate-200/80 rounded-2xl w-fit text-xs font-bold border border-slate-300/60 mb-6 shadow-inner">
    <Link
      :href="route('admin.posts.index', { clubSlug: club.slug })"
      :class="[
        'px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer',
        activeTab === 'posts' ? 'bg-slate-900 text-white shadow-md font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'
      ]"
    >
      <span>📰</span>
      <span>News Articles & Posts</span>
    </Link>

    <Link
      :href="route('admin.newsletters.index', { clubSlug: club.slug })"
      :class="[
        'px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer',
        activeTab === 'newsletters' ? 'bg-slate-900 text-white shadow-md font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'
      ]"
    >
      <span>✉️</span>
      <span>Newsletters & Broadcasts</span>
    </Link>

    <Link
      :href="route('admin.updates.index', { clubSlug: club.slug })"
      :class="[
        'px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer',
        activeTab === 'updates' ? 'bg-slate-900 text-white shadow-md font-black' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60'
      ]"
    >
      <span>📜</span>
      <span>Updates & Weekly Digest</span>
    </Link>
  </div>
</template>
