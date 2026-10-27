<script setup>
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// A section of the accounting reconciliation screen, in its own file so the page stays readable.
const statementLinesFilter = defineModel('statementLinesFilter', { type: [String, Number, Boolean, Array, Object], default: undefined });
const selectedStatementLineIds = defineModel('selectedStatementLineIds', { type: [String, Number, Boolean, Array, Object], default: undefined });
defineEmits(['open-import']);

defineProps({
    deletableStatementLines: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    deleteSelectedStatementLines: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredStatementLines: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    restorableStatementLines: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    restoreSelectedStatementLines: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    showImportModal: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    toggleStatementLinesSelectAll: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const statementFilterOptions = [
    { value: 'statement_lines', label: 'Statement lines' },
    { value: 'deleted', label: 'Deleted statement lines' },
    { value: 'all', label: 'All statement lines' },
];
</script>

<template>
    <div class="space-y-4">
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
          <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Showing</span>
          <Select v-model="statementLinesFilter" aria-label="Showing" :options="statementFilterOptions" />
          <span v-if="selectedStatementLineIds.length === 0" class="text-xs text-slate-500 dark:text-slate-400">No transactions selected</span>
          <Badge v-else variant="info">
            {{ selectedStatementLineIds.length }} transaction(s) selected
          </Badge>
        </div>
        <div class="flex items-center gap-2">
          <Button
            v-if="deletableStatementLines.length > 0"
            variant="danger"
            size="sm"
            @click="deleteSelectedStatementLines"
          >
            <template #icon><UiIcon name="trash" :size="14" /></template>
            Delete ({{ deletableStatementLines.length }})
          </Button>
          <Button
            v-if="restorableStatementLines.length > 0"
            variant="secondary"
            size="sm"
            @click="restoreSelectedStatementLines"
          >
            <template #icon><UiIcon name="restore" :size="14" /></template>
            Restore ({{ restorableStatementLines.length }})
          </Button>
          <Button size="sm" @click="$emit('open-import')">
            <template #icon><UiIcon name="upload" :size="14" /></template>
            Upload statement
          </Button>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="py-2.5 px-3 w-8">
                <input
                  type="checkbox"
                  aria-label="Select all statement lines"
                  @change="toggleStatementLinesSelectAll"
                  :checked="selectedStatementLineIds.length === filteredStatementLines.length && filteredStatementLines.length > 0"
                  class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-900 cursor-pointer"
                />
              </th>
              <th scope="col" class="py-2.5 px-3 font-medium">Date</th>
              <th scope="col" class="py-2.5 px-3 font-medium">Type</th>
              <th scope="col" class="py-2.5 px-3 font-medium">Particulars / description</th>
              <th scope="col" class="py-2.5 px-3 font-medium">Reference</th>
              <th scope="col" class="py-2.5 px-3 font-medium text-right">Spent</th>
              <th scope="col" class="py-2.5 px-3 font-medium text-right">Received</th>
              <th scope="col" class="py-2.5 px-3 font-medium text-center">Source</th>
              <th scope="col" class="py-2.5 px-3 font-medium text-center">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-for="line in filteredStatementLines" :key="line.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
              <td class="py-2.5 px-3">
                <input
                  type="checkbox"
                  :value="line.id"
                  v-model="selectedStatementLineIds"
                  :aria-label="`Select ${line.raw_description}`"
                  class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-900 cursor-pointer"
                />
              </td>
              <td class="py-2.5 px-3 tabular-nums text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ line.transaction_date }}</td>
              <td class="py-2.5 px-3 text-slate-600 dark:text-slate-300">{{ line.type }}</td>
              <td class="py-2.5 px-3 font-medium text-slate-900 dark:text-white">{{ line.raw_description }}</td>
              <td class="py-2.5 px-3 text-slate-500 dark:text-slate-400 font-mono text-[11px]">{{ line.reference }}</td>
              <td class="py-2.5 px-3 text-right tabular-nums font-medium text-slate-900 dark:text-white">{{ line.spent }}</td>
              <td class="py-2.5 px-3 text-right tabular-nums font-medium text-emerald-700 dark:text-emerald-300">{{ line.received }}</td>
              <td class="py-2.5 px-3 text-center text-slate-500 dark:text-slate-400 text-[11px]">{{ line.source }}</td>
              <td class="py-2.5 px-3 text-center whitespace-nowrap">
                <Badge :variant="line.status === 'Reconciled' ? 'success' : line.status === 'Deleted' ? 'danger' : 'warning'">
                  {{ line.status }}
                </Badge>
              </td>
            </tr>
            <tr v-if="filteredStatementLines.length === 0">
              <td colspan="9" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">No statement lines found for the selected filter.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
