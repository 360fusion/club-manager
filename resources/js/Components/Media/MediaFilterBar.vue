<script setup>
import Button from '@/Components/Ui/Button.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// The search box, file count, filter dropdowns and reset button above the media library's file grid.
const searchQuery = defineModel('searchQuery', { type: String, default: '' });
const filterType = defineModel('filterType', { type: String, default: 'all' });
const filterExtension = defineModel('filterExtension', { type: String, default: 'all' });
const filterDate = defineModel('filterDate', { type: String, default: 'all' });
const sortBy = defineModel('sortBy', { type: String, default: 'newest' });

defineProps({
  shownCount: { type: Number, default: 0 },
  hasMore: { type: Boolean, default: false },
  totalFiles: { type: Number, default: 0 },
  activeFolderLabel: { type: String, default: '' },
  availableExtensions: { type: Array, default: () => [] },
  availableMonths: { type: Array, default: () => [] },
});

defineEmits(['reset']);
</script>

<template>
  <!-- Inline Search & Filter Controls Bar -->
  <div class="space-y-4 pb-4 border-b border-slate-200 dark:border-slate-800">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">

      <!-- Search Input Bar -->
      <div class="relative flex-1">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 dark:text-slate-400"><UiIcon name="search" /></span>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search files by name"
          aria-label="Search files by name"
          class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-9 text-sm text-slate-900 placeholder-slate-400 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder-slate-500"
        />
        <button
          v-if="searchQuery"
          type="button"
          @click="searchQuery = ''"
          aria-label="Clear search"
          class="absolute right-2 top-1/2 -translate-y-1/2 rounded-xl p-1 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
        >
          <UiIcon name="x" />
        </button>
      </div>

      <!-- Counter Badge -->
      <div class="text-xs font-medium text-slate-500 dark:text-slate-400 shrink-0">
        Showing {{ shownCount }}<template v-if="hasMore"> of {{ totalFiles }}</template> {{ totalFiles === 1 ? 'file' : 'files' }} in <span class="text-slate-900 dark:text-white font-semibold">{{ activeFolderLabel }}</span>
      </div>
    </div>

    <!-- Inline Filter Dropdowns Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
      <Select
        v-model="filterType"
        label="Type"
        :options="[{ value: 'all', label: 'All types' }, { value: 'image', label: 'Images only' }, { value: 'document', label: 'Documents only' }]"
      />

      <!-- Only the extensions the club's files actually have -->
      <Select
        v-model="filterExtension"
        label="Extension"
        :options="[{ value: 'all', label: 'All extensions' }, ...availableExtensions.map(ext => ({ value: ext, label: `.${ext.toUpperCase()}` }))]"
      />

      <Select
        v-model="filterDate"
        label="Date added"
        :options="[{ value: 'all', label: 'All dates' }, ...availableMonths.map(m => ({ value: m.value, label: m.label }))]"
      />

      <Select
        v-model="sortBy"
        label="Sort"
        :options="[
          { value: 'newest', label: 'Newest first' },
          { value: 'oldest', label: 'Oldest first' },
          { value: 'name_asc', label: 'Name (A to Z)' },
          { value: 'name_desc', label: 'Name (Z to A)' },
          { value: 'size_desc', label: 'Size (largest)' },
          { value: 'size_asc', label: 'Size (smallest)' },
        ]"
      />
    </div>

    <div v-if="searchQuery || filterType !== 'all' || filterExtension !== 'all' || filterDate !== 'all' || sortBy !== 'newest'">
      <Button variant="ghost" size="sm" title="Reset all search and filter settings" @click="$emit('reset')">
        <template #icon><UiIcon name="x" /></template>
        Reset filters
      </Button>
    </div>
  </div>
</template>
