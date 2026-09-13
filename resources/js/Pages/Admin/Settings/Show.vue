<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: {
    type: Object,
    required: true,
  },
  settings: {
    type: Object,
    default: () => ({}),
  },
  allModules: {
    type: Array,
    default: () => [],
  },
  members: {
    type: Array,
    default: () => [],
  },
  availableRoles: {
    type: Array,
    default: () => [],
  },
});

const activeTab = ref('general');

// Primary Settings Form
const form = useForm({
  name: props.club.name || '',
  tagline: props.settings.tagline || '',
  logo_url: props.club.logo_url || '',
  primary_color: props.settings.primary_color || '#0369a1',
  sidebar_theme: props.settings.sidebar_theme || 'dark_slate',
  currency: props.settings.currency || 'GBP',
  timezone: props.settings.timezone || 'Europe/London',
  contact_email: props.settings.contact_email || '',
  phone: props.settings.phone || '',
  address: props.settings.address || '',
  social_facebook: props.settings.social_facebook || '',
  social_instagram: props.settings.social_instagram || '',
  social_twitter: props.settings.social_twitter || '',
  registration_mode: props.settings.registration_mode || 'open',
  member_prefix: props.settings.member_prefix || '',
  default_role: props.settings.default_role || 'member',
  custom_domain: props.club.custom_domain || '',
  enabled_modules: props.settings.enabled_modules || [],
  permission_matrix: props.settings.permission_matrix || {},
  notify_event_reminders: props.settings.notify_event_reminders ?? true,
  notify_dues_overdue: props.settings.notify_dues_overdue ?? true,
  email_from_name: props.settings.email_from_name || props.club.name,
});

const submitSettings = () => {
  form.put(route('admin.settings.update', { clubSlug: props.club.slug }), {
    preserveScroll: true,
  });
};

const toggleModule = (code) => {
  const idx = form.enabled_modules.indexOf(code);
  if (idx > -1) {
    form.enabled_modules.splice(idx, 1);
  } else {
    form.enabled_modules.push(code);
  }
};

const togglePermissionRole = (permKey, roleCode) => {
  if (!form.permission_matrix[permKey]) return;
  const roles = form.permission_matrix[permKey].roles || [];
  const idx = roles.indexOf(roleCode);
  if (idx > -1) {
    roles.splice(idx, 1);
  } else {
    roles.push(roleCode);
  }
};

const updateMemberRole = (userId, newRole) => {
  router.post(
    route('admin.users.role.update', { clubSlug: props.club.slug, userId }),
    { role: newRole },
    { preserveScroll: true }
  );
};
</script>

<template>
  <AdminLayout :title="`${club.name} Settings`" :club="club" active-tab="profile">
    <Head :title="`${club.name} Settings`" />

    <div class="space-y-6 max-w-6xl mx-auto">
      
      <!-- Top Action Bar & Header -->
      <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Club Configuration Settings</h1>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-200">
              {{ club.slug }}
            </span>
          </div>
          <p class="text-xs sm:text-sm text-slate-500 mt-1">Manage organization details, user admin roles, feature modules, branding, and custom domains.</p>
        </div>

        <button
          @click="submitSettings"
          :disabled="form.processing"
          class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20 transition-all flex items-center gap-2 self-start sm:self-auto"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>Save Changes</span>
        </button>
      </div>

      <!-- Horizontal Navigation Tabs Bar -->
      <div class="bg-white p-1.5 rounded-2xl shadow-sm border border-slate-200/80 flex overflow-x-auto gap-1 text-xs font-bold scrollbar-none">
        <button
          @click="activeTab = 'general'"
          :class="[
            'px-4 py-3 rounded-xl transition-all flex items-center gap-2 flex-shrink-0',
            activeTab === 'general' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
          ]"
        >
          <span>🏢</span>
          <span>General</span>
        </button>

        <button
          @click="activeTab = 'branding'"
          :class="[
            'px-4 py-3 rounded-xl transition-all flex items-center gap-2 flex-shrink-0',
            activeTab === 'branding' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
          ]"
        >
          <span>🎨</span>
          <span>Branding & Theme</span>
        </button>

        <button
          @click="activeTab = 'roles'"
          :class="[
            'px-4 py-3 rounded-xl transition-all flex items-center gap-2 flex-shrink-0',
            activeTab === 'roles' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
          ]"
        >
          <span>🛡️</span>
          <span>Roles & Permissions</span>
        </button>

        <button
          @click="activeTab = 'modules'"
          :class="[
            'px-4 py-3 rounded-xl transition-all flex items-center gap-2 flex-shrink-0',
            activeTab === 'modules' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
          ]"
        >
          <span>⚡</span>
          <span>Feature Modules</span>
        </button>

        <button
          @click="activeTab = 'domain'"
          :class="[
            'px-4 py-3 rounded-xl transition-all flex items-center gap-2 flex-shrink-0',
            activeTab === 'domain' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
          ]"
        >
          <span>🌐</span>
          <span>Custom Domain</span>
        </button>

        <button
          @click="activeTab = 'notifications'"
          :class="[
            'px-4 py-3 rounded-xl transition-all flex items-center gap-2 flex-shrink-0',
            activeTab === 'notifications' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
          ]"
        >
          <span>🔔</span>
          <span>Notifications</span>
        </button>
      </div>

      <!-- TAB 1: GENERAL SETTINGS -->
      <div v-if="activeTab === 'general'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">🏢 Organization Profile</h2>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Club / Organization Name</label>
              <input v-model="form.name" type="text" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Tagline or Motto</label>
              <input v-model="form.tagline" type="text" placeholder="e.g. Excellence on the Isis & Thames" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Contact Email</label>
              <input v-model="form.contact_email" type="email" placeholder="admin@club.org" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Support Phone Number</label>
              <input v-model="form.phone" type="text" placeholder="+44 20 7946 0912" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div class="sm:col-span-2">
              <label class="block font-bold text-slate-700 mb-1">Club Headquarters / Address</label>
              <input v-model="form.address" type="text" placeholder="100 Boathouse Way, Oxford, UK" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">⚙️ Onboarding & Access Control</h2>
          
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Registration Mode</label>
              <select v-model="form.registration_mode" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold">
                <option value="open">🔓 Public Open Join</option>
                <option value="invite_only">🔒 Invite-Only / Admin Approval</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Member Number Prefix</label>
              <input v-model="form.member_prefix" type="text" placeholder="OUBC-" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Default New Member Role</label>
              <select v-model="form.default_role" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold">
                <option value="member">Member</option>
                <option value="coach">Coach</option>
                <option value="treasurer">Treasurer</option>
                <option value="admin">Admin</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: BRANDING & THEME -->
      <div v-if="activeTab === 'branding'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">🎨 Logo & Visual Assets</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Logo Image URL</label>
              <input v-model="form.logo_url" type="url" placeholder="https://example.com/logo.png" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>

            <div class="flex items-center gap-4 p-4 bg-slate-50 border border-slate-200 rounded-2xl">
              <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-500 to-sky-400 text-white font-black flex items-center justify-center text-sm uppercase shadow-md flex-shrink-0">
                {{ club.name.substring(0, 2) }}
              </div>
              <div>
                <div class="font-bold text-slate-900">{{ club.name }}</div>
                <div class="text-[11px] text-slate-500">Live Header Preview Badge</div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">🌈 Primary Color Tokens</h2>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-2">Primary Accent Color</label>
              <div class="flex items-center gap-3">
                <input v-model="form.primary_color" type="color" class="w-12 h-10 rounded-xl cursor-pointer border border-slate-200 p-1 bg-white" />
                <input v-model="form.primary_color" type="text" class="w-32 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-mono uppercase font-bold text-xs" />
              </div>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-2">Admin Navigation Sidebar Theme</label>
              <select v-model="form.sidebar_theme" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold">
                <option value="dark_slate">🌑 Dark Slate (#1e293b)</option>
                <option value="deep_navy">🌌 Deep Navy (#0f172a)</option>
                <option value="emerald_forest">🌲 Emerald Forest (#064e3b)</option>
                <option value="royal_indigo">👑 Royal Indigo (#312e81)</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 3: ROLES & PERMISSIONS -->
      <div v-if="activeTab === 'roles'" class="space-y-6">
        
        <!-- Role Permission Matrix Grid -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">🛡️ Granular Admin Permission Matrix</h2>
            <p class="text-xs text-slate-500 mt-1">Define which administrative actions each club role is allowed to perform.</p>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
                  <th class="py-3 px-4">Administrative Action</th>
                  <th v-for="r in availableRoles" :key="r.code" class="py-3 px-3 text-center">
                    <span :class="['px-2 py-0.5 rounded text-[10px] font-extrabold border', r.badge]">
                      {{ r.name }}
                    </span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-xs">
                <tr v-for="(perm, permKey) in form.permission_matrix" :key="permKey" class="hover:bg-slate-50/50">
                  <td class="py-3.5 px-4">
                    <div class="font-bold text-slate-900">{{ perm.label }}</div>
                    <div class="text-[11px] text-slate-400">{{ perm.description }}</div>
                  </td>

                  <td v-for="r in availableRoles" :key="r.code" class="py-3.5 px-3 text-center">
                    <input
                      type="checkbox"
                      :checked="perm.roles && perm.roles.includes(r.code)"
                      @change="togglePermissionRole(permKey, r.code)"
                      :disabled="r.code === 'owner'"
                      class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 cursor-pointer disabled:opacity-50"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Role Directory & Quick Assignment -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">👥 Member Administrative Role Directory</h2>
            <p class="text-xs text-slate-500 mt-1">Assign or change role privileges for members in this club roster.</p>
          </div>

          <div class="divide-y divide-slate-100">
            <div v-for="m in members" :key="m.id" class="py-3 flex items-center justify-between gap-4 text-xs">
              <div class="overflow-hidden">
                <div class="font-bold text-slate-900 truncate">{{ m.name }}</div>
                <div class="text-[11px] text-slate-400 truncate">{{ m.email }} • <span class="font-mono">{{ m.member_number }}</span></div>
              </div>

              <select
                :value="m.role"
                @change="updateMemberRole(m.id, $event.target.value)"
                class="px-3 py-1.5 font-bold rounded-xl border border-slate-200 bg-slate-50 text-xs cursor-pointer outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="member">Member</option>
                <option value="coach">Coach</option>
                <option value="treasurer">Treasurer</option>
                <option value="admin">Admin</option>
                <option value="owner">Owner</option>
              </select>
            </div>
          </div>
        </div>

      </div>

      <!-- TAB 4: FEATURE MODULES -->
      <div v-if="activeTab === 'modules'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">⚡ Active Club Feature Modules</h2>
            <p class="text-xs text-slate-500 mt-1">Toggle interactive tools enabled for administrators and members in this club workspace.</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="mod in allModules"
              :key="mod.code"
              @click="toggleModule(mod.code)"
              :class="[
                'p-4 rounded-2xl border transition-all cursor-pointer flex items-start justify-between gap-4',
                form.enabled_modules.includes(mod.code)
                  ? 'bg-indigo-50/50 border-indigo-300 ring-2 ring-indigo-500/20'
                  : 'bg-white border-slate-200 hover:border-slate-300 opacity-60'
              ]"
            >
              <div class="flex items-start gap-3">
                <span class="text-2xl">{{ mod.icon }}</span>
                <div>
                  <div class="font-bold text-slate-900 text-xs sm:text-sm">{{ mod.name }}</div>
                  <div class="text-[11px] text-slate-500 mt-0.5">{{ mod.description }}</div>
                </div>
              </div>

              <div :class="['w-10 h-6 rounded-full transition-colors flex items-center p-1', form.enabled_modules.includes(mod.code) ? 'bg-indigo-600' : 'bg-slate-300']">
                <div :class="['w-4 h-4 rounded-full bg-white shadow-md transform transition-transform', form.enabled_modules.includes(mod.code) ? 'translate-x-4' : 'translate-x-0']"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 5: CUSTOM DOMAIN -->
      <div v-if="activeTab === 'domain'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">🌐 Custom Domain Setup</h2>
            <p class="text-xs text-slate-500 mt-1">Connect your custom branded domain (e.g. <code class="bg-slate-100 text-slate-700 px-1 py-0.5 rounded">members.oxfordboating.org</code>) to this club portal.</p>
          </div>

          <div class="space-y-4 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Custom Domain Name</label>
              <input v-model="form.custom_domain" type="text" placeholder="members.oxfordboating.org" class="w-full sm:w-96 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold" />
            </div>

            <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl space-y-2 text-amber-900">
              <div class="font-bold text-xs">DNS Configuration Instructions:</div>
              <p class="text-[11px]">Add an **A Record** or **CNAME** at your DNS provider pointing your subdomain to this server's IP address.</p>
              <div class="font-mono text-[11px] bg-white p-2.5 rounded-xl border border-amber-200 font-bold">
                Host: members • Type: CNAME • Value: manager.360fusionhosting.co.uk
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 6: NOTIFICATIONS -->
      <div v-if="activeTab === 'notifications'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">🔔 Email Broadcasts & Automatic Reminders</h2>
            <p class="text-xs text-slate-500 mt-1">Configure automated notifications sent to club members.</p>
          </div>

          <div class="space-y-4 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Email Sender From Name</label>
              <input v-model="form.email_from_name" type="text" class="w-full sm:w-96 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
            </div>

            <div class="space-y-3 pt-2">
              <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" v-model="form.notify_event_reminders" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500" />
                <div>
                  <div class="font-bold text-slate-900">Automated Event Reminders</div>
                  <div class="text-[11px] text-slate-500">Send automated email reminders to attendees 48 hours before an event.</div>
                </div>
              </label>

              <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" v-model="form.notify_dues_overdue" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500" />
                <div>
                  <div class="font-bold text-slate-900">Automated Overdue Dues Notices</div>
                  <div class="text-[11px] text-slate-500">Automatically notify members when membership dues invoices are overdue.</div>
                </div>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Save Action Bar -->
      <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
        <button
          @click="submitSettings"
          :disabled="form.processing"
          class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/20 transition-all flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>Save All Settings</span>
        </button>
      </div>

    </div>
  </AdminLayout>
</template>
