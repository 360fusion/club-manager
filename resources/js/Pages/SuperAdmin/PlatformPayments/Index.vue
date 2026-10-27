<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { ref } from 'vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Alert from '@/Components/Ui/Alert.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
  lodges: { type: Array, default: () => [] },
  byMonth: { type: Array, default: () => [] },
  enabled: { type: Boolean, default: false },
  defaults: { type: Object, default: () => ({}) },
});

// One small form per lodge for its commission override.
const rows = ref(Object.fromEntries(props.lodges.map((l) => [l.id, { commission_percent: l.commission_percent ?? '', commission_fixed: l.commission_fixed ?? '' }])));
const form = useForm({ commission_percent: '', commission_fixed: '' });
const save = (lodge) => {
  form.commission_percent = rows.value[lodge.id].commission_percent === '' ? null : rows.value[lodge.id].commission_percent;
  form.commission_fixed = rows.value[lodge.id].commission_fixed === '' ? null : rows.value[lodge.id].commission_fixed;
  form.put(route('superadmin.platform_payments.update', lodge.id), { preserveScroll: true });
};

const input = 'w-24 rounded-xl border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder-slate-500';
</script>

<template>
  <SuperAdminLayout title="Platform payments">
    <Head title="Superadmin - Platform payments" />

    <div class="space-y-6">
      <PageHeader title="Platform payments" description="Lodges that have connected Stripe, the commission rate each pays, and the commission earned on their event card payments.">
        <template #actions>
          <!-- A plain link, not an Inertia visit: the CSV is a download. -->
          <a :href="route('superadmin.platform_payments.export')" class="inline-flex items-center gap-2 rounded-xl border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950"><UiIcon name="download" />Download CSV</a>
        </template>
      </PageHeader>

      <Alert v-if="!enabled" variant="warning">Platform payments are switched off (EVENTS_PLATFORM_PAYMENTS). Lodges cannot connect until they are on.</Alert>

      <Card padding="none" class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
            <tr><th class="p-3">Lodge</th><th class="p-3">Status</th><th class="p-3">Commission (% + fixed)</th><th class="p-3">Payments</th><th class="p-3 text-right">Taken</th><th class="p-3 text-right">Commission</th><th class="p-3 text-right">Refunded</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
            <tr v-for="lodge in lodges" :key="lodge.id">
              <td class="p-3 font-medium text-slate-900 dark:text-white">{{ lodge.club }}<div class="text-[11px] font-normal text-slate-500 dark:text-slate-400">{{ lodge.type === 'express' ? 'Created for the lodge' : 'Their own account' }}</div></td>
              <td class="p-3">
                <Badge :variant="lodge.ready ? 'success' : 'warning'">{{ !lodge.connected ? 'Disconnected' : lodge.ready ? 'Ready' : !lodge.charges_enabled ? 'Setup unfinished' : 'Terms not accepted' }}</Badge>
              </td>
              <td class="p-3">
                <form class="flex items-center gap-1.5" @submit.prevent="save(lodge)">
                  <input v-model="rows[lodge.id].commission_percent" type="number" step="0.01" min="0" max="100" :placeholder="`${defaults.percent}%`" :class="input" />
                  <input v-model="rows[lodge.id].commission_fixed" type="number" step="0.01" min="0" max="1000" :placeholder="String(defaults.fixed)" :class="input" />
                  <Button type="submit" variant="secondary" size="sm">Save</Button>
                </form>
              </td>
              <td class="p-3 font-mono">{{ lodge.totals.reduce((n, t) => n + t.payments, 0) }}</td>
              <td class="p-3 text-right font-mono"><div v-for="t in lodge.totals" :key="t.currency">{{ t.currency }} {{ t.gross }}</div></td>
              <td class="p-3 text-right font-mono font-semibold"><div v-for="t in lodge.totals" :key="t.currency">{{ t.currency }} {{ t.commission }}</div></td>
              <td class="p-3 text-right font-mono"><div v-for="t in lodge.totals" :key="t.currency">{{ t.currency }} {{ t.refunded }}</div></td>
            </tr>
            <tr v-if="!lodges.length"><td colspan="7" class="p-8 text-center text-slate-500 dark:text-slate-400">No lodge has connected Stripe yet.</td></tr>
          </tbody>
        </table>
      </Card>

      <Card v-if="byMonth.length" padding="sm">
        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Commission by month</h2>
        <div class="grid gap-1 text-xs sm:grid-cols-2">
          <div v-for="row in byMonth" :key="row.month + row.currency" class="flex justify-between rounded-xl bg-slate-50 px-3 py-1.5 dark:bg-slate-800/50"><span class="font-medium text-slate-700 dark:text-slate-200">{{ row.month }} ({{ row.payments }})</span><span class="font-mono font-semibold">{{ row.currency }} {{ row.commission }}</span></div>
        </div>
      </Card>
    </div>
  </SuperAdminLayout>
</template>
