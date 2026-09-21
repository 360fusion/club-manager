<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { ref } from 'vue';

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

const input = 'w-24 rounded-lg border border-slate-300 bg-slate-50 px-2 py-1.5 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white';
</script>

<template>
  <SuperAdminLayout title="Platform Payments">
    <Head title="Superadmin - Platform Payments" />

    <div class="space-y-6">
      <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Platform Payments</h1>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Lodges that have connected Stripe, the commission rate each pays, and the commission earned on their event card payments.</p>
          <p v-if="!enabled" class="mt-2 text-xs font-semibold text-amber-600">Platform payments are switched off (EVENTS_PLATFORM_PAYMENTS). Lodges cannot connect until they are on.</p>
        </div>
        <a :href="route('superadmin.platform_payments.export')" class="self-start rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-500">Download CSV</a>
      </div>

      <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 font-bold uppercase tracking-wider text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300">
            <tr><th class="p-3">Lodge</th><th class="p-3">Status</th><th class="p-3">Commission (% + fixed)</th><th class="p-3">Payments</th><th class="p-3 text-right">Taken</th><th class="p-3 text-right">Commission</th><th class="p-3 text-right">Refunded</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-for="lodge in lodges" :key="lodge.id">
              <td class="p-3 font-bold text-slate-900 dark:text-white">{{ lodge.club }}<div class="text-[10px] font-normal text-slate-400">{{ lodge.type === 'express' ? 'Created for the lodge' : 'Their own account' }}</div></td>
              <td class="p-3">
                <span :class="['rounded px-2 py-0.5 text-[10px] font-bold uppercase', lodge.ready ? 'bg-emerald-500/15 text-emerald-600' : 'bg-amber-500/15 text-amber-600']">{{ !lodge.connected ? 'Disconnected' : lodge.ready ? 'Ready' : !lodge.charges_enabled ? 'Setup unfinished' : 'Terms not accepted' }}</span>
              </td>
              <td class="p-3">
                <form class="flex items-center gap-1.5" @submit.prevent="save(lodge)">
                  <input v-model="rows[lodge.id].commission_percent" type="number" step="0.01" min="0" max="100" :placeholder="`${defaults.percent}%`" :class="input" />
                  <input v-model="rows[lodge.id].commission_fixed" type="number" step="0.01" min="0" max="1000" :placeholder="String(defaults.fixed)" :class="input" />
                  <button type="submit" class="rounded-lg border border-slate-300 px-2 py-1.5 font-bold text-slate-700 dark:border-slate-700 dark:text-slate-200">Save</button>
                </form>
              </td>
              <td class="p-3 font-mono">{{ lodge.totals.reduce((n, t) => n + t.payments, 0) }}</td>
              <td class="p-3 text-right font-mono"><div v-for="t in lodge.totals" :key="t.currency">{{ t.currency }} {{ t.gross }}</div></td>
              <td class="p-3 text-right font-mono font-bold"><div v-for="t in lodge.totals" :key="t.currency">{{ t.currency }} {{ t.commission }}</div></td>
              <td class="p-3 text-right font-mono"><div v-for="t in lodge.totals" :key="t.currency">{{ t.currency }} {{ t.refunded }}</div></td>
            </tr>
            <tr v-if="!lodges.length"><td colspan="7" class="p-8 text-center text-slate-500 dark:text-slate-400">No lodge has connected Stripe yet.</td></tr>
          </tbody>
        </table>
      </div>

      <div v-if="byMonth.length" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Commission by month</h2>
        <div class="grid gap-1 text-xs sm:grid-cols-2">
          <div v-for="row in byMonth" :key="row.month + row.currency" class="flex justify-between rounded-lg bg-slate-50 px-3 py-1.5 dark:bg-slate-800/50"><span class="font-semibold text-slate-700 dark:text-slate-200">{{ row.month }} ({{ row.payments }})</span><span class="font-mono font-bold">{{ row.currency }} {{ row.commission }}</span></div>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>
