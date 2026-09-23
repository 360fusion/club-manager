<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: {
    type: Object,
    required: true,
  },
  members: {
    type: Array,
    default: () => [],
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
const searchQuery = ref('');
const roleFilter = ref('');
const rankFilter = ref('');
const statusFilter = ref('');
const committeeRoleFilter = ref('');
const activeRosterTab = ref('all'); // 'all', 'active', 'invited_pending', 'deactivated', 'past'
const inviteCopied = ref(false);

const registrationUrl = computed(() => {
  if (typeof window !== 'undefined') {
    return `${window.location.origin}/register?club=${props.club.slug}`;
  }
  return `/register?club=${props.club.slug}`;
});

const copyRegistrationLink = () => {
  const url = registrationUrl.value;
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(url).then(() => {
      inviteCopied.value = true;
      setTimeout(() => (inviteCopied.value = false), 2500);
    }).catch(() => {
      fallbackCopyText(url);
    });
  } else {
    fallbackCopyText(url);
  }
};

const fallbackCopyText = (text) => {
  try {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    const successful = document.execCommand('copy');
    document.body.removeChild(textArea);
    if (successful) {
      inviteCopied.value = true;
      setTimeout(() => (inviteCopied.value = false), 2500);
    } else {
      prompt('Copy your public registration link:', text);
    }
  } catch (err) {
    prompt('Copy your public registration link:', text);
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
    default:
      return 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-800';
  }
};

// Computed counts for tabs
// Join requests only: people who were invited by email are pending too, but they approve themselves by accepting.
const pendingMembers = computed(() => {
  return props.members.filter((m) => m.status === 'pending' && !m.invitation_token);
});

const counts = computed(() => {
  return {
    all: props.members.length,
    active: props.members.filter((m) => m.status === 'active').length,
    invited_pending: props.members.filter((m) => m.status === 'pending').length,
    deactivated: props.members.filter((m) => m.status === 'inactive').length,
    past: props.members.filter((m) => m.status === 'past').length,
  };
});

const filteredMembers = computed(() => {
  return props.members.filter((m) => {
    // 1. Tab Filter
    let matchesTab = true;
    if (activeRosterTab.value === 'active') {
      matchesTab = m.status === 'active';
    } else if (activeRosterTab.value === 'invited_pending') {
      matchesTab = m.status === 'pending';
    } else if (activeRosterTab.value === 'deactivated') {
      matchesTab = m.status === 'inactive';
    } else if (activeRosterTab.value === 'past') {
      matchesTab = m.status === 'past';
    }

    // 2. Search & Dropdown Filters
    const matchesSearch =
      m.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      m.email.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (m.member_number && m.member_number.toLowerCase().includes(searchQuery.value.toLowerCase()));

    const matchesRole = roleFilter.value ? m.role === roleFilter.value : true;
    const matchesRank = rankFilter.value ? m.rank === rankFilter.value : true;
    const matchesStatus = statusFilter.value ? m.status === statusFilter.value : true;
    const matchesCommittee = committeeRoleFilter.value
      ? (committeeRoleFilter.value === 'any'
          ? !!m.committee_role
          : (committeeRoleFilter.value === 'none'
              ? !m.committee_role
              : m.committee_role === committeeRoleFilter.value))
      : true;

    return matchesTab && matchesSearch && matchesRole && matchesRank && matchesStatus && matchesCommittee;
  });
});

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

const removeMember = (userId, name) => {
  if (confirm(`Are you sure you want to remove ${name} from the club roster?`)) {
    router.delete(route('admin.users.destroy', { clubSlug: props.club.slug, userId }), {
      preserveScroll: true,
    });
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
      
      <!-- Top Action Bar & Summary Header -->
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Club Member Roster</h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
              {{ members.length }} Total Members
            </span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Manage club member directory, member IDs, administrative roles, pending approvals, and roster exports.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
          <a
            :href="route('clubs.members.export', { slug: club.slug })"
            class="px-3.5 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 shadow-sm"
          >
            <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>Export CSV</span>
          </a>

          <a
            :href="`/${club.slug}/admin/members/import`"
            class="px-3.5 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 shadow-sm"
          >
            <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            <span>Import CSV</span>
          </a>

          <button
            @click="showAddModal = true"
            class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md shadow-blue-600/20 transition-all flex items-center gap-1.5"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>Add Member</span>
          </button>
        </div>
      </div>

      <!-- Pending Approval Banner (If Invite-Only Registrations are Pending) -->
      <div v-if="pendingMembers.length > 0" class="bg-amber-50/80 dark:bg-amber-950/80 border border-amber-200 dark:border-amber-800/60 p-5 rounded-2xl shadow-sm space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
            <h3 class="text-sm font-bold text-amber-900 dark:text-amber-200">
              Pending Member Approval Requests ({{ pendingMembers.length }})
            </h3>
          </div>
          <span class="text-xs text-amber-700 dark:text-amber-300 font-medium">Invite-Only Approvals</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="pending in pendingMembers" :key="pending.id" class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-amber-200/90 dark:border-amber-800/90 shadow-sm flex items-center justify-between gap-3">
            <div class="overflow-hidden">
              <div class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ pending.name }}</div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ pending.email }}</div>
              <div v-if="pending.matching_member" class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-300 truncate">Matches member record: {{ pending.matching_member }}</div>
            </div>
            <div class="flex items-center gap-1.5 flex-shrink-0">
              <button
                @click="approveMember(pending.id)"
                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold rounded-lg transition-all shadow-sm"
              >
                Approve
              </button>
              <button
                @click="rejectMember(pending.id)"
                class="px-2.5 py-1.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/60 text-[11px] font-bold rounded-lg transition-all"
              >
                Reject
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs along top of Roster -->
      <div class="flex items-center gap-1.5 overflow-x-auto pb-1 border-b border-slate-200/80 dark:border-slate-800/80">
        <button
          @click="activeRosterTab = 'all'"
          :class="[
            'px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap',
            activeRosterTab === 'all'
              ? 'bg-slate-900 dark:bg-slate-700 text-white shadow-sm'
              : 'bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-slate-800/80'
          ]"
        >
          <span>👥 All Members</span>
          <span :class="['px-2 py-0.5 rounded-full text-[10px] font-extrabold', activeRosterTab === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200']">
            {{ counts.all }}
          </span>
        </button>

        <button
          @click="activeRosterTab = 'active'"
          :class="[
            'px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap',
            activeRosterTab === 'active'
              ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/20'
              : 'bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-slate-800/80'
          ]"
        >
          <span>✅ Active</span>
          <span :class="['px-2 py-0.5 rounded-full text-[10px] font-extrabold', activeRosterTab === 'active' ? 'bg-emerald-700 text-white' : 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60']">
            {{ counts.active }}
          </span>
        </button>

        <button
          @click="activeRosterTab = 'invited_pending'"
          :class="[
            'px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap',
            activeRosterTab === 'invited_pending'
              ? 'bg-amber-600 text-white shadow-sm shadow-amber-600/20'
              : 'bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-slate-800/80'
          ]"
        >
          <span>✉️ Invited & Pending</span>
          <span :class="['px-2 py-0.5 rounded-full text-[10px] font-extrabold', activeRosterTab === 'invited_pending' ? 'bg-amber-700 text-white' : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60']">
            {{ counts.invited_pending }}
          </span>
        </button>

        <button
          @click="activeRosterTab = 'deactivated'"
          :class="[
            'px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap',
            activeRosterTab === 'deactivated'
              ? 'bg-slate-700 text-white shadow-sm'
              : 'bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-slate-800/80'
          ]"
        >
          <span>⏸️ Deactivated</span>
          <span :class="['px-2 py-0.5 rounded-full text-[10px] font-extrabold', activeRosterTab === 'deactivated' ? 'bg-slate-800 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800']">
            {{ counts.deactivated }}
          </span>
        </button>

        <button
          @click="activeRosterTab = 'past'"
          :class="[
            'px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer whitespace-nowrap',
            activeRosterTab === 'past'
              ? 'bg-rose-600 text-white shadow-sm shadow-rose-600/20'
              : 'bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-600 dark:text-slate-300 border border-slate-200/80 dark:border-slate-800/80'
          ]"
        >
          <span>📜 Past Members</span>
          <span :class="['px-2 py-0.5 rounded-full text-[10px] font-extrabold', activeRosterTab === 'past' ? 'bg-rose-700 text-white' : 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60']">
            {{ counts.past }}
          </span>
        </button>
      </div>

      <!-- Filter & Search Controls -->
      <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-col sm:flex-row gap-3 items-center justify-between">
        <!-- Search Input -->
        <div class="relative w-full sm:w-80">
          <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by name, email, or MEM #..."
            class="w-full pl-10 pr-4 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-xs rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
          />
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto justify-end">
          <select
            v-if="enableMemberRanks"
            v-model="rankFilter"
            class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 text-xs rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none font-medium"
          >
            <option value="">All Ranks</option>
            <option v-for="r in memberRanks" :key="r" :value="r">🏅 {{ r }}</option>
          </select>

          <select
            v-model="roleFilter"
            class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 text-xs rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
          >
            <option value="">All Roles</option>
            <option value="admin">Admin / Owner</option>
            <option value="coach">Secretary</option>
            <option value="treasurer">Treasurer</option>
            <option value="member">Media Manager</option>
          </select>

          <select
            v-model="statusFilter"
            class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 text-xs rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
          >
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Deactivated</option>
            <option value="past">Past Member</option>
            <option value="pending">Pending</option>
          </select>

          <select
            v-model="committeeRoleFilter"
            class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 text-xs rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none font-medium"
          >
            <option value="">All Committee Roles</option>
            <option value="any">On Committee (Any)</option>
            <option value="chair">👑 Chair</option>
            <option value="secretary">✍️ Secretary</option>
            <option value="member">🏛️ Member</option>
            <option value="none">Not on Committee</option>
          </select>
        </div>
      </div>

      <!-- Members Directory Table -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/80 dark:bg-slate-800/80 border-b border-slate-200/80 dark:border-slate-800/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                <th class="py-3.5 px-6">Member Name & Email</th>
                <th class="py-3.5 px-4">Member ID #</th>
                <th v-if="enableMemberRanks" class="py-3.5 px-4">Rank</th>
                <th class="py-3.5 px-4">Club Role</th>
                <th class="py-3.5 px-4">Committee Role</th>
                <th class="py-3.5 px-4">Status</th>
                <th class="py-3.5 px-4">Joined Date</th>
                <th class="py-3.5 px-6 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs font-medium text-slate-700 dark:text-slate-200">
              <tr
                v-for="m in filteredMembers"
                :key="m.id"
                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors"
              >
                <!-- Name & Email -->
                <td class="py-4 px-6">
                  <div class="flex items-center gap-3">
                    <Link 
                      :href="route('admin.users.show', { clubSlug: club.slug, userId: m.id })"
                      class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-blue-700 text-white font-bold flex items-center justify-center text-xs uppercase flex-shrink-0 shadow-sm hover:opacity-90 transition-opacity"
                    >
                      {{ m.name.substring(0, 2) }}
                    </Link>
                    <div class="overflow-hidden">
                      <Link 
                        :href="route('admin.users.show', { clubSlug: club.slug, userId: m.id })"
                        class="font-bold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors truncate block"
                      >
                        {{ m.name }}
                      </Link>
                      <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ m.email }}</div>
                    </div>
                  </div>
                </td>

                <!-- Member Number -->
                <td class="py-4 px-4 font-mono font-bold text-slate-800 dark:text-slate-100">
                  {{ m.member_number }}
                </td>

                <!-- Rank Selector -->
                <td v-if="enableMemberRanks" class="py-4 px-4">
                  <select
                    :value="m.rank"
                    @change="updateRank(m.id, $event.target.value)"
                    class="px-2.5 py-1 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 outline-none cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition-all"
                  >
                    <option value="">No Rank</option>
                    <option v-for="r in memberRanks" :key="r" :value="r">🏅 {{ r }}</option>
                  </select>
                </td>

                <!-- Role Selector -->
                <td class="py-4 px-4">
                  <select
                    :value="m.role"
                    @change="updateRole(m.id, $event.target.value)"
                    :class="[
                      'px-2.5 py-1 text-xs font-bold rounded-lg border outline-none cursor-pointer transition-all',
                      roleBadgeClass(m.role)
                    ]"
                  >
                    <option value="member">Media Manager</option>
                    <option value="coach">Secretary</option>
                    <option value="treasurer">Treasurer</option>
                    <option value="admin">Admin</option>
                  </select>
                </td>

                <!-- Committee Role Selector -->
                <td class="py-4 px-4">
                  <select
                    :value="m.committee_role || ''"
                    @change="updateCommitteeRole(m.id, $event.target.value)"
                    :class="[
                      'px-2.5 py-1 text-xs font-bold rounded-lg border outline-none cursor-pointer transition-all',
                      m.committee_role === 'chair'
                        ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border-amber-300 dark:border-amber-700/60'
                        : m.committee_role === 'secretary'
                        ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700/60'
                        : m.committee_role === 'member'
                        ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border-emerald-300 dark:border-emerald-700/60'
                        : 'bg-slate-50 dark:bg-slate-800/50 text-slate-400 border-slate-200 dark:border-slate-800'
                    ]"
                  >
                    <option value="">— None —</option>
                    <option value="chair">👑 Chair</option>
                    <option value="secretary">✍️ Secretary</option>
                    <option value="member">🏛️ Member</option>
                  </select>
                </td>

                <!-- Status -->
                <td class="py-4 px-4">
                  <span
                    :class="[
                      'px-2.5 py-0.5 rounded-full text-[11px] font-bold border uppercase tracking-wider',
                      m.status === 'active'
                        ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60'
                        : m.status === 'inactive'
                        ? 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700'
                        : m.status === 'past'
                        ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800/60'
                        : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60'
                    ]"
                  >
                    {{ m.status === 'inactive' ? 'Deactivated' : m.status === 'past' ? 'Past Member' : m.status }}
                  </span>
                </td>

                <!-- Joined Date -->
                <td class="py-4 px-4 text-slate-500 dark:text-slate-400">
                  {{ m.joined_at }}
                </td>

                <!-- Actions -->
                <td class="py-4 px-6 text-right space-x-1.5 whitespace-nowrap">
                  <!-- 1. Invitation Actions for pending/unaccepted members -->
                  <template v-if="m.status === 'pending' && !m.invitation_accepted_at">
                    <button
                      @click="sendInviteEmail(m.id)"
                      class="px-2 py-1 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 text-xs font-bold rounded-lg transition-all inline-flex items-center gap-1 cursor-pointer"
                      :title="m.invitation_token ? `Invited on ${m.invited_at}` : 'Send activation email'"
                    >
                      <span>✉️</span>
                      <span>{{ m.invitation_token ? 'Resend' : 'Invite' }}</span>
                    </button>
                    <button
                      v-if="m.invitation_token || m.invited_at"
                      @click="revokeInviteEmail(m.id, m.name)"
                      class="px-2 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 text-xs font-bold rounded-lg transition-all cursor-pointer"
                      title="Revoke active invitation token"
                    >
                      <span>Revoke</span>
                    </button>
                  </template>

                  <!-- 2. Status Actions depending on member status -->
                  <!-- Deactivated Member -->
                  <template v-if="m.status === 'inactive'">
                    <button
                      @click="restoreMember(m.id, m.name)"
                      class="px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 text-xs font-bold rounded-lg transition-all cursor-pointer"
                    >
                      ✓ Restore Access
                    </button>
                  </template>

                  <!-- Past Member -->
                  <template v-else-if="m.status === 'past'">
                    <button
                      @click="restoreMember(m.id, m.name)"
                      class="px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 text-xs font-bold rounded-lg transition-all cursor-pointer"
                    >
                      ↺ Restore Member
                    </button>
                    <button
                      @click="forceDeleteMember(m.id, m.name)"
                      class="px-2.5 py-1 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-lg transition-all cursor-pointer shadow-sm"
                      title="Delete from member directory (preserves historical records)"
                    >
                      Delete
                    </button>
                  </template>
                </td>
              </tr>

              <tr v-if="filteredMembers.length === 0">
                <td :colspan="enableMemberRanks ? 7 : 6" class="py-12 text-center text-slate-400">
                  <p class="text-sm font-medium">No members found matching your search or filters.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Public Registration Link Widget Card (At Bottom) -->
      <div class="bg-gradient-to-r from-blue-50/90 dark:from-blue-950/90 via-blue-50/70 dark:via-blue-950/70 to-white p-5 rounded-2xl border border-blue-100/90 dark:border-blue-900/90 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <span class="text-base">🔗</span>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Public Member Registration Link</h3>
            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">Invite Link</span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400">Share this direct URL with prospective members so they can register or submit membership requests.</p>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto">
          <input
            type="text"
            readonly
            :value="registrationUrl"
            class="w-full sm:w-80 px-3 py-2 bg-white dark:bg-slate-900 border border-blue-200 dark:border-blue-800/60 rounded-xl text-xs font-mono text-slate-700 dark:text-slate-200 truncate outline-none select-all shadow-sm"
          />
          <button
            @click="copyRegistrationLink"
            :class="[
              'px-4 py-2 font-bold text-xs rounded-xl transition-all flex items-center gap-1.5 shadow-sm whitespace-nowrap cursor-pointer',
              inviteCopied ? 'bg-emerald-600 text-white' : 'bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white'
            ]"
          >
            <svg v-if="!inviteCopied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
            </svg>
            <svg v-else class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{ inviteCopied ? 'Copied!' : 'Copy Link' }}</span>
          </button>
        </div>
      </div>

    </div>

    <!-- Modal 1: Add Member Modal -->
    <div
      v-if="showAddModal"
      class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-5 border border-slate-100 dark:border-slate-800">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
          <h3 class="text-base font-bold text-slate-900 dark:text-white">Add New Club Member</h3>
          <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg font-bold">
            &times;
          </button>
        </div>

        <form @submit.prevent="submitAddMember" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Full Name</label>
            <input
              v-model="addForm.name"
              type="text"
              required
              placeholder="e.g. Samuel Green"
              class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Email Address</label>
            <input
              v-model="addForm.email"
              type="email"
              required
              placeholder="samuel@example.com"
              class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Club Role</label>
              <select
                v-model="addForm.role"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="member">Media Manager</option>
                <option value="coach">Secretary</option>
                <option value="treasurer">Treasurer</option>
                <option value="admin">Admin</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Committee Role</label>
              <select
                v-model="addForm.committee_role"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">— Not on Committee —</option>
                <option value="chair">👑 Chair</option>
                <option value="secretary">✍️ Secretary</option>
                <option value="member">🏛️ Committee Member</option>
              </select>
            </div>

            <div v-if="enableMemberRanks">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Member Rank</label>
              <select
                v-model="addForm.rank"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">No Rank Assigned</option>
                <option v-for="r in memberRanks" :key="r" :value="r">🏅 {{ r }}</option>
              </select>
            </div>

            <div :class="enableMemberRanks ? '' : 'col-span-2'">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Member Number</label>
              <input
                v-model="addForm.member_number"
                type="text"
                placeholder="OUBC-204"
                class="w-full px-3.5 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          <label class="flex items-center gap-2.5 cursor-pointer pt-1">
            <input
              type="checkbox"
              v-model="addForm.send_invite"
              class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 cursor-pointer"
            />
            <span class="text-xs font-bold text-slate-700 dark:text-slate-200">Send email invitation to activate account</span>
          </label>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
            <button
              type="button"
              @click="showAddModal = false"
              class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="addForm.processing"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md shadow-blue-600/20"
            >
              Add Member
            </button>
          </div>
        </form>
      </div>
    </div>

  </AdminLayout>
</template>
