<script setup>
// Dining tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
defineProps({
    form: { type: Object, required: true }
});
</script>

<template>
    <div class="space-y-6">
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">🍽️ Dining & Menu RSVP Policy</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configure catering deadlines, dietary restriction prompts, and guest meals.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Catering RSVP Cutoff (Hours Prior)</label>
            <input v-model.number="form.dining_rsvp_cutoff_hours" type="number" min="0" max="168" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            <p class="text-[11px] text-slate-400 mt-1">Locks meal selections for kitchen headcount preparation.</p>
          </div>
        </div>

        <div class="space-y-3 pt-2 text-xs">
          <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" v-model="form.require_dietary_allergens" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
            <div>
              <div class="font-bold text-slate-900 dark:text-white">Prompt for Dietary Requirements</div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">Require members to specify vegetarian/vegan/allergen preferences during dining checkout.</div>
            </div>
          </label>

          <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" v-model="form.allow_guest_meals" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
            <div>
              <div class="font-bold text-slate-900 dark:text-white">Allow Guest Meal RSVPs</div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">Permit members to purchase additional guest dining tickets.</div>
            </div>
          </label>
        </div>
      </div>
    </div>
</template>
