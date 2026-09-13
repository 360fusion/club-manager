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
</script>

<template>
  <Head :title="`Summons RSVP — ${meeting.title}`" />

  <div class="min-h-screen bg-slate-100 flex flex-col justify-center py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full mx-auto space-y-6">
      
      <!-- Card Container -->
      <div class="bg-white rounded-3xl p-6 shadow-xl border border-slate-200/80 space-y-6">
        
        <!-- Header & Club Info -->
        <div class="text-center border-b border-slate-100 pb-5">
          <div class="w-12 h-12 bg-indigo-600 text-white rounded-2xl flex items-center justify-center font-black text-xl mx-auto mb-3 shadow-md shadow-indigo-600/30">
            {{ club.name.substring(0, 2).toUpperCase() }}
          </div>
          <h1 class="text-lg font-extrabold text-slate-900">{{ club.name }}</h1>
          <h2 class="text-xs font-bold text-indigo-600 mt-0.5 uppercase tracking-wider">{{ meeting.title }}</h2>
        </div>

        <!-- Meeting Schedule Details Banner -->
        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 text-xs space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-slate-500">📅 Date:</span>
            <span class="font-bold text-slate-900">{{ meeting.meeting_date }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500">🕒 Meeting Start:</span>
            <span class="font-bold text-slate-900">{{ meeting.starts_at }} (Rehearsal: {{ meeting.rehearsal_starts_at || '-' }})</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500">📍 Venue:</span>
            <span class="font-bold text-slate-900 truncate max-w-[180px]">{{ meeting.venue }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-500">👔 Dress Code:</span>
            <span class="font-bold text-slate-900">{{ meeting.dress_code }}</span>
          </div>
        </div>

        <!-- Cutoff Warning Banner if Passed -->
        <div v-if="isCutoffPassed" class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-700 font-medium text-center">
          ⚠️ <strong>Dining Cutoff Passed</strong><br>
          The official deadline for festive board catering has passed. Please contact the Secretary directly for last-minute changes.
        </div>

        <!-- RSVP Form -->
        <form v-else @submit.prevent="submit" class="space-y-5">
          
          <div class="space-y-2">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Your Attendance Choice</label>
            
            <div class="space-y-2">
              <label :class="['flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all', form.attendance_status === 'attending_dining' ? 'bg-emerald-50 border-emerald-500 text-emerald-900 font-bold shadow-sm' : 'bg-slate-50 border-slate-200 text-slate-700']">
                <input type="radio" value="attending_dining" v-model="form.attendance_status" class="text-emerald-600 focus:ring-emerald-500" />
                <div class="text-xs">
                  <div>Attending Meeting & Dining</div>
                  <div class="text-[10px] text-slate-500 font-normal">Festive Board (£{{ meeting.dining_cost_member }})</div>
                </div>
              </label>

              <label :class="['flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all', form.attendance_status === 'attending_meeting_only' ? 'bg-sky-50 border-sky-500 text-sky-900 font-bold shadow-sm' : 'bg-slate-50 border-slate-200 text-slate-700']">
                <input type="radio" value="attending_meeting_only" v-model="form.attendance_status" class="text-sky-600 focus:ring-sky-500" />
                <div class="text-xs">
                  <div>Attending Meeting Only</div>
                  <div class="text-[10px] text-slate-500 font-normal">No festive board dining</div>
                </div>
              </label>

              <label :class="['flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all', form.attendance_status === 'apologies' ? 'bg-rose-50 border-rose-500 text-rose-900 font-bold shadow-sm' : 'bg-slate-50 border-slate-200 text-slate-700']">
                <input type="radio" value="apologies" v-model="form.attendance_status" class="text-rose-600 focus:ring-rose-500" />
                <div class="text-xs">
                  <div>Send Apologies for Absence</div>
                  <div class="text-[10px] text-slate-500 font-normal">Unable to attend meeting</div>
                </div>
              </label>
            </div>
          </div>

          <!-- Apologies Reason Field -->
          <div v-if="form.attendance_status === 'apologies'" class="space-y-1.5">
            <label class="block text-xs font-bold text-slate-700">Reason / Note for Minutes (Optional)</label>
            <input v-model="form.apology_reason" type="text" placeholder="Away on business / Family commitment..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
          </div>

          <!-- Dining Details (Dietary & Visitors) -->
          <div v-if="form.attendance_status === 'attending_dining'" class="space-y-4 pt-2 border-t border-slate-100">
            <div class="space-y-1.5">
              <label class="block text-xs font-bold text-slate-700">Dietary Requirements / Allergies</label>
              <input v-model="form.dietary_requirements" type="text" placeholder="Vegetarian, Gluten Free, Nut Allergy..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
            </div>

            <!-- Guest Bookings -->
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-slate-700">Visiting Brethren / Guests</label>
                <button type="button" @click="addGuest" class="text-xs text-indigo-600 font-bold hover:underline">+ Add Guest</button>
              </div>

              <div v-for="(g, idx) in form.guests" :key="idx" class="p-3 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-[11px] font-bold text-slate-700">Guest #{{ idx + 1 }}</span>
                  <button type="button" @click="removeGuest(idx)" class="text-rose-500 text-xs font-bold">Remove</button>
                </div>
                <input v-model="g.guest_name" type="text" placeholder="Full Guest Name" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs" />
                <input v-model="g.home_club_lodge" type="text" placeholder="Home Club / Lodge Name" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs" />
              </div>
            </div>

            <!-- Payment Details Card -->
            <div v-if="meeting.bank_sort_code" class="p-4 bg-indigo-50/70 border border-indigo-200 rounded-2xl text-xs space-y-2">
              <div class="font-bold text-indigo-950 flex items-center justify-between">
                <span>💳 BACS Bank Payment Info</span>
                <button type="button" @click="copyBankRef" class="px-2 py-1 bg-indigo-600 text-white text-[10px] font-bold rounded-lg shadow-sm">Copy Ref</button>
              </div>
              <div class="text-indigo-900 font-mono text-[11px]">
                Sort Code: <strong>{{ meeting.bank_sort_code }}</strong> | Acc: <strong>{{ meeting.bank_account_number }}</strong>
              </div>
            </div>
          </div>

          <button type="submit" :disabled="form.processing" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-2xl shadow-lg shadow-indigo-600/30 transition-all">
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
