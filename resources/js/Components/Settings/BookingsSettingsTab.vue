<script setup>
// Bookings tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';

defineProps({
    form: { type: Object, required: true }
});
</script>

<template>
    <div class="space-y-6">
      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Equipment and facility bookings</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Rules for boat bay, pitch, or clubhouse facility reservations.</p>
        </template>

        <div class="space-y-6">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <Input v-model.number="form.booking_window_days" type="number" min="1" max="365" label="Advance booking window (days)" />
            <Input v-model.number="form.max_booking_hours" type="number" min="1" max="24" label="Longest reservation (hours)" />
          </div>

          <label class="flex items-start gap-3 text-sm cursor-pointer">
            <input type="checkbox" v-model="form.require_coach_approval_equipment" class="mt-0.5 h-4 w-4 rounded accent-blue-600" />
            <span>
              <span class="font-medium text-slate-900 dark:text-white">Require coach approval</span>
              <span class="block text-slate-500 dark:text-slate-400">Require coach sign-off before high-performance racing shells or specialized gear bookings are confirmed.</span>
            </span>
          </label>
        </div>
      </Card>
    </div>
</template>
