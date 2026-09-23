<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
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
          <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Change what the emails your lodge sends say. Anything you don't change uses the standard wording.</p>
        </div>
        <Link :href="route('admin.events.index', { clubSlug: club.slug })" class="self-start rounded-xl border border-slate-300 bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">&larr; Back to events</Link>
      </div>

      <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        <div class="space-y-2 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-4">
          <button v-for="template in templates" :key="template.key" type="button" :class="['w-full rounded-lg border p-3 text-left text-sm transition-all', selectedKey === template.key ? 'border-blue-400 bg-blue-50 dark:border-blue-600 dark:bg-blue-950/60' : 'border-slate-200 bg-slate-50 hover:border-slate-400 dark:border-slate-800 dark:bg-slate-900/60']" @click="selectedKey = template.key">
            <span class="font-semibold text-slate-900 dark:text-white">{{ template.name }}</span>
            <span v-if="template.customised" class="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Customised</span>
          </button>
        </div>

        <div v-if="selected" class="lg:col-span-8">
          <EmailTemplateEditor :template="selected" :processing="form.processing" :errors="form.errors" :can-reset="selected.customised" @save="send('put', 'update', $event)" @test="send('post', 'test', $event)" @reset="reset" />
          <p v-if="selected.required_placeholder" class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">This email must keep <span class="font-mono">&#123;&#123; {{ selected.required_placeholder }} &#125;&#125;</span> so the link in the email keeps working.</p>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
