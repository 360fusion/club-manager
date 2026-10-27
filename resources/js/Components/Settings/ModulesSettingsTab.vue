<script setup>
// Modules tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
import Card from '@/Components/Ui/Card.vue';

defineProps({
    form: { type: Object, required: true },
    toggleModule: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    allModules: { type: [Object, Array, Function, String, Number, Boolean], required: false }
});
</script>

<template>
    <div class="space-y-6">
      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Feature modules</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Toggle interactive tools enabled for administrators and members in this club workspace.</p>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="mod in allModules"
            :key="mod.code"
            @click="toggleModule(mod.code)"
            :class="[
              'p-4 rounded-xl border transition-colors cursor-pointer flex items-start justify-between gap-4',
              form.enabled_modules.includes(mod.code)
                ? 'bg-blue-50/50 dark:bg-blue-950/40 border-blue-300 dark:border-blue-700/60'
                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'
            ]"
          >
            <div>
              <div class="text-sm font-medium text-slate-900 dark:text-white">{{ mod.name }}</div>
              <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ mod.description }}</div>
            </div>

            <div class="flex shrink-0 items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
              <span :class="['relative inline-block w-9 h-5 rounded-full transition-colors', form.enabled_modules.includes(mod.code) ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-600']">
                <span :class="['absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform', form.enabled_modules.includes(mod.code) ? 'translate-x-4' : '']"></span>
              </span>
              <span class="w-6">{{ form.enabled_modules.includes(mod.code) ? 'On' : 'Off' }}</span>
            </div>
          </div>
        </div>
      </Card>
    </div>
</template>
