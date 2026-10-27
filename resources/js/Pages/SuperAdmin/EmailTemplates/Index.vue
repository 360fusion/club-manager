<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import EmailTemplateEditor from '@/Components/EmailTemplateEditor.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';

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
    <SuperAdminLayout title="Default email templates">
        <Head title="Superadmin - Email templates" />

        <div class="space-y-6">
            <PageHeader title="Default email templates" description="The platform's default circulars and automatic notification emails for all lodges and chapters. Lodges can reword the event emails for themselves; the wording here is what they start from." />

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <Card padding="sm" class="lg:col-span-4">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3 px-2 dark:text-slate-400">System templates ({{ templates.length }})</h2>
                    <div class="space-y-2">
                        <button
                            v-for="tmpl in templates"
                            :key="tmpl.id"
                            type="button"
                            :class="[
                                'block w-full text-left p-3.5 rounded-xl border transition-colors',
                                selectedId === tmpl.id
                                    ? 'bg-blue-50 border-blue-400 text-slate-900 dark:bg-blue-950/60 dark:border-blue-600 dark:text-white'
                                    : 'bg-slate-50 border-slate-200 text-slate-700 hover:border-slate-400 hover:text-slate-900 dark:bg-slate-900/60 dark:border-slate-800 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-white'
                            ]"
                            @click="selectedId = tmpl.id"
                        >
                            <div class="font-semibold text-sm">{{ tmpl.name }}</div>
                            <div class="text-xs font-mono text-slate-500 mt-1 dark:text-slate-400">{{ tmpl.template_key }}</div>
                        </button>
                    </div>
                </Card>

                <div v-if="selected" class="lg:col-span-8">
                    <EmailTemplateEditor :template="selected" :processing="form.processing" :errors="form.errors" @save="send('put', 'update', $event)" @test="send('post', 'test', $event)" />
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
