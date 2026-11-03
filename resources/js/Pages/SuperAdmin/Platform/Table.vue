<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import Input from '@/Components/Ui/Input.vue';

// One read-only table for the platform lists; each column is { key, label } and `badge: true` shows a yes/no mark.
const props = defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  links: { type: Array, default: () => [] },
  search: { type: Boolean, default: false },
  q: { type: String, default: '' },
});

const query = ref(props.q);
const find = () => router.get(window.location.pathname, query.value ? { q: query.value } : {}, { preserveState: true, replace: true });
</script>

<template>
  <SuperAdminLayout :title="title">
    <Head :title="`Superadmin - ${title}`" />

    <div class="space-y-6">
      <PageHeader :title="title" :description="description" />

      <form v-if="search" class="max-w-sm" @submit.prevent="find">
        <Input v-model="query" label="Search" placeholder="Type, then press Enter" />
      </form>

      <Card padding="none" class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
            <tr><th v-for="col in columns" :key="col.key" class="p-3">{{ col.label }}</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
            <tr v-for="(row, i) in rows" :key="row.id ?? i">
              <td v-for="col in columns" :key="col.key" class="p-3 text-slate-700 dark:text-slate-200">
                <Badge v-if="col.badge" :variant="row[col.key] ? 'success' : 'warning'">{{ row[col.key] ? 'Yes' : 'No' }}</Badge>
                <template v-else>{{ row[col.key] }}</template>
              </td>
            </tr>
            <tr v-if="!rows.length"><td :colspan="columns.length" class="p-8 text-center text-slate-500 dark:text-slate-400">Nothing to show yet.</td></tr>
          </tbody>
        </table>
      </Card>

      <Pagination :links="links" />
    </div>
  </SuperAdminLayout>
</template>
