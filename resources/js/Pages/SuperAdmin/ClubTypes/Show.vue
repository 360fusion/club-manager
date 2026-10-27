<script setup>
import { Head, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { orderColour } from '@/Utils/orderColour';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
  clubType: {
    type: Object,
    required: true,
  },
  clubs: {
    type: Array,
    default: () => [],
  },
  allClubTypes: {
    type: Array,
    default: () => [],
  },
});

function handleSwitchOrder(event) {
  const selectedId = event.target.value;
  if (selectedId) {
    router.get(route('superadmin.club_types.show', selectedId));
  }
}
</script>

<template>
  <SuperAdminLayout :title="`${clubType.name} - order details`">
    <Head :title="`Superadmin - ${clubType.name}`" />

    <div class="space-y-8">
      <PageHeader
        :title="clubType.name"
        :breadcrumbs="[
          { label: 'Dashboard', href: route('superadmin.dashboard') },
          { label: 'Masonic orders', href: route('superadmin.club_types.index') },
          { label: clubType.name },
        ]"
      >
        <template #badge>
          <span :class="['h-3 w-3 rounded-full', orderColour(clubType.colour).dot]" aria-hidden="true" />
          <Badge class="font-mono">Code: {{ clubType.code }}</Badge>
          <Badge variant="info">{{ clubType.clubs_count || clubs.length }} registered club(s)</Badge>
        </template>
        <template #actions>
          <a
            v-if="clubType.website_url"
            :href="clubType.website_url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
          >
            <UiIcon name="globe" />
            Official website
            <UiIcon name="external-link" :size="14" />
          </a>
          <Button variant="secondary" :href="route('superadmin.club_types.index')">
            <template #icon><UiIcon name="settings" /></template>
            Manage order presets
          </Button>
        </template>
      </PageHeader>

      <div class="flex items-center gap-2">
        <label for="switch-order" class="text-sm font-medium text-slate-700 whitespace-nowrap dark:text-slate-300">Switch order</label>
        <select
          id="switch-order"
          :value="clubType.id"
          class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
          @change="handleSwitchOrder"
        >
          <option v-for="ot in allClubTypes" :key="ot.id" :value="ot.id">
            {{ ot.name }} ({{ ot.code }})
          </option>
        </select>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-7 space-y-6">
          <Card class="space-y-3">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">About {{ clubType.name }}</h2>
            <p class="text-sm text-slate-700 leading-relaxed dark:text-slate-300">
              {{ clubType.description || 'No description has been added for this order yet.' }}
            </p>
          </Card>

          <Card v-if="clubType.website_url" class="space-y-3">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Official headquarters and governing body</h2>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200 dark:bg-slate-800/60 dark:border-slate-800">
              <div>
                <span class="text-[11px] text-slate-500 font-semibold block uppercase tracking-wider dark:text-slate-400">Official web address</span>
                <span class="text-xs text-slate-900 font-mono block mt-0.5 dark:text-white">{{ clubType.website_url }}</span>
              </div>
              <a
                :href="clubType.website_url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1.5 self-start rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 sm:self-auto dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
              >
                Open the website
                <UiIcon name="external-link" :size="14" />
              </a>
            </div>
          </Card>

          <Card class="space-y-4">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Terminology</h2>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
              <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-1 dark:bg-slate-800/60 dark:border-slate-800">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider block dark:text-slate-400">Body</span>
                <span class="font-semibold text-slate-900 text-sm block dark:text-white">{{ clubType.terminology?.club || 'Lodge' }}</span>
              </div>

              <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-1 dark:bg-slate-800/60 dark:border-slate-800">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider block dark:text-slate-400">Presiding officer</span>
                <span class="font-semibold text-slate-900 text-sm block dark:text-white">{{ clubType.terminology?.master || 'Worshipful Master' }}</span>
              </div>

              <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-1 dark:bg-slate-800/60 dark:border-slate-800">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider block dark:text-slate-400">Summons</span>
                <span class="font-semibold text-slate-900 text-sm block dark:text-white">{{ clubType.terminology?.summons || 'Summons' }}</span>
              </div>

              <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-1 dark:bg-slate-800/60 dark:border-slate-800">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider block dark:text-slate-400">Members</span>
                <span class="font-semibold text-slate-900 text-sm block dark:text-white">{{ clubType.terminology?.members || 'Brethren' }}</span>
              </div>
            </div>
          </Card>

          <Card v-if="clubType.rulers_schema?.length" class="space-y-3">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Provincial executive and rulers</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">The ruler titles given to Provincial officers for clubs of this order.</p>

            <div class="flex flex-wrap gap-2 pt-1">
              <Badge v-for="(ruler, idx) in clubType.rulers_schema" :key="idx">{{ ruler }}</Badge>
            </div>
          </Card>
        </div>

        <div class="lg:col-span-5 space-y-6">
          <Card class="space-y-4">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 pb-3 dark:border-slate-800">
              <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Offices and officer ladder</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Preset officer roles for {{ clubType.name }}</p>
              </div>
              <Badge>{{ clubType.default_officer_roles?.length || 0 }} roles</Badge>
            </div>

            <div class="space-y-2 max-h-[520px] overflow-y-auto pr-1">
              <div
                v-for="role in clubType.default_officer_roles || []"
                :key="role.id || role.title"
                class="p-3 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-between text-xs dark:bg-slate-800/60 dark:border-slate-800"
              >
                <div class="flex items-center gap-3">
                  <span class="w-6 h-6 flex items-center justify-center bg-white text-slate-700 font-mono font-semibold text-[11px] rounded-full border border-slate-300 dark:bg-slate-900 dark:text-slate-300 dark:border-slate-700">
                    {{ role.rank_level || '—' }}
                  </span>
                  <div>
                    <span class="font-semibold text-slate-900 block dark:text-white">{{ role.title }}</span>
                    <span class="text-[11px] text-slate-500 font-mono dark:text-slate-400">Code: {{ role.short_code }}</span>
                  </div>
                </div>
                <Badge v-if="role.is_executive" variant="warning">Key officer</Badge>
              </div>

              <div v-if="!clubType.default_officer_roles?.length" class="text-xs text-slate-500 italic p-4 text-center dark:text-slate-400">
                No officer ladder set up for this order.
              </div>
            </div>
          </Card>
        </div>
      </div>

      <Card padding="none" class="overflow-hidden">
        <div class="flex items-center justify-between gap-4 border-b border-slate-200 p-6 dark:border-slate-800">
          <div>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Registered {{ clubType.name }} clubs</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">All active lodges and chapters of this order registered on the platform.</p>
          </div>
          <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ clubs.length }} club(s)</span>
        </div>

        <div v-if="clubs.length" class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider dark:bg-slate-950 dark:border-slate-800 dark:text-slate-400">
              <tr>
                <th class="py-3 px-4">Club name and no.</th>
                <th class="py-3 px-4">Masonic province</th>
                <th class="py-3 px-4 text-center">Active members</th>
                <th class="py-3 px-4">Registered</th>
                <th class="py-3 px-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
              <tr v-for="c in clubs" :key="c.id" class="hover:bg-slate-50 transition-colors dark:hover:bg-slate-800/40">
                <td class="py-3.5 px-4 font-medium text-slate-900 dark:text-white">
                  {{ c.name }} <span v-if="c.lodge_number !== '—'" class="text-slate-500 font-normal dark:text-slate-400">No. {{ c.lodge_number }}</span>
                </td>
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

        <div v-else class="p-8 text-center space-y-2">
          <UiIcon name="building" :size="20" class="mx-auto text-slate-400" />
          <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">No {{ clubType.name }} clubs registered yet</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">Clubs registered under this order preset will appear here.</p>
        </div>
      </Card>
    </div>
  </SuperAdminLayout>
</template>
