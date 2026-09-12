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
});

const activeTab = ref('overview');

const updateRole = (newRole) => {
  router.post(
    route('admin.users.role.update', { clubSlug: props.club.slug, userId: props.member.id }),
    { role: newRole },
    { preserveScroll: true }
  );
};

const removeMember = () => {
  if (confirm(`Are you sure you want to remove ${props.member.name} from the club roster?`)) {
    router.delete(route('admin.users.destroy', { clubSlug: props.club.slug, userId: props.member.id }));
  }
};

const approveMember = () => {
  router.post(
    route('clubs.members.approve', { slug: props.club.slug, userId: props.member.id }),
    {},
    { preserveScroll: true }
  );
};

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
</script>

<template>
  <AdminLayout :title="`${member.name} - Member Profile`" :club="club" active-tab="members">
    
    <div class="space-y-6">
      
      <!-- Back Link & Title Navigation -->
      <div class="flex items-center justify-between">
        <Link 
          :href="route('admin.users.index', { clubSlug: club.slug })" 
          class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors"
        >
          &larr; Back to Member Roster
        </Link>
        <span class="text-xs text-slate-400 font-mono">Member ID: {{ member.member_number }}</span>
      </div>

      <!-- Member Header Card -->
      <div class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-center gap-4 sm:gap-6 overflow-hidden">
          <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-tr from-indigo-500 to-sky-400 text-white font-black text-xl sm:text-2xl flex items-center justify-center shadow-lg shadow-indigo-500/20 uppercase flex-shrink-0">
            {{ member.name.substring(0, 2) }}
          </div>
          <div class="space-y-1 overflow-hidden">
            <div class="flex items-center gap-3 flex-wrap">
              <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">{{ member.name }}</h1>
              <span 
                :class="[
                  'px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border uppercase tracking-wider',
                  member.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'
                ]"
              >
                {{ member.status }}
              </span>
            </div>
            <p class="text-xs text-slate-500 font-medium">{{ member.email }}</p>
            <div class="flex items-center gap-2 pt-1 text-[11px] text-slate-400">
              <span>Joined {{ member.joined_at }}</span>
              <span>•</span>
              <span class="font-mono">{{ member.member_number }}</span>
            </div>
          </div>
        </div>

        <!-- Role & Management Actions -->
        <div class="flex flex-wrap items-center gap-3 border-t md:border-t-0 border-slate-100 pt-4 md:pt-0">
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
              <option value="member">Member</option>
              <option value="coach">Coach</option>
              <option value="treasurer">Treasurer</option>
              <option value="admin">Admin</option>
            </select>
          </div>

          <button
            v-if="member.status === 'pending'"
            @click="approveMember"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition-all self-end"
          >
            Approve Member
          </button>

          <button
            @click="removeMember"
            class="px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 font-bold text-xs rounded-xl transition-all self-end"
          >
            Remove
          </button>
        </div>
      </div>

      <!-- KPI Stats Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-1">
          <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Attendance Rate</div>
          <div class="text-3xl font-black text-slate-900 tracking-tight">{{ stats.attendance_rate }}%</div>
          <div class="text-xs font-medium text-slate-400">{{ stats.attended_count }} of {{ stats.total_rsvps }} sessions attended</div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-1">
          <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total RSVPs</div>
          <div class="text-3xl font-black text-slate-900 tracking-tight">{{ stats.total_rsvps }}</div>
          <div class="text-xs font-medium text-slate-400">Events & training outings</div>
        </div>

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-1">
          <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Paid Dues / Invoices</div>
          <div class="text-3xl font-black text-emerald-700 tracking-tight">{{ stats.total_invoices_paid }}</div>
          <div class="text-xs font-medium text-slate-400">Paid membership receipts</div>
        </div>
      </div>

      <!-- Content Dual Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left Column: Member Info & Clubs List (1 col) -->
        <div class="space-y-6">
          
          <!-- Personal Profile Details Card -->
          <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
            <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">👤 Member Profile Info</h3>
            
            <div class="space-y-3 text-xs">
              <div>
                <div class="text-[10px] font-extrabold uppercase text-slate-400">Full Name</div>
                <div class="font-bold text-slate-900 text-sm mt-0.5">{{ member.name }}</div>
              </div>

              <div>
                <div class="text-[10px] font-extrabold uppercase text-slate-400">Email Address</div>
                <div class="font-semibold text-slate-700 mt-0.5">{{ member.email }}</div>
              </div>

              <div>
                <div class="text-[10px] font-extrabold uppercase text-slate-400">Member Number</div>
                <div class="font-mono text-slate-900 font-bold mt-0.5">{{ member.member_number }}</div>
              </div>

              <div>
                <div class="text-[10px] font-extrabold uppercase text-slate-400">Security & 2FA Status</div>
                <div class="mt-1">
                  <span 
                    :class="[
                      'px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider border',
                      member.two_factor_enabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'
                    ]"
                  >
                    {{ member.two_factor_enabled ? '🔒 2FA Enabled' : '🔓 2FA Disabled' }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Associated Clubs Card -->
          <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
            <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">🏆 Joined Clubs ({{ userClubs.length }})</h3>
            
            <div v-if="userClubs.length" class="space-y-2.5">
              <div v-for="c in userClubs" :key="c.id" class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between gap-3 text-xs">
                <div class="overflow-hidden">
                  <div class="font-bold text-slate-900 truncate">{{ c.name }}</div>
                  <div class="text-[10px] text-slate-400 capitalize">{{ c.club_type?.name || 'General' }}</div>
                </div>
                <span :class="['px-2 py-0.5 rounded text-[9px] font-extrabold uppercase border', roleBadgeClass(c.role)]">
                  {{ c.role }}
                </span>
              </div>
            </div>
          </div>

        </div>

        <!-- Right Column: RSVPs & Invoices Log (2 cols) -->
        <div class="lg:col-span-2 space-y-6">
          
          <!-- Event RSVPs & Dining Selections -->
          <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 class="text-base font-bold text-slate-900">📅 Event RSVPs & Dining Selections</h3>
              <span class="text-xs text-slate-500 font-medium">{{ rsvps.length }} total events</span>
            </div>

            <div v-if="rsvps.length" class="space-y-3">
              <div v-for="(rsvp, idx) in rsvps" :key="idx" class="p-4 bg-slate-50 border border-slate-200/90 rounded-xl space-y-2 text-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <div>
                    <div class="font-bold text-slate-900 text-sm">{{ rsvp.event_title }}</div>
                    <div class="text-[11px] text-slate-500">📍 {{ rsvp.location }} • 🕒 {{ rsvp.starts_at }}</div>
                  </div>

                  <div class="flex items-center gap-2">
                    <span v-if="rsvp.checked_in_at" class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                      ✓ CHECKED IN
                    </span>
                    <span 
                      :class="[
                        'px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border',
                        rsvp.attendance_status === 'attending' ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-slate-100 text-slate-600 border-slate-200'
                      ]"
                    >
                      {{ rsvp.attendance_status }}
                    </span>
                  </div>
                </div>

                <!-- Dining Choice Breakdown -->
                <div v-if="rsvp.attending_dining || rsvp.dietary_requirements" class="pt-2 border-t border-slate-200/60 grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                  <div v-if="rsvp.attending_dining && rsvp.menu_selections">
                    <span class="font-bold text-slate-700">🍽️ Dinner Selections:</span>
                    <div class="text-slate-600">
                      {{ [rsvp.menu_selections.starter, rsvp.menu_selections.main, rsvp.menu_selections.dessert].filter(Boolean).join(' • ') || 'Standard Menu' }}
                    </div>
                  </div>
                  <div v-if="rsvp.dietary_requirements">
                    <span class="font-bold text-amber-800">⚠️ Dietary Requirements:</span>
                    <div class="text-slate-600">{{ rsvp.dietary_requirements }}</div>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="text-xs text-slate-400 text-center py-6">
              No event RSVPs recorded for this member.
            </div>
          </div>

          <!-- Invoices & Dues Receipts -->
          <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 class="text-base font-bold text-slate-900">💳 Membership Dues & Receipts</h3>
              <span class="text-xs text-slate-500 font-medium">{{ invoices.length }} invoices</span>
            </div>

            <div v-if="invoices.length" class="space-y-3">
              <div v-for="inv in invoices" :key="inv.id" class="p-4 bg-slate-50 border border-slate-200/90 rounded-xl flex items-center justify-between gap-4 text-xs">
                <div>
                  <div class="font-bold text-slate-900">{{ inv.title }}</div>
                  <div class="text-[11px] text-slate-500 font-mono">#{{ inv.invoice_number }} • {{ inv.created_at }}</div>
                </div>

                <div class="flex items-center gap-3">
                  <div class="text-right">
                    <div class="font-bold text-slate-900 text-sm">£{{ inv.amount }}</div>
                    <span class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                      {{ inv.status }}
                    </span>
                  </div>

                  <a 
                    :href="route('invoices.download', { slug: club.slug, id: inv.id })" 
                    target="_blank"
                    class="px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 text-xs font-bold rounded-lg transition-all shadow-sm flex items-center gap-1"
                  >
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

    </div>

  </AdminLayout>
</template>
