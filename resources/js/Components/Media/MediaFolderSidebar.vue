<script setup>
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// The media library's folder list and storage meter, beside the file grid.
const activeFolder = defineModel('activeFolder', { type: String, default: 'all' });

defineProps({
  folders: { type: Array, default: () => [] },
  trashCount: { type: Number, default: 0 },
  storage: { type: Object, required: true },
});
</script>

<template>
<Card padding="sm" class="lg:col-span-1 space-y-3 sticky top-6">
  <div class="text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 px-3 pt-1">
    Folders
  </div>

  <div class="space-y-1">
    <button
      v-for="folder in folders"
      :key="folder.id"
      type="button"
      @click="activeFolder = folder.id"
      :aria-current="activeFolder === folder.id ? 'true' : undefined"
      :class="[
        'w-full flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors text-left',
        activeFolder === folder.id
          ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'
          : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
      ]"
    >
      <span class="flex items-center gap-2.5">
        <UiIcon :name="folder.icon" />
        <span>{{ folder.label }}</span>
      </span>
      <Badge v-if="folder.id === 'trash' && trashCount > 0">{{ trashCount }}</Badge>
    </button>
  </div>

  <!-- Storage Usage -->
  <div v-if="storage.quota_bytes !== null" class="px-3 pt-2 pb-1 space-y-1.5">
    <div class="flex items-center justify-between text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
      <span>Storage used</span>
      <span>{{ storage.percent }}%</span>
    </div>
    <div class="w-full h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
      <div
        class="h-full rounded-full transition-all"
        :class="storage.percent >= 90 ? 'bg-rose-500' : storage.percent >= 70 ? 'bg-amber-500' : 'bg-blue-500'"
        :style="{ width: Math.min(100, storage.percent || 0) + '%' }"
      ></div>
    </div>
    <div class="text-xs text-slate-500 dark:text-slate-400">
      {{ storage.used_human }} of {{ storage.quota_human }} used
    </div>
  </div>
</Card>
</template>
