<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

import { computed } from 'vue';

const props = defineProps({
  club: Object,
  metrics: Object,
  upcomingMeetings: Array,
  gettingStarted: { type: Object, default: null },
});

// The first three things a newly set-up lodge needs, each ticked off once done.
const firstSteps = computed(() => props.gettingStarted ? [
  { done: props.gettingStarted.members, label: 'Add your members and invite them to sign in', href: route('admin.club_acc.members.index', { clubSlug: props.club.slug }) },
  { done: props.gettingStarted.payments, label: 'Choose how people pay for dining and events', href: route('admin.payment_options.index', { clubSlug: props.club.slug }) },
  { done: props.gettingStarted.meetings, label: 'Add your first meeting and send the summons', href: route('admin.meetings.index', { clubSlug: props.club.slug }) },
] : []);
const stillToDo = computed(() => firstSteps.value.some((step) => !step.done));
</script>

<template>
  <AdminLayout title="Dashboard" :club="club" active-tab="dashboard">

    <section v-if="stillToDo" aria-labelledby="first-steps-heading" class="mb-4 rounded-2xl border border-blue-200 bg-blue-50 p-4 sm:mb-6 sm:p-6 dark:border-blue-900/60 dark:bg-blue-950/30">
      <h2 id="first-steps-heading" class="text-base font-semibold text-slate-900 dark:text-white">Getting your lodge started</h2>
      <ol class="mt-3 space-y-2">
        <li v-for="step in firstSteps" :key="step.label" class="flex items-start gap-2 text-sm">
          <UiIcon v-if="step.done" name="check" class="mt-0.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
          <span v-else aria-hidden="true" class="mt-1 h-3.5 w-3.5 shrink-0 rounded-full border-2 border-slate-400" />
          <span v-if="step.done" class="text-slate-500 line-through dark:text-slate-400">{{ step.label }}</span>
          <Link v-else :href="step.href" class="font-medium text-blue-700 hover:underline dark:text-blue-300">{{ step.label }}</Link>
        </li>
      </ol>
    </section>

    <!-- The headline numbers, two to a row even on a phone: members, then money. -->
    <div class="grid grid-cols-2 gap-3 sm:gap-6">
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-col gap-2">
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active members</div>
        <div class="text-2xl sm:text-4xl font-black text-slate-900 dark:text-white font-sans tracking-tight">{{ metrics.total_members || 0 }}</div>
        <div class="text-xs text-slate-500 dark:text-slate-400">On the roster or with an account</div>
      </div>

      <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-col gap-2">
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pending</div>
        <div class="text-2xl sm:text-4xl font-black text-slate-900 dark:text-white font-sans tracking-tight">{{ metrics.pending_users_count || 0 }}</div>
        <div class="text-xs text-slate-500 dark:text-slate-400">Asking to join or visit</div>
      </div>

      <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-col gap-2">
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Money received</div>
        <div class="text-2xl sm:text-4xl font-black text-slate-900 dark:text-white font-sans tracking-tight">{{ $cs }}{{ metrics.monthly_dues_est }}</div>
        <div class="text-xs text-slate-500 dark:text-slate-400">Invoices, dues and events, all time</div>
      </div>

      <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-col gap-2">
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Open invoices and bills</div>
        <div class="text-2xl sm:text-4xl font-black text-slate-900 dark:text-white font-sans tracking-tight">{{ metrics.open_invoices_count || 0 }}</div>
        <div class="text-xs text-slate-500 dark:text-slate-400">Not yet paid</div>
      </div>
    </div>

    <!-- Bottom Section Dual Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      
      <!-- Left Panel: Events -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Events</h2>
          <Link :href="route('admin.events.index', { clubSlug: club.slug })" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200">View all events →</Link>
        </div>
        <dl class="grid grid-cols-2 gap-4">
          <div class="space-y-1">
            <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">Events</dt>
            <dd class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ metrics.events_count }}</dd>
          </div>
          <div class="space-y-1">
            <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">Bookings attending</dt>
            <dd class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ metrics.attending_rsvps }} <span class="text-sm font-semibold text-slate-500 dark:text-slate-400">of {{ metrics.total_rsvps }}</span></dd>
          </div>
          <div class="space-y-1">
            <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">Taken for events</dt>
            <dd class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ $cs }}{{ metrics.total_event_revenue }}</dd>
          </div>
          <div class="space-y-1">
            <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">Attending rate</dt>
            <dd class="text-2xl font-extrabold text-slate-900 dark:text-white">{{ metrics.attendance_rate }}%</dd>
          </div>
        </dl>
        <p v-if="!metrics.events_count" class="text-sm text-slate-500 dark:text-slate-400">No events yet. Add one from Events to take bookings and payments.</p>
      </div>

      <!-- Right Panel: Meetings Summary -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-4 sm:p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Meetings</h2>
          <Link :href="route('admin.meetings.index', { clubSlug: club.slug })" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 flex items-center gap-1">
            View all meetings →
          </Link>
        </div>

        <div v-if="upcomingMeetings && upcomingMeetings.length" class="space-y-3">
          <div v-for="m in upcomingMeetings" :key="m.id" class="p-4 bg-slate-50/70 dark:bg-slate-800/70 hover:bg-slate-100/90 dark:hover:bg-slate-800/90 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 transition-all space-y-2.5">
            <div class="flex items-center justify-between gap-2">
              <Link :href="route('admin.meetings.show', { clubSlug: club.slug, id: m.id })" title="Open Secretary Dashboard" aria-label="Open Secretary Dashboard" class="font-bold text-slate-900 dark:text-white text-sm hover:text-blue-600 dark:hover:text-blue-400 transition-colors flex items-center gap-2 group">
                <span class="w-7 h-7 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 font-bold flex items-center justify-center text-xs border border-blue-200/60 dark:border-blue-800/60 group-hover:bg-blue-600 group-hover:text-white transition-all">
                  <UiIcon name="document" class="inline-block align-text-bottom" />
                </span>
                <span class="hover:underline">{{ m.title }}</span>
              </Link>
              <span :class="['px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border', m.status === 'published' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60' : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60']">
                {{ m.status }}
              </span>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400 font-medium pt-1 border-t border-slate-200/50 dark:border-slate-800/50">
              <div class="flex items-center gap-3">
                <span class="flex items-center gap-1"><UiIcon name="calendar" /> {{ m.meeting_date }}<template v-if="m.starts_at"> at {{ m.starts_at }}</template></span>
                <span v-if="m.venue" class="hidden sm:flex items-center gap-1 text-slate-500 dark:text-slate-400"><UiIcon name="building" /> {{ m.venue }}</span>
              </div>
              <div class="flex items-center gap-2 text-[11px] font-bold">
                <span class="text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded-lg border border-emerald-200 dark:border-emerald-800/60">{{ m.dining_count }} dining</span>
                <span class="text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/40 px-2 py-0.5 rounded-lg border border-rose-200 dark:border-rose-800/60">{{ m.apologies_count }} apologies</span>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="p-4 sm:p-8 text-center text-xs text-slate-500 dark:text-slate-400 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl space-y-3">
          <div class="flex justify-center text-slate-500 dark:text-slate-400"><UiIcon name="document" :size="20" /></div>
          <div class="font-semibold text-slate-700 dark:text-slate-200 text-sm">No upcoming meetings scheduled</div>
          <p class="text-slate-500 dark:text-slate-400 max-w-xs mx-auto">Generate season schedules or create individual summonses for your club.</p>
          <Link :href="route('admin.meetings.create', { clubSlug: club.slug })" class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-md transition-all">
            <UiIcon name="plus" class="inline-block align-text-bottom" /> Add meeting
          </Link>
        </div>
      </div>

    </div>

  </AdminLayout>
</template>
