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
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <Input v-model.number="form.dining_rsvp_cutoff_hours" type="number" min="0" max="168" label="Catering RSVP cutoff (hours before)" hint="Locks meal selections for kitchen headcount preparation." />
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
