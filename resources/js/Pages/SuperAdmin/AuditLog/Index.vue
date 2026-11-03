<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Pagination from '@/Components/Ui/Pagination.vue';

const props = defineProps({
  actions: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search || '');
const open = ref(null);

const apply = () => router.get(route('superadmin.audit_log.index'), { search: search.value || undefined }, { preserveState: true, replace: true });

const toggle = (id) => {
  open.value = open.value === id ? null : id;
};

const show = (value) => JSON.stringify(value, null, 2);
</script>

<template>
  <SuperAdminLayout title="Audit log">
    <Head title="Superadmin - Audit log" />

    <div class="space-y-6">
      <PageHeader title="Audit log" description="Everything superadmins changed: who, which screen, what they submitted, and each record's before and after. Passwords, keys and tokens are never kept." />

      <form class="flex items-end gap-2" @submit.prevent="apply">
        <Input v-model="search" label="Screen" placeholder="e.g. lodges.update" class="max-w-xs" />
        <Button type="submit" variant="secondary" size="sm">Filter</Button>
      </form>

      <Card padding="none" class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
            <tr><th class="p-3">When</th><th class="p-3">Who</th><th class="p-3">What</th><th class="p-3">Result</th><th class="p-3">Records</th><th class="p-3"></th></tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
            <template v-for="a in actions.data" :key="a.id">
              <tr>
                <td class="whitespace-nowrap p-3 font-mono">{{ a.when }}</td>
                <td class="p-3 font-medium text-slate-900 dark:text-white">{{ a.actor }}<div class="text-[11px] font-normal text-slate-500 dark:text-slate-400">{{ a.ip }}</div></td>
                <td class="p-3"><span class="font-mono">{{ a.action }}</span><div class="text-[11px] text-slate-500 dark:text-slate-400">{{ a.method }} /{{ a.path }}</div></td>
                <td class="p-3"><Badge :variant="a.status < 400 ? 'success' : 'warning'">{{ a.status }}</Badge></td>
                <td class="p-3">{{ a.changes ? a.changes.length : 0 }}</td>
                <td class="p-3 text-right"><Button variant="ghost" size="sm" @click="toggle(a.id)">{{ open === a.id ? 'Hide' : 'Details' }}</Button></td>
              </tr>
              <tr v-if="open === a.id">
                <td colspan="6" class="bg-slate-50 p-3 dark:bg-slate-950">
                  <div class="grid gap-3 md:grid-cols-2">
                    <div>
                      <div class="mb-1 font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Submitted</div>
                      <pre class="overflow-x-auto whitespace-pre-wrap font-mono text-[11px]">{{ show(a.input) }}</pre>
                    </div>
                    <div>
                      <div class="mb-1 font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Saved (before and after)</div>
                      <pre class="overflow-x-auto whitespace-pre-wrap font-mono text-[11px]">{{ show(a.changes) }}</pre>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-if="!actions.data.length"><td colspan="6" class="p-8 text-center text-slate-500 dark:text-slate-400">Nothing has been recorded yet.</td></tr>
          </tbody>
        </table>
      </Card>

      <Pagination :links="actions.links" />
    </div>
  </SuperAdminLayout>
</template>
