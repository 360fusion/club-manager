<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import EmailTemplateEditor from '@/Components/EmailTemplateEditor.vue';

const props = defineProps({
    templates: {
        type: Array,
        required: true,
    },
});

const selectedId = ref(props.templates[0]?.id ?? null);
const selected = computed(() => {
    const template = props.templates.find((t) => t.id === selectedId.value);
    return template ? { ...template, key: template.template_key } : null;
});

const form = useForm({ subject: '', body_html: '' });

function send(method, name, data) {
    form.subject = data.subject;
    form.body_html = data.body_html;
    form[method](route(`superadmin.email_templates.${name}`, selected.value.id), { preserveScroll: true });
}
</script>

<template>
    <SuperAdminLayout title="Default Email Templates Editor">
        <Head title="Superadmin - Email Templates" />

        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-5 dark:border-slate-800">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight dark:text-white">Default Email Templates</h1>
                    <p class="text-sm text-slate-500 mt-1 dark:text-slate-400">Manage global email default circulars and automated notification templates across all lodges & chapters. Lodges can reword the event emails for themselves; the wording here is what they start from.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div class="lg:col-span-4 bg-white border border-slate-200 rounded-xl p-4 shadow-xl dark:bg-slate-900 dark:border-slate-800">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3 px-2 dark:text-slate-400">System Templates ({{ templates.length }})</h2>
                    <div class="space-y-2">
                        <div
                            v-for="tmpl in templates"
                            :key="tmpl.id"
                            @click="selectedId = tmpl.id"
                            :class="[
                                'p-3.5 rounded-lg border cursor-pointer transition-all duration-150',
                                selectedId === tmpl.id
                                    ? 'bg-blue-50 border-blue-400 text-slate-900 shadow-md dark:bg-blue-950/60 dark:border-blue-600 dark:text-white'
                                    : 'bg-slate-50 border-slate-200 text-slate-700 hover:border-slate-400 hover:text-slate-900 dark:bg-slate-900/60 dark:border-slate-800 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-white'
                            ]"
                        >
                            <div class="font-semibold text-sm">{{ tmpl.name }}</div>
                            <div class="text-xs font-mono text-blue-600/80 mt-1">{{ tmpl.template_key }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="selected" class="lg:col-span-8">
                    <EmailTemplateEditor :template="selected" :processing="form.processing" :errors="form.errors" @save="send('put', 'update', $event)" @test="send('post', 'test', $event)" />
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
