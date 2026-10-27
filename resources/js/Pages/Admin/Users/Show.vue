<script setup>
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Select from '@/Components/Ui/Select.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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
  <AdminLayout :title="`${member.name} - Member profile`" :club="club" active-tab="members">

    <div class="space-y-6">

      <!-- Back link -->
      <div class="flex items-center justify-between">
        <Button variant="ghost" size="sm" :href="route('admin.users.index', { clubSlug: club.slug })">
          <template #icon><UiIcon name="arrow-left" /></template>
          Back to member roster
        </Button>
        <span class="font-mono text-xs text-slate-500 dark:text-slate-400">Member number: {{ member.member_number }}</span>
      </div>

      <!-- Member header -->
      <Card padding="lg" class="flex flex-col justify-between gap-6 md:flex-row md:items-center">
        <div class="flex items-center gap-4 overflow-hidden sm:gap-6">
          <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-2xl bg-gradient-to-tr from-blue-600 to-blue-700 text-xl font-semibold uppercase text-white shadow-sm sm:h-20 sm:w-20 sm:text-2xl">
            {{ member.name.substring(0, 2) }}
          </div>
          <div class="space-y-1 overflow-hidden">
            <div class="flex flex-wrap items-center gap-3">
              <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-2xl">{{ member.name }}</h1>
              <span
                :class="[
                  'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium capitalize whitespace-nowrap',
                  statusBadgeClass(member.status)
                ]"
              >
                {{ member.status }}
              </span>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ member.email }}</p>
            <div class="flex items-center gap-2 pt-1 text-xs text-slate-500 dark:text-slate-400">
              <span>Joined {{ member.joined_at }}</span>
              <span aria-hidden="true">·</span>
              <span class="font-mono">{{ member.member_number }}</span>
            </div>
          </div>
        </div>

        <!-- Role and invitation -->
        <div class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800 md:border-t-0 md:pt-0">
          <Select
            v-if="enableMemberRanks"
            label="Member rank"
            class="min-w-40"
            :model-value="member.rank || ''"
            :options="[{ value: '', label: 'No rank' }, ...memberRanks.map((r) => ({ value: r, label: r }))]"
            @update:model-value="updateRank"
          />

          <Select
            label="Club role"
            class="min-w-40"
            :model-value="member.role"
            :options="[
              { value: 'member', label: 'Media manager' },
              { value: 'coach', label: 'Secretary' },
              { value: 'treasurer', label: 'Treasurer' },
              { value: 'admin', label: 'Admin' },
            ]"
            @update:model-value="updateRole"
          />

          <div class="space-y-1.5">
            <p class="text-xs font-medium text-slate-700 dark:text-slate-300">Account invitation</p>
            <div class="flex items-center gap-1.5">
              <Badge
                v-if="member.invitation_accepted_at || member.status === 'active'"
                variant="success"
              >
                <UiIcon name="check" />
                Account active
              </Badge>
              <template v-else>
                <Button variant="secondary" @click="sendInvite">
                  <template #icon><UiIcon name="mail" /></template>
                  {{ member.invited_at ? 'Resend invite' : 'Send email invite' }}
                </Button>
                <Button
                  v-if="member.invited_at || member.invitation_token"
                  variant="ghost"
                  @click="revokeInvite"
                  title="Revoke active invitation token"
                >
                  <template #icon><UiIcon name="x" /></template>
                  Revoke
                </Button>
              </template>
            </div>
          </div>
        </div>
      </Card>

      <!-- Stats -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <StatCard
          label="Attendance rate"
          :value="`${stats.attendance_rate}%`"
          :caption="`${stats.attended_count} of ${stats.total_rsvps} sessions attended`"
        />
        <StatCard label="Total RSVPs" :value="stats.total_rsvps" caption="Events and training outings" />
        <StatCard label="Paid dues and invoices" :value="stats.total_invoices_paid" caption="Paid membership receipts" />
      </div>

      <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">

        <!-- Left column: profile and clubs -->
        <div class="space-y-6">

          <!-- Profile details -->
          <Card>
            <template #header>
              <h3 class="text-base font-semibold text-slate-900 dark:text-white">Member profile</h3>
            </template>

            <div class="space-y-3 text-sm">
              <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Full name</div>
                <div class="mt-0.5 font-medium text-slate-900 dark:text-white">{{ member.name }}</div>
              </div>

              <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Email address</div>
                <div class="mt-0.5 text-slate-700 dark:text-slate-200">{{ member.email }}</div>
              </div>

              <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Member number</div>
                <div class="mt-0.5 font-mono font-medium text-slate-900 dark:text-white">{{ member.member_number }}</div>
              </div>

              <div v-if="enableMemberRanks">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Rank</div>
                <div class="mt-1">
                  <Badge variant="info">{{ member.rank || 'No rank assigned' }}</Badge>
                </div>
              </div>

              <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Account invitation</div>
                <div class="mt-1 flex items-center gap-2">
                  <Badge v-if="member.invitation_accepted_at" variant="success">
                    Accepted ({{ member.invitation_accepted_at }})
                  </Badge>
                  <Badge v-else-if="member.invited_at" variant="warning">
                    Invited on {{ member.invited_at }}
                  </Badge>
                  <Badge v-else>
                    Not invited yet
                  </Badge>
                </div>
              </div>

              <div>
                <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Security and 2FA</div>
                <div class="mt-1">
                  <Badge :variant="member.two_factor_enabled ? 'success' : 'neutral'">
                    {{ member.two_factor_enabled ? '2FA enabled' : '2FA disabled' }}
                  </Badge>
                </div>
              </div>
            </div>
          </Card>

          <!-- Affiliated orders and clubs -->
          <Card>
            <template #header>
              <div class="flex items-center justify-between gap-2">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Affiliated orders and clubs ({{ userClubs.length }})</h3>
                <Badge variant="info">Cross-order view</Badge>
              </div>
            </template>

            <div v-if="userClubs.length" class="space-y-2.5">
              <div v-for="c in userClubs" :key="c.id" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs transition-colors hover:border-blue-300 dark:border-slate-800 dark:bg-slate-800/50 dark:hover:border-blue-700/60">
                <div class="overflow-hidden">
                  <div class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ c.name }} <span v-if="c.lodge_number" class="font-mono font-normal text-slate-500 dark:text-slate-400">No. {{ c.lodge_number }}</span></div>
                  <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ c.club_type?.name || 'Craft Lodge' }}</div>
                </div>
                <span :class="['inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium capitalize whitespace-nowrap', roleBadgeClass(c.role)]">
                  {{ c.role }}
                </span>
              </div>
            </div>
            <div v-else class="py-4 text-center text-sm text-slate-500 dark:text-slate-400">
              No other clubs or side orders recorded.
            </div>
          </Card>

        </div>

        <!-- Right column: RSVPs and invoices -->
        <div class="space-y-6 lg:col-span-2">

          <!-- Event RSVPs and dining -->
          <Card>
            <template #header>
              <div class="flex items-center justify-between gap-2">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Event RSVPs and dining</h3>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ rsvps.length }} total events</span>
              </div>
            </template>

            <div v-if="rsvps.length" class="space-y-3">
              <div v-for="(rsvp, idx) in rsvps" :key="idx" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs dark:border-slate-800 dark:bg-slate-800/50">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                  <div>
                    <div class="text-sm font-medium text-slate-900 dark:text-white">{{ rsvp.event_title }}</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ rsvp.location }} · {{ rsvp.starts_at }}</div>
                  </div>

                  <div class="flex items-center gap-2">
                    <Badge v-if="rsvp.checked_in_at" variant="success">Checked in</Badge>
                    <Badge :variant="rsvp.attendance_status === 'attending' ? 'info' : 'neutral'" class="capitalize">
                      {{ rsvp.attendance_status }}
                    </Badge>
                  </div>
                </div>

                <!-- Dining choices -->
                <div v-if="rsvp.attending_dining || rsvp.dietary_requirements" class="grid grid-cols-1 gap-2 border-t border-slate-200 pt-2 dark:border-slate-800 sm:grid-cols-2">
                  <div v-if="rsvp.attending_dining && rsvp.menu_selections">
                    <span class="font-medium text-slate-700 dark:text-slate-200">Dinner choices:</span>
                    <div class="text-slate-600 dark:text-slate-300">
                      {{ [rsvp.menu_selections.starter, rsvp.menu_selections.main, rsvp.menu_selections.dessert].filter(Boolean).join(' • ') || 'Standard menu' }}
                    </div>
                  </div>
                  <div v-if="rsvp.dietary_requirements">
                    <span class="font-medium text-amber-800 dark:text-amber-200">Dietary requirements:</span>
                    <div class="text-slate-600 dark:text-slate-300">{{ rsvp.dietary_requirements }}</div>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">
              No event RSVPs recorded for this member.
            </div>
          </Card>

          <!-- Dues and receipts -->
          <Card>
            <template #header>
              <div class="flex items-center justify-between gap-2">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Membership dues and receipts</h3>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ invoices.length }} invoices</span>
              </div>
            </template>

            <div v-if="invoices.length" class="space-y-3">
              <div v-for="inv in invoices" :key="inv.id" class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs dark:border-slate-800 dark:bg-slate-800/50">
                <div>
                  <div class="text-sm font-medium text-slate-900 dark:text-white">{{ inv.title }}</div>
                  <div class="font-mono text-xs text-slate-500 dark:text-slate-400">#{{ inv.invoice_number }} • {{ inv.created_at }}</div>
                </div>

                <div class="flex items-center gap-3">
                  <div class="space-y-0.5 text-right">
                    <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ $cs }}{{ inv.amount }}</div>
                    <Badge variant="success" class="capitalize">{{ inv.status }}</Badge>
                  </div>

                  <a
                    :href="route('invoices.download', { slug: club.slug, id: inv.id })"
                    target="_blank"
                    class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                  >
                    <UiIcon name="download" />
                    <span>PDF</span>
                  </a>
                </div>
              </div>
            </div>

            <div v-else class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">
              No invoice receipts recorded for this member.
            </div>
          </Card>

        </div>

      </div>

      <!-- Membership status -->
      <Card padding="lg">
        <template #header>
          <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
              <h3 class="text-base font-semibold text-slate-900 dark:text-white">Membership status</h3>
              <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Deactivate access, move to past members, or delete from the directory while keeping historical records.</p>
            </div>
            <span :class="['inline-flex items-center self-start rounded-full border px-2.5 py-0.5 text-[11px] font-medium whitespace-nowrap sm:self-auto', statusBadgeClass(member.status)]">
              Current status: {{ member.status }}
            </span>
          </div>
        </template>

        <div class="flex flex-wrap items-center gap-3">
          <Button
            v-if="member.status === 'pending'"
            @click="approveMember"
          >
            <template #icon><UiIcon name="check" /></template>
            Approve member registration
          </Button>

          <Button
            v-if="member.status === 'inactive'"
            variant="secondary"
            @click="updateStatus('active')"
          >
            <template #icon><UiIcon name="restore" /></template>
            Reactivate member access
          </Button>

          <Button
            v-if="member.status === 'active'"
            variant="secondary"
            @click="updateStatus('inactive')"
          >
            <template #icon><UiIcon name="pause" /></template>
            Deactivate member access
          </Button>

          <Button
            v-if="member.status === 'past'"
            variant="secondary"
            @click="updateStatus('active')"
          >
            <template #icon><UiIcon name="restore" /></template>
            Restore member to active roster
          </Button>

          <Button
            v-if="member.status !== 'past'"
            variant="secondary"
            @click="archiveMember"
            title="Move member to the past members list"
          >
            <template #icon><UiIcon name="archive" /></template>
            Move to past members
          </Button>

          <Button
            variant="danger"
            @click="forceDeleteMember"
            title="Delete member from directory (preserves historical database records)"
          >
            <template #icon><UiIcon name="trash" /></template>
            Delete member
          </Button>
        </div>
      </Card>

    </div>

  </AdminLayout>
</template>
