<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    // { name, key, subject, body_html, available_placeholders, customised? }
    template: { type: Object, required: true },
    processing: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
    // Show "Reset to default" (only for wording a lodge has changed).
    canReset: { type: Boolean, default: false },
});

const emit = defineEmits(['save', 'test', 'reset']);

const subject = ref('');
const body = ref('');
const showPreview = ref(false);

watch(() => props.template.key, () => {
    subject.value = props.template.subject;
    body.value = props.template.body_html;
    showPreview.value = false;
}, { immediate: true });

watch(() => [props.template.subject, props.template.body_html], () => {
    subject.value = props.template.subject;
    body.value = props.template.body_html;
});

const previewHtml = computed(() => {
    const samples = Object.fromEntries((props.template.available_placeholders || []).map((p) => [`{{${p}}}`, `[${p}]`]));
    return body.value.replace(/\{\{\s*\w+\s*\}\}/g, (m) => samples[m.replace(/\s/g, '')] ?? m);
});

const payload = () => ({ subject: subject.value, body_html: body.value });
const insertPlaceholder = (name) => { body.value += ` {{${name}}}`; };
</script>

<template>
    <div class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4 dark:border-slate-800">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">
                    {{ template.name }}
                    <span v-if="canReset" class="ml-2 rounded bg-amber-100 px-1.5 py-0.5 align-middle text-[10px] font-bold uppercase text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Customised</span>
                </h2>
                <span class="font-mono text-xs text-slate-500 dark:text-slate-400">{{ template.key }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200" @click="showPreview = !showPreview">{{ showPreview ? 'Hide preview' : 'Preview' }}</button>
                <button type="button" :disabled="processing" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200" @click="emit('test', payload())">Send test to me</button>
                <button v-if="canReset" type="button" :disabled="processing" class="rounded-lg border border-rose-300 px-3 py-2 text-xs font-medium text-rose-700 dark:border-rose-800 dark:text-rose-300" @click="emit('reset')">Reset to default</button>
                <button type="button" :disabled="processing" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-blue-500" @click="emit('save', payload())">
                    {{ processing ? 'Saving...' : 'Save' }}
                </button>
            </div>
        </div>

        <iframe v-if="showPreview" sandbox="" :srcdoc="previewHtml" title="Email preview" class="h-72 w-full rounded-lg border border-slate-300 bg-white dark:border-slate-700"></iframe>

        <div class="space-y-4 text-xs">
            <div>
                <label class="mb-1.5 block font-semibold text-slate-700 dark:text-slate-300">Subject line</label>
                <input v-model="subject" type="text" maxlength="255" class="w-full rounded-lg border border-slate-300 bg-slate-100 px-3.5 py-2.5 text-sm font-medium text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white" />
                <p v-if="errors.subject" class="mt-1 font-semibold text-rose-600">{{ errors.subject }}</p>
            </div>

            <div>
                <label class="mb-1.5 block font-semibold text-slate-700 dark:text-slate-300">Placeholders (click to add to the body)</label>
                <div class="flex flex-wrap gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900/60">
                    <button v-for="ph in template.available_placeholders || []" :key="ph" type="button" class="rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 font-mono text-[11px] text-blue-700 transition hover:bg-blue-100 dark:border-blue-800/60 dark:bg-blue-950/60 dark:text-blue-300" @click="insertPlaceholder(ph)">
                        &#123;&#123; {{ ph }} &#125;&#125;
                    </button>
                    <span v-if="!template.available_placeholders?.length" class="text-[11px] italic text-slate-500 dark:text-slate-400">No placeholders for this email.</span>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block font-semibold text-slate-700 dark:text-slate-300">Email body (HTML)</label>
                <textarea v-model="body" rows="12" maxlength="50000" class="w-full rounded-lg border border-slate-300 bg-slate-100 p-3.5 font-mono text-xs leading-relaxed text-slate-900 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"></textarea>
                <p v-if="errors.body_html" class="mt-1 font-semibold text-rose-600">{{ errors.body_html }}</p>
            </div>
        </div>
    </div>
</template>
