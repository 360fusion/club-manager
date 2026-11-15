<script setup>
// News tags tab of the club settings. The tags save themselves as they are edited, not with the page's Save changes.
import Card from '@/Components/Ui/Card.vue';
import NewsTagsManager from '@/Components/NewsTagsManager.vue';

defineProps({
    club: { type: Object, required: true },
    newsTags: { type: Array, default: () => [] },
});
</script>

<template>
      <div class="space-y-6">
        <Card>
          <template #header>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">News tags</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">The tags available when writing a news item. Editors can only choose from this list, and members can filter news by them. Tags are saved as you add or edit them; you don't need to press Save changes.</p>
          </template>
          <NewsTagsManager :club-slug="club.slug" :tags="newsTags" />
        </Card>
      </div>
</template>
