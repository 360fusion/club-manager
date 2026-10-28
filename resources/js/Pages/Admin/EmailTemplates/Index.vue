<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import EmailTemplateEditor from '@/Components/EmailTemplateEditor.vue';

const props = defineProps({
  club: Object,
  templates: { type: Array, default: () => [] },
});

const selectedKey = ref(props.templates[0]?.key ?? null);
const selected = computed(() => props.templates.find((t) => t.key === selectedKey.value) ?? null);

const form = useForm({ subject: '', body_html: '' });
const url = (name, key) => route(`admin.email_templates.${name}`, { clubSlug: props.club.slug, key });

const send = (method, name, data) => {
  form.subject = data.subject;
  form.body_html = data.body_html;
  form[method](url(name, selected.value.key), { preserveScroll: true });
};

const reset = () => {
  if (confirm('Go back to the standard wording for this email?')) {
    router.delete(url('destroy', selected.value.key), { preserveScroll: true });
  }
};
</script>

<template>
  <AdminLayout title="Email wording" :club="club" active-tab="events">
    <Head title="Email wording" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Email wording</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Change what the emails your lodge sends say. Anything you don't change uses the standard wording.</p>
        </div>
        <Button variant="secondary" size="sm" class="self-start" :href="route('admin.events.index', { clubSlug: club.slug })">
          <template #icon><UiIcon name="arrow-left" /></template>
          Back to events
        </Button>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <Card padding="sm" class="space-y-2 self-start lg:col-span-4">
          <button v-for="template in templates" :key="template.key" type="button" :class="['flex w-full flex-wrap items-center gap-2 rounded-xl border p-3 text-left text-sm transition-colors', selectedKey === template.key ? 'border-blue-400 bg-blue-50 dark:border-blue-600 dark:bg-blue-950/60' : 'border-slate-200 bg-slate-50 hover:border-slate-400 dark:border-slate-800 dark:bg-slate-900/60']" @click="selectedKey = template.key">
            <span class="font-medium text-slate-900 dark:text-white">{{ template.name }}</span>
            <Badge v-if="template.customised" variant="info">Customised</Badge>
          </button>
        </Card>

        <div v-if="selected" class="lg:col-span-8">
          <EmailTemplateEditor :template="selected" :processing="form.processing" :errors="form.errors" :can-reset="selected.customised" @save="send('put', 'update', $event)" @test="send('post', 'test', $event)" @reset="reset" />
          <p v-if="selected.required_placeholder" class="mt-2 text-xs text-slate-500 dark:text-slate-400">This email must keep <span class="font-mono">&#123;&#123; {{ selected.required_placeholder }} &#125;&#125;</span> so the link in the email keeps working.</p>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
