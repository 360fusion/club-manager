<script setup>
// Officers tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
defineProps({
    form: { type: Object, required: true },
    club: { type: [Object, Array, Function, String, Number, Boolean], required: false }
});
</script>

<template>
    <div class="space-y-6">
      
      <!-- Provincial Executive Card -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">🏛️ Provincial Executive & Rulers</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configure annual Provincial Grand Lodge rulers and executive officers. These settings automatically populate new meeting summonses.</p>
        </div>

        <div class="space-y-4 text-xs">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Provincial Title / Name</label>
              <input v-model="form.provincial_name" type="text" placeholder="Provincial Grand Lodge of Durham" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Provincial Grand Master</label>
              <input v-model="form.provincial_grand_master" type="text" placeholder="R WBro John David Watts" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Deputy Provincial Grand Master</label>
              <input v-model="form.deputy_provincial_grand_master" type="text" placeholder="WBro Andrew Peter Faul Foster PSGD" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Assistant Provincial Grand Masters (One per line)</label>
              <textarea v-model="form.assistant_provincial_grand_masters" rows="4" placeholder="WBro Dr. Rakesh Bhalla PSGD..." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono text-xs"></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Annual Officers Roster Managed Card -->
      <div class="bg-blue-50/60 dark:bg-blue-950/60 rounded-2xl p-6 border border-blue-200/80 dark:border-blue-800/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-blue-600 dark:text-blue-400">👔 Lodge Governance</span>
            <span class="text-xs px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 font-extrabold text-[10px]">Active Domain</span>
          </div>
          <h3 class="text-base font-black text-slate-900 dark:text-white">Annual Officer Rosters &amp; History</h3>
          <p class="text-xs text-slate-600 dark:text-slate-300">
            Lodge Officer rosters, multi-year installation dates, progressive ladder seats, and committee approvals are now managed centrally in the Member Directory.
          </p>
        </div>
        <a
          :href="route('admin.officers.index', { clubSlug: club.slug })"
          class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md transition-all cursor-pointer flex-shrink-0 flex items-center gap-1.5"
        >
          <span>Manage officers roster</span>
          <span>→</span>
        </a>
      </div>

    </div>
</template>
