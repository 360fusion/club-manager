<script setup>
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// Above the file grid: the breadcrumb back out of a custom folder, or (in a fixed folder) its subfolders and the
// new-folder form.
const activeFolder = defineModel('activeFolder', { type: String, default: 'all' });
const showNewFolderForm = defineModel('showNewFolderForm', { type: Boolean, default: false });
const newFolderName = defineModel('newFolderName', { type: String, default: '' });
const newFolderError = defineModel('newFolderError', { type: String, default: '' });

defineProps({
  activeCustomFolder: { type: Object, default: null },
  fixedFolderById: { type: Function, required: true },
  subfoldersOf: { type: Function, required: true },
  isCreatingFolder: { type: Boolean, default: false },
});

defineEmits(['create', 'delete']);
</script>

<template>
  <!-- Breadcrumb: viewing a custom folder, inside one of the fixed folders -->
  <nav v-if="activeCustomFolder" aria-label="Breadcrumb" class="flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400 pb-1">
    <button
      type="button"
      @click="activeFolder = activeCustomFolder.parent_slug"
      class="hover:text-slate-900 dark:hover:text-white hover:underline flex items-center gap-1"
    >
      <UiIcon :name="fixedFolderById(activeCustomFolder.parent_slug)?.icon || 'folder'" />
      <span>{{ fixedFolderById(activeCustomFolder.parent_slug)?.label }}</span>
    </button>
    <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
    <span class="flex items-center gap-1 text-slate-900 dark:text-white" aria-current="page"><UiIcon name="folder" /> {{ activeCustomFolder.name }}</span>
  </nav>

  <!-- Subfolders: viewing one of the fixed folders -->
  <div v-if="activeFolder !== 'all' && activeFolder !== 'trash' && !activeCustomFolder" class="space-y-2 pb-1">
    <div class="flex items-center justify-between">
      <span class="text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Subfolders</span>
      <Button variant="ghost" size="sm" @click="showNewFolderForm = !showNewFolderForm; newFolderError = ''; newFolderName = ''">
        <template #icon><UiIcon name="plus" /></template>
        New folder
      </Button>
    </div>

    <div v-if="showNewFolderForm" class="flex items-center gap-2">
      <Input
        v-model="newFolderName"
        :placeholder="`New folder inside ${fixedFolderById(activeFolder)?.label}`"
        aria-label="New folder name"
        maxlength="60"
        class="flex-1"
        @keyup.enter="$emit('create')"
        @keyup.escape="showNewFolderForm = false"
      />
      <Button size="sm" class="shrink-0" :loading="isCreatingFolder" :disabled="!newFolderName.trim()" @click="$emit('create')">
        Add
      </Button>
    </div>
    <p v-if="newFolderError" class="text-xs text-rose-600 dark:text-rose-400">{{ newFolderError }}</p>

    <div v-if="subfoldersOf(activeFolder).length" class="flex flex-wrap gap-1.5">
      <div
        v-for="sub in subfoldersOf(activeFolder)"
        :key="sub.id"
        class="group relative"
      >
        <button
          type="button"
          @click="activeFolder = sub.slug"
          class="pl-3 pr-8 py-1.5 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium rounded-full flex items-center gap-1.5"
        >
          <UiIcon name="folder" />
          <span>{{ sub.name }}</span>
        </button>
        <button
          type="button"
          @click.stop="$emit('delete', sub)"
          title="Delete folder"
          :aria-label="`Delete the ${sub.name} folder`"
          class="absolute right-1.5 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 focus-visible:opacity-100 transition-opacity p-0.5 rounded-full text-slate-500 dark:text-slate-400 hover:text-rose-600 dark:hover:text-rose-400"
        >
          <UiIcon name="x" />
        </button>
      </div>
    </div>
  </div>
</template>
