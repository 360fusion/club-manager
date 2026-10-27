<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CommunicationsTabs from '@/Components/CommunicationsTabs.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import { orderColour } from '@/Utils/orderColour';

const props = defineProps({
  club: Object,
  posts: Object, // a Laravel paginator: `data` is this page, `links` are the page links
});

const postToDelete = ref(null);
const deleting = ref(false);

const deletePost = () => {
  deleting.value = true;
  router.delete(route('admin.posts.destroy', { clubSlug: props.club.slug, id: postToDelete.value.id }), {
    onFinish: () => {
      deleting.value = false;
      postToDelete.value = null;
    },
  });
};
</script>

<template>
  <AdminLayout title="Communications" :club="club" active-tab="posts">
    <Head title="Communications and news articles" />

    <div class="mx-auto max-w-6xl space-y-6">
      <CommunicationsTabs :club="club" active-tab="posts" />

      <Card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Communications and news articles</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Publish regatta announcements, match reports and club newsletters.</p>
          </div>
          <Button size="sm" :href="route('admin.posts.create', { clubSlug: club.slug })">Write a post</Button>
        </div>
      </Card>

      <div v-if="posts.data.length" class="space-y-4">
        <Card v-for="post in posts.data" :key="post.id">
          <div class="flex flex-col justify-between gap-6 md:flex-row md:items-center">
            <div class="min-w-0 space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <Badge :variant="post.status === 'published' ? 'success' : 'neutral'" class="capitalize">{{ post.status }}</Badge>
                <span class="text-xs text-slate-500 dark:text-slate-400">By {{ post.author?.name || 'Club admin' }}</span>
              </div>

              <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ post.title }}</h3>
              <div v-if="post.tags?.length" class="flex flex-wrap gap-1.5">
                <span v-for="tag in post.tags" :key="tag.id" :class="['rounded-full px-2.5 py-0.5 text-xs font-medium', orderColour(tag.color).soft]">{{ tag.name }}</span>
              </div>
              <p class="line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ post.excerpt || post.content }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 md:shrink-0">
              <Button variant="secondary" size="sm" :href="route('admin.posts.show', { clubSlug: club.slug, id: post.id })">Preview</Button>
              <Button variant="secondary" size="sm" :href="route('admin.posts.edit', { clubSlug: club.slug, id: post.id })">Edit</Button>
              <Button variant="secondary" size="sm" @click="postToDelete = post">Delete</Button>
            </div>
          </div>
        </Card>

        <Pagination :links="posts.links" />
      </div>

      <Card v-else class="text-center">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">No news posts yet</h3>
        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">There are no posts for this club.</p>
        <Button class="mt-4" :href="route('admin.posts.create', { clubSlug: club.slug })">Write the first post</Button>
      </Card>
    </div>

    <ConfirmDialog :open="Boolean(postToDelete)" title="Delete this post?" confirm-label="Delete post" :processing="deleting" @confirm="deletePost" @close="postToDelete = null">
      {{ postToDelete?.title }} will be removed.
    </ConfirmDialog>
  </AdminLayout>
</template>
