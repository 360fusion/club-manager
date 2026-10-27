<script setup>
// Branding tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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
      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Logo</h2>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-2">
            <Input v-model="form.logo_url" type="url" placeholder="https://example.com/logo.png" label="Logo image URL" />
            <Button variant="secondary" size="sm" @click="$emit('openMedia')">
              <template #icon><UiIcon name="folder" :size="14" /></template>
              Choose from the media library
            </Button>
          </div>

          <div class="flex items-center gap-4 p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl">
            <div class="w-12 h-12 rounded-xl bg-blue-600 text-white font-semibold flex items-center justify-center text-sm uppercase flex-shrink-0">
              {{ club.name.substring(0, 2) }}
            </div>
            <div>
              <div class="text-sm font-medium text-slate-900 dark:text-white">{{ club.name }}</div>
              <div class="text-xs text-slate-500 dark:text-slate-400">Header preview</div>
            </div>
          </div>
        </div>
      </Card>

      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Colours</h2>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
          <div class="space-y-1.5">
            <label for="branding-primary-colour" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Primary accent colour</label>
            <div class="flex items-center gap-3">
              <input id="branding-primary-colour" v-model="form.primary_color" type="color" class="w-12 h-10 rounded-xl cursor-pointer border border-slate-300 dark:border-slate-700 p-1 bg-white dark:bg-slate-950" />
              <input v-model="form.primary_color" type="text" aria-label="Primary accent colour code" class="w-32 rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono text-sm uppercase text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white" />
            </div>
          </div>

          <Select
            v-model="form.sidebar_theme"
            label="Admin sidebar theme"
            :options="[
              { value: 'dark_slate', label: 'Dark slate (#1e293b)' },
              { value: 'deep_navy', label: 'Deep navy (#0f172a)' },
              { value: 'emerald_forest', label: 'Emerald forest (#064e3b)' },
              { value: 'royal_indigo', label: 'Royal indigo (#312e81)' },
            ]"
          />
        </div>
      </Card>

      <Card>
        <template #header>
          <h2 class="text-base font-semibold text-slate-900 dark:text-white">Storage</h2>
        </template>

        <div class="space-y-6">
          <div v-if="storage.quota_bytes !== null" class="space-y-1.5 text-sm">
            <div class="flex items-center justify-between font-medium text-slate-600 dark:text-slate-300">
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
          <p v-else class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ storage.used_human }} used · unlimited quota</p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <Input
              v-model.number="form.storage_quota_mb"
              type="number"
              min="0"
              :disabled="!isSuperAdmin"
              :placeholder="`Platform default (${platformDefaultStorageQuotaMb ?? 'unlimited'} MB)`"
              label="Storage quota (MB)"
              :hint="isSuperAdmin
                ? `Leave blank to use the platform default (${platformDefaultStorageQuotaMb ?? 'unlimited'} MB).`
                : 'Only platform admins can change a club\'s storage quota. Leave a support request if this club needs more room.'"
            />
          </div>
        </div>
      </Card>

    </div>
</template>
