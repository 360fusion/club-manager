<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MemberLayout from '@/Layouts/MemberLayout.vue';

const props = defineProps({
  club: Object,
  memberRole: String,
  post: Object,
});

const getFileIcon = (mimeOrName) => {
  const name = (mimeOrName || '').toLowerCase();
  if (name.includes('pdf')) return '📄';
  if (name.includes('image') || name.endsWith('.png') || name.endsWith('.jpg') || name.endsWith('.jpeg')) return '🖼️';
  if (name.includes('sheet') || name.endsWith('.xls') || name.endsWith('.xlsx') || name.endsWith('.csv')) return '📊';
  if (name.includes('word') || name.endsWith('.doc') || name.endsWith('.docx')) return '📝';
  if (name.endsWith('.zip') || name.endsWith('.rar')) return '📦';
  return '📎';
};
</script>

<template>
  <MemberLayout title="News Article" :club="club" :member-role="memberRole">
    <Head :title="`${post.title} - ${club.name}`" />

    <div class="max-w-3xl mx-auto space-y-6">
      <!-- Back Link -->
      <div>
        <Link
          :href="route('member.dashboard', club.slug)"
          class="inline-flex items-center text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
        >
          ← Back to Portal Dashboard
        </Link>
      </div>

      <!-- Article Card -->
      <article class="bg-white dark:bg-slate-900 rounded-2xl p-6 md:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        
        <!-- Header -->
        <div class="space-y-3">
          <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400">
            <span class="px-2.5 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 font-bold border border-blue-100 dark:border-blue-900/40 uppercase tracking-wider text-[10px]">
              📰 News Bulletin
            </span>
            <span>•</span>
            <span>Published {{ post.published_at }}</span>
            <span>•</span>
            <span>By {{ post.author_name }}</span>
          </div>

          <h1 class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white leading-tight">
            {{ post.title }}
          </h1>

          <p v-if="post.excerpt" class="text-sm font-medium text-slate-600 dark:text-slate-300 italic border-l-4 border-blue-500 pl-3 py-1 bg-slate-50 dark:bg-slate-800/50 rounded-r-xl">
            {{ post.excerpt }}
          </p>
        </div>

        <!-- Cover Image -->
        <div v-if="post.cover_image_url" class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800">
          <img :src="post.cover_image_url" :alt="post.title" class="w-full max-h-96 object-cover" />
        </div>

        <!-- Structured Page Blocks -->
        <div v-if="post.blocks && post.blocks.length" class="space-y-6">
          <div v-for="block in post.blocks" :key="block.id">
            
            <!-- 1. Text Block -->
            <div
              v-if="block.type === 'text'"
              class="prose prose-slate max-w-none text-sm leading-relaxed text-slate-800 dark:text-slate-100 space-y-3"
              v-html="block.content"
            ></div>

            <!-- 2. Single Image with Positioning & Sizing -->
            <div
              v-else-if="block.type === 'image' && block.url"
              :class="[
                'my-4 flex',
                block.position === 'left' ? 'justify-start' : block.position === 'right' ? 'justify-end' : block.position === 'center' ? 'justify-center' : 'w-full'
              ]"
            >
              <figure :class="[
                'rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-1 shadow-sm',
                block.size === 'small' ? 'w-full sm:w-1/4' : block.size === 'medium' ? 'w-full sm:w-1/2' : block.size === 'large' ? 'w-full sm:w-3/4' : 'w-full'
              ]">
                <img :src="block.url" :alt="block.caption || post.title" class="w-full h-auto max-h-[500px] object-cover rounded-lg" />
                <figcaption v-if="block.caption" class="text-xs text-center text-slate-500 dark:text-slate-400 italic mt-2 p-1">
                  {{ block.caption }}
                </figcaption>
              </figure>
            </div>

            <!-- 3. Image Gallery -->
            <div v-else-if="block.type === 'images' && block.items && block.items.length" class="my-6">
              <div :class="[
                'grid gap-3',
                block.columns === 2 ? 'grid-cols-1 sm:grid-cols-2' : block.columns === 4 ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-1 sm:grid-cols-3'
              ]">
                <figure
                  v-for="(gItem, gIdx) in block.items"
                  :key="gIdx"
                  class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 p-1 shadow-sm"
                >
                  <img v-if="gItem.url" :src="gItem.url" :alt="gItem.caption || ''" class="w-full h-40 object-cover rounded-lg" />
                  <figcaption v-if="gItem.caption" class="text-[11px] text-center text-slate-600 dark:text-slate-300 font-medium italic mt-1.5 p-1">
                    {{ gItem.caption }}
                  </figcaption>
                </figure>
              </div>
            </div>

            <!-- 4. Notice Callout Box -->
            <div
              v-else-if="block.type === 'notice'"
              :class="[
                'p-4 rounded-xl border text-xs space-y-1 my-4',
                block.style === 'warning' ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/60 text-amber-900 dark:text-amber-200' :
                block.style === 'important' ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-800/60 text-blue-900 dark:text-blue-200' :
                block.style === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/60 text-emerald-900 dark:text-emerald-200' :
                'bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-800/60 text-blue-900 dark:text-blue-200'
              ]"
            >
              <h4 class="font-bold text-sm flex items-center gap-1.5">
                <span>{{ block.style === 'warning' ? '⚠️' : block.style === 'important' ? '🚨' : block.style === 'success' ? '✅' : '💡' }}</span>
                {{ block.title }}
              </h4>
              <p class="leading-relaxed font-medium">{{ block.text }}</p>
            </div>

            <!-- 5. Button Link -->
            <div
              v-else-if="block.type === 'button' && block.url"
              :class="[
                'my-4 flex',
                block.align === 'left' ? 'justify-start' : block.align === 'right' ? 'justify-end' : 'justify-center'
              ]"
            >
              <a
                :href="block.url"
                target="_blank"
                class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md transition-all inline-flex items-center gap-1"
              >
                {{ block.label }}
              </a>
            </div>

          </div>
        </div>

        <!-- Fallback Raw Content -->
        <div
          v-else-if="post.content"
          class="prose prose-slate max-w-none text-sm leading-relaxed text-slate-800 dark:text-slate-100 space-y-4"
          v-html="post.content"
        ></div>

        <!-- Downloadable Attachments Section -->
        <div v-if="post.attachments && post.attachments.length" class="pt-6 border-t border-slate-100 dark:border-slate-800 space-y-3">
          <h4 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
            <span>📎</span> Downloadable Files & Documents ({{ post.attachments.length }})
          </h4>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a
              v-for="(att, aIdx) in post.attachments"
              :key="aIdx"
              :href="att.url"
              target="_blank"
              class="flex items-center justify-between p-3.5 bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-800 transition-all text-xs group"
            >
              <div class="flex items-center gap-3 overflow-hidden">
                <span class="text-xl">{{ getFileIcon(att.mime_type || att.name) }}</span>
                <div class="truncate">
                  <span class="font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 truncate block">
                    {{ att.name }}
                  </span>
                  <span class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold">{{ att.size || 'Download File' }}</span>
                </div>
              </div>
              <span class="text-blue-600 dark:text-blue-400 font-bold group-hover:translate-x-0.5 transition-transform">
                Download ↓
              </span>
            </a>
          </div>
        </div>

      </article>

    </div>
  </MemberLayout>
</template>
