<script setup>
// The settings page's section picker: a dropdown on phones and a grouped side navigation on wider screens.
import Card from '@/Components/Ui/Card.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const activeTab = defineModel({ type: String, required: true });

// The settings sections, grouped as the side navigation (and the phone dropdown) shows them.
const settingsNav = [
  { label: 'Workspace', items: [
    { key: 'general', label: 'General', icon: 'building' },
    { key: 'positions', label: 'Club positions', icon: 'award' },
    { key: 'ranks', label: 'Grand and Provincial ranks', icon: 'star' },
    { key: 'officers', label: 'Officers roster', icon: 'users' },
    { key: 'branding', label: 'Branding and styling', icon: 'palette' },
    { key: 'roles', label: 'Roles and permissions', icon: 'shield' },
    { key: 'modules', label: 'Feature modules', icon: 'lightning' },
  ] },
  { label: 'Operations', items: [
    { key: 'accounting', label: 'Accounting', icon: 'chart' },
    { key: 'subscriptions', label: 'Subscriptions and dues', icon: 'cash' },
    { key: 'payments', label: 'Payment gateways', icon: 'bank' },
    { key: 'events', label: 'Events and check-ins', icon: 'calendar' },
    { key: 'dining', label: 'Dining and RSVPs', icon: 'clipboard' },
    { key: 'communications', label: 'Communications', icon: 'mail' },
    { key: 'news-tags', label: 'News tags', icon: 'tag' },
  ] },
  { label: 'Module policies', items: [
    { key: 'bookings', label: 'Equipment bookings', icon: 'clock' },
  ] },
];
</script>

<template>
        <div class="lg:col-span-1 space-y-4">
          <!-- Mobile Category Dropdown Selector -->
          <Card padding="sm" class="lg:hidden">
            <div class="space-y-1.5">
              <label for="settings-section" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Settings section</label>
              <select id="settings-section" v-model="activeTab" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors cursor-pointer focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <optgroup v-for="group in settingsNav" :key="group.label" :label="group.label">
                  <option v-for="item in group.items" :key="item.key" :value="item.key">{{ item.label }}</option>
                </optgroup>
              </select>
            </div>
          </Card>

          <!-- Desktop Vertical Sidebar Navigation Card -->
          <nav aria-label="Settings sections" class="hidden lg:block bg-white dark:bg-slate-900 p-3 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-5 sticky top-6">
            <div v-for="group in settingsNav" :key="group.label">
              <div class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">{{ group.label }}</div>
              <div class="space-y-0.5">
                <button
                  v-for="item in group.items"
                  :key="item.key"
                  type="button"
                  @click="activeTab = item.key"
                  :aria-current="activeTab === item.key ? 'page' : undefined"
                  :class="[
                    'w-full px-3 py-2 rounded-xl transition-colors flex items-center gap-2.5 cursor-pointer text-left text-sm font-medium',
                    activeTab === item.key ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <UiIcon :name="item.icon" />
                  {{ item.label }}
                </button>
              </div>
            </div>
          </nav>
        </div>
</template>
