<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: {
    type: Object,
    required: true,
  },
  member: {
    type: Object,
    required: true,
  },
  userClubs: {
    type: Array,
    default: () => [],
  },
  rsvps: {
    type: Array,
    default: () => [],
  },
  invoices: {
    type: Array,
    default: () => [],
  },
  stats: {
    type: Object,
    default: () => ({}),
  },
  enableMemberRanks: {
    type: Boolean,
    default: true,
  },
  memberRanks: {
    type: Array,
    default: () => ['Novice', 'Intermediate', 'Senior', 'Captain', 'Coxswain', 'Veteran'],
  },
});

const activeTab = ref('overview');

const updateRole = (newRole) => {
  router.post(
    route('admin.users.role.update', { clubSlug: props.club.slug, userId: props.member.id }),
    { role: newRole },
    { preserveScroll: true }
  );
};

const updateRank = (newRank) => {
  router.post(
    route('admin.users.rank.update', { clubSlug: props.club.slug, userId: props.member.id }),
    { rank: newRank },
    { preserveScroll: true }
  );
};

const updateStatus = (newStatus) => {
  router.post(
    route('admin.users.status.update', { clubSlug: props.club.slug, userId: props.member.id }),
    { status: newStatus },
    { preserveScroll: true }
  );
};

const archiveMember = () => {
  if (confirm(`Move ${props.member.name} to Past Members? Historical records (RSVPs, dues) will be retained.`)) {
    router.delete(
      route('admin.users.destroy', { clubSlug: props.club.slug, userId: props.member.id }),
      { preserveScroll: true }
    );
  }
};

const forceDeleteMember = () => {
  if (confirm(`Are you sure you want to delete ${props.member.name}? This will hide them from the member directory, but all historical records (RSVPs, dues, receipts) will be safely preserved.`)) {
    router.delete(
      route('admin.users.force_delete', { clubSlug: props.club.slug, userId: props.member.id })
    );
  }
};

const approveMember = () => {
  router.post(
    route('clubs.members.approve', { slug: props.club.slug, userId: props.member.id }),
    {},
    { preserveScroll: true }
  );
};

const sendInvite = () => {
  router.post(
    route('admin.users.invite', { clubSlug: props.club.slug, userId: props.member.id }),
    {},
    { preserveScroll: true }
  );
};

const revokeInvite = () => {
  if (confirm(`Are you sure you want to revoke the account invitation for ${props.member.name}?`)) {
    router.post(
      route('admin.users.revoke_invite', { clubSlug: props.club.slug, userId: props.member.id }),
      {},
      { preserveScroll: true }
    );
  }
};

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

const statusBadgeClass = (status) => {
  switch (status) {
    case 'active':
      return 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60';
    case 'inactive':
      return 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60';
    case 'past':
      return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-800';
    case 'pending':
      return 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/60';
    default:
      return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-800';
  }
};
</script>

<template>
  <AdminLayout :title="`${member.name} - Member Profile`" :club="club" active-tab="members">
    
    <div class="space-y-6">
      
      <!-- Back Link & Title Navigation -->
      <div class="flex items-center justify-between">
        <Link 
          :href="route('admin.users.index', { clubSlug: club.slug })" 
          class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100 transition-colors"
        >
          &larr; Back to Member Roster
        </Link>
        <span class="text-xs text-slate-400 font-mono">Member ID: {{ member.member_number }}</span>
      </div>

      <!-- Member Header Card -->
      <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-center gap-4 sm:gap-6 overflow-hidden">
          <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-tr from-blue-600 to-blue-700 text-white font-black text-xl sm:text-2xl flex items-center justify-center shadow-lg shadow-blue-500/20 uppercase flex-shrink-0">
            {{ member.name.substring(0, 2) }}
          </div>
          <div class="space-y-1 overflow-hidden">
            <div class="flex items-center gap-3 flex-wrap">
              <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ member.name }}</h1>
              <span 
                :class="[
                  'px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border uppercase tracking-wider',
                  statusBadgeClass(member.status)
                ]"
              >
                {{ member.status }}
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ member.email }}</p>
            <div class="flex items-center gap-2 pt-1 text-[11px] text-slate-400">
              <span>Joined {{ member.joined_at }}</span>
              <span>•</span>
              <span class="font-mono">{{ member.member_number }}</span>
            </div>
          </div>
        </div>

        <!-- Role & Management Actions -->
        <div class="flex flex-wrap items-center gap-3 border-t md:border-t-0 border-slate-100 dark:border-slate-800 pt-4 md:pt-0">
          <div v-if="enableMemberRanks" class="space-y-1">
            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Member Rank</label>
            <select
              :value="member.rank"
              @change="updateRank($event.target.value)"
              class="px-3 py-2 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 outline-none cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition-all shadow-sm"
            >
              <option value="">No Rank</option>
              <option v-for="r in memberRanks" :key="r" :value="r">🏅 {{ r }}</option>
            </select>
          </div>

          <div class="space-y-1">
            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Club Role</label>
            <select
              :value="member.role"
              @change="updateRole($event.target.value)"
              :class="[
                'px-3 py-2 text-xs font-bold rounded-xl border outline-none cursor-pointer shadow-sm transition-all',
                roleBadgeClass(member.role)
              ]"
            >
              <option value="member">Media Manager</option>
              <option value="coach">Secretary</option>
              <option value="treasurer">Treasurer</option>
              <option value="admin">Admin</option>
            </select>
          </div>

          <div class="space-y-1">
            <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Account Invitation</label>
            <div class="flex items-center gap-1.5">
              <button
                v-if="member.invitation_accepted_at || member.status === 'active'"
                type="button"
                disabled
                class="px-3 py-2 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 font-bold text-xs rounded-xl flex items-center gap-1.5 opacity-90 cursor-default"
              >
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Account Active</span>
              </button>
              <template v-else>
                <button
                  @click="sendInvite"
                  class="px-3.5 py-2 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 font-bold text-xs rounded-xl transition-all flex items-center gap-1.5 shadow-sm cursor-pointer"
                >
                  <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                  </svg>
                  <span>{{ member.invited_at ? 'Resend Invite' : 'Send Email Invite' }}</span>
                </button>
                <button
                  v-if="member.invited_at || member.invitation_token"
                  @click="revokeInvite"
                  class="px-3 py-2 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 font-bold text-xs rounded-xl transition-all flex items-center gap-1 cursor-pointer"
                  title="Revoke active invitation token"
                >
                  <svg class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                  <span>Revoke</span>
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- KPI Stats Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-1">
          <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Attendance Rate</div>
          <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ stats.attendance_rate }}%</div>
          <div class="text-xs font-medium text-slate-400">{{ stats.attended_count }} of {{ stats.total_rsvps }} sessions attended</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-1">
          <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total RSVPs</div>
          <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">{{ stats.total_rsvps }}</div>
          <div class="text-xs font-medium text-slate-400">Events & training outings</div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-1">
          <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Paid Dues / Invoices</div>
          <div class="text-3xl font-black text-emerald-700 dark:text-emerald-300 tracking-tight">{{ stats.total_invoices_paid }}</div>
          <div class="text-xs font-medium text-slate-400">Paid membership receipts</div>
        </div>
      </div>

      <!-- Content Dual Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left Column: Member Info & Clubs List (1 col) -->
        <div class="space-y-6">
          
          <!-- Personal Profile Details Card -->
          <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
            <h3 class="text-base font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">👤 Member Profile Info</h3>
            
            <div class="space-y-3 text-xs">
              <div>
                <div class="text-[10px] font-extrabold uppercase text-slate-400">Full Name</div>
                <div class="font-bold text-slate-900 dark:text-white text-sm mt-0.5">{{ member.name }}</div>
              </div>

              <div>
                <div class="text-[10px] font-extrabold uppercase text-slate-400">Email Address</div>
                <div class="font-semibold text-slate-700 dark:text-slate-200 mt-0.5">{{ member.email }}</div>
              </div>

              <div>
                <div class="text-[10px] font-extrabold uppercase text-slate-400">Member Number</div>
                <div class="font-mono text-slate-900 dark:text-white font-bold mt-0.5">{{ member.member_number }}</div>
              </div>

              <div v-if="enableMemberRanks">
                <div class="text-[10px] font-extrabold uppercase text-slate-400">Configured Rank</div>
                <div class="mt-0.5 flex items-center gap-1.5">
                  <span class="px-2.5 py-0.5 rounded-lg bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 text-xs font-extrabold">
                    🏅 {{ member.rank || 'No Rank Assigned' }}
                  </span>
                </div>
              </div>

              <div>
                <div class="text-[10px] font-extrabold uppercase text-slate-400">Account Invitation Status</div>
                <div class="mt-1 flex items-center gap-2">
                  <span 
                    v-if="member.invitation_accepted_at" 
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 uppercase"
                  >
                    ✓ Accepted ({{ member.invitation_accepted_at }})
                  </span>
                  <span 
                    v-else-if="member.invited_at" 
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 uppercase"
                  >
                    ✉ Invited on {{ member.invited_at }}
                  </span>
                  <span 
                    v-else 
                    class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 uppercase"
                  >
                    Not Invited Yet
                  </span>
                </div>
              </div>

              <div>
                <div class="text-[10px] font-extrabold uppercase text-slate-400">Security & 2FA Status</div>
                <div class="mt-1">
                  <span 
                    :class="[
                      'px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider border',
                      member.two_factor_enabled ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-800'
                    ]"
                  >
                    {{ member.two_factor_enabled ? '🔒 2FA Enabled' : '🔓 2FA Disabled' }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Associated Clubs & Masonic Orders Card -->
          <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <h3 class="text-base font-bold text-slate-900 dark:text-white">🏛️ Affiliated Orders & Clubs ({{ userClubs.length }})</h3>
              <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 font-extrabold border border-blue-200 dark:border-blue-800/60">Cross-Order View</span>
            </div>
            
            <div v-if="userClubs.length" class="space-y-2.5">
              <div v-for="c in userClubs" :key="c.id" class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/90 dark:border-slate-800/90 rounded-xl flex items-center justify-between gap-3 text-xs hover:border-blue-300 dark:hover:border-blue-700/60 transition-all">
                <div class="overflow-hidden">
                  <div class="font-bold text-slate-900 dark:text-white truncate">{{ c.name }} <span v-if="c.lodge_number" class="text-slate-500 dark:text-slate-400 font-mono font-normal">No. {{ c.lodge_number }}</span></div>
                  <div class="text-[10px] text-blue-600 dark:text-blue-400 font-extrabold capitalize flex items-center gap-1 mt-0.5">
                    <span>👑 {{ c.club_type?.name || 'Craft Lodge' }}</span>
                  </div>
                </div>
                <span :class="['px-2 py-0.5 rounded text-[9px] font-extrabold uppercase border', roleBadgeClass(c.role)]">
                  {{ c.role }}
                </span>
              </div>
            </div>
            <div v-else class="text-xs text-slate-400 text-center py-4 italic">
              No additional affiliated clubs or side orders recorded.
            </div>
          </div>

        </div>

        <!-- Right Column: RSVPs & Invoices Log (2 cols) -->
        <div class="lg:col-span-2 space-y-6">
          
          <!-- Event RSVPs & Dining Selections -->
          <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <h3 class="text-base font-bold text-slate-900 dark:text-white">📅 Event RSVPs & Dining Selections</h3>
              <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ rsvps.length }} total events</span>
            </div>

            <div v-if="rsvps.length" class="space-y-3">
              <div v-for="(rsvp, idx) in rsvps" :key="idx" class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/90 dark:border-slate-800/90 rounded-xl space-y-2 text-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <div>
                    <div class="font-bold text-slate-900 dark:text-white text-sm">{{ rsvp.event_title }}</div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400">📍 {{ rsvp.location }} • 🕒 {{ rsvp.starts_at }}</div>
                  </div>

                  <div class="flex items-center gap-2">
                    <span v-if="rsvp.checked_in_at" class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                      ✓ CHECKED IN
                    </span>
                    <span 
                      :class="[
                        'px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border',
                        rsvp.attendance_status === 'attending' ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-800'
                      ]"
                    >
                      {{ rsvp.attendance_status }}
                    </span>
                  </div>
                </div>

                <!-- Dining Choice Breakdown -->
                <div v-if="rsvp.attending_dining || rsvp.dietary_requirements" class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                  <div v-if="rsvp.attending_dining && rsvp.menu_selections">
                    <span class="font-bold text-slate-700 dark:text-slate-200">🍽️ Dinner Selections:</span>
                    <div class="text-slate-600 dark:text-slate-300">
                      {{ [rsvp.menu_selections.starter, rsvp.menu_selections.main, rsvp.menu_selections.dessert].filter(Boolean).join(' • ') || 'Standard Menu' }}
                    </div>
                  </div>
                  <div v-if="rsvp.dietary_requirements">
                    <span class="font-bold text-amber-800 dark:text-amber-200">⚠️ Dietary Requirements:</span>
                    <div class="text-slate-600 dark:text-slate-300">{{ rsvp.dietary_requirements }}</div>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="text-xs text-slate-400 text-center py-6">
              No event RSVPs recorded for this member.
            </div>
          </div>

          <!-- Invoices & Dues Receipts -->
          <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <h3 class="text-base font-bold text-slate-900 dark:text-white">💳 Membership Dues & Receipts</h3>
              <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ invoices.length }} invoices</span>
            </div>

            <div v-if="invoices.length" class="space-y-3">
              <div v-for="inv in invoices" :key="inv.id" class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/90 dark:border-slate-800/90 rounded-xl flex items-center justify-between gap-4 text-xs">
                <div>
                  <div class="font-bold text-slate-900 dark:text-white">{{ inv.title }}</div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">#{{ inv.invoice_number }} • {{ inv.created_at }}</div>
                </div>

                <div class="flex items-center gap-3">
                  <div class="text-right">
                    <div class="font-bold text-slate-900 dark:text-white text-sm">{{ $cs }}{{ inv.amount }}</div>
                    <span class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                      {{ inv.status }}
                    </span>
                  </div>

                  <a 
                    :href="route('invoices.download', { slug: club.slug, id: inv.id })" 
                    target="_blank"
                    class="px-3 py-1.5 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800 text-xs font-bold rounded-lg transition-all shadow-sm flex items-center gap-1"
                  >
                    <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>PDF</span>
                  </a>
                </div>
              </div>
            </div>

            <div v-else class="text-xs text-slate-400 text-center py-6">
              No invoice receipts recorded for this member.
            </div>
          </div>

        </div>

      </div>

      <!-- Member Lifecycle Management Card (At Bottom) -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
          <div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">⚡ Member Lifecycle & Status Controls</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Manage roster status, deactivate active access, archive to past members, or delete from directory while preserving historical records.</p>
          </div>
          <span :class="['px-3 py-1 rounded-full text-xs font-extrabold border uppercase tracking-wider self-start sm:self-auto', statusBadgeClass(member.status)]">
            Current Status: {{ member.status }}
          </span>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-1">
          <button
            v-if="member.status === 'pending'"
            @click="approveMember"
            class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition-all cursor-pointer flex items-center gap-2"
          >
            ✓ Approve Member Registration
          </button>

          <button
            v-if="member.status === 'inactive'"
            @click="updateStatus('active')"
            class="px-4 py-2.5 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-2"
          >
            ✓ Reactivate Member Access
          </button>

          <button
            v-if="member.status === 'active'"
            @click="updateStatus('inactive')"
            class="px-4 py-2.5 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-2"
          >
            ⏸ Deactivate Member Access
          </button>

          <button
            v-if="member.status === 'past'"
            @click="updateStatus('active')"
            class="px-4 py-2.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-2"
          >
            ↺ Restore Member to Active Roster
          </button>

          <button
            v-if="member.status !== 'past'"
            @click="archiveMember"
            class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800 font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-2"
            title="Move member to Past Members list"
          >
            📜 Move to Past Members
          </button>

          <button
            @click="forceDeleteMember"
            class="px-4 py-2.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/60 font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-2"
            title="Delete member from directory (preserves historical database records)"
          >
            🗑️ Delete Member
          </button>
        </div>
      </div>

    </div>

  </AdminLayout>
</template>
