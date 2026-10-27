<script setup>
import { Link } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
  metrics: Object,
  clubsByType: Array,
  clubs: Array,
});
</script>

<template>
  <SuperAdminLayout title="System analytics">
    <div class="space-y-8">
      <PageHeader title="System analytics and metrics" description="An overview of registered clubs, orders, user accounts and platform volume.">
        <template #actions>
          <Button :href="route('superadmin.club_types.index')">
            <template #icon><UiIcon name="settings" /></template>
            Manage club types and presets
          </Button>
        </template>
      </PageHeader>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Registered clubs" :value="metrics?.totalClubsCount || 0" caption="Across all order types" />
        <StatCard label="User accounts" :value="metrics?.totalUsersCount || 0" caption="Brethren, officers and members" />
        <StatCard label="Meeting summonses" :value="metrics?.totalMeetingsCount || 0" caption="Circulars and convocations" />
        <StatCard label="Charity grants disbursed" :value="metrics?.totalGrantsDisbursed || ($cs + '0.00')" caption="Total Relief Chest payouts" />
      </div>

      <Card class="space-y-4">
        <div>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Clubs by order or type</h2>
          <p class="text-xs text-slate-500 mt-0.5 dark:text-slate-400">Open an order to see its page, offices, registered clubs and official website.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <Link
            v-for="ct in clubsByType"
            :key="ct.id"
            :href="route('superadmin.club_types.show', ct.id)"
            class="p-4 bg-slate-50 rounded-xl border border-slate-200 hover:border-blue-400 hover:bg-white transition-colors group flex items-center justify-between dark:bg-slate-800/60 dark:border-slate-700 dark:hover:bg-slate-800"
          >
            <div>
              <div class="font-semibold text-sm text-slate-900 group-hover:text-blue-700 transition-colors flex items-center gap-1.5 dark:text-white dark:group-hover:text-blue-300">
                <span>{{ ct.name }}</span>
                <span class="text-xs text-blue-600 dark:text-blue-400" aria-hidden="true">→</span>
              </div>
              <div class="text-[11px] text-slate-500 font-mono mt-0.5 dark:text-slate-400">Code: {{ ct.code }}</div>
            </div>
            <Badge variant="info">{{ ct.count }}</Badge>
          </Link>
        </div>
      </Card>

      <Card padding="none" class="overflow-hidden">
        <div class="flex items-center justify-between gap-4 border-b border-slate-200 p-6 dark:border-slate-800">
          <div>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Club directory</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">All clubs registered on the platform with member counts and order types.</p>
          </div>
          <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ clubs.length }} club(s)</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider dark:bg-slate-950 dark:border-slate-800 dark:text-slate-400">
              <tr>
                <th class="py-3 px-4">Club name and no.</th>
                <th class="py-3 px-4">Order or type</th>
                <th class="py-3 px-4">Masonic province</th>
                <th class="py-3 px-4 text-center">Active members</th>
                <th class="py-3 px-4">Created</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
              <tr v-for="c in clubs" :key="c.id" class="hover:bg-slate-50 transition-colors dark:hover:bg-slate-800/40">
                <td class="py-3.5 px-4 font-medium text-slate-900 dark:text-white">
                  {{ c.name }} <span v-if="c.lodge_number !== '—'" class="text-slate-500 font-normal dark:text-slate-400">No. {{ c.lodge_number }}</span>
                </td>
                <td class="py-3.5 px-4"><Badge variant="info">{{ c.club_type }}</Badge></td>
                <td class="py-3.5 px-4"><Badge>{{ c.province }}</Badge></td>
                <td class="py-3.5 px-4 text-center font-medium text-slate-900 dark:text-white">{{ c.members_count }}</td>
                <td class="py-3.5 px-4 text-slate-500 dark:text-slate-400">{{ c.created_at }}</td>
                <td class="py-3.5 px-4 text-right">
                  <Button variant="secondary" size="sm" :href="route('admin.club_acc.charity.index', c.slug)">View workspace</Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  </SuperAdminLayout>
</template>
