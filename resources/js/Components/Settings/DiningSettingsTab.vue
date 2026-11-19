<script setup>
// Dining tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
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
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Dining and menu RSVP policy</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Configure catering deadlines, dietary restriction prompts, and guest meals.</p>
        </template>

        <div class="space-y-6">
          <div class="space-y-2">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Meeting replies</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">Sent automatically at 9am, by email and to the app. Members can change their reply up to the meeting, but once the numbers have gone to the Secretary they can only drop dining, not add it.</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
              <Input v-model.number="form.meeting_ask_days" type="number" min="1" max="30" label="Ask for replies (days before)" :error="form.errors.meeting_ask_days" hint="To members who have not replied yet." />
              <Input v-model.number="form.meeting_deadline_days" type="number" min="1" max="14" label="Dining numbers (days before)" :error="form.errors.meeting_deadline_days" hint="Dining replies close at midnight before; a last call goes that morning." />
              <Input v-model.number="form.meeting_confirm_days" type="number" min="0" max="7" label="Reminder to confirm (days before)" :error="form.errors.meeting_confirm_days" hint="To everyone coming, with a link to change their reply or pay." />
            </div>
            <Input v-model="form.caterer_email" type="email" label="Caterer's email (optional)" :error="form.errors.caterer_email" hint="Gets the dining count, menu and dietary needs when replies close, and again if the count changes. No names of absentees or payments." class="sm:max-w-md" />
          </div>

          <div class="space-y-3">
            <label class="flex items-start gap-3 text-sm cursor-pointer">
              <input type="checkbox" v-model="form.require_dietary_allergens" class="mt-0.5 h-4 w-4 rounded accent-blue-600" />
              <span>
                <span class="font-medium text-slate-900 dark:text-white">Ask for dietary requirements</span>
                <span class="block text-slate-500 dark:text-slate-400">Require members to specify vegetarian/vegan/allergen preferences during dining checkout.</span>
              </span>
            </label>

            <label class="flex items-start gap-3 text-sm cursor-pointer">
              <input type="checkbox" v-model="form.allow_guest_meals" class="mt-0.5 h-4 w-4 rounded accent-blue-600" />
              <span>
                <span class="font-medium text-slate-900 dark:text-white">Allow guest meal RSVPs</span>
                <span class="block text-slate-500 dark:text-slate-400">Permit members to purchase additional guest dining tickets.</span>
              </span>
            </label>
          </div>
        </div>
      </Card>
    </div>
</template>
