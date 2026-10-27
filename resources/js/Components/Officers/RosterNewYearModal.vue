<script setup>


// A dialog of the officer roster page, in its own file so the page stays readable.
const selectedStartYear = defineModel('selectedStartYear', { type: [String, Number, Boolean, Array, Object], default: undefined });
const yearSearchInput = defineModel('yearSearchInput', { type: [String, Number, Boolean, Array, Object], default: undefined });
defineEmits(['close']);

defineProps({
    calculatedEndYear: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    calculatedMasonicYear: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    createNewMasonicYear: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    currentYearNum: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredStartYearOptions: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    installationMonth: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
          <h3 class="text-base font-bold text-slate-900 dark:text-white">Add New Masonic Year Roster</h3>
          <button @click="$emit('close')" class="text-slate-400 text-lg font-bold">✕</button>
        </div>

        <div class="space-y-4">
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase">Installation Start Year</label>
              <span class="text-[10px] text-slate-400 font-semibold">1717 to {{ currentYearNum + 3 }}</span>
            </div>

            <!-- Direct Number Input + Filter Input Row -->
            <div class="flex items-center gap-2">
              <input
                type="number"
                v-model.number="selectedStartYear"
                :min="1717"
                :max="currentYearNum + 3"
                placeholder="e.g. 1849"
                class="w-32 px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"
              />
              <input
                type="text"
                v-model="yearSearchInput"
                placeholder="Search year (e.g. 1850)..."
                class="flex-1 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <!-- Compact Scrollable List (Fixed Max Height 160px) -->
            <div class="max-h-40 overflow-y-auto border border-slate-200 dark:border-slate-800 rounded-xl p-1 bg-slate-50 dark:bg-slate-800/50 space-y-0.5">
              <button
                v-for="y in filteredStartYearOptions"
                :key="y"
                type="button"
                @click="selectedStartYear = y"
                :class="[
                  'w-full text-left px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center justify-between cursor-pointer',
                  selectedStartYear === y ? 'bg-blue-600 text-white shadow-xs' : 'hover:bg-slate-200/80 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200'
                ]"
              >
                <span>{{ y }} (installation start)</span>
                <span v-if="selectedStartYear === y" class="text-[10px] uppercase font-black bg-white/20 dark:bg-slate-900/20 px-1.5 py-0.5 rounded">Selected</span>
              </button>
              <div v-if="!filteredStartYearOptions.length" class="p-3 text-center text-xs text-slate-400">
                No available start year matching "{{ yearSearchInput }}"
              </div>
            </div>
          </div>

          <!-- Dynamic Preview Box -->
          <div class="p-3.5 bg-blue-50/70 dark:bg-blue-950/70 border border-blue-200/80 dark:border-blue-800/80 rounded-2xl space-y-1">
            <div class="flex items-center justify-between text-xs font-black text-blue-950 dark:text-blue-100">
              <span>Calculated Masonic Year:</span>
              <span class="text-sm font-black text-blue-700 dark:text-blue-300 bg-white dark:bg-slate-900 px-2.5 py-0.5 rounded-lg border border-blue-200/60 dark:border-blue-800/60">
                {{ calculatedMasonicYear }}
              </span>
            </div>
            <p class="text-[11px] text-blue-800 dark:text-blue-200 font-medium">
              Period: 1st {{ installationMonth }} {{ selectedStartYear }} – 30th {{ installationMonth }} {{ calculatedEndYear }}
            </p>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
          <button type="button" @click="$emit('close')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl">Cancel</button>
          <button type="button" @click="createNewMasonicYear" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md cursor-pointer">
            Start roster draft
          </button>
        </div>
      </div>
    </div>
</template>
