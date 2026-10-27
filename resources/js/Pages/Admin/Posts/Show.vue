<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Alert from '@/Components/Ui/Alert.vue';

defineProps({
  club: Object,
  post: Object,
});

const fileKind = (mimeOrName) => {
  const name = (mimeOrName || '').toLowerCase();
  if (name.includes('pdf')) return 'PDF';
  if (name.includes('image') || name.endsWith('.png') || name.endsWith('.jpg') || name.endsWith('.jpeg')) return 'Image';
  if (name.includes('sheet') || name.endsWith('.xls') || name.endsWith('.xlsx') || name.endsWith('.csv')) return 'Spreadsheet';
  if (name.includes('word') || name.endsWith('.doc') || name.endsWith('.docx')) return 'Document';
  if (name.endsWith('.zip') || name.endsWith('.rar')) return 'Archive';
  return 'File';
};

const noticeVariant = (style) => ({ warning: 'warning', success: 'success' }[style] ?? 'info');
</script>

<template>
  <AdminLayout title="Communications" :club="club" active-tab="posts">
    <Head :title="`Preview: ${post.title} - ${club.name}`" />

    <div class="space-y-6">
      <Card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <Link
                :href="route('admin.posts.index', { clubSlug: club.slug })"
                class="text-xs font-medium text-slate-500 transition-colors hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
              >
                Back to communications
              </Link>
              <Badge :variant="post.status === 'published' ? 'success' : 'warning'" class="capitalize">{{ post.status }}</Badge>
            </div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Article preview</h2>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <Button size="sm" :href="route('admin.posts.edit', { clubSlug: club.slug, id: post.id })">Edit article</Button>
            <a
              :href="route('member.posts.show', { slug: club.slug, id: post.id })"
              target="_blank"
              rel="noopener"
              class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
              View in the member portal (opens a new tab)
            </a>
          </div>
        </div>
      </Card>

      <div class="mx-auto max-w-3xl space-y-6">
        <Card padding="lg">
          <article class="space-y-6">
            <div class="space-y-3">
              <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                <Badge variant="info">News bulletin</Badge>
                <span>Published {{ post.published_at }}</span>
                <span aria-hidden="true">·</span>
                <span>By {{ post.author_name }}</span>
              </div>

              <h1 class="text-2xl font-bold leading-tight text-slate-900 dark:text-white md:text-3xl">
                {{ post.title }}
              </h1>

              <p v-if="post.excerpt" class="rounded-r-xl border-l-4 border-blue-500 bg-slate-50 py-1 pl-3 text-sm font-medium italic text-slate-600 dark:bg-slate-800/50 dark:text-slate-300">
                {{ post.excerpt }}
              </p>
            </div>

            <div v-if="post.cover_image_url" class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
              <img :src="post.cover_image_url" :alt="post.title" class="max-h-96 w-full object-cover" />
            </div>

            <div v-if="post.blocks && post.blocks.length" class="space-y-6">
              <div v-for="block in post.blocks" :key="block.id">
                <div
                  v-if="block.type === 'text'"
                  class="prose prose-slate max-w-none space-y-3 text-sm leading-relaxed text-slate-800 dark:text-slate-100"
                  v-html="block.content"
                ></div>

                <div
                  v-else-if="block.type === 'image' && block.url"
                  :class="[
                    'my-4 flex',
                    block.position === 'left' ? 'justify-start' : block.position === 'right' ? 'justify-end' : block.position === 'center' ? 'justify-center' : 'w-full'
                  ]"
                >
                  <figure :class="[
                    'overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900',
                    block.size === 'small' ? 'w-full sm:w-1/4' : block.size === 'medium' ? 'w-full sm:w-1/2' : block.size === 'large' ? 'w-full sm:w-3/4' : 'w-full'
                  ]">
                    <img :src="block.url" :alt="block.caption || post.title" class="h-auto max-h-[500px] w-full rounded-lg object-cover" />
                    <figcaption v-if="block.caption" class="mt-2 p-1 text-center text-xs italic text-slate-500 dark:text-slate-400">
                      {{ block.caption }}
                    </figcaption>
                  </figure>
                </div>

                <div v-else-if="block.type === 'images' && block.items && block.items.length" class="my-6">
                  <div :class="[
                    'grid gap-3',
                    block.columns === 2 ? 'grid-cols-1 sm:grid-cols-2' : block.columns === 4 ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-1 sm:grid-cols-3'
                  ]">
                    <figure
                      v-for="(gItem, gIdx) in block.items"
                      :key="gIdx"
                      class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50 p-1 shadow-sm dark:border-slate-800 dark:bg-slate-800/50"
                    >
                      <img v-if="gItem.url" :src="gItem.url" :alt="gItem.caption || ''" class="h-40 w-full rounded-lg object-cover" />
                      <figcaption v-if="gItem.caption" class="mt-1.5 p-1 text-center text-xs font-medium italic text-slate-600 dark:text-slate-300">
                        {{ gItem.caption }}
                      </figcaption>
                    </figure>
                  </div>
                </div>

                <Alert v-else-if="block.type === 'notice'" :variant="noticeVariant(block.style)" :title="block.title" class="my-4">
                  {{ block.text }}
                </Alert>

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
                    rel="noopener"
                    class="inline-flex items-center gap-1 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-blue-500"
                  >
                    {{ block.label }}
                  </a>
                </div>
              </div>
            </div>

            <div
              v-else-if="post.content"
              class="prose prose-slate max-w-none space-y-4 text-sm leading-relaxed text-slate-800 dark:text-slate-100"
              v-html="post.content"
            ></div>

            <div v-if="post.attachments && post.attachments.length" class="space-y-3 border-t border-slate-100 pt-6 dark:border-slate-800">
              <h4 class="text-sm font-semibold text-slate-900 dark:text-white">
                Files to download ({{ post.attachments.length }})
              </h4>

              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <a
                  v-for="(att, aIdx) in post.attachments"
                  :key="aIdx"
                  :href="att.url"
                  target="_blank"
                  rel="noopener"
                  class="group flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5 transition-colors hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-800/50 dark:hover:bg-slate-800"
                >
                  <div class="flex min-w-0 items-center gap-3">
                    <Badge>{{ fileKind(att.mime_type || att.name) }}</Badge>
                    <div class="min-w-0">
                      <span class="block truncate text-sm font-medium text-slate-900 group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                        {{ att.name }}
                      </span>
                      <span class="text-xs text-slate-500 dark:text-slate-400">{{ att.size || 'Download file' }}</span>
                    </div>
                  </div>
                  <span class="shrink-0 text-xs font-medium text-blue-600 dark:text-blue-400">Download</span>
                </a>
              </div>
            </div>
          </article>
        </Card>
      </div>
    </div>
  </AdminLayout>
</template>
