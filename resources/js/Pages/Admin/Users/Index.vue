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

// Modals state
const showAddModal = ref(false);
const showImportModal = ref(false);

// Forms
const addForm = useForm({
  name: '',
  email: '',
  role: 'member',
  rank: '',
  member_number: '',
  send_invite: true,
});

const importForm = useForm({
  csv_file: null,
});

// Role Badge Color Mapper
const roleBadgeClass = (role) => {
  switch (role) {
    case 'owner':
    case 'admin':
      return 'bg-purple-50 text-purple-700 border-purple-200';
    case 'coach':
      return 'bg-indigo-50 text-indigo-700 border-indigo-200';
    case 'treasurer':
      return 'bg-amber-50 text-amber-700 border-amber-200';
    default:
      return 'bg-slate-100 text-slate-700 border-slate-200';
  }
};

// Computed lists
const pendingMembers = computed(() => {
  return props.members.filter((m) => m.status === 'pending');
});

const filteredMembers = computed(() => {
  return props.members.filter((m) => {
    const matchesSearch =
      m.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      m.email.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (m.member_number && m.member_number.toLowerCase().includes(searchQuery.value.toLowerCase()));

    const matchesRole = roleFilter.value ? m.role === roleFilter.value : true;
    const matchesRank = rankFilter.value ? m.rank === rankFilter.value : true;
    const matchesStatus = statusFilter.value ? m.status === statusFilter.value : true;

    return matchesSearch && matchesRole && matchesRank && matchesStatus;
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

const sendInviteEmail = (userId) => {
  router.post(
    route('admin.users.invite', { clubSlug: props.club.slug, userId }),
    {},
    { preserveScroll: true }
  );
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

const submitImportCsv = () => {
  importForm.post(route('clubs.members.import', { slug: props.club.slug }), {
    onSuccess: () => {
      importForm.reset();
      showImportModal.value = false;
    },
  });
};
</script>

<template>
  <AdminLayout title="Members" :club="club" active-tab="members">
    
    <div class="space-y-6">
      
      <!-- Top Action Bar & Summary Header -->
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-xl font-bold text-slate-900">Club Member Roster</h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-200">
              {{ members.length }} Total Members
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">Manage club member directory, member IDs, administrative roles, pending approvals, and roster exports.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
          <a
            :href="route('clubs.members.export', { slug: club.slug })"
            class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 shadow-sm"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span>Export CSV</span>
          </a>

          <button
            @click="showImportModal = true"
            class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 shadow-sm"
          >
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            <span>Import CSV</span>
          </button>

          <button
            @click="showAddModal = true"
            class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all flex items-center gap-1.5"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            <span>Add Member</span>
          </button>
        </div>
      </div>

      <!-- Pending Approval Banner (If Invite-Only Registrations are Pending) -->
      <div v-if="pendingMembers.length > 0" class="bg-amber-50/80 border border-amber-200 p-5 rounded-2xl shadow-sm space-y-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
            <h3 class="text-sm font-bold text-amber-900">
              Pending Member Approval Requests ({{ pendingMembers.length }})
            </h3>
          </div>
          <span class="text-xs text-amber-700 font-medium">Invite-Only Approvals</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="pending in pendingMembers" :key="pending.id" class="bg-white p-4 rounded-xl border border-amber-200/90 shadow-sm flex items-center justify-between gap-3">
            <div class="overflow-hidden">
              <div class="text-xs font-bold text-slate-900 truncate">{{ pending.name }}</div>
              <div class="text-[11px] text-slate-500 truncate">{{ pending.email }}</div>
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
                class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 text-[11px] font-bold rounded-lg transition-all"
              >
                Reject
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Filter & Search Controls -->
      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80 flex flex-col sm:flex-row gap-3 items-center justify-between">
        <!-- Search Input -->
        <div class="relative w-full sm:w-80">
          <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by name, email, or MEM #..."
            class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 text-slate-800 placeholder-slate-400 text-xs rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition-all"
          />
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto justify-end">
          <select
            v-if="enableMemberRanks"
            v-model="rankFilter"
            class="px-3 py-2 bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none font-medium"
          >
            <option value="">All Ranks</option>
            <option v-for="r in memberRanks" :key="r" :value="r">🏅 {{ r }}</option>
          </select>

          <select
            v-model="roleFilter"
            class="px-3 py-2 bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
          >
            <option value="">All Roles</option>
            <option value="admin">Admin / Owner</option>
            <option value="coach">Coach</option>
            <option value="treasurer">Treasurer</option>
            <option value="member">Member</option>
          </select>

          <select
            v-model="statusFilter"
            class="px-3 py-2 bg-slate-50 border border-slate-200 text-slate-700 text-xs rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none"
          >
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="pending">Pending</option>
          </select>
        </div>
      </div>

      <!-- Members Directory Table -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                <th class="py-3.5 px-6">Member Name & Email</th>
                <th class="py-3.5 px-4">Member ID #</th>
                <th v-if="enableMemberRanks" class="py-3.5 px-4">Rank</th>
                <th class="py-3.5 px-4">Club Role</th>
                <th class="py-3.5 px-4">Status</th>
                <th class="py-3.5 px-4">Joined Date</th>
                <th class="py-3.5 px-6 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
              <tr
                v-for="m in filteredMembers"
                :key="m.id"
                class="hover:bg-slate-50/50 transition-colors"
              >
                <!-- Name & Email -->
                <td class="py-4 px-6">
                  <Link :href="route('admin.users.show', { clubSlug: club.slug, userId: m.id })" class="flex items-center gap-3 group">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-indigo-500 to-sky-400 text-white font-bold flex items-center justify-center text-xs shadow-sm uppercase group-hover:scale-105 transition-transform">
                      {{ m.name.substring(0, 2) }}
                    </div>
                    <div>
                      <div class="font-bold text-slate-900 text-sm group-hover:text-indigo-600 transition-colors">{{ m.name }}</div>
                      <div class="text-[11px] text-slate-400 font-normal">{{ m.email }}</div>
                    </div>
                  </Link>
                </td>

                <!-- Member Number -->
                <td class="py-4 px-4">
                  <span class="font-mono text-xs font-semibold px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg border border-slate-200">
                    {{ m.member_number }}
                  </span>
                </td>

                <!-- Rank Selector -->
                <td v-if="enableMemberRanks" class="py-4 px-4">
                  <select
                    :value="m.rank"
                    @change="updateRank(m.id, $event.target.value)"
                    class="px-2.5 py-1 text-xs font-bold rounded-lg border border-indigo-200 bg-indigo-50/60 text-indigo-700 outline-none cursor-pointer"
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
                    <option value="member">Member</option>
                    <option value="coach">Coach</option>
                    <option value="treasurer">Treasurer</option>
                    <option value="admin">Admin</option>
                  </select>
                </td>

                <!-- Status -->
                <td class="py-4 px-4">
                  <span
                    :class="[
                      'px-2.5 py-0.5 rounded-full text-[11px] font-bold border uppercase tracking-wider',
                      m.status === 'active'
                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                        : 'bg-amber-50 text-amber-700 border-amber-200'
                    ]"
                  >
                    {{ m.status }}
                  </span>
                </td>

                <!-- Joined Date -->
                <td class="py-4 px-4 text-slate-500">
                  {{ m.joined_at }}
                </td>

                <!-- Actions -->
                <td class="py-4 px-6 text-right space-x-2">
                  <button
                    v-if="!m.invitation_accepted_at"
                    @click="sendInviteEmail(m.id)"
                    class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 text-xs font-bold rounded-lg transition-all inline-flex items-center gap-1 cursor-pointer"
                    :title="m.invitation_token ? `Invited on ${m.invited_at}` : 'Send activation email'"
                  >
                    <span>✉️</span>
                    <span>{{ m.invitation_token ? 'Resend Invite' : 'Send Invite' }}</span>
                  </button>
                  <span
                    v-else
                    class="px-2 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-extrabold rounded-lg inline-block"
                  >
                    ✓ Account Active
                  </span>

                  <Link
                    :href="route('admin.users.show', { clubSlug: club.slug, userId: m.id })"
                    class="px-2.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-bold rounded-lg transition-all inline-block"
                  >
                    View Profile
                  </Link>
                  <button
                    @click="removeMember(m.id, m.name)"
                    class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 text-xs font-bold rounded-lg transition-all"
                  >
                    Remove
                  </button>
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

    </div>

    <!-- Modal 1: Add Member Modal -->
    <div
      v-if="showAddModal"
      class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-5 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="text-base font-bold text-slate-900">Add New Club Member</h3>
          <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">
            &times;
          </button>
        </div>

        <form @submit.prevent="submitAddMember" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Full Name</label>
            <input
              v-model="addForm.name"
              type="text"
              required
              placeholder="e.g. Samuel Green"
              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
            <input
              v-model="addForm.email"
              type="email"
              required
              placeholder="samuel@example.com"
              class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Club Role</label>
              <select
                v-model="addForm.role"
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="member">Member</option>
                <option value="coach">Coach</option>
                <option value="treasurer">Treasurer</option>
                <option value="admin">Admin</option>
              </select>
            </div>

            <div v-if="enableMemberRanks">
              <label class="block text-xs font-bold text-slate-700 mb-1">Member Rank</label>
              <select
                v-model="addForm.rank"
                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="">No Rank Assigned</option>
                <option v-for="r in memberRanks" :key="r" :value="r">🏅 {{ r }}</option>
              </select>
            </div>

            <div :class="enableMemberRanks ? 'col-span-2' : 'col-span-1'">
              <label class="block text-xs font-bold text-slate-700 mb-1">Member Number</label>
              <input
                v-model="addForm.member_number"
                type="text"
                placeholder="OUBC-204"
                class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
              />
            </div>
          </div>

          <label class="flex items-center gap-2.5 cursor-pointer pt-1">
            <input
              type="checkbox"
              v-model="addForm.send_invite"
              class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 cursor-pointer"
            />
            <span class="text-xs font-bold text-slate-700">Send email invitation to activate account</span>
          </label>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showAddModal = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="addForm.processing"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20"
            >
              Add Member
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal 2: Import CSV Modal -->
    <div
      v-if="showImportModal"
      class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-5 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="text-base font-bold text-slate-900">Bulk Import Roster via CSV</h3>
          <button @click="showImportModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">
            &times;
          </button>
        </div>

        <p class="text-xs text-slate-500">
          Upload a CSV file containing columns: <code class="bg-slate-100 text-slate-700 px-1 py-0.5 rounded">Name, Email, Role, MemberNumber</code>.
        </p>

        <form @submit.prevent="submitImportCsv" class="space-y-4">
          <div>
            <input
              type="file"
              accept=".csv,.txt"
              @change="importForm.csv_file = $event.target.files[0]"
              required
              class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
            />
          </div>

          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
            <button
              type="button"
              @click="showImportModal = false"
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="importForm.processing"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20"
            >
              Upload & Import
            </button>
          </div>
        </form>
      </div>
    </div>

  </AdminLayout>
</template>
