<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MemberLayout from '@/Layouts/MemberLayout.vue';

const props = defineProps({
  club: Object,
  memberRole: String,
  post: Object,
});
</script>

<template>
  <MemberLayout title="News Article" :club="club" :member-role="memberRole">
    <Head :title="`${post.title} - ${club.name}`" />

    <div class="max-w-3xl mx-auto space-y-6">
      <!-- Back Link -->
      <div>
        <Link
          :href="route('member.dashboard', club.slug)"
          class="inline-flex items-center text-xs font-bold text-slate-500 hover:text-indigo-600 transition-colors"
        >
          ← Back to Portal Dashboard
        </Link>
      </div>

      <!-- Article Card -->
      <article class="bg-white rounded-2xl p-6 md:p-8 shadow-sm border border-slate-200/80 space-y-6">
        
        <!-- Header -->
        <div class="space-y-3">
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
            <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 font-bold border border-indigo-100 uppercase tracking-wider text-[10px]">
              📰 News Bulletin
            </span>
            <span>•</span>
            <span>Published {{ post.published_at }}</span>
            <span>•</span>
            <span>By {{ post.author_name }}</span>
          </div>

          <h1 class="text-2xl md:text-3xl font-black text-slate-900 leading-tight">
            {{ post.title }}
          </h1>

          <p v-if="post.excerpt" class="text-sm font-medium text-slate-600 italic border-l-4 border-indigo-500 pl-3 py-1 bg-slate-50 rounded-r-xl">
            {{ post.excerpt }}
          </p>
        </div>

        <!-- Cover Image -->
        <div v-if="post.cover_image_url" class="rounded-xl overflow-hidden border border-slate-200">
          <img :src="post.cover_image_url" :alt="post.title" class="w-full max-h-96 object-cover" />
        </div>

        <!-- Article Content -->
        <div
          class="prose prose-slate max-w-none text-sm leading-relaxed text-slate-800 space-y-4"
          v-html="post.content"
        ></div>

      </article>

    </div>
  </MemberLayout>
</template>
