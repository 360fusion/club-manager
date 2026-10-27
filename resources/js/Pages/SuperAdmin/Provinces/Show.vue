<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
  province: {
    type: Object,
    required: true,
  },
  // People who review suggested fixes to this province's lodge listings.
  admins: {
    type: Array,
    default: () => [],
  },
  allProvinces: {
    type: Array,
    default: () => [],
  },
  grandLodges: {
    type: Array,
    default: () => [],
  },
});

const adminForm = useForm({ email: '' });

function addAdmin() {
  adminForm.post(route('superadmin.provinces.admins.store', props.province.id), {
    preserveScroll: true,
    onSuccess: () => adminForm.reset(),
  });
}

function removeAdmin(admin) {
  if (confirm(`Remove ${admin.name} as an admin of this province?`)) {
    router.delete(route('superadmin.provinces.admins.destroy', [props.province.id, admin.id]), { preserveScroll: true });
  }
}

function handleSwitchProvince(event) {
  const selectedId = event.target.value;
  if (selectedId) {
    router.get(route('superadmin.provinces.show', selectedId));
  }
}
</script>

<template>
  <SuperAdminLayout :title="province.name">
    <Head :title="`Superadmin - ${province.name}`" />

    <div class="space-y-6">
      <PageHeader
        :title="province.name"
        :breadcrumbs="[
          { label: 'Dashboard', href: route('superadmin.dashboard') },
          { label: 'Masonic provinces', href: route('superadmin.provinces.index') },
          { label: province.name },
        ]"
      >
        <template #actions>
          <a
            v-if="province.website_url"
            :href="province.website_url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:ring-offset-slate-950"
          >
            <UiIcon name="external-link" />
            Visit website
          </a>
          <Button :href="route('superadmin.provinces.edit', province.id)">
            <template #icon><UiIcon name="pencil" /></template>
            Edit province
          </Button>
        </template>
      </PageHeader>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-wrap items-center gap-2">
          <Badge class="font-mono">Code: {{ province.code }}</Badge>
          <Badge>{{ province.country || 'England' }}</Badge>
          <Badge v-if="province.grand_lodge" variant="info">{{ province.grand_lodge.short_name || province.grand_lodge.name }}</Badge>
          <Badge variant="info">{{ province.clubs_count || province.clubs?.length || 0 }} associated lodges</Badge>
        </div>

        <div class="flex items-center gap-2">
          <label for="province-switcher" class="text-xs font-medium text-slate-700 whitespace-nowrap dark:text-slate-300">Switch province</label>
          <select
            id="province-switcher"
            :value="province.id"
            @change="handleSwitchProvince"
            class="max-w-xs truncate rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
          >
            <option v-for="p in allProvinces" :key="p.id" :value="p.id">
              {{ p.name }}
            </option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-6 space-y-6">
          <Card>
            <template #header>
              <h3 class="text-base font-semibold text-slate-900 dark:text-white">Provincial executive</h3>
            </template>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40">
                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Provincial Grand Master</dt>
                <dd class="mt-1 font-semibold text-slate-900 dark:text-white">
                  {{ province.provincial_grand_master || 'Not specified' }}
                </dd>
              </div>

              <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40">
                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Provincial Grand Secretary</dt>
                <dd class="mt-1 font-semibold text-slate-900 dark:text-white">
                  {{ province.provincial_grand_secretary || 'Not specified' }}
                </dd>
              </div>
            </dl>
          </Card>

          <Card class="space-y-4">
            <template #header>
              <h3 class="text-base font-semibold text-slate-900 dark:text-white">Provincial office and address</h3>
            </template>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-2 dark:border-slate-800 dark:bg-slate-950/40">
              <span class="text-xs font-medium text-slate-500 block dark:text-slate-400">Postal address</span>

              <div v-if="province.address_line_1 || province.town || province.postcode" class="text-sm text-slate-900 space-y-0.5 leading-relaxed dark:text-white">
                <div v-if="province.address_line_1">{{ province.address_line_1 }}</div>
                <div v-if="province.address_line_2">{{ province.address_line_2 }}</div>
                <div v-if="province.town">{{ province.town }}</div>
                <div v-if="province.county">{{ province.county }}</div>
                <div v-if="province.postcode" class="font-mono">{{ province.postcode }}</div>
                <div class="text-slate-500 dark:text-slate-400">{{ province.country || 'United Kingdom' }}</div>
              </div>
              <div v-else class="text-sm text-slate-500 dark:text-slate-400">No office address recorded for this province.</div>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
              <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 space-y-1 dark:border-slate-800 dark:bg-slate-950/40">
                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Telephone</dt>
                <dd>
                  <a v-if="province.telephone" :href="`tel:${province.telephone}`" class="font-mono font-medium text-slate-900 hover:text-blue-700 dark:text-white dark:hover:text-blue-300">
                    {{ province.telephone }}
                  </a>
                  <span v-else class="text-slate-500 dark:text-slate-400">—</span>
                </dd>
              </div>

              <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 space-y-1 dark:border-slate-800 dark:bg-slate-950/40">
                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Email address</dt>
                <dd class="truncate">
                  <a v-if="province.email" :href="`mailto:${province.email}`" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                    {{ province.email }}
                  </a>
                  <span v-else class="text-slate-500 dark:text-slate-400">—</span>
                </dd>
              </div>

              <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 space-y-1 dark:border-slate-800 dark:bg-slate-950/40">
                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Official website</dt>
                <dd class="min-w-0">
                  <a v-if="province.website_url" :href="province.website_url" target="_blank" class="flex min-w-0 items-center gap-1 font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                    <span class="truncate">{{ province.website_url }}</span>
                    <UiIcon name="external-link" :size="14" />
                  </a>
                  <span v-else class="text-slate-500 dark:text-slate-400">—</span>
                </dd>
              </div>

              <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 space-y-1 dark:border-slate-800 dark:bg-slate-950/40">
                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Social channels</dt>
                <dd class="flex items-center gap-3 pt-0.5">
                  <a v-if="province.twitter_url" :href="province.twitter_url" target="_blank" class="inline-flex items-center gap-1 font-medium text-blue-600 hover:underline dark:text-blue-400">
                    Twitter / X
                    <UiIcon name="external-link" :size="14" />
                  </a>
                  <a v-if="province.facebook_url" :href="province.facebook_url" target="_blank" class="inline-flex items-center gap-1 font-medium text-blue-600 hover:underline dark:text-blue-400">
                    Facebook
                    <UiIcon name="external-link" :size="14" />
                  </a>
                  <span v-if="!province.twitter_url && !province.facebook_url" class="text-slate-500 dark:text-slate-400">—</span>
                </dd>
              </div>
            </dl>
          </Card>
        </div>

        <div class="lg:col-span-6 space-y-6">
          <Card class="space-y-3">
            <template #header>
              <h3 class="text-base font-semibold text-slate-900 dark:text-white">Jurisdiction and regional scope</h3>
            </template>

            <dl class="grid grid-cols-2 gap-3 text-sm">
              <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 dark:border-slate-800 dark:bg-slate-950/40">
                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Geographic region</dt>
                <dd class="mt-0.5 font-semibold text-slate-900 dark:text-white">{{ province.region || '—' }}</dd>
              </div>

              <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 dark:border-slate-800 dark:bg-slate-950/40">
                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Sovereign Grand Lodge</dt>
                <dd class="mt-0.5 font-semibold text-slate-900 dark:text-white">{{ province.grand_lodge?.name || 'UGLE' }}</dd>
              </div>
            </dl>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-1 dark:border-slate-800 dark:bg-slate-950/40">
              <span class="text-xs font-medium text-slate-500 block dark:text-slate-400">Notes and history</span>
              <p class="text-sm text-slate-700 leading-relaxed dark:text-slate-300">
                {{ province.description || 'No detailed background notes recorded for this province.' }}
              </p>
            </div>
          </Card>

          <Card class="space-y-4">
            <template #header>
              <h3 class="text-base font-semibold text-slate-900 dark:text-white">Provincial admins</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">They review suggested fixes to this province's lodge listings, from My lodges in the members area. They see no other province.</p>
            </template>

            <ul v-if="admins.length" class="divide-y divide-slate-200 text-sm dark:divide-slate-800">
              <li v-for="admin in admins" :key="admin.id" class="flex items-center justify-between gap-3 py-2">
                <span><strong class="font-medium text-slate-900 dark:text-white">{{ admin.name }}</strong> <span class="text-slate-500 dark:text-slate-400">{{ admin.email }}</span></span>
                <Button variant="danger" size="sm" @click="removeAdmin(admin)">Remove</Button>
              </li>
            </ul>
            <p v-else class="text-sm text-slate-500 dark:text-slate-400">No one yet.</p>

            <form class="flex flex-wrap items-end gap-2" @submit.prevent="addAdmin">
              <Input
                v-model="adminForm.email"
                type="email"
                label="Email address"
                placeholder="Their account email address"
                :error="adminForm.errors.email"
                required
                class="flex-1 min-w-56"
              />
              <Button type="submit" variant="secondary" :loading="adminForm.processing">Add admin</Button>
            </form>
          </Card>

          <Card class="space-y-4">
            <template #header>
              <div class="flex items-center justify-between gap-3">
                <div>
                  <h3 class="text-base font-semibold text-slate-900 dark:text-white">Registered lodges in {{ province.name }}</h3>
                  <p class="text-xs text-slate-500 dark:text-slate-400">Lodges and chapters operating within this province.</p>
                </div>
                <span class="text-xs font-medium text-slate-500 whitespace-nowrap dark:text-slate-400">{{ province.clubs?.length || 0 }} lodge(s)</span>
              </div>
            </template>

            <div v-if="province.clubs?.length" class="border border-slate-200 rounded-xl overflow-hidden max-h-80 overflow-y-auto dark:border-slate-800">
              <table class="w-full text-left border-collapse text-sm">
                <thead>
                  <tr class="bg-slate-50 text-slate-500 text-xs border-b border-slate-200 dark:bg-slate-900/60 dark:text-slate-400 dark:border-slate-800">
                    <th scope="col" class="py-3 px-4 font-medium">Lodge name and no.</th>
                    <th scope="col" class="py-3 px-3 font-medium">Order type</th>
                    <th scope="col" class="py-3 px-3 text-center font-medium">Active members</th>
                    <th scope="col" class="py-3 px-4 text-right font-medium">Action</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-slate-700 dark:divide-slate-800 dark:text-slate-300">
                  <tr v-for="c in province.clubs" :key="c.id" class="hover:bg-slate-50 transition-colors dark:hover:bg-slate-800/40">
                    <td class="py-3 px-4 font-medium text-slate-900 dark:text-white">
                      {{ c.name }} <span v-if="c.lodge_number" class="text-slate-500 font-normal dark:text-slate-400">No. {{ c.lodge_number }}</span>
                    </td>
                    <td class="py-3 px-3">
                      <Badge>{{ c.club_type?.name || 'Craft Lodge' }}</Badge>
                    </td>
                    <td class="py-3 px-3 text-center font-medium text-slate-900 dark:text-white">{{ c.users_count || 0 }}</td>
                    <td class="py-3 px-4 text-right">
                      <Button variant="secondary" size="sm" :href="route('admin.club_acc.charity.index', c.slug)">Workspace</Button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else class="p-6 text-center bg-slate-50 rounded-xl border border-slate-200 space-y-1 dark:bg-slate-950/40 dark:border-slate-800">
              <span class="flex justify-center text-slate-400"><UiIcon name="building" :size="20" /></span>
              <h4 class="text-sm font-medium text-slate-700 dark:text-slate-300">No lodges registered in this province yet</h4>
              <p class="text-xs text-slate-500 dark:text-slate-400">New lodges assigned to {{ province.name }} will appear here.</p>
            </div>
          </Card>
        </div>
      </div>
    </div>
  </SuperAdminLayout>
</template>
