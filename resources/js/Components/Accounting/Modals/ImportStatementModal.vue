<script setup>
import { useId } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Alert from '@/Components/Ui/Alert.vue';

// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    importForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitStatementImport: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const fileInputId = useId();
</script>

<template>
    <Modal :open="true" title="Import a bank statement" subtitle="Upload a UK CSV or OFX bank export to stage it for matching." @close="$emit('close')">
        <form @submit.prevent="submitStatementImport" class="space-y-4">
          <div class="space-y-1.5">
            <label :for="fileInputId" class="block text-xs font-medium text-slate-700 dark:text-slate-300">
              Bank statement file (CSV or OFX)
              <span class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span>
            </label>
            <input
              :id="fileInputId"
              type="file"
              @change="importForm.statement_file = $event.target.files[0]"
              accept=".csv,.txt,.ofx,.qfx"
              class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm text-slate-900 dark:text-white file:mr-3 file:rounded-xl file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 dark:file:bg-slate-800 dark:file:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              required
            />
          </div>

          <Alert title="Supported formats">
            Standard UK bank CSV exports (Barclays, HSBC, Lloyds, NatWest, Santander, Starling) and OFX/QFX files.
          </Alert>

          <div class="mt-4 flex items-center justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
            <Button variant="secondary" @click="$emit('close')">Cancel</Button>
            <Button type="submit" :loading="importForm.processing">Upload and process statement</Button>
          </div>
        </form>
    </Modal>
</template>
