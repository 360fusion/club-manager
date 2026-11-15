<script setup>
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { getFileIcon, isImage } from '@/Composables/useMediaLibrary';

// The media library's file cards (or the loading and empty states) and the "Load more" button.
defineProps({
  mediaItems: { type: Array, default: () => [] },
  isLoading: { type: Boolean, default: false },
  isLoadingMore: { type: Boolean, default: false },
  hasMore: { type: Boolean, default: false },
  totalFiles: { type: Number, default: 0 },
  selectedMediaIds: { type: Array, default: () => [] },
  activeFolder: { type: String, default: 'all' },
});

defineEmits(['open', 'toggle-select', 'copy', 'restore', 'force-delete', 'delete', 'upload', 'load-more']);
</script>

<template>
  <!-- Files Grid -->
  <div>
    <div v-if="isLoading" class="flex items-center justify-center gap-2 py-20 text-slate-500 dark:text-slate-400 text-sm">
      <UiIcon name="refresh" class="animate-spin" /> Loading files…
    </div>

    <div v-else-if="!mediaItems.length" class="text-center py-20 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl bg-slate-50/50 dark:bg-slate-800/50 space-y-3">
      <span class="flex justify-center text-slate-500 dark:text-slate-400"><UiIcon name="folder" :size="20" /></span>
      <div>
        <span class="text-sm font-medium text-slate-900 dark:text-white block">No files in this folder</span>
        <span class="text-xs text-slate-500 dark:text-slate-400">Drag and drop files here, or choose them below.</span>
      </div>
      <Button variant="secondary" size="sm" class="mt-2" @click="$emit('upload')">
        <template #icon><UiIcon name="upload" /></template>
        Choose files to upload
      </Button>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
      <div
        v-for="item in mediaItems"
        :key="item.id"
        :class="[
          'group rounded-2xl border p-3 transition-all shadow-sm hover:shadow-md flex flex-col justify-between relative',
          selectedMediaIds.includes(item.id)
            ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-400 ring-2 ring-blue-200 dark:ring-blue-800/60'
            : 'bg-white dark:bg-slate-900 hover:border-slate-300 dark:hover:border-slate-700 border-slate-200 dark:border-slate-800'
        ]"
      >
        <!-- Image or Icon Box -->
        <div
          @click="$emit('open', item)"
          class="h-40 w-full rounded-xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-center relative mb-3 cursor-pointer group-hover:border-blue-300 dark:group-hover:border-blue-700/60"
        >
          <img
            v-if="isImage(item.mime_type || item.file_name)"
            :src="item.original_url"
            :alt="item.alt_text || item.name"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
          />
          <div v-else class="flex flex-col items-center justify-center space-y-1">
            <span class="text-slate-500 dark:text-slate-400"><UiIcon :name="getFileIcon(item.mime_type || item.file_name)" :size="20" /></span>
            <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ item.file_name.split('.').pop() }}</span>
          </div>

          <!-- Multi-Select Checkbox -->
          <div class="absolute top-2 right-2 z-20" @click.stop>
            <input
              type="checkbox"
              :checked="selectedMediaIds.includes(item.id)"
              @change="$emit('toggle-select', item.id)"
              :aria-label="`Select ${item.name || item.file_name}`"
              class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950"
            />
          </div>

          <!-- Folder Collection Tag & Badges -->
          <div class="absolute top-2 left-2 flex flex-col gap-1 items-start z-10">
            <Badge>{{ item.collection_name }}</Badge>
            <Badge v-if="item.is_variant" variant="info">Variant</Badge>
            <Badge v-if="item.is_trashed" variant="danger">Trashed</Badge>
            <Badge v-if="item.version_count > 0">
              {{ item.version_count }} {{ item.version_count === 1 ? 'version' : 'versions' }}
            </Badge>
          </div>

          <!-- Hover Inspect Overlay -->
          <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-medium gap-1">
            <UiIcon name="eye" />
            <span>Details and edit</span>
          </div>
        </div>

        <!-- File Info -->
        <div class="space-y-1 cursor-pointer" @click="$emit('open', item)">
          <div class="font-medium text-slate-900 dark:text-white text-sm truncate group-hover:text-blue-700 dark:group-hover:text-blue-300" :title="item.name || item.file_name">
            {{ item.name || item.file_name }}
          </div>
          <div v-if="item.alt_text" class="text-[11px] text-slate-500 dark:text-slate-400 italic truncate">
            Alt: "{{ item.alt_text }}"
          </div>
          <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
            <span>{{ item.human_size }}</span>
            <span>{{ item.created_at }}</span>
          </div>
        </div>

        <!-- Card Bottom Controls -->
        <div class="pt-3 border-t border-slate-200 dark:border-slate-800 mt-3 flex items-center justify-between gap-1">
          <Button variant="secondary" size="sm" title="View details and edit" @click="$emit('open', item)">
            <template #icon><UiIcon name="eye" /></template>
            Details
          </Button>

          <Button variant="ghost" size="sm" title="Copy the file's link" @click="$emit('copy', item)">
            <template #icon><UiIcon name="copy" /></template>
            Copy
          </Button>

          <template v-if="activeFolder === 'trash' || item.is_trashed">
            <Button variant="secondary" size="sm" title="Restore the file from the trash" @click="$emit('restore', item)">
              <template #icon><UiIcon name="restore" /></template>
              Restore
            </Button>
            <button
              type="button"
              @click="$emit('force-delete', item)"
              class="rounded-xl p-1.5 text-slate-500 dark:text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400"
              title="Delete permanently"
              aria-label="Delete permanently"
            >
              <UiIcon name="trash" />
            </button>
          </template>
          <template v-else>
            <button
              type="button"
              @click="$emit('delete', item)"
              class="rounded-xl p-1.5 text-slate-500 dark:text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400"
              title="Move to trash"
              aria-label="Move to trash"
            >
              <UiIcon name="trash" />
            </button>
          </template>
        </div>
      </div>
    </div>

    <div v-if="hasMore && !isLoading" class="flex justify-center pt-2">
      <Button variant="secondary" size="sm" :disabled="isLoadingMore" @click="$emit('load-more')">
        {{ isLoadingMore ? 'Loading…' : `Load more files (${mediaItems.length} of ${totalFiles})` }}
      </Button>
    </div>

  </div>
</template>
