<script setup>


// A dialog of the officer roster page, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    grandRanks: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isCreatingMember: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    provincialRanks: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    quickMemberError: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    quickMemberForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitQuickMember: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
              <span>👤</span>
              <span>Add Historic Member</span>
            </h3>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Quickly add a member to assign to roster entries.</p>
          </div>
          <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg font-bold cursor-pointer">✕</button>
        </div>

        <div v-if="quickMemberError" class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-200 p-3 rounded-xl text-xs font-bold">
          {{ quickMemberError }}
        </div>

        <form @submit.prevent="submitQuickMember" class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block text-[11px] font-extrabold uppercase text-slate-700 dark:text-slate-200">First Name <span class="text-rose-500">*</span></label>
              <input
                type="text"
                v-model="quickMemberForm.first_name"
                placeholder="e.g. Thomas"
                required
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div class="space-y-1">
              <label class="block text-[11px] font-extrabold uppercase text-slate-700 dark:text-slate-200">Surname <span class="text-rose-500">*</span></label>
              <input
                type="text"
                v-model="quickMemberForm.last_name"
                placeholder="e.g. Dunckerley"
                required
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block text-[11px] font-extrabold uppercase text-slate-700 dark:text-slate-200">Member Status</label>
              <select
                v-model="quickMemberForm.membership_status"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
              >
                <option value="historical">Historical Member (Default)</option>
                <option value="active">Active Member</option>
                <option value="honorary">Honorary Member</option>
                <option value="resigned">Resigned</option>
                <option value="excluded_rule_181">Excluded (Rule 181)</option>
                <option value="deceased">Deceased</option>
              </select>
            </div>

            <div class="space-y-1">
              <label class="block text-[11px] font-extrabold uppercase text-slate-700 dark:text-slate-200">Title / Rank <span class="text-slate-400 font-normal">(Optional)</span></label>
              <select
                v-model="quickMemberForm.masonic_rank"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
              >
                <option value="Bro">Bro (Brother)</option>
                <option value="WBro">WBro (Worshipful Brother)</option>
                <option value="VWBro">VWBro (Very Worshipful Brother)</option>
                <option value="RWBro">RWBro (Right Worshipful Brother)</option>
                <option value="MWBro">MWBro (Most Worshipful Brother)</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block text-[11px] font-extrabold uppercase text-slate-700 dark:text-slate-200">Grand Rank <span class="text-slate-400 font-normal">(Optional)</span></label>
              <select v-model="quickMemberForm.grand_rank" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">None</option>
                <option v-for="rank in grandRanks" :key="rank.value" :value="rank.value">{{ rank.label }}</option>
              </select>
            </div>
            <div class="space-y-1">
              <label class="block text-[11px] font-extrabold uppercase text-slate-700 dark:text-slate-200">Provincial Rank <span class="text-slate-400 font-normal">(Optional)</span></label>
              <select v-model="quickMemberForm.provincial_rank" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">None</option>
                <option v-for="rank in provincialRanks" :key="rank.value" :value="rank.value">{{ rank.label }}</option>
              </select>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
            <button type="button" @click="$emit('close')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl cursor-pointer">Cancel</button>
            <button type="submit" :disabled="isCreatingMember" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md cursor-pointer flex items-center gap-1.5">
              <span v-if="isCreatingMember">Saving...</span>
              <span v-else>Save &amp; Select Member</span>
            </button>
          </div>
        </form>
      </div>
    </div>
</template>
