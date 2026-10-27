<script setup>


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
</script>

<template>
    <div class="space-y-4">
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-200">Showing</span>
          <select v-model="statementLinesFilter" class="px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded text-xs font-semibold text-slate-800 dark:text-slate-100">
            <option value="statement_lines">Statement lines</option>
            <option value="deleted">Deleted statement lines</option>
            <option value="all">All statement lines</option>
          </select>
          <span v-if="selectedStatementLineIds.length === 0" class="text-xs text-slate-400">No transactions selected</span>
          <span v-else class="text-xs font-bold text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40 px-2 py-0.5 rounded border border-blue-200 dark:border-blue-800/60">
            {{ selectedStatementLineIds.length }} transaction(s) selected
          </span>
        </div>
        <div class="flex items-center gap-2">
          <button
            v-if="deletableStatementLines.length > 0"
            type="button"
            @click="deleteSelectedStatementLines"
            class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs rounded-lg shadow transition-all cursor-pointer flex items-center gap-1.5"
          >
            Delete ({{ deletableStatementLines.length }})
          </button>
          <button
            v-if="restorableStatementLines.length > 0"
            type="button"
            @click="restoreSelectedStatementLines"
            class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs rounded-lg shadow transition-all cursor-pointer flex items-center gap-1.5"
          >
            Restore ({{ restorableStatementLines.length }})
          </button>
          <button type="button" @click="$emit('open-import')" class="px-3.5 py-1.5 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-extrabold text-xs rounded-lg shadow cursor-pointer">
            + Upload Statement
          </button>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead class="bg-slate-50 dark:bg-slate-800/50 text-[11px] font-bold text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-800">
            <tr>
              <th class="py-2.5 px-3 w-8">
                <input
                  type="checkbox"
                  @change="toggleStatementLinesSelectAll"
                  :checked="selectedStatementLineIds.length === filteredStatementLines.length && filteredStatementLines.length > 0"
                  class="rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 cursor-pointer"
                />
              </th>
              <th class="py-2.5 px-3">Date</th>
              <th class="py-2.5 px-3">Type</th>
              <th class="py-2.5 px-3">Particulars / Description</th>
              <th class="py-2.5 px-3">Reference</th>
              <th class="py-2.5 px-3 text-right">Spent</th>
              <th class="py-2.5 px-3 text-right">Received</th>
              <th class="py-2.5 px-3 text-center">Source</th>
              <th class="py-2.5 px-3 text-center">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
            <tr v-for="line in filteredStatementLines" :key="line.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
              <td class="py-2.5 px-3">
                <input
                  type="checkbox"
                  :value="line.id"
                  v-model="selectedStatementLineIds"
                  class="rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 cursor-pointer"
                />
              </td>
              <td class="py-2.5 px-3 font-mono text-[11px] text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ line.transaction_date }}</td>
              <td class="py-2.5 px-3 text-slate-600 dark:text-slate-300 font-medium">{{ line.type }}</td>
              <td class="py-2.5 px-3 font-bold text-slate-900 dark:text-white">{{ line.raw_description }}</td>
              <td class="py-2.5 px-3 text-slate-500 dark:text-slate-400 font-mono text-[11px]">{{ line.reference }}</td>
              <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900 dark:text-white">{{ line.spent }}</td>
              <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-700 dark:text-emerald-300">{{ line.received }}</td>
              <td class="py-2.5 px-3 text-center text-slate-500 dark:text-slate-400 text-[11px]">{{ line.source }}</td>
              <td class="py-2.5 px-3 text-center whitespace-nowrap">
                <span
                  :class="[
                    'px-2.5 py-0.5 text-[10px] font-black rounded-full uppercase tracking-wider',
                    line.status === 'Reconciled' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200' :
                    line.status === 'Deleted' ? 'bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-800/60' :
                    'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200'
                  ]"
                >
                  {{ line.status }}
                </span>
              </td>
            </tr>
            <tr v-if="filteredStatementLines.length === 0">
              <td colspan="9" class="py-8 text-center text-slate-400 italic">No statement lines found for selected filter.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
