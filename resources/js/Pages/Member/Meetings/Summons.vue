<script setup>
import { formatLocal } from '@/Utils/localDateTime';
import { formatMeetingDate } from '@/Utils/lodgeDates';
import { ref } from 'vue';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import MeetingRsvpModal from '@/Components/MeetingRsvpModal.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import StatCard from '@/Components/Ui/StatCard.vue';

defineProps({
  club: { type: Object, required: true },
  meeting: { type: Object, required: true },
  members: { type: Array, default: () => [] },
  userRsvp: { type: Object, default: null },
  secretaryUser: { type: Object, default: null },
  worshipfulMaster: { type: Object, default: null },
  memberRole: { type: String, default: 'member' },
  isCutoffPassed: { type: Boolean, default: false },
  charityGrants: { type: Array, default: () => [] },
});

const showRsvpModal = ref(false);

const openRsvpModal = () => {
  showRsvpModal.value = true;
};

const grantStatusLabel = (status) => {
  const label = (status || 'proposed').replace('_', ' ');

  return label.charAt(0).toUpperCase() + label.slice(1);
};

const linkButtonClass = 'inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:ring-offset-slate-950';
</script>

<template>
  <MembersLayout :title="`Summons — ${meeting.title || formatMeetingDate(meeting.meeting_date)}`" :club="club" :member-role="memberRole" active-tab="dashboard">

    <div class="max-w-4xl mx-auto space-y-6">
      <!-- Top action bar -->
      <Card padding="sm" class="flex flex-wrap items-center justify-between gap-4">
        <Button variant="ghost" :href="route('member.dashboard', { slug: club.slug })">Back to dashboard</Button>

        <div class="flex flex-wrap items-center gap-3">
          <Button @click="openRsvpModal">{{ userRsvp ? 'Edit your response' : 'Respond to summons' }}</Button>
          <a :href="route('member.meetings.pdf', { slug: club.slug, id: meeting.id })" target="_blank" :class="linkButtonClass">Download PDF summons</a>
        </div>
      </Card>

      <!-- Summons sheet -->
      <Card padding="lg" class="space-y-8">

        <!-- Header -->
        <div class="text-center border-b border-slate-200 dark:border-slate-800 pb-8 space-y-3">
          <div class="w-16 h-16 bg-slate-900 dark:bg-slate-700 text-gold-400 rounded-2xl flex items-center justify-center font-bold text-2xl mx-auto" aria-hidden="true">
            {{ club.name.substring(0, 2).toUpperCase() }}
          </div>

          <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">{{ club.name }}</h1>
          <p v-if="club.lodge_number" class="text-sm font-semibold text-slate-500 dark:text-slate-400">No. {{ club.lodge_number }}</p>
          <Badge variant="info">Official meeting summons</Badge>
          <h2 class="text-xl font-semibold text-slate-800 dark:text-slate-100 pt-2">{{ meeting.title || 'Regular meeting' }}</h2>
        </div>

        <!-- Your response -->
        <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-sm">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-slate-500 dark:text-slate-400 font-medium">Your attendance:</span>
            <Badge v-if="userRsvp?.attendance_status === 'attending_dining'" variant="success">Attending dining</Badge>
            <Badge v-else-if="userRsvp?.attendance_status === 'attending_meeting_only'" variant="info">Attending meeting only</Badge>
            <Badge v-else-if="userRsvp?.attendance_status === 'apologies'" variant="danger">Apologies sent</Badge>
            <Badge v-else variant="warning">Awaiting response</Badge>
          </div>

          <Button variant="ghost" size="sm" @click="openRsvpModal">{{ userRsvp ? 'Change response' : 'Send response' }}</Button>
        </div>

        <!-- Schedule and venue -->
        <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-6 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl">
          <div>
            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Date</dt>
            <dd class="text-base font-semibold text-slate-900 dark:text-white mt-1">{{ formatMeetingDate(meeting.meeting_date) }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Timings</dt>
            <dd class="text-base font-semibold text-slate-900 dark:text-white mt-1">Start: {{ meeting.starts_at }} <span v-if="meeting.rehearsal_starts_at" class="text-sm font-normal text-slate-500 dark:text-slate-400">(Rehearsal: {{ meeting.rehearsal_starts_at }})</span></dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Location</dt>
            <dd class="text-base font-semibold text-slate-900 dark:text-white mt-1">{{ meeting.venue }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Dress code</dt>
            <dd class="text-base font-semibold text-slate-900 dark:text-white mt-1">{{ meeting.dress_code }}</dd>
          </div>
        </dl>

        <!-- Formal salutation -->
        <div class="p-6 bg-gold-50 dark:bg-gold-900/20 border border-gold-200 dark:border-gold-800/60 rounded-2xl text-sm text-slate-800 dark:text-slate-200 leading-relaxed space-y-2">
          <p class="font-semibold text-base text-slate-900 dark:text-white">{{ meeting.salutation || 'Dear Sir and Brother,' }}</p>
          <p>
            You are hereby requested and summoned to attend the proceedings of <strong>{{ club.name }}</strong> at the date, time, and venue specified above.
          </p>
          <div class="flex flex-wrap items-center justify-between gap-2 pt-2 text-xs font-medium text-slate-600 dark:text-slate-300">
            <span>By command of the Worshipful Master: <strong>{{ worshipfulMaster?.name || 'Worshipful Master' }}</strong></span>
            <span>Secretary: <strong>{{ secretaryUser?.name || 'Secretary' }}</strong></span>
          </div>
        </div>

        <!-- Agenda -->
        <section class="space-y-4 pt-2">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center justify-between">
            <span>Agenda and business to be transacted</span>
            <span v-if="meeting.agenda_items?.length" class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ meeting.agenda_items.length }} items</span>
          </h3>

          <ol v-if="meeting.agenda_items?.length" class="space-y-3">
            <li v-for="(item, idx) in meeting.agenda_items" :key="item.id || idx" class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-1">
              <div class="flex items-start gap-3">
                <span class="w-7 h-7 rounded-full bg-blue-600 text-white font-semibold text-sm flex items-center justify-center flex-shrink-0 mt-0.5">
                  {{ item.item_number || (idx + 1) }}
                </span>
                <div>
                  <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ item.title }}</h4>
                  <p v-if="item.description" class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed mt-1 whitespace-pre-line">{{ item.description }}</p>
                </div>
              </div>
            </li>
          </ol>

          <div v-else class="p-6 text-center text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-2xl text-sm">
            Standard business and agenda items will be transacted.
          </div>
        </section>

        <!-- Charitable donation proposals -->
        <section v-if="charityGrants?.length" class="space-y-4 pt-2">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2 flex flex-wrap items-center justify-between gap-2">
            <span>Charitable donation proposals and alms voting</span>
            <Badge variant="neutral">{{ charityGrants.length }} grant proposal(s)</Badge>
          </h3>

          <div class="space-y-3">
            <div v-for="grant in charityGrants" :key="grant.id" class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-2">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                <div>
                  <h4 class="text-base font-semibold text-slate-900 dark:text-white">
                    {{ $money(grant.amount) }} — {{ grant.recipient_name }}
                  </h4>
                  <p class="text-xs text-slate-600 dark:text-slate-300 font-medium mt-0.5">{{ grant.purpose }}</p>
                </div>
                <Badge variant="warning" class="self-start sm:self-center">{{ grantStatusLabel(grant.approval_status) }}</Badge>
              </div>
              <div class="flex flex-wrap items-center gap-x-4 text-xs text-slate-600 dark:text-slate-300 font-medium">
                <span v-if="grant.proposer">Proposed by: <strong class="text-slate-900 dark:text-white">{{ grant.proposer.first_name }} {{ grant.proposer.last_name }}</strong></span>
                <span v-if="grant.seconder">Seconded by: <strong class="text-slate-900 dark:text-white">{{ grant.seconder.first_name }} {{ grant.seconder.last_name }}</strong></span>
              </div>
            </div>
          </div>
        </section>

        <!-- Officers -->
        <section v-if="meeting.officer_assignments?.length" class="space-y-4 pt-2">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2">
            Officers ({{ meeting.officers_year_label || 'Officers' }})
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            <div v-for="off in meeting.officer_assignments" :key="off.id" class="p-3.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 text-sm flex items-center justify-between gap-2">
              <span class="font-medium text-slate-600 dark:text-slate-300">{{ off.officer_role?.name || 'Officer' }}</span>
              <span class="font-semibold text-slate-900 dark:text-white">{{ off.custom_name || off.user?.name || '-' }}</span>
            </div>
          </div>
        </section>

        <!-- Festive board and dining -->
        <section class="space-y-4 pt-2">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2">
            Festive board and dining
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <StatCard label="Member dining fee" :value="$money(meeting.dining_cost_member)" />
            <StatCard label="Guest dining fee" :value="$money(meeting.dining_cost_guest)" />
            <StatCard label="Dining cut-off" :value="meeting.rsvp_cutoff_at ? formatLocal(meeting.rsvp_cutoff_at) : '5 days before the meeting'" />
          </div>

          <!-- Bank details and payment reference -->
          <div v-if="(meeting.bank_sort_code || meeting.bank_account_number) && (userRsvp?.attendance_status === 'attending_dining' || userRsvp?.guests?.some(g => g.attending_dining))" class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl text-sm space-y-2">
            <div class="font-semibold text-slate-900 dark:text-white flex items-center justify-between gap-2">
              <span>Bank transfer details</span>
              <Badge v-if="userRsvp?.payment_status === 'paid'" variant="success">Paid</Badge>
              <Badge v-else-if="userRsvp?.payment_status === 'waived'" variant="info">Waived</Badge>
              <Badge v-else variant="warning">Unpaid</Badge>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-slate-700 dark:text-slate-200 font-mono text-xs">
              <div>Sort code: <strong class="text-slate-900 dark:text-white">{{ meeting.bank_sort_code }}</strong></div>
              <div>Account no: <strong class="text-slate-900 dark:text-white">{{ meeting.bank_account_number }}</strong></div>
              <div>Reference prefix: <strong class="text-blue-600 dark:text-blue-400">{{ meeting.payment_reference_prefix || 'SUMMONS' }}</strong></div>
            </div>
          </div>
          <Alert v-else-if="userRsvp && userRsvp.attendance_status !== 'attending_dining'" variant="info">
            No dining fee is due for meeting-only attendance or apologies.
          </Alert>
        </section>

        <!-- Provincial leadership -->
        <section v-if="meeting.provincial_grand_master || meeting.deputy_provincial_grand_master || meeting.assistant_provincial_grand_masters" class="space-y-4 pt-2 border-t border-slate-200 dark:border-slate-800">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2">
            {{ meeting.front_page_title || 'Provincial Grand Lodge leadership' }}
          </h3>

          <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div v-if="meeting.provincial_grand_master" class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl text-sm space-y-1">
              <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Provincial Grand Master</dt>
              <dd class="text-base font-semibold text-slate-900 dark:text-white">{{ meeting.provincial_grand_master }}</dd>
            </div>

            <div v-if="meeting.deputy_provincial_grand_master" class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl text-sm space-y-1">
              <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Deputy Provincial Grand Master</dt>
              <dd class="text-base font-semibold text-slate-900 dark:text-white">{{ meeting.deputy_provincial_grand_master }}</dd>
            </div>

            <div v-if="meeting.assistant_provincial_grand_masters" class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl text-sm space-y-1">
              <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Assistant Provincial Grand Masters</dt>
              <dd class="text-sm font-medium text-slate-800 dark:text-slate-100 whitespace-pre-line leading-relaxed">{{ meeting.assistant_provincial_grand_masters }}</dd>
            </div>
          </dl>
        </section>

        <!-- Fraternal visits -->
        <section v-if="meeting.fraternal_visits?.length || meeting.fraternal_visits_text" class="space-y-4 pt-2 border-t border-slate-200 dark:border-slate-800">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2">
            Fraternal visits and official delegations
          </h3>

          <Alert v-if="meeting.fraternal_visits_text" variant="info" class="whitespace-pre-line">{{ meeting.fraternal_visits_text }}</Alert>

          <div v-if="meeting.fraternal_visits?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div v-for="visit in meeting.fraternal_visits" :key="visit.id" class="p-3.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 text-sm space-y-1">
              <div class="font-semibold text-slate-900 dark:text-white">{{ visit.visiting_club_name || visit.lodge_name }} <span v-if="visit.visiting_club_number || visit.lodge_number">No. {{ visit.visiting_club_number || visit.lodge_number }}</span></div>
              <div v-if="visit.leader_name" class="text-slate-600 dark:text-slate-300 font-medium">Delegation leader: {{ visit.leader_name }}</div>
              <div v-if="visit.notes" class="text-slate-500 dark:text-slate-400 italic text-xs">{{ visit.notes }}</div>
            </div>
          </div>
        </section>

        <!-- Honorary members and welfare notices -->
        <section v-if="meeting.honorary_members_text || meeting.sick_distressed_notes || meeting.almoner_notice" class="pt-2 border-t border-slate-200 dark:border-slate-800">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Alert v-if="meeting.honorary_members_text" variant="info" title="Honorary members" class="whitespace-pre-line">{{ meeting.honorary_members_text }}</Alert>
            <Alert v-if="meeting.sick_distressed_notes || meeting.almoner_notice" variant="warning" title="Almoner, sick and distressed notice" class="whitespace-pre-line">{{ meeting.sick_distressed_notes || meeting.almoner_notice }}</Alert>
          </div>
        </section>

        <!-- Secretary contact -->
        <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl text-sm flex flex-wrap items-center justify-between gap-3">
          <div>
            <span class="text-slate-500 dark:text-slate-400 font-medium">Lodge Secretary: </span>
            <strong class="text-slate-900 dark:text-white">{{ secretaryUser?.name || 'Secretary' }}</strong>
          </div>
          <div class="flex flex-wrap items-center gap-4 text-slate-600 dark:text-slate-300 text-xs">
            <span>Email: <span class="font-mono">{{ secretaryUser?.email || club.email }}</span></span>
            <span v-if="club.settings?.phone">Phone: <span class="font-mono">{{ club.settings.phone }}</span></span>
          </div>
        </div>

      </Card>

      <MeetingRsvpModal v-if="showRsvpModal" :meeting="meeting" :club-slug="club.slug" :rsvp="userRsvp" @close="showRsvpModal = false" />
    </div>
  </MembersLayout>
</template>
