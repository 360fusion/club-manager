<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

import RichTextEditor from '@/Components/RichTextEditor.vue';

const props = defineProps({
  club: Object,
  post: Object,
});

const form = useForm({
  id: props.post.id || null,
  title: props.post.title || '',
  slug: props.post.slug || '',
  excerpt: props.post.excerpt || '',
  content: props.post.content || '',
  status: props.post.status || 'published',
});

const submit = () => {
  form.post(route('admin.posts.store', { clubSlug: props.club.slug }));
};
</script>

<template>
  <AdminLayout :title="`${post.id ? 'Edit' : 'Create'} Post`" :club="club" active-tab="posts">
    
    <div class="max-w-4xl mx-auto space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex items-center justify-between bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900">{{ post.id ? 'Edit News Post' : 'Create News Article' }}</h2>
          <p class="text-xs text-slate-500 mt-0.5">Publish newsletters and regatta announcements.</p>
        </div>
        <Link :href="route('admin.posts.index', { clubSlug: club.slug })" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl transition-all">
          &larr; Back to Posts
        </Link>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Article Title</label>
            <input v-model="form.title" type="text" required placeholder="Summer Regatta Results & Trophy Ceremony" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-sky-500" />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">URL Slug</label>
            <input v-model="form.slug" type="text" required placeholder="summer-regatta-results" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-sky-500" />
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Excerpt</label>
          <input v-model="form.excerpt" type="text" placeholder="Short summary for member newsletters..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-sky-500" />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Full Content (WYSIWYG)</label>
          <RichTextEditor v-model="form.content" placeholder="Write rich article content here..." />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Status</label>
          <select v-model="form.status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-sky-500">
            <option value="published">Published</option>
            <option value="draft">Draft</option>
          </select>
        </div>

        <button type="submit" :disabled="form.processing" class="w-full py-3.5 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded-xl text-xs transition-all shadow-md shadow-sky-600/20">
          {{ form.processing ? 'Saving Post...' : 'Save & Publish Article' }}
        </button>

      </form>

    </div>

  </AdminLayout>
</template>
