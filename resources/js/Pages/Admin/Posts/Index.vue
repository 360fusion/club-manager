<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CommunicationsTabs from '@/Components/CommunicationsTabs.vue';

const props = defineProps({
  club: Object,
  posts: Array,
});

const deletePost = (postId) => {
  if (confirm('Are you sure you want to delete this blog post?')) {
    router.delete(route('admin.posts.destroy', { clubSlug: props.club.slug, id: postId }));
  }
};
</script>

<template>
  <AdminLayout title="Communications" :club="club" active-tab="posts">
    <Head title="Communications & News Articles" />

    <div class="max-w-6xl mx-auto space-y-6">
      <CommunicationsTabs :club="club" active-tab="posts" />
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900">Communications & News Articles</h2>
          <p class="text-xs text-slate-500 mt-1">Publish regatta announcements, match reports, and club newsletters.</p>
        </div>
        <Link :href="route('admin.posts.create', { clubSlug: club.slug })" class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-xl shadow-md shadow-sky-600/20 transition-all text-center">
          + New Post Article
        </Link>
      </div>

      <!-- Posts List Grid -->
      <div v-if="posts.length" class="grid grid-cols-1 gap-4">
        <div v-for="post in posts" :key="post.id" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 hover:border-slate-300 transition-all flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <span :class="['px-2.5 py-0.5 rounded text-xs font-bold uppercase tracking-wider border', post.status === 'published' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200']">
                {{ post.status }}
              </span>
              <span class="text-xs text-slate-400">By {{ post.author?.name || 'Club Admin' }}</span>
            </div>

            <h3 class="text-lg font-bold text-slate-900">{{ post.title }}</h3>
            <p class="text-xs text-slate-500 line-clamp-2">{{ post.excerpt || post.content }}</p>
          </div>

          <div class="flex items-center gap-3 self-start md:self-auto">
            <Link
              :href="route('admin.posts.show', { clubSlug: club.slug, id: post.id })"
              class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold rounded-xl border border-indigo-200 transition-all flex items-center gap-1"
            >
              👁️ Preview Article
            </Link>
            <Link :href="route('admin.posts.edit', { clubSlug: club.slug, id: post.id })" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition-all">
              ✏️ Edit Post
            </Link>
            <button @click="deletePost(post.id)" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold rounded-xl border border-rose-200 transition-all cursor-pointer">
              🗑️ Delete
            </button>
          </div>
        </div>
      </div>

      <div v-else class="bg-white rounded-2xl p-12 text-center shadow-sm border border-slate-200/80 space-y-4">
        <p class="text-slate-500 text-sm">No blog posts found for this club.</p>
        <Link :href="route('admin.posts.create', { clubSlug: club.slug })" class="inline-block px-5 py-2.5 bg-sky-600 text-white font-bold text-xs rounded-xl shadow-md shadow-sky-600/20">
          Write First Post
        </Link>
      </div>

    </div>

  </AdminLayout>
</template>
