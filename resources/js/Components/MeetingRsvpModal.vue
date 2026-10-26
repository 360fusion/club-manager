<script setup>
import { useId } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import { formatMeetingDate } from '@/Utils/lodgeDates';

const props = defineProps({
  // { id, title, meeting_date? }
  meeting: { type: Object, required: true },
  clubSlug: { type: String, required: true },
  // Existing reply: { attendance_status, dietary_requirements, apology_reason, guests }
  rsvp: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const formId = useId();

const CHOICES = [
  ['attending_dining', 'Meeting and dining'],
  ['attending_meeting_only', 'Meeting only'],
  ['apologies', 'Apologies'],
];

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

const fieldClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
</script>

<template>
  <Modal :open="true" title="Respond to summons" :subtitle="meeting.title || formatMeetingDate(meeting.meeting_date)" @close="emit('close')">
    <form :id="formId" class="space-y-4" @submit.prevent="submit">
      <Alert v-if="form.errors.cutoff" variant="danger" role="alert">{{ form.errors.cutoff }}</Alert>

      <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Your attendance">
        <label v-for="choice in CHOICES" :key="choice[0]"
          :class="['flex cursor-pointer items-center justify-center rounded-xl border p-3 text-center text-sm font-medium transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-blue-500', form.attendance_status === choice[0] ? 'border-blue-500 bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-200' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300']">
          <input v-model="form.attendance_status" type="radio" :value="choice[0]" class="sr-only" />
          {{ choice[1] }}
        </label>
      </div>

      <Input v-if="form.attendance_status === 'attending_dining'" v-model="form.dietary_requirements" label="Your dietary requirements" placeholder="Vegetarian, gluten free, nut allergy" />

      <Textarea v-if="form.attendance_status === 'apologies'" v-model="form.apology_reason" :rows="2" label="Reason for apologies" placeholder="Away on business, unwell" />

      <div v-if="form.attendance_status === 'attending_dining'" class="space-y-2 border-t border-slate-200 pt-3 dark:border-slate-800">
        <div class="flex items-center justify-between">
          <h3 class="text-xs font-medium text-slate-700 dark:text-slate-300">Guests coming with you</h3>
          <Button variant="secondary" size="sm" @click="addGuestRow">Add a guest</Button>
        </div>

        <div v-for="(g, idx) in form.guests" :key="idx" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/40">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">Guest {{ idx + 1 }}</span>
            <Button variant="ghost" size="sm" :aria-label="`Remove guest ${idx + 1}`" @click="removeGuestRow(idx)">Remove</Button>
          </div>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <input v-model="g.guest_name" type="text" placeholder="Guest's full name" :aria-label="`Guest ${idx + 1} full name`" :class="fieldClass" required />
            <input v-model="g.dietary_requirements" type="text" placeholder="Dietary notes (optional)" :aria-label="`Guest ${idx + 1} dietary notes`" :class="fieldClass" />
          </div>
        </div>
      </div>
    </form>

    <template #footer>
      <Button variant="secondary" @click="emit('close')">Cancel</Button>
      <Button type="submit" :form="formId" :loading="form.processing">Save response</Button>
    </template>
  </Modal>
</template>
