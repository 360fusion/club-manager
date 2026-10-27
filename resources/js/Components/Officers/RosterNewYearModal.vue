<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Alert from '@/Components/Ui/Alert.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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
    <Modal :open="true" title="Add a Masonic year" @close="$emit('close')">
        <div class="space-y-4">
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span class="block text-sm font-medium text-slate-700 dark:text-slate-300">Installation start year</span>
              <span class="text-xs text-slate-500 dark:text-slate-400">1717 to {{ currentYearNum + 3 }}</span>
            </div>

            <div class="flex items-center gap-2">
              <Input
                type="number"
                v-model.number="selectedStartYear"
                :min="1717"
                :max="currentYearNum + 3"
                placeholder="e.g. 1849"
                aria-label="Installation start year"
                class="w-32"
              />
              <Input
                v-model="yearSearchInput"
                placeholder="Search for a year, e.g. 1850"
                aria-label="Search for a year"
                class="flex-1"
              />
            </div>

            <div class="max-h-40 overflow-y-auto border border-slate-200 dark:border-slate-800 rounded-xl p-1 bg-slate-50 dark:bg-slate-800/50 space-y-0.5">
              <button
                v-for="y in filteredStartYearOptions"
                :key="y"
                type="button"
                @click="selectedStartYear = y"
                :class="[
                  'w-full text-left px-3 py-1.5 rounded-xl text-sm font-medium transition-colors flex items-center justify-between cursor-pointer',
                  selectedStartYear === y ? 'bg-blue-600 text-white' : 'hover:bg-slate-200/80 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200'
                ]"
              >
                <span>{{ y }} (installation start)</span>
                <span v-if="selectedStartYear === y" class="flex items-center gap-1 text-xs">
                  <UiIcon name="check" :size="14" />
                  Selected
                </span>
              </button>
              <div v-if="!filteredStartYearOptions.length" class="p-3 text-center text-sm text-slate-500 dark:text-slate-400">
                No available start year matching "{{ yearSearchInput }}"
              </div>
            </div>
          </div>

          <Alert variant="info">
            <div class="flex items-center justify-between gap-2 font-medium">
              <span>Masonic year</span>
              <span class="text-sm font-semibold">{{ calculatedMasonicYear }}</span>
            </div>
            <p>
              Period: 1st {{ installationMonth }} {{ selectedStartYear }} – 30th {{ installationMonth }} {{ calculatedEndYear }}
            </p>
          </Alert>
        </div>

        <template #footer>
          <Button variant="secondary" @click="$emit('close')">Cancel</Button>
          <Button @click="createNewMasonicYear">Start roster draft</Button>
        </template>
    </Modal>
</template>
