<script setup>
// Modules tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
defineProps({
    form: { type: Object, required: true },
    toggleModule: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    allModules: { type: [Object, Array, Function, String, Number, Boolean], required: false }
});
</script>

<template>
    <div class="space-y-6">
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">⚡ Active Club Feature Modules</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Toggle interactive tools enabled for administrators and members in this club workspace.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="mod in allModules"
            :key="mod.code"
            @click="toggleModule(mod.code)"
            :class="[
              'p-4 rounded-2xl border transition-all cursor-pointer flex items-start justify-between gap-4',
              form.enabled_modules.includes(mod.code)
                ? 'bg-blue-50/50 dark:bg-blue-950/50 border-blue-300 dark:border-blue-700/60 ring-2 ring-blue-500/20'
                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 opacity-60'
            ]"
          >
            <div class="flex items-start gap-3">
              <span class="text-2xl">{{ mod.icon }}</span>
              <div>
                <div class="font-bold text-slate-900 dark:text-white text-xs sm:text-sm">{{ mod.name }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ mod.description }}</div>
              </div>
            </div>

            <div :class="['w-10 h-6 rounded-full transition-colors flex items-center p-1', form.enabled_modules.includes(mod.code) ? 'bg-blue-600' : 'bg-slate-300']">
              <div :class="['w-4 h-4 rounded-full bg-white shadow-md transform transition-transform', form.enabled_modules.includes(mod.code) ? 'translate-x-4' : 'translate-x-0']"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
</template>
