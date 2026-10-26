<script setup>
// The supporting document on a bill or invoice: shows the one already attached (if any) and takes a new file, which
// replaces it on save. The server accepts PDF and images up to 10 MB.
import { useId } from 'vue';
import { formatBytes } from '@/Utils/fileSize';

defineProps({
    modelValue: { type: Object, default: null },
    label: { type: String, default: 'Supporting document' },
    currentUrl: { type: String, default: '' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);
const id = useId();
</script>

<template>
    <div class="space-y-1.5">
        <label :for="id" class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ label }}</label>
        <p v-if="currentUrl" class="text-xs text-slate-600 dark:text-slate-300">
            Attached now: <a :href="currentUrl" target="_blank" rel="noopener" class="font-medium text-blue-600 underline dark:text-blue-400">view file</a>. A new file replaces it.
        </p>
        <input
            :id="id"
            type="file"
            accept=".pdf,.png,.jpg,.jpeg,.webp"
            :aria-describedby="`${id}-hint`"
            class="block w-full cursor-pointer text-xs text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200 dark:hover:file:bg-slate-700"
            @change="emit('update:modelValue', $event.target.files[0] || null)"
        />
        <p v-if="error" class="text-xs text-rose-600 dark:text-rose-400">{{ error }}</p>
        <p v-else :id="`${id}-hint`" class="text-xs text-slate-500 dark:text-slate-400">
            <template v-if="modelValue">Chosen: {{ modelValue.name }} ({{ formatBytes(modelValue.size) }})</template>
            <template v-else>PDF, PNG, JPG or WEBP, up to 10 MB.</template>
        </p>
    </div>
</template>
