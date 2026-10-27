<script setup>
// Branding tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
defineEmits(['openMedia']);
defineProps({
    form: { type: Object, required: true },
    isSuperAdmin: { type: [Object, Array, Function, String, Number, Boolean], required: true },
    club: { type: [Object, Array, Function, String, Number, Boolean], required: false },
    platformDefaultStorageQuotaMb: { type: [Object, Array, Function, String, Number, Boolean], required: false },
    storage: { type: [Object, Array, Function, String, Number, Boolean], required: false }
});
</script>

<template>
    <div class="space-y-6">
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">🎨 Logo & Visual Assets</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block font-bold text-slate-700 dark:text-slate-200">Logo Image URL</label>
              <button
                type="button"
                @click="$emit('openMedia')"
                class="px-2.5 py-1 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs font-bold rounded-xl border border-blue-200 dark:border-blue-800/60 transition-all flex items-center gap-1 cursor-pointer"
              >
                📁 Choose from Media Library
              </button>
            </div>
            <input v-model="form.logo_url" type="url" placeholder="https://example.com/logo.png" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
          </div>

          <div class="flex items-center gap-4 p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-blue-600 to-blue-700 text-white font-black flex items-center justify-center text-sm uppercase shadow-md flex-shrink-0">
              {{ club.name.substring(0, 2) }}
            </div>
            <div>
              <div class="font-bold text-slate-900 dark:text-white">{{ club.name }}</div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">Live Header Preview Badge</div>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">🌈 Primary Color Tokens</h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-2">Primary Accent Color</label>
            <div class="flex items-center gap-3">
              <input v-model="form.primary_color" type="color" class="w-12 h-10 rounded-xl cursor-pointer border border-slate-200 dark:border-slate-800 p-1 bg-white dark:bg-slate-900" />
              <input v-model="form.primary_color" type="text" class="w-32 px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-mono uppercase font-bold text-xs" />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-2">Admin Navigation Sidebar Theme</label>
            <select v-model="form.sidebar_theme" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold">
              <option value="dark_slate">🌑 Dark Slate (#1e293b)</option>
              <option value="deep_navy">🌌 Deep Navy (#0f172a)</option>
              <option value="emerald_forest">🌲 Emerald Forest (#064e3b)</option>
              <option value="royal_indigo">👑 Royal Indigo (#312e81)</option>
            </select>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">💾 Storage</h2>

        <div v-if="storage.quota_bytes !== null" class="space-y-1.5 text-xs">
          <div class="flex items-center justify-between font-bold text-slate-600 dark:text-slate-300">
            <span>{{ storage.used_human }} of {{ storage.quota_human }} used</span>
            <span>{{ storage.percent }}%</span>
          </div>
          <div class="w-full h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
            <div
              class="h-full rounded-full transition-all"
              :class="storage.percent >= 90 ? 'bg-rose-500' : storage.percent >= 70 ? 'bg-amber-500' : 'bg-blue-500'"
              :style="{ width: Math.min(100, storage.percent || 0) + '%' }"
            ></div>
          </div>
        </div>
        <p v-else class="text-xs font-bold text-slate-600 dark:text-slate-300">{{ storage.used_human }} used · unlimited quota</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-2">Storage Quota (MB)</label>
            <input
              v-model.number="form.storage_quota_mb"
              type="number"
              min="0"
              :disabled="!isSuperAdmin"
              :placeholder="`Platform default (${platformDefaultStorageQuotaMb ?? 'unlimited'} MB)`"
              class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold disabled:opacity-60"
            />
            <p v-if="!isSuperAdmin" class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Only platform admins can change a club's storage quota. Leave a support request if this club needs more room.</p>
            <p v-else class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Leave blank to use the platform default ({{ platformDefaultStorageQuotaMb ?? 'unlimited' }} MB).</p>
          </div>
        </div>
      </div>

    </div>
</template>
