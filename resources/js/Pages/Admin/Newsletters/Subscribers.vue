<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';

const props = defineProps({
  club: Object,
  types: Array,
  subscriptions: Array,
});

const activeTab = ref('all'); // all, active, pending_approval, external
const searchQuery = ref('');
const selectedTypeId = ref('all');

const filteredSubscriptions = computed(() => {
  return (props.subscriptions || []).filter(sub => {
    const query = searchQuery.value.toLowerCase().trim();
    const matchesQuery = !query ||
      (sub.name && sub.name.toLowerCase().includes(query)) ||
      (sub.email && sub.email.toLowerCase().includes(query)) ||
      (sub.home_club_name && sub.home_club_name.toLowerCase().includes(query)) ||
      (sub.rank && sub.rank.toLowerCase().includes(query));

    if (!matchesQuery) return false;

    if (selectedTypeId.value !== 'all' && Number(sub.newsletter_type_id) !== Number(selectedTypeId.value)) {
      return false;
    }

    if (activeTab.value === 'active') return sub.status === 'active';
    if (activeTab.value === 'pending_approval') return sub.status === 'pending_approval';
    if (activeTab.value === 'external') return !sub.is_internal_member && sub.status === 'active';

    return true;
  });
});

const pendingCount = computed(() => {
  return (props.subscriptions || []).filter(s => s.status === 'pending_approval').length;
});

const updateStatus = (subId, status) => {
  router.post(
    route('admin.newsletters.subscribers.status', { clubSlug: props.club.slug, id: subId }),
    { status },
    { preserveScroll: true }
  );
};

const channelOptions = computed(() => [{ value: 'all', label: 'All channels' }, ...(props.types || []).map((t) => ({ value: t.id, label: `${t.icon} ${t.name}` }))]);
</script>

<template>
  <AdminLayout title="Newsletter subscribers" :club="club" active-tab="newsletters">
    <Head title="Newsletter subscribers" />

    <div class="space-y-6">
      <Card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <Button variant="ghost" size="sm" :href="route('admin.newsletters.index', club.slug)">Back to newsletters</Button>
            <h2 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">Subscribers and approvals</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Manage subscribers across all channels, including visiting brethren and requests waiting for approval.
            </p>
          </div>
          <Button variant="secondary" size="sm" :href="route('admin.newsletters.types', club.slug)">Channel settings</Button>
        </div>
      </Card>

      <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        <StatCard label="Subscribers" :value="subscriptions.length" caption="Members and visitors" />
        <StatCard label="Waiting for approval" :value="pendingCount" caption="Visiting brethren to review" />
        <StatCard label="Visiting brethren" :value="subscriptions.filter(s => !s.is_internal_member && s.status === 'active').length" caption="Subscribed from other lodges" />
        <StatCard label="Members" :value="subscriptions.filter(s => s.is_internal_member && s.status === 'active').length" caption="Active members of this club" />
      </div>

      <Card>
        <div class="space-y-4">
          <div class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-4 dark:border-slate-800 lg:flex-row lg:items-center">
            <div class="flex items-center gap-1 overflow-x-auto" role="group" aria-label="Show">
              <button
                v-for="[key, label] in [['all', `Everyone (${subscriptions.length})`], ['pending_approval', `Waiting for approval (${pendingCount})`], ['external', 'Visiting brethren']]"
                :key="key"
                type="button"
                :aria-pressed="activeTab === key"
                :class="['rounded-full px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors', activeTab === key ? 'bg-slate-900 text-white dark:bg-slate-700' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800']"
                @click="activeTab = key"
              >
                {{ label }}
              </button>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
              <Select v-model="selectedTypeId" class="sm:w-48" aria-label="Channel" :options="channelOptions" />
              <Input v-model="searchQuery" class="sm:w-64" type="search" aria-label="Search subscribers" placeholder="Search name, email or lodge" />
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-200">
              <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                  <th scope="col" class="p-3 font-medium">Name</th>
                  <th scope="col" class="p-3 font-medium">Home lodge or club</th>
                  <th scope="col" class="p-3 font-medium">Email</th>
                  <th scope="col" class="p-3 font-medium">Channel</th>
                  <th scope="col" class="p-3 font-medium">Source</th>
                  <th scope="col" class="p-3 font-medium">Status</th>
                  <th scope="col" class="p-3 text-right font-medium"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="sub in filteredSubscriptions" :key="sub.id">
                  <td class="p-3">
                    <div class="font-medium text-slate-900 dark:text-white">
                      <span v-if="sub.rank" class="mr-1 font-normal text-slate-500 dark:text-slate-400">{{ sub.rank }}</span>
                      {{ sub.name }}
                    </div>
                    <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Subscribed {{ sub.subscribed_at }}</div>
                  </td>

                  <td class="p-3">
                    <span v-if="sub.home_club_name">{{ sub.home_club_name }} {{ sub.home_club_number ? 'No. ' + sub.home_club_number : '' }}</span>
                    <span v-else class="text-slate-500 dark:text-slate-400">Member of this club</span>
                  </td>

                  <td class="p-3 text-xs text-slate-600 dark:text-slate-300">{{ sub.email }}</td>

                  <td class="p-3">
                    <Badge>
                      <span :style="{ backgroundColor: sub.type_color }" class="inline-block h-2 w-2 rounded-full" aria-hidden="true"></span>
                      {{ sub.type_icon }} {{ sub.type_name }}
                    </Badge>
                  </td>

                  <td class="p-3">
                    <Badge v-if="sub.is_internal_member" variant="info">Member roster</Badge>
                    <Badge v-else variant="info">Visiting brother</Badge>
                  </td>

                  <td class="p-3">
                    <Badge v-if="sub.status === 'active'" variant="success">Active</Badge>
                    <Badge v-else-if="sub.status === 'pending_approval'" variant="warning">Waiting for approval</Badge>
                    <Badge v-else>Unsubscribed</Badge>
                  </td>

                  <td class="whitespace-nowrap p-3 text-right">
                    <div v-if="sub.status === 'pending_approval'" class="flex items-center justify-end gap-2">
                      <Button size="sm" @click="updateStatus(sub.id, 'active')">Approve</Button>
                      <Button variant="secondary" size="sm" @click="updateStatus(sub.id, 'rejected')">Reject</Button>
                    </div>
                    <Button v-else-if="sub.status === 'active'" variant="secondary" size="sm" @click="updateStatus(sub.id, 'unsubscribed')">Unsubscribe</Button>
                    <Button v-else variant="secondary" size="sm" @click="updateStatus(sub.id, 'active')">Reactivate</Button>
                  </td>
                </tr>

                <tr v-if="filteredSubscriptions.length === 0">
                  <td colspan="7" class="p-8 text-center text-slate-500 dark:text-slate-400">No subscribers match.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </Card>
    </div>
  </AdminLayout>
</template>
