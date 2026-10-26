<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CommunicationsTabs from '@/Components/CommunicationsTabs.vue';
import Card from '@/Components/Ui/Card.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import { formatLocal } from '@/Utils/localDateTime';

const props = defineProps({
  club: Object,
  newsletters: Array,
  types: Array,
});

const searchQuery = ref('');
const selectedTypeId = ref('all');
const selectedStatus = ref('all');
const selectedRole = ref('all');
const selectedDateRange = ref('all');

const availableRoles = [
  { id: 'member', label: 'Members and brethren' },
  { id: 'coach', label: 'Coaches and officers' },
  { id: 'admin', label: 'Admins and board' },
  { id: 'treasurer', label: 'Treasurers' },
];

const filteredNewsletters = computed(() => {
  return (props.newsletters || []).filter(item => {
    // 1. Search Query Filter
    const query = searchQuery.value.toLowerCase().trim();
    if (query) {
      const matchesSubject = item.subject && item.subject.toLowerCase().includes(query);
      const matchesContent = item.content && item.content.toLowerCase().includes(query);
      if (!matchesSubject && !matchesContent) return false;
    }

    // 2. Channel Type Filter
    if (selectedTypeId.value !== 'all' && Number(item.newsletter_type_id) !== Number(selectedTypeId.value)) {
      return false;
    }

    // 3. Status Filter (sent / draft)
    if (selectedStatus.value !== 'all' && item.status !== selectedStatus.value) {
      return false;
    }

    // 4. Target Role Filter
    if (selectedRole.value !== 'all') {
      const roles = item.target_roles || [];
      if (!roles.includes(selectedRole.value)) return false;
    }

    // 5. Date Range Filter
    if (selectedDateRange.value !== 'all' && item.sent_at_iso) {
      const sentDate = new Date(item.sent_at_iso);
      const now = new Date();
      if (selectedDateRange.value === '30_days') {
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(now.getDate() - 30);
        if (sentDate < thirtyDaysAgo) return false;
      } else if (selectedDateRange.value === 'this_year') {
        if (sentDate.getFullYear() !== now.getFullYear()) return false;
      }
    }

    return true;
  });
});

// Sending emails everyone once and cannot be undone, so it waits for the confirm dialog.
const newsletterToSend = ref(null);
const sending = ref(false);
const sendBroadcast = () => {
  sending.value = true;
  router.post(route('admin.newsletters.send', { clubSlug: props.club.slug, id: newsletterToSend.value.id }), {}, {
    onFinish: () => { sending.value = false; newsletterToSend.value = null; },
  });
};

const duplicateNewsletter = (id) => router.post(route('admin.newsletters.duplicate', { clubSlug: props.club.slug, id }));
const retryFailed = (id) => router.post(route('admin.newsletters.retry', { clubSlug: props.club.slug, id }), {}, { preserveScroll: true });

const newsletterToDelete = ref(null);
const deleteNewsletter = () => {
  router.delete(route('admin.newsletters.destroy', { clubSlug: props.club.slug, id: newsletterToDelete.value.id }), {
    onFinish: () => { newsletterToDelete.value = null; },
  });
};

const people = (count) => `${count} ${count === 1 ? 'person' : 'people'}`;
const sentCount = computed(() => (props.newsletters || []).filter(n => n.status === 'sent').length);
const draftCount = computed(() => (props.newsletters || []).filter(n => n.status === 'draft').length);

const statusOptions = computed(() => [
  { value: 'all', label: `All statuses (${props.newsletters.length})` },
  { value: 'sent', label: `Sent (${sentCount.value})` },
  { value: 'draft', label: `Drafts (${draftCount.value})` },
]);
const channelOptions = computed(() => [{ value: 'all', label: 'All channels' }, ...(props.types || []).map((t) => ({ value: t.id, label: `${t.icon} ${t.name}` }))]);
const roleOptions = [{ value: 'all', label: 'All roles' }, ...availableRoles.map((r) => ({ value: r.id, label: r.label }))];
const DATE_OPTIONS = [
  { value: 'all', label: 'All time' },
  { value: '30_days', label: 'Last 30 days' },
  { value: 'this_year', label: 'This year' },
];
const roleLabel = (role) => availableRoles.find((r) => r.id === role)?.label ?? role;
</script>

<template>
  <AdminLayout title="Newsletters and email broadcasts" :club="club" active-tab="newsletters">
    <Head title="Newsletters and email broadcasts" />

    <div class="mx-auto max-w-6xl space-y-6">
      <CommunicationsTabs :club="club" active-tab="newsletters" />

      <Card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Newsletters and broadcasts</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Write and send emails to a channel, to chosen roles, or to visiting subscribers.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <Button variant="secondary" size="sm" :href="route('admin.newsletters.subscribers', { clubSlug: club.slug })">Manage subscribers</Button>
            <Button variant="secondary" size="sm" :href="route('admin.newsletters.types', { clubSlug: club.slug })">Channels and settings</Button>
            <Button size="sm" :href="route('admin.newsletters.create', { clubSlug: club.slug })">Write a newsletter</Button>
          </div>
        </div>
      </Card>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard label="Sent" :value="sentCount" />
        <StatCard label="Drafts" :value="draftCount" />
        <StatCard label="Channels" :value="(types || []).length" />
      </div>

      <Card>
        <div class="space-y-4">
          <div class="grid grid-cols-1 items-end gap-3 border-b border-slate-100 pb-4 dark:border-slate-800 sm:grid-cols-2 lg:grid-cols-5">
            <Input v-model="searchQuery" class="sm:col-span-2 lg:col-span-1" type="search" label="Search" placeholder="Subject or content" />
            <Select v-model="selectedStatus" label="Status" :options="statusOptions" />
            <Select v-model="selectedTypeId" label="Channel" :options="channelOptions" />
            <Select v-model="selectedRole" label="Role" :options="roleOptions" />
            <Select v-model="selectedDateRange" label="Sent" :options="DATE_OPTIONS" />
          </div>

          <div v-if="filteredNewsletters.length" class="space-y-4">
            <div v-for="item in filteredNewsletters" :key="item.id" class="flex flex-col justify-between gap-6 rounded-xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-800/50 md:flex-row md:items-center">
              <div class="min-w-0 space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                  <Badge v-if="item.status === 'sent'" variant="success">Sent {{ item.sent_at_iso ? formatLocal(item.sent_at_iso) : item.sent_at }}</Badge>
                  <Badge v-else variant="warning">Draft</Badge>

                  <Badge>
                    <span :style="{ backgroundColor: item.type_color }" class="inline-block h-2 w-2 rounded-full" aria-hidden="true"></span>
                    {{ item.type_icon }} {{ item.type_name }}
                  </Badge>

                  <Badge v-for="role in item.target_roles" :key="role">{{ roleLabel(role) }}</Badge>

                  <Badge>
                    To {{ people(item.recipient_count) }}<template v-if="item.external_recipient_count > 0">, {{ item.external_recipient_count }} visiting</template>
                  </Badge>

                  <template v-if="item.status === 'sent'">
                    <Badge variant="success">{{ item.deliveries.sent }} delivered</Badge>
                    <Badge v-if="item.deliveries.queued">{{ item.deliveries.queued }} waiting</Badge>
                    <Badge v-if="item.deliveries.failed" variant="danger">{{ item.deliveries.failed }} failed</Badge>
                  </template>

                  <Badge v-if="item.attachments && item.attachments.length" variant="info">
                    {{ item.attachments.length }} {{ item.attachments.length === 1 ? 'attachment' : 'attachments' }}
                  </Badge>
                </div>

                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ item.subject }}</h3>

                <div v-if="item.attachments && item.attachments.length" class="flex flex-wrap gap-2">
                  <a
                    v-for="(att, aIdx) in item.attachments"
                    :key="aIdx"
                    :href="att.url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                  >
                    {{ att.name }}
                    <span v-if="att.size" class="text-slate-500 dark:text-slate-400">({{ att.size }})</span>
                  </a>
                </div>
              </div>

              <div class="flex flex-wrap items-center gap-2 md:shrink-0">
                <Button v-if="item.status === 'draft'" size="sm" @click="newsletterToSend = item">Send</Button>
                <Button v-if="item.status === 'sent' && item.deliveries.failed" variant="secondary" size="sm" @click="retryFailed(item.id)">Retry {{ item.deliveries.failed }} failed</Button>
                <Button v-if="item.status === 'sent'" variant="secondary" size="sm" @click="duplicateNewsletter(item.id)">Duplicate</Button>
                <Button v-if="item.status === 'draft'" variant="secondary" size="sm" :href="route('admin.newsletters.edit', { clubSlug: club.slug, id: item.id })">Edit draft</Button>
                <Button variant="ghost" size="sm" @click="newsletterToDelete = item">Delete</Button>
              </div>
            </div>
          </div>

          <p v-else class="p-12 text-center text-sm text-slate-500 dark:text-slate-400">No newsletters match these filters.</p>
        </div>
      </Card>
    </div>

    <ConfirmDialog
      :open="Boolean(newsletterToSend)"
      title="Send this newsletter now?"
      confirm-label="Send now"
      cancel-label="Not yet"
      variant="primary"
      :processing="sending"
      @confirm="sendBroadcast"
      @close="newsletterToSend = null"
    >
      "{{ newsletterToSend?.subject }}" will be emailed to {{ people(newsletterToSend?.recipient_count ?? 0) }} now. It cannot be changed or sent again afterwards.
    </ConfirmDialog>

    <ConfirmDialog :open="Boolean(newsletterToDelete)" title="Delete this newsletter?" confirm-label="Delete newsletter" @confirm="deleteNewsletter" @close="newsletterToDelete = null">
      "{{ newsletterToDelete?.subject }}" will be deleted.
    </ConfirmDialog>
  </AdminLayout>
</template>
