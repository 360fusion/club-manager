<script setup>
// Events tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
import Alert from '@/Components/Ui/Alert.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';

defineProps({
    form: { type: Object, required: true },
    paymentOptionsUrl: { type: [Object, Array, Function, String, Number, Boolean], required: true }
});
</script>

<template>
    <div class="space-y-6">
      <Alert title="Payment options for events and bookings">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <p>Bank transfer, card, PayPal, pay later and pay on the night. Set them up and switch them on once here, and every event offers them.</p>
          <a :href="paymentOptionsUrl('events')" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Manage payment options</a>
        </div>
      </Alert>

      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Events and attendance policy</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Set RSVP deadlines, guest policies, and attendance QR code expiration limits.</p>
        </template>

        <div class="space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <Input v-model.number="form.event_rsvp_cutoff_hours" type="number" min="0" max="168" label="Default RSVP cutoff (hours before the event)" />
            <Input v-model.number="form.max_guests_per_member" type="number" min="0" max="20" label="Most guests per member" />
            <Input v-model.number="form.qr_code_expiry_minutes" type="number" min="5" max="1440" label="Attendance QR code expiry (minutes)" />
          </div>

          <label class="flex items-start gap-3 text-sm cursor-pointer">
            <input type="checkbox" v-model="form.notify_event_reminders" class="mt-0.5 h-4 w-4 rounded accent-blue-600" />
            <span>
              <span class="font-medium text-slate-900 dark:text-white">Automated event reminders</span>
              <span class="block text-slate-500 dark:text-slate-400">Send automated email reminders to confirmed attendees 48 hours before events.</span>
            </span>
          </label>
        </div>
      </Card>
    </div>
</template>
