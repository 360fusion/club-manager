<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
    templates: {
        type: Array,
        required: true,
    },
});

const selectedTemplate = ref(props.templates[0] || null);

const editForm = useForm({
    subject: '',
    body_html: '',
});

function selectTemplate(tmpl) {
    selectedTemplate.value = tmpl;
    editForm.subject = tmpl.subject;
    editForm.body_html = tmpl.body_html;
}

if (selectedTemplate.value) {
    selectTemplate(selectedTemplate.value);
}

function updateTemplate() {
    if (!selectedTemplate.value) return;
    editForm.put(route('superadmin.email_templates.update', selectedTemplate.value.id), {
        onSuccess: () => {
            // Updated successfully
        },
    });
}

function sendTest() {
    if (!selectedTemplate.value) return;
    editForm.post(route('superadmin.email_templates.test', selectedTemplate.value.id), { preserveScroll: true });
}

const showPreview = ref(false);
const previewHtml = computed(() => {
    const samples = Object.fromEntries((selectedTemplate.value?.available_placeholders || []).map((p) => [`{{${p}}}`, `[${p}]`]));
    return editForm.body_html.replace(/\{\{\w+\}\}/g, (m) => samples[m] ?? m);
});

function insertPlaceholder(variable) {
    editForm.body_html += ` {{${variable}}}`;
}
</script>

<template>
    <SuperAdminLayout title="Default Email Templates Editor">
        <Head title="Superadmin - Email Templates" />

        <div class="space-y-6">
            <!-- Header section -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-5 dark:border-slate-800">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight dark:text-white">Default Email Templates</h1>
                    <p class="text-sm text-slate-500 mt-1 dark:text-slate-400">Manage global email default circulars and automated notification templates across all lodges & chapters.</p>
                </div>
            </div>

            <!-- Main grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Left panel: Template List -->
                <div class="lg:col-span-4 bg-white border border-slate-200 rounded-xl p-4 shadow-xl dark:bg-slate-900 dark:border-slate-800">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3 px-2 dark:text-slate-400">System Templates ({{ templates.length }})</h2>
                    <div class="space-y-2">
                        <div
                            v-for="tmpl in templates"
                            :key="tmpl.id"
                            @click="selectTemplate(tmpl)"
                            :class="[
                                'p-3.5 rounded-lg border cursor-pointer transition-all duration-150',
                                selectedTemplate && selectedTemplate.id === tmpl.id
                                    ? 'bg-blue-50 border-blue-400 text-slate-900 shadow-md dark:bg-blue-950/60 dark:border-blue-600 dark:text-white'
                                    : 'bg-slate-50 border-slate-200 text-slate-700 hover:border-slate-400 hover:text-slate-900 dark:bg-slate-900/60 dark:border-slate-800 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-white'
                            ]"
                        >
                            <div class="font-semibold text-sm">{{ tmpl.name }}</div>
                            <div class="text-xs font-mono text-blue-600/80 mt-1">{{ tmpl.template_key }}</div>
                        </div>
                    </div>
                </div>

                <!-- Right panel: Selected Template Editor -->
                <div v-if="selectedTemplate" class="lg:col-span-8 bg-white border border-slate-200 rounded-xl p-6 shadow-xl space-y-6 dark:bg-slate-900 dark:border-slate-800">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-4 dark:border-slate-800">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ selectedTemplate.name }}</h2>
                            <span class="text-xs font-mono text-slate-500 dark:text-slate-400">{{ selectedTemplate.template_key }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                        <button type="button" @click="showPreview = !showPreview" class="px-3 py-2 border border-slate-300 text-slate-700 dark:border-slate-700 dark:text-slate-200 font-medium text-xs rounded-lg">{{ showPreview ? 'Hide preview' : 'Preview' }}</button>
                        <button type="button" @click="sendTest" :disabled="editForm.processing" class="px-3 py-2 border border-slate-300 text-slate-700 dark:border-slate-700 dark:text-slate-200 font-medium text-xs rounded-lg">Send test to me</button>
                        <button
                            @click="updateTemplate"
                            :disabled="editForm.processing"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-medium text-xs rounded-lg transition inline-flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ editForm.processing ? 'Saving...' : 'Save Template' }}
                        </button>
                        </div>
                    </div>

                    <iframe v-if="showPreview" sandbox="" :srcdoc="previewHtml" class="h-72 w-full rounded-lg border border-slate-300 bg-white dark:border-slate-700"></iframe>

                    <!-- Editor Fields -->
                    <div class="space-y-4 text-xs">
                        <!-- Subject Line -->
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1.5 dark:text-slate-300">Default Email Subject Line</label>
                            <input
                                v-model="editForm.subject"
                                type="text"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 text-sm rounded-lg px-3.5 py-2.5 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 font-medium dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                                required
                            />
                        </div>

                        <!-- Dynamic Placeholder Chips -->
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1.5 dark:text-slate-300">Available Dynamic Placeholders (Click to insert into body)</label>
                            <div class="flex flex-wrap gap-2 p-3 bg-slate-50 border border-slate-200 rounded-lg dark:bg-slate-900/60 dark:border-slate-800">
                                <button
                                    v-for="ph in selectedTemplate.available_placeholders || []"
                                    :key="ph"
                                    @click="insertPlaceholder(ph)"
                                    type="button"
                                    class="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 border border-blue-200 text-blue-700 font-mono text-[11px] rounded-md transition dark:bg-blue-950/60 dark:hover:bg-blue-900/40 dark:border-blue-800/60 dark:text-blue-300"
                                >
                                    &#123;&#123; {{ ph }} &#125;&#125;
                                </button>
                                <span v-if="!selectedTemplate.available_placeholders?.length" class="text-slate-500 italic text-[11px] dark:text-slate-400">No specific dynamic placeholders defined.</span>
                            </div>
                        </div>

                        <!-- HTML Body Content -->
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1.5 dark:text-slate-300">Email Body (HTML Supported)</label>
                            <textarea
                                v-model="editForm.body_html"
                                rows="12"
                                class="w-full bg-slate-100 border border-slate-300 text-slate-900 font-mono text-xs rounded-lg p-3.5 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 leading-relaxed dark:bg-slate-800 dark:border-slate-700 dark:text-white"
                                required
                            ></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
