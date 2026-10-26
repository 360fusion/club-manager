<script setup>
import { useClipboard } from '@/Composables/useClipboard';
import { formatMeetingDate } from '@/Utils/lodgeDates';
import { Head, useForm } from '@inertiajs/vue3';
import FlashMessages from '@/Components/Ui/FlashMessages.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';

const props = defineProps({
  token: String,
  club: Object,
  user: Object,
  meeting: Object,
  rsvp: Object,
  isCutoffPassed: Boolean,
  isVisitor: Boolean,
  visitorHomeClub: String,
});

const form = useForm({
  attendance_status: props.rsvp.attendance_status !== 'pending' ? props.rsvp.attendance_status : 'attending_dining',
  apology_reason: props.rsvp.apology_reason || '',
  dietary_requirements: props.rsvp.dietary_requirements || '',
  guests: props.rsvp.guests || [],
});

const addGuest = () => {
  form.guests.push({
    guest_name: '',
    guest_title_rank: '',
    home_club_lodge: '',
    attending_dining: true,
    dietary_requirements: '',
  });
};

const removeGuest = (index) => {
  form.guests.splice(index, 1);
};

const submit = () => {
  form.post(route('summons.rsvp.store', { token: props.token }));
};

const { copied: refCopied, copy } = useClipboard();
const copyBankRef = () => copy(props.rsvp.payment_reference || `SUMMONS-${props.meeting.id}-${props.user.name.split(' ').pop().toUpperCase()}`);

const fieldClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
const tileClass = (value) => ['flex cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-blue-500', form.attendance_status === value ? 'border-blue-500 bg-blue-50 text-blue-900 dark:bg-blue-950/40 dark:text-blue-100' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200'];
const showsPayment = () => form.attendance_status === 'attending_dining' || form.guests.some(g => g.attending_dining);

</script>

<template>
  <Head :title="`Summons RSVP — ${meeting.title}`" />

  <div class="min-h-screen bg-slate-100 dark:bg-slate-950 flex flex-col justify-center py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full mx-auto space-y-6">

      <FlashMessages />

      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 space-y-6">

        <!-- Club and meeting -->
        <div class="text-center border-b border-slate-200 dark:border-slate-800 pb-5">
          <div class="w-12 h-12 bg-blue-600 text-white rounded-2xl flex items-center justify-center font-bold text-xl mx-auto mb-3" aria-hidden="true">
            {{ club.name.substring(0, 2).toUpperCase() }}
          </div>
          <h1 class="text-lg font-bold text-slate-900 dark:text-white">{{ club.name }}</h1>
          <h2 class="text-sm font-semibold text-blue-600 dark:text-blue-400 mt-0.5">{{ meeting.title }}</h2>
          <Badge v-if="isVisitor" variant="info" class="mt-2">
            Visiting brother or guest<span v-if="visitorHomeClub"> ({{ visitorHomeClub }})</span>
          </Badge>
        </div>

        <!-- Meeting details -->
        <dl class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-200 dark:border-slate-800 text-sm space-y-2">
          <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-500 dark:text-slate-400">Date</dt>
            <dd class="font-semibold text-slate-900 dark:text-white">{{ formatMeetingDate(meeting.meeting_date) }}</dd>
          </div>
          <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-500 dark:text-slate-400">Meeting starts</dt>
            <dd class="font-semibold text-slate-900 dark:text-white text-right">{{ meeting.starts_at }} (Rehearsal: {{ meeting.rehearsal_starts_at || '-' }})</dd>
          </div>
          <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-500 dark:text-slate-400">Venue</dt>
            <dd class="font-semibold text-slate-900 dark:text-white truncate max-w-[180px]">{{ meeting.venue }}</dd>
          </div>
          <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-500 dark:text-slate-400">Dress code</dt>
            <dd class="font-semibold text-slate-900 dark:text-white">{{ meeting.dress_code }}</dd>
          </div>
        </dl>

        <Alert v-if="isCutoffPassed" variant="warning" title="Dining cut-off passed">
          The deadline for festive board catering has passed. Please contact the Secretary directly for last-minute changes.
        </Alert>

        <!-- RSVP form -->
        <form v-else class="space-y-5" @submit.prevent="submit">
          <Alert v-if="form.errors.cutoff || form.errors.token" variant="danger" role="alert">{{ form.errors.cutoff || form.errors.token }}</Alert>

          <fieldset class="space-y-2">
            <legend class="mb-2 block text-xs font-medium text-slate-700 dark:text-slate-300">Your attendance</legend>

            <label :class="tileClass('attending_dining')">
              <input v-model="form.attendance_status" type="radio" value="attending_dining" class="sr-only" />
              <span class="text-sm">
                <span class="block font-medium">Attending meeting and dining</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400">Festive board ({{ $money(meeting.dining_cost_member) }})</span>
              </span>
            </label>

            <label :class="tileClass('attending_meeting_only')">
              <input v-model="form.attendance_status" type="radio" value="attending_meeting_only" class="sr-only" />
              <span class="text-sm">
                <span class="block font-medium">Attending meeting only</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400">No festive board dining</span>
              </span>
            </label>

            <!-- Apologies (subscribing members only) -->
            <label v-if="!isVisitor" :class="tileClass('apologies')">
              <input v-model="form.attendance_status" type="radio" value="apologies" class="sr-only" />
              <span class="text-sm">
                <span class="block font-medium">Send apologies for absence</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400">Unable to attend the meeting</span>
              </span>
            </label>

            <Alert v-else variant="info" title="Visiting brethren">
              As a visitor, apologies are not required if you are unable to attend.
            </Alert>
          </fieldset>

          <Input v-if="form.attendance_status === 'apologies'" v-model="form.apology_reason" label="Reason or note for the minutes (optional)" placeholder="Away on business, family commitment" />

          <!-- Dining details -->
          <div v-if="form.attendance_status === 'attending_dining'" class="space-y-4 pt-2 border-t border-slate-200 dark:border-slate-800">
            <Input v-model="form.dietary_requirements" label="Dietary requirements or allergies" placeholder="Vegetarian, gluten free, nut allergy" />

            <!-- Guests -->
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <h3 class="text-xs font-medium text-slate-700 dark:text-slate-300">Visiting brethren or guests</h3>
                <Button variant="secondary" size="sm" @click="addGuest">Add a guest</Button>
              </div>

              <div v-for="(g, idx) in form.guests" :key="idx" class="p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Guest {{ idx + 1 }}</span>
                  <Button variant="ghost" size="sm" :aria-label="`Remove guest ${idx + 1}`" @click="removeGuest(idx)">Remove</Button>
                </div>
                <input v-model="g.guest_name" type="text" placeholder="Guest's full name" :aria-label="`Guest ${idx + 1} full name`" required :class="fieldClass" />
                <input v-model="g.home_club_lodge" type="text" placeholder="Home club or lodge" :aria-label="`Guest ${idx + 1} home club or lodge`" :class="fieldClass" />
              </div>
            </div>

            <!-- Online payment link (dining only) -->
            <a v-if="meeting.payment_link && showsPayment()" :href="meeting.payment_link" target="_blank" class="flex w-full items-center justify-center rounded-xl border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950">
              Pay online now ({{ $money(meeting.dining_cost_member) }})
            </a>

            <!-- Bank payment details (dining only) -->
            <div v-if="meeting.bank_sort_code && showsPayment()" class="p-4 bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 rounded-xl text-sm space-y-2">
              <div class="font-semibold text-slate-900 dark:text-white flex items-center justify-between gap-2">
                <span>Bank transfer details</span>
                <Button variant="secondary" size="sm" @click="copyBankRef">{{ refCopied ? 'Copied' : 'Copy ref' }}</Button>
              </div>
              <div class="text-slate-700 dark:text-slate-200 font-mono text-xs">
                Sort code: <strong>{{ meeting.bank_sort_code }}</strong> | Acc: <strong>{{ meeting.bank_account_number }}</strong>
              </div>
            </div>
          </div>

          <Button type="submit" size="lg" block :loading="form.processing">Submit my RSVP</Button>
        </form>

      </div>

      <p class="text-center text-xs text-slate-500 dark:text-slate-400">
        Passwordless access through your private link · Club Manager
      </p>

    </div>
  </div>
</template>
