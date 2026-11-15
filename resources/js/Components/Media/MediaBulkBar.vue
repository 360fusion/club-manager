<script setup>
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// The bar that appears while files are selected: select all, then restore or delete them (trash) or move them.
const selectedMediaIds = defineModel('selectedMediaIds', { type: Array, default: () => [] });
const targetBulkFolder = defineModel('targetBulkFolder', { type: String, default: 'images' });

defineProps({
  activeFolder: { type: String, default: 'all' },
  itemCount: { type: Number, default: 0 },
  selectableFolders: { type: Array, default: () => [] },
});

defineEmits(['toggle-all', 'restore', 'force-delete', 'move', 'delete']);
</script>

<template>
  <!-- Bulk Actions Floating Control Bar -->
  <div v-if="selectedMediaIds.length > 0" class="p-3 bg-blue-50 border border-blue-200 dark:bg-blue-950/40 dark:border-blue-800/60 rounded-2xl flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <Badge variant="info">{{ selectedMediaIds.length }} selected</Badge>
      <Button variant="ghost" size="sm" @click="$emit('toggle-all')">
        {{ selectedMediaIds.length === itemCount ? 'Deselect all' : 'Select all' }}
      </Button>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <template v-if="activeFolder === 'trash'">
        <Button variant="secondary" size="sm" @click="$emit('restore')">
          <template #icon><UiIcon name="restore" /></template>
          Restore selected
        </Button>
        <Button variant="danger" size="sm" @click="$emit('force-delete')">
          <template #icon><UiIcon name="trash" /></template>
          Delete permanently
        </Button>
      </template>
      <template v-else>
        <!-- Target Folder Selection for Bulk Move -->
        <Select
          v-model="targetBulkFolder"
          aria-label="Move selected files to"
          class="w-44"
          :options="selectableFolders.map(f => ({ value: f.id, label: f.label }))"
        />

        <Button variant="secondary" size="sm" @click="$emit('move')">
          <template #icon><UiIcon name="folder" /></template>
          Move
        </Button>

        <Button variant="secondary" size="sm" @click="$emit('delete')">
          <template #icon><UiIcon name="trash" /></template>
          Move to trash
        </Button>
      </template>

      <Button variant="ghost" size="sm" @click="selectedMediaIds = []">
        <template #icon><UiIcon name="x" /></template>
        Clear
      </Button>
    </div>
  </div>
</template>
