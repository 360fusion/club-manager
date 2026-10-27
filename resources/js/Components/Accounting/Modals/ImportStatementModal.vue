<script setup>


// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    importForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitStatementImport: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="$emit('close')">
      <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div>
            <h3 class="text-base font-black text-slate-900 dark:text-white">Import Bank Statement</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Upload UK CSV or OFX bank export for automated matcher staging.</p>
          </div>
          <button type="button" @click="$emit('close')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm">✕</button>
        </div>

        <form @submit.prevent="submitStatementImport" class="space-y-4 text-xs">
          <div class="space-y-1">
            <label class="block font-bold text-slate-700 dark:text-slate-200">Bank Statement File (CSV/OFX) *</label>
            <input
              type="file"
              @change="importForm.statement_file = $event.target.files[0]"
              accept=".csv,.txt,.ofx,.qfx"
              class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs focus:outline-none focus:border-blue-500"
              required
            />
          </div>

          <div class="p-3 bg-blue-50 dark:bg-blue-950/40 rounded-xl border border-blue-200 dark:border-blue-800/60 text-blue-900 dark:text-blue-200 text-[11px] space-y-1">
            <span class="font-extrabold block">💡 Supported Formats</span>
            <p class="text-blue-800 dark:text-blue-200">
              Standard UK bank CSV exports (Barclays, HSBC, Lloyds, NatWest, Santander, Starling) and OFX/QFX files.
            </p>
          </div>

          <div class="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="$emit('close')"
              class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="importForm.processing"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-sm cursor-pointer"
            >
              Upload &amp; process statement
            </button>
          </div>
        </form>
      </div>
    </div>
</template>
