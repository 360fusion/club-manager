<script setup>
// Events tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
defineProps({
    form: { type: Object, required: true },
    paymentOptionsUrl: { type: [Object, Array, Function, String, Number, Boolean], required: true }
});
</script>

<template>
    <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-800/60 dark:bg-blue-950/30">
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">💳 Payment options for events and bookings</h3>
          <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Bank transfer, card, PayPal, pay later and pay on the night. Set them up and switch them on once here, and every event offers them.</p>
        </div>
        <a :href="paymentOptionsUrl('events')" class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-blue-700">Manage payment options</a>
      </div>

      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">📅 Events & Attendance Policy</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Set RSVP deadlines, guest policies, and attendance QR code expiration limits.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default RSVP Cutoff (Hours Before Event)</label>
            <input v-model.number="form.event_rsvp_cutoff_hours" type="number" min="0" max="168" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Max Guests Per Member</label>
            <input v-model.number="form.max_guests_per_member" type="number" min="0" max="20" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Attendance QR Code Expiry (Minutes)</label>
            <input v-model.number="form.qr_code_expiry_minutes" type="number" min="5" max="1440" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
          </div>
        </div>

        <div class="pt-2">
          <label class="flex items-center gap-3 cursor-pointer text-xs">
            <input type="checkbox" v-model="form.notify_event_reminders" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
            <div>
              <div class="font-bold text-slate-900 dark:text-white">Automated Event Reminders</div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">Send automated email reminders to confirmed attendees 48 hours before events.</div>
            </div>
          </label>
        </div>
      </div>
    </div>
</template>
