<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  // { id, title }
  meeting: { type: Object, required: true },
  clubSlug: { type: String, required: true },
  // Existing reply: { attendance_status, dietary_requirements, apology_reason, guests }
  rsvp: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const form = useForm({
  attendance_status: props.rsvp?.attendance_status || 'attending_dining',
  dietary_requirements: props.rsvp?.dietary_requirements || '',
  apology_reason: props.rsvp?.apology_reason || '',
  guests: (props.rsvp?.guests || []).map((g) => ({
    guest_name: g.guest_name,
    dietary_requirements: g.dietary_requirements || '',
    attending_dining: g.attending_dining ?? true,
  })),
});

const addGuestRow = () => {
  form.guests.push({ guest_name: '', dietary_requirements: '', attending_dining: true });
};

const removeGuestRow = (index) => {
  form.guests.splice(index, 1);
};

const submit = () => {
  form.post(route('member.meetings.rsvp', { slug: props.clubSlug, id: props.meeting.id }), {
    preserveScroll: true,
    onSuccess: () => emit('close'),
  });
};
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
    <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-4 my-8">
      <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">
          ✍️ Respond to Summons — {{ meeting.title }}
        </h3>
        <button type="button" @click="emit('close')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg font-bold cursor-pointer" aria-label="Close">&times;</button>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <p v-if="form.errors.cutoff" class="rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 p-2.5 text-xs font-semibold text-rose-700 dark:text-rose-300">{{ form.errors.cutoff }}</p>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1">Select Attendance</label>
          <div class="grid grid-cols-3 gap-2">
            <label :class="['flex items-center justify-center p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all', form.attendance_status === 'attending_dining' ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-500 text-emerald-800 dark:text-emerald-200' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300']">
              <input type="radio" v-model="form.attendance_status" value="attending_dining" class="sr-only" />
              🟢 Dining & Meeting
            </label>
            <label :class="['flex items-center justify-center p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all', form.attendance_status === 'attending_meeting_only' ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-500 text-blue-800 dark:text-blue-200' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300']">
              <input type="radio" v-model="form.attendance_status" value="attending_meeting_only" class="sr-only" />
              🔵 Meeting Only
            </label>
            <label :class="['flex items-center justify-center p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all', form.attendance_status === 'apologies' ? 'bg-rose-50 dark:bg-rose-950/40 border-rose-500 text-rose-800 dark:text-rose-200' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300']">
              <input type="radio" v-model="form.attendance_status" value="apologies" class="sr-only" />
              🔴 Apologies
            </label>
          </div>
        </div>

        <div v-if="form.attendance_status === 'attending_dining'">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1">Your Dietary Requirements</label>
          <input type="text" v-model="form.dietary_requirements" placeholder="e.g. Vegetarian, Gluten-free, Nut allergy" class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 focus:border-blue-500 focus:ring-blue-500 p-2.5" />
        </div>

        <div v-if="form.attendance_status === 'apologies'">
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1">Apology Reason / Notes</label>
          <textarea v-model="form.apology_reason" rows="2" placeholder="e.g. Away on business, unwell" class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 focus:border-blue-500 focus:ring-blue-500 p-2.5"></textarea>
        </div>

        <div v-if="form.attendance_status === 'attending_dining'" class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
          <div class="flex items-center justify-between">
            <label class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Accompanying Guests</label>
            <button type="button" @click="addGuestRow" class="text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 bg-blue-50 dark:bg-blue-950/40 px-2.5 py-1 rounded-lg border border-blue-200 dark:border-blue-800/60 cursor-pointer">
              + Add Guest
            </button>
          </div>

          <div v-for="(g, idx) in form.guests" :key="idx" class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-700 dark:text-slate-200">Guest #{{ idx + 1 }}</span>
              <button type="button" @click="removeGuestRow(idx)" class="text-rose-600 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-200 text-xs font-bold cursor-pointer">Remove</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <input type="text" v-model="g.guest_name" placeholder="Guest Full Name" class="text-xs rounded-lg border-slate-300 dark:border-slate-700 p-2" required />
              <input type="text" v-model="g.dietary_requirements" placeholder="Dietary notes (optional)" class="text-xs rounded-lg border-slate-300 dark:border-slate-700 p-2" />
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
          <button type="button" @click="emit('close')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl cursor-pointer">Cancel</button>
          <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md cursor-pointer disabled:opacity-50">
            Save Response
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
