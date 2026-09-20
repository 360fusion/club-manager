<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

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

const copyBankRef = () => {
  const refText = props.rsvp.payment_reference || `SUMMONS-${props.meeting.id}-${props.user.name.split(' ').pop().toUpperCase()}`;
  navigator.clipboard.writeText(refText);
  alert(`Payment Reference "${refText}" copied to clipboard!`);
};

const formatDate = (dateVal) => {
  if (!dateVal) return '';
  const cleanStr = String(dateVal).split('T')[0];
  const parts = cleanStr.split('-');
  if (parts.length === 3) {
    const year = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10) - 1;
    const day = parseInt(parts[2], 10);
    const d = new Date(year, month, day);
    if (!isNaN(d.getTime())) {
      return d.toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    }
  }
  return dateVal;
};
</script>

<template>
  <Head :title="`Summons RSVP — ${meeting.title}`" />

  <div class="min-h-screen bg-slate-100 dark:bg-slate-950 flex flex-col justify-center py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full mx-auto space-y-6">
      
      <!-- Card Container -->
      <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-xl border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        
        <!-- Header & Club Info -->
        <div class="text-center border-b border-slate-100 dark:border-slate-800 pb-5">
          <div class="w-12 h-12 bg-blue-600 text-white rounded-2xl flex items-center justify-center font-black text-xl mx-auto mb-3 shadow-md shadow-blue-600/30">
            {{ club.name.substring(0, 2).toUpperCase() }}
          </div>
          <h1 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ club.name }}</h1>
          <h2 class="text-xs font-bold text-blue-600 dark:text-blue-400 mt-0.5 uppercase tracking-wider">{{ meeting.title }}</h2>
          <div v-if="isVisitor" class="mt-2 inline-flex items-center gap-1.5 px-3 py-1 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-blue-700 dark:text-blue-300 text-xs font-bold rounded-full">
            <span>✨ Visiting Brother / Guest</span>
            <span v-if="visitorHomeClub">({{ visitorHomeClub }})</span>
          </div>
        </div>

        <!-- Meeting Schedule Details Banner -->
        <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-slate-500 dark:text-slate-400">📅 Date:</span>
            <span class="font-bold text-slate-900 dark:text-white">{{ formatDate(meeting.meeting_date) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500 dark:text-slate-400">🕒 Meeting Start:</span>
            <span class="font-bold text-slate-900 dark:text-white">{{ meeting.starts_at }} (Rehearsal: {{ meeting.rehearsal_starts_at || '-' }})</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500 dark:text-slate-400">📍 Venue:</span>
            <span class="font-bold text-slate-900 dark:text-white truncate max-w-[180px]">{{ meeting.venue }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500 dark:text-slate-400">👔 Dress Code:</span>
            <span class="font-bold text-slate-900 dark:text-white">{{ meeting.dress_code }}</span>
          </div>
        </div>

        <!-- Cutoff Warning Banner if Passed -->
        <div v-if="isCutoffPassed" class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 rounded-2xl text-xs text-rose-700 dark:text-rose-300 font-medium text-center">
          ⚠️ <strong>Dining Cutoff Passed</strong><br>
          The official deadline for festive board catering has passed. Please contact the Secretary directly for last-minute changes.
        </div>

        <!-- RSVP Form -->
        <form v-else @submit.prevent="submit" class="space-y-5">
          
          <div class="space-y-2">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Your Attendance Choice</label>
            
            <div class="space-y-2">
              <label :class="['flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all', form.attendance_status === 'attending_dining' ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-500 text-emerald-900 dark:text-emerald-200 font-bold shadow-sm' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200']">
                <input type="radio" value="attending_dining" v-model="form.attendance_status" class="text-emerald-600 dark:text-emerald-400 focus:ring-emerald-500" />
                <div class="text-xs">
                  <div>Attending Meeting & Dining</div>
                  <div class="text-[10px] text-slate-500 dark:text-slate-400 font-normal">Festive Board (£{{ meeting.dining_cost_member }})</div>
                </div>
              </label>

              <label :class="['flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all', form.attendance_status === 'attending_meeting_only' ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-500 text-blue-900 dark:text-blue-200 font-bold shadow-sm' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200']">
                <input type="radio" value="attending_meeting_only" v-model="form.attendance_status" class="text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
                <div class="text-xs">
                  <div>Attending Meeting Only</div>
                  <div class="text-[10px] text-slate-500 dark:text-slate-400 font-normal">No festive board dining</div>
                </div>
              </label>

              <!-- Apologies Option (Subscribing Members Only) -->
              <label v-if="!isVisitor" :class="['flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all', form.attendance_status === 'apologies' ? 'bg-rose-50 dark:bg-rose-950/40 border-rose-500 text-rose-900 dark:text-rose-200 font-bold shadow-sm' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200']">
                <input type="radio" value="apologies" v-model="form.attendance_status" class="text-rose-600 dark:text-rose-400 focus:ring-rose-500" />
                <div class="text-xs">
                  <div>Send Apologies for Absence</div>
                  <div class="text-[10px] text-slate-500 dark:text-slate-400 font-normal">Unable to attend meeting</div>
                </div>
              </label>

              <!-- Visitor Note -->
              <div v-else class="p-3 bg-blue-50/60 dark:bg-blue-950/60 border border-blue-200/80 dark:border-blue-800/80 rounded-2xl text-[11px] text-blue-800 dark:text-blue-200 font-medium">
                ℹ️ <strong>Visiting Brethren Note:</strong> As a visitor, apologies are not required if you are unable to attend.
              </div>
            </div>
          </div>

          <!-- Apologies Reason Field -->
          <div v-if="form.attendance_status === 'apologies'" class="space-y-1.5">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">Reason / Note for Minutes (Optional)</label>
            <input v-model="form.apology_reason" type="text" placeholder="Away on business / Family commitment..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs" />
          </div>

          <!-- Dining Details (Dietary & Visitors) -->
          <div v-if="form.attendance_status === 'attending_dining'" class="space-y-4 pt-2 border-t border-slate-100 dark:border-slate-800">
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200">Dietary Requirements / Allergies</label>
              <input v-model="form.dietary_requirements" type="text" placeholder="Vegetarian, Gluten Free, Nut Allergy..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs" />
            </div>

            <!-- Guest Bookings -->
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-slate-700 dark:text-slate-200">Visiting Brethren / Guests</label>
                <button type="button" @click="addGuest" class="text-xs text-blue-600 dark:text-blue-400 font-bold hover:underline">+ Add Guest</button>
              </div>

              <div v-for="(g, idx) in form.guests" :key="idx" class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200">Guest #{{ idx + 1 }}</span>
                  <button type="button" @click="removeGuest(idx)" class="text-rose-500 text-xs font-bold">Remove</button>
                </div>
                <input v-model="g.guest_name" type="text" placeholder="Full Guest Name" required class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs" />
                <input v-model="g.home_club_lodge" type="text" placeholder="Home Club / Lodge Name" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs" />
              </div>
            </div>

            <!-- Direct Online Payment Link Button (Dining Only) -->
            <div v-if="meeting.payment_link && (form.attendance_status === 'attending_dining' || form.guests.some(g => g.attending_dining))" class="pt-1">
              <a :href="meeting.payment_link" target="_blank" class="block w-full text-center py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md transition-all">
                💳 Pay Online Now (£{{ meeting.dining_cost_member }}) &rarr;
              </a>
            </div>

            <!-- Payment Details Card (Dining Only) -->
            <div v-if="meeting.bank_sort_code && (form.attendance_status === 'attending_dining' || form.guests.some(g => g.attending_dining))" class="p-4 bg-blue-50/70 dark:bg-blue-950/70 border border-blue-200 dark:border-blue-800/60 rounded-2xl text-xs space-y-2">
              <div class="font-bold text-blue-950 dark:text-blue-100 flex items-center justify-between">
                <span>💳 BACS Bank Payment Info</span>
                <button type="button" @click="copyBankRef" class="px-2 py-1 bg-blue-600 text-white text-[10px] font-bold rounded-lg shadow-sm">Copy Ref</button>
              </div>
              <div class="text-blue-900 dark:text-blue-200 font-mono text-[11px]">
                Sort Code: <strong>{{ meeting.bank_sort_code }}</strong> | Acc: <strong>{{ meeting.bank_account_number }}</strong>
              </div>
            </div>
          </div>

          <button type="submit" :disabled="form.processing" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-2xl shadow-lg shadow-blue-600/30 transition-all">
            Submit My RSVP Response
          </button>
        </form>

      </div>

      <div class="text-center text-[11px] text-slate-400">
        Passwordless Authentication via Cryptographic Token Engine • Club Manager
      </div>

    </div>
  </div>
</template>
