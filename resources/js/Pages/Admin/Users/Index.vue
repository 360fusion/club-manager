<script setup>
import { useClipboard } from '@/Composables/useClipboard';
import { ref, computed, watch } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Modal from '@/Components/Ui/Modal.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
  club: {
    type: Object,
    required: true,
  },
  // One page of the roster (a Laravel paginator): the server applies the search, tab and filters.
  members: {
    type: Object,
    required: true,
  },
  pendingMembers: {
    type: Array,
    default: () => [],
  },
  counts: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  enableMemberRanks: {
    type: Boolean,
    default: true,
  },
  memberRanks: {
    type: Array,
    default: () => [],
  },
});

// Search and filtering state
const searchQuery = ref(props.filters.q ?? '');
const roleFilter = ref(props.filters.role ?? '');
const rankFilter = ref(props.filters.rank ?? '');
const statusFilter = ref(props.filters.status ?? '');
const committeeRoleFilter = ref(props.filters.committee ?? '');
const activeRosterTab = ref(props.filters.tab || 'all'); // 'all', 'active', 'invited_pending', 'deactivated', 'past'
const { copied: inviteCopied, copy } = useClipboard({ resetAfter: 2500 });

const registrationUrl = computed(() => {
  if (typeof window !== 'undefined') {
    return `${window.location.origin}/register?club=${props.club.slug}`;
  }
  return `/register?club=${props.club.slug}`;
});

const copyRegistrationLink = async () => {
  if (!(await copy(registrationUrl.value))) {
    prompt('Copy your public registration link:', registrationUrl.value);
  }
};

// Modals state
const showAddModal = ref(false);

// Forms
const addForm = useForm({
  name: '',
  email: '',
  role: 'member',
  rank: '',
  committee_role: '',
  member_number: '',
  send_invite: true,
});

// Role Badge Color Mapper
const roleBadgeClass = (role) => {
  switch (role) {
    case 'owner':
    case 'admin':
      return 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/60';
    case 'coach':
      return 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/60';
    case 'treasurer':
      return 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60';
    case 'examiner':
      return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700';
    default:
      return 'bg-white dark:bg-slate-950 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700';
  }
};

// Any change to the search or filters reloads the first page from the server.
let reloadTimer = null;
const reloadRoster = () => {
  clearTimeout(reloadTimer);
  reloadTimer = setTimeout(() => {
    router.get(route('admin.users.index', { clubSlug: props.club.slug }), {
      q: searchQuery.value || undefined,
      tab: activeRosterTab.value === 'all' ? undefined : activeRosterTab.value,
      role: roleFilter.value || undefined,
      rank: rankFilter.value || undefined,
      status: statusFilter.value || undefined,
      committee: committeeRoleFilter.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true, only: ['members', 'filters'] });
  }, 300);
};
watch([searchQuery, roleFilter, rankFilter, statusFilter, committeeRoleFilter, activeRosterTab], reloadRoster);

// Actions
const updateRole = (userId, newRole) => {
  router.post(
    route('admin.users.role.update', { clubSlug: props.club.slug, userId }),
    { role: newRole },
    { preserveScroll: true }
  );
};

const updateRank = (userId, newRank) => {
  router.post(
    route('admin.users.rank.update', { clubSlug: props.club.slug, userId }),
    { rank: newRank },
    { preserveScroll: true }
  );
};

const updateCommitteeRole = (userId, newCommitteeRole) => {
  router.post(
    route('admin.users.committee_role.update', { clubSlug: props.club.slug, userId }),
    { committee_role: newCommitteeRole || null },
    { preserveScroll: true }
  );
};

const updateStatus = (userId, status) => {
  router.post(
    route('admin.users.status.update', { clubSlug: props.club.slug, userId }),
    { status },
    { preserveScroll: true }
  );
};

const restoreMember = (userId, name) => {
  if (confirm(`Restore ${name} to active member status?`)) {
    updateStatus(userId, 'active');
  }
};

const forceDeleteMember = (userId, name) => {
  if (confirm(`Are you sure you want to delete ${name}? This will hide them from the member directory, but all historical records (RSVPs, dues) will be safely preserved.`)) {
    router.delete(route('admin.users.force_delete', { clubSlug: props.club.slug, userId }), {
      preserveScroll: true,
    });
  }
};

const sendInviteEmail = (userId) => {
  router.post(
    route('admin.users.invite', { clubSlug: props.club.slug, userId }),
    {},
    { preserveScroll: true }
  );
};

const revokeInviteEmail = (userId, name) => {
  if (confirm(`Are you sure you want to revoke the account invitation for ${name}?`)) {
    router.post(
      route('admin.users.revoke_invite', { clubSlug: props.club.slug, userId }),
      {},
      { preserveScroll: true }
    );
  }
};

const approveMember = (userId) => {
  router.post(
    route('clubs.members.approve', { slug: props.club.slug, userId }),
    {},
    { preserveScroll: true }
  );
};

const rejectMember = (userId) => {
  if (confirm('Reject this member registration request?')) {
    router.post(
      route('clubs.members.reject', { slug: props.club.slug, userId }),
      {},
      { preserveScroll: true }
    );
  }
};

const submitAddMember = () => {
  addForm.post(route('admin.users.store', { clubSlug: props.club.slug }), {
    onSuccess: () => {
      addForm.reset();
      showAddModal.value = false;
    },
  });
};

</script>

<template>
  <AdminLayout title="Members" :club="club" active-tab="members">

    <div class="space-y-6">

      <!-- Header and actions -->
      <Card>
        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
          <div>
            <div class="flex flex-wrap items-center gap-3">
              <h2 class="text-xl font-bold text-slate-900 dark:text-white">Member roster</h2>
              <Badge variant="info">{{ counts.all }} members</Badge>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage the member directory, member numbers, roles, pending approvals and roster exports.</p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <a
              :href="route('clubs.members.export', { slug: club.slug })"
              class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
              <UiIcon name="download" />
              <span>Export CSV</span>
            </a>

            <a
              :href="`/${club.slug}/admin/members/import`"
              class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
              <UiIcon name="upload" />
              <span>Import CSV</span>
            </a>

            <Button size="sm" @click="showAddModal = true">
              <template #icon><UiIcon name="plus" /></template>
              Add member
            </Button>
          </div>
        </div>
      </Card>

      <!-- Pending approval requests -->
      <Alert v-if="pendingMembers.length > 0" variant="warning" :title="`Pending approval requests (${pendingMembers.length})`">
        <p class="mb-3">Invite-only registrations waiting for an admin.</p>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
          <div v-for="pending in pendingMembers" :key="pending.id" class="flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-white p-3 dark:border-amber-800/40 dark:bg-slate-900">
            <div class="overflow-hidden">
              <div class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ pending.name }}</div>
              <div class="truncate text-xs text-slate-500 dark:text-slate-400">{{ pending.email }}</div>
              <div v-if="pending.matching_member" class="truncate text-xs font-medium text-emerald-700 dark:text-emerald-400">Matches member record: {{ pending.matching_member }}</div>
            </div>
            <div class="flex flex-shrink-0 items-center gap-1.5">
              <Button variant="secondary" size="sm" @click="approveMember(pending.id)">Approve</Button>
              <Button variant="ghost" size="sm" @click="rejectMember(pending.id)">Reject</Button>
            </div>
          </div>
        </div>
      </Alert>

      <!-- Roster tabs -->
      <nav aria-label="Roster" class="max-w-full overflow-x-auto">
        <div class="flex w-fit items-center gap-1 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 dark:border-slate-700/60 dark:bg-slate-800/80">
          <button
            v-for="tab in [
              { key: 'all', label: 'All members', count: counts.all },
              { key: 'active', label: 'Active', count: counts.active },
              { key: 'invited_pending', label: 'Invited and pending', count: counts.invited_pending },
              { key: 'deactivated', label: 'Deactivated', count: counts.deactivated },
              { key: 'past', label: 'Past members', count: counts.past },
            ]"
            :key="tab.key"
            type="button"
            :aria-pressed="activeRosterTab === tab.key"
            :class="[
              'inline-flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-xs font-semibold transition-colors',
              activeRosterTab === tab.key
                ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-700'
                : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white'
            ]"
            @click="activeRosterTab = tab.key"
          >
            <span>{{ tab.label }}</span>
            <Badge>{{ tab.count }}</Badge>
          </button>
        </div>
      </nav>

      <!-- Search and filters -->
      <Card padding="sm" class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <div class="relative w-full sm:w-80">
          <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><UiIcon name="search" /></span>
          <input
            v-model="searchQuery"
            type="text"
            aria-label="Search members"
            placeholder="Search by name, email or member number"
            class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 placeholder-slate-400 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder-slate-500"
          />
        </div>

        <div class="flex w-full flex-wrap items-center justify-end gap-2 sm:w-auto">
          <select
            v-if="enableMemberRanks"
            v-model="rankFilter"
            aria-label="Filter by rank"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
          >
            <option value="">All ranks</option>
            <option v-for="r in memberRanks" :key="r" :value="r">{{ r }}</option>
          </select>

          <select
            v-model="roleFilter"
            aria-label="Filter by role"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
          >
            <option value="">All roles</option>
            <option value="admin">Admin / owner</option>
            <option value="coach">Secretary</option>
            <option value="treasurer">Treasurer</option>
            <option value="examiner">Examiner / auditor</option>
            <option value="member">Media manager</option>
          </select>

          <select
            v-model="statusFilter"
            aria-label="Filter by status"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
          >
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Deactivated</option>
            <option value="past">Past member</option>
            <option value="pending">Pending</option>
          </select>

          <select
            v-model="committeeRoleFilter"
            aria-label="Filter by committee role"
            class="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
          >
            <option value="">All committee roles</option>
            <option value="any">On committee (any)</option>
            <option value="chair">Chair</option>
            <option value="secretary">Secretary</option>
            <option value="member">Member</option>
            <option value="none">Not on committee</option>
          </select>
        </div>
      </Card>

      <!-- Members table -->
      <Card padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-left">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-400">
                <th class="px-6 py-3.5">Name and email</th>
                <th class="px-4 py-3.5">Member number</th>
                <th v-if="enableMemberRanks" class="px-4 py-3.5">Rank</th>
                <th class="px-4 py-3.5">Club role</th>
                <th class="px-4 py-3.5">Committee role</th>
                <th class="px-4 py-3.5">Status</th>
                <th class="px-4 py-3.5">Joined</th>
                <th class="px-6 py-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs text-slate-700 dark:divide-slate-800 dark:text-slate-200">
              <tr
                v-for="m in members.data"
                :key="m.id"
                class="transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-800/50"
              >
                <!-- Name & Email -->
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <Link
                      :href="route('admin.users.show', { clubSlug: club.slug, userId: m.id })"
                      class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-600 to-blue-700 text-xs font-semibold uppercase text-white shadow-sm transition-opacity hover:opacity-90"
                    >
                      {{ m.name.substring(0, 2) }}
                    </Link>
                    <div class="overflow-hidden">
                      <Link
                        :href="route('admin.users.show', { clubSlug: club.slug, userId: m.id })"
                        class="block truncate text-sm font-medium text-slate-900 transition-colors hover:text-blue-600 dark:text-white dark:hover:text-blue-400"
                      >
                        {{ m.name }}
                      </Link>
                      <div class="truncate text-xs text-slate-500 dark:text-slate-400">{{ m.email }}</div>
                    </div>
                  </div>
                </td>

                <!-- Member Number -->
                <td class="px-4 py-4 font-mono font-medium text-slate-800 dark:text-slate-100">
                  {{ m.member_number }}
                </td>

                <!-- Rank Selector -->
                <td v-if="enableMemberRanks" class="px-4 py-4">
                  <select
                    :value="m.rank"
                    aria-label="Rank"
                    @change="updateRank(m.id, $event.target.value)"
                    class="cursor-pointer rounded-xl border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                  >
                    <option value="">No rank</option>
                    <option v-for="r in memberRanks" :key="r" :value="r">{{ r }}</option>
                  </select>
                </td>

                <!-- Role Selector -->
                <td class="px-4 py-4">
                  <select
                    :value="m.role"
                    aria-label="Club role"
                    @change="updateRole(m.id, $event.target.value)"
                    :class="[
                      'cursor-pointer rounded-xl border px-2.5 py-1 text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500',
                      roleBadgeClass(m.role)
                    ]"
                  >
                    <option value="member">Media manager</option>
                    <option value="coach">Secretary</option>
                    <option value="treasurer">Treasurer</option>
                    <option value="examiner">Examiner / auditor</option>
                    <option value="admin">Admin</option>
                  </select>
                </td>

                <!-- Committee Role Selector -->
                <td class="px-4 py-4">
                  <select
                    :value="m.committee_role || ''"
                    aria-label="Committee role"
                    @change="updateCommitteeRole(m.id, $event.target.value)"
                    :class="[
                      'cursor-pointer rounded-xl border px-2.5 py-1 text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500',
                      m.committee_role === 'chair'
                        ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700/60'
                        : m.committee_role === 'secretary'
                        ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700/60'
                        : m.committee_role === 'member'
                        ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border-emerald-300 dark:border-emerald-700/60'
                        : 'bg-white dark:bg-slate-950 text-slate-500 dark:text-slate-400 border-slate-300 dark:border-slate-700'
                    ]"
                  >
                    <option value="">None</option>
                    <option value="chair">Chair</option>
                    <option value="secretary">Secretary</option>
                    <option value="member">Member</option>
                  </select>
                </td>

                <!-- Status -->
                <td class="px-4 py-4">
                  <Badge
                    :variant="m.status === 'active' ? 'success' : m.status === 'inactive' ? 'neutral' : m.status === 'past' ? 'danger' : 'warning'"
                    class="capitalize"
                  >
                    {{ m.status === 'inactive' ? 'Deactivated' : m.status === 'past' ? 'Past member' : m.status }}
                  </Badge>
                </td>

                <!-- Joined Date -->
                <td class="px-4 py-4 text-slate-500 dark:text-slate-400">
                  {{ m.joined_at }}
                </td>

                <!-- Actions -->
                <td class="space-x-1.5 whitespace-nowrap px-6 py-4 text-right">
                  <!-- 1. Invitation Actions for pending/unaccepted members -->
                  <template v-if="m.status === 'pending' && !m.invitation_accepted_at">
                    <Button
                      variant="secondary"
                      size="sm"
                      @click="sendInviteEmail(m.id)"
                      :title="m.invitation_token ? `Invited on ${m.invited_at}` : 'Send activation email'"
                    >
                      <template #icon><UiIcon name="mail" /></template>
                      {{ m.invitation_token ? 'Resend' : 'Invite' }}
                    </Button>
                    <Button
                      v-if="m.invitation_token || m.invited_at"
                      variant="ghost"
                      size="sm"
                      @click="revokeInviteEmail(m.id, m.name)"
                      title="Revoke active invitation token"
                    >
                      Revoke
                    </Button>
                  </template>

                  <!-- 2. Status Actions depending on member status -->
                  <!-- Deactivated Member -->
                  <template v-if="m.status === 'inactive'">
                    <Button variant="secondary" size="sm" @click="restoreMember(m.id, m.name)">
                      <template #icon><UiIcon name="restore" /></template>
                      Restore access
                    </Button>
                  </template>

                  <!-- Past Member -->
                  <template v-else-if="m.status === 'past'">
                    <Button variant="secondary" size="sm" @click="restoreMember(m.id, m.name)">
                      <template #icon><UiIcon name="restore" /></template>
                      Restore member
                    </Button>
                    <Button
                      variant="danger"
                      size="sm"
                      @click="forceDeleteMember(m.id, m.name)"
                      title="Delete from member directory (preserves historical records)"
                    >
                      Delete
                    </Button>
                  </template>
                </td>
              </tr>

              <tr v-if="members.data.length === 0">
                <td :colspan="enableMemberRanks ? 7 : 6" class="py-12 text-center text-slate-500 dark:text-slate-400">
                  <p class="text-sm">No members found matching your search or filters.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="border-t border-slate-100 px-6 py-3 dark:border-slate-800"><Pagination :links="members.links" /></div>
      </Card>

      <!-- Public registration link -->
      <Card padding="sm" class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <span class="text-blue-600 dark:text-blue-400"><UiIcon name="link" /></span>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Public registration link</h3>
            <Badge variant="info">Invite link</Badge>
          </div>
          <p class="text-sm text-slate-500 dark:text-slate-400">Share this link with prospective members so they can register or ask to join.</p>
        </div>

        <div class="flex w-full items-center gap-2 sm:w-auto">
          <input
            type="text"
            readonly
            aria-label="Public registration link"
            :value="registrationUrl"
            class="w-full truncate rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono text-xs text-slate-700 select-all focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 sm:w-80"
          />
          <Button variant="secondary" class="whitespace-nowrap" @click="copyRegistrationLink">
            <template #icon><UiIcon :name="inviteCopied ? 'check' : 'copy'" /></template>
            {{ inviteCopied ? 'Copied' : 'Copy link' }}
          </Button>
        </div>
      </Card>

    </div>

    <!-- Add member -->
    <Modal :open="showAddModal" title="Add a member" @close="showAddModal = false">
      <form id="add-member-form" @submit.prevent="submitAddMember" class="space-y-4">
        <Input
          v-model="addForm.name"
          label="Full name"
          required
          placeholder="e.g. Samuel Green"
        />

        <Input
          v-model="addForm.email"
          type="email"
          label="Email address"
          required
          placeholder="samuel@example.com"
        />

        <div class="grid grid-cols-2 gap-3">
          <Select
            v-model="addForm.role"
            label="Club role"
            :options="[
              { value: 'member', label: 'Media manager' },
              { value: 'coach', label: 'Secretary' },
              { value: 'treasurer', label: 'Treasurer' },
              { value: 'examiner', label: 'Examiner / auditor' },
              { value: 'admin', label: 'Admin' },
            ]"
          />

          <Select
            v-model="addForm.committee_role"
            label="Committee role"
            :options="[
              { value: '', label: 'Not on committee' },
              { value: 'chair', label: 'Chair' },
              { value: 'secretary', label: 'Secretary' },
              { value: 'member', label: 'Committee member' },
            ]"
          />

          <Select
            v-if="enableMemberRanks"
            v-model="addForm.rank"
            label="Member rank"
            :options="[{ value: '', label: 'No rank assigned' }, ...memberRanks.map((r) => ({ value: r, label: r }))]"
          />

          <Input
            v-model="addForm.member_number"
            label="Member number"
            placeholder="OUBC-204"
            :class="enableMemberRanks ? '' : 'col-span-2'"
          />
        </div>

        <label class="flex cursor-pointer items-center gap-2.5 pt-1">
          <input
            type="checkbox"
            v-model="addForm.send_invite"
            class="h-4 w-4 cursor-pointer rounded accent-blue-600"
          />
          <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Send an email invitation to activate the account</span>
        </label>
      </form>

      <template #footer>
        <Button variant="secondary" @click="showAddModal = false">Cancel</Button>
        <Button type="submit" form="add-member-form" :disabled="addForm.processing">Add member</Button>
      </template>
    </Modal>

  </AdminLayout>
</template>
