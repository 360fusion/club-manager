<script setup>
// Bookings tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
defineProps({
    form: { type: Object, required: true }
});
</script>

<template>
    <div class="space-y-6">
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">🚣 Equipment & Facility Bookings</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Rules for boat bay, pitch, or clubhouse facility reservations.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Advance Booking Window (Days)</label>
            <input v-model.number="form.booking_window_days" type="number" min="1" max="365" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Max Duration Per Reservation (Hours)</label>
            <input v-model.number="form.max_booking_hours" type="number" min="1" max="24" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
          </div>
        </div>

        <div class="pt-2 text-xs">
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" v-model="form.require_coach_approval_equipment" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
            <div>
              <div class="font-bold text-slate-900 dark:text-white">Require Coach Approval</div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">Require coach sign-off before high-performance racing shells or specialized gear bookings are confirmed.</div>
            </div>
          </label>
        </div>
      </div>
    </div>
</template>
