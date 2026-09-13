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
  enable_member_ranks: props.settings.enable_member_ranks ?? true,
  member_ranks: props.settings.member_ranks || ['Novice', 'Intermediate', 'Senior', 'Captain', 'Coxswain', 'Veteran'],
  custom_domain: props.club.custom_domain || '',
  enabled_modules: props.settings.enabled_modules || [],
  permission_matrix: props.settings.permission_matrix || {},

  // Subscriptions & Dues
  dues_grace_period_days: props.settings.dues_grace_period_days ?? 14,
  auto_invoice_days_before: props.settings.auto_invoice_days_before ?? 7,
  tax_registration_number: props.settings.tax_registration_number || '',
  receipt_footer_notes: props.settings.receipt_footer_notes || '',

  // Events & Check-Ins
  event_rsvp_cutoff_hours: props.settings.event_rsvp_cutoff_hours ?? 24,
  max_guests_per_member: props.settings.max_guests_per_member ?? 2,
  qr_code_expiry_minutes: props.settings.qr_code_expiry_minutes ?? 60,
  notify_event_reminders: props.settings.notify_event_reminders ?? true,

  // Dining & Catering
  dining_rsvp_cutoff_hours: props.settings.dining_rsvp_cutoff_hours ?? 48,
  require_dietary_allergens: props.settings.require_dietary_allergens ?? true,
  allow_guest_meals: props.settings.allow_guest_meals ?? true,

  // Newsletters & Communications
  email_from_name: props.settings.email_from_name || props.club.name,
  email_reply_to: props.settings.email_reply_to || '',
  email_footer_address: props.settings.email_footer_address || '',
  notify_dues_overdue: props.settings.notify_dues_overdue ?? true,

  // Website Builder & SEO
  seo_title_suffix: props.settings.seo_title_suffix || `| ${props.club.name}`,
  seo_meta_description: props.settings.seo_meta_description || '',

  // Equipment & Pitch Bookings
  booking_window_days: props.settings.booking_window_days ?? 14,
  max_booking_hours: props.settings.max_booking_hours ?? 4,
  require_coach_approval_equipment: props.settings.require_coach_approval_equipment ?? true,

  // Athletic Performance & Erg Log
  leaderboard_visibility: props.settings.leaderboard_visibility || 'public',
  default_distance_unit: props.settings.default_distance_unit || 'meters',
  require_score_verification: props.settings.require_score_verification ?? false,
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

const newRankInput = ref('');

const addRank = () => {
  const val = newRankInput.value.trim();
  if (val && !form.member_ranks.includes(val)) {
    form.member_ranks.push(val);
    newRankInput.value = '';
  }
};

const removeRank = (index) => {
  form.member_ranks.splice(index, 1);
};

const updateMemberRank = (userId, newRank) => {
  router.post(
    route('admin.users.rank.update', { clubSlug: props.club.slug, userId }),
    { rank: newRank },
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
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Club Feature & Configuration Settings</h1>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-200">
              {{ club.slug }}
            </span>
          </div>
          <p class="text-xs sm:text-sm text-slate-500 mt-1">Configure policies, feature modules, branding, automated notifications, and permission matrices for your club.</p>
        </div>

        <button
          @click="submitSettings"
          :disabled="form.processing"
          class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20 transition-all flex items-center gap-2 self-start sm:self-auto cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>Save Changes</span>
        </button>
      </div>

      <!-- 2-Column Responsive Settings Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        
        <!-- Left Settings Sidebar Navigation -->
        <div class="lg:col-span-1 space-y-4">
          <!-- Mobile Category Dropdown Selector -->
          <div class="lg:hidden bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm space-y-2">
            <label class="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">Settings Category</label>
            <select v-model="activeTab" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer">
              <optgroup label="Workspace & Security">
                <option value="general">🏢 General Profile & Access</option>
                <option value="branding">🎨 Branding & Custom Domain</option>
                <option value="roles">🛡️ Roles & Permissions</option>
                <option value="modules">⚡ Active Feature Modules</option>
              </optgroup>
              <optgroup label="Operations & Finance">
                <option value="subscriptions">💳 Subscriptions & Dues</option>
                <option value="events">📅 Events & Check-Ins</option>
                <option value="dining">🍽️ Dining & Catering RSVPs</option>
                <option value="communications">✉️ Communications & Emails</option>
              </optgroup>
              <optgroup label="Module Policies">
                <option value="website">🌐 Website Builder & SEO</option>
                <option value="bookings">🚣 Pitch & Equipment Bookings</option>
                <option value="performance">📊 Athletic & Erg Logs</option>
              </optgroup>
            </select>
          </div>

          <!-- Desktop Vertical Sidebar Navigation Card -->
          <div class="hidden lg:block bg-white p-3.5 rounded-3xl border border-slate-200/80 shadow-sm space-y-5 sticky top-6">
            
            <!-- Group 1: Workspace -->
            <div>
              <div class="px-3 text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Workspace</div>
              <div class="space-y-0.5 text-xs font-bold">
                <button
                  @click="activeTab = 'general'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'general' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🏢</span> General</span>
                </button>

                <button
                  @click="activeTab = 'branding'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'branding' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🎨</span> Branding & Domain</span>
                </button>

                <button
                  @click="activeTab = 'roles'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'roles' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🛡️</span> Roles & Permissions</span>
                </button>

                <button
                  @click="activeTab = 'modules'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'modules' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>⚡</span> Feature Modules</span>
                </button>
              </div>
            </div>

            <!-- Group 2: Operations -->
            <div>
              <div class="px-3 text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Operations</div>
              <div class="space-y-0.5 text-xs font-bold">
                <button
                  @click="activeTab = 'subscriptions'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'subscriptions' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>💳</span> Subscriptions & Dues</span>
                </button>

                <button
                  @click="activeTab = 'events'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'events' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>📅</span> Events & Check-Ins</span>
                </button>

                <button
                  @click="activeTab = 'dining'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'dining' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🍽️</span> Dining & RSVPs</span>
                </button>

                <button
                  @click="activeTab = 'communications'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'communications' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>✉️</span> Communications</span>
                </button>
              </div>
            </div>

            <!-- Group 3: Module Policies -->
            <div>
              <div class="px-3 text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Module Policies</div>
              <div class="space-y-0.5 text-xs font-bold">
                <button
                  @click="activeTab = 'website'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'website' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🌐</span> Website Builder</span>
                </button>

                <button
                  @click="activeTab = 'bookings'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'bookings' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🚣</span> Equipment Bookings</span>
                </button>

                <button
                  @click="activeTab = 'performance'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'performance' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>📊</span> Performance Logs</span>
                </button>
              </div>
            </div>

          </div>
        </div>

        <!-- Right Content Panel -->
        <div class="lg:col-span-3 space-y-6">

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

          <!-- Member Ranks Settings -->
          <div class="pt-6 border-t border-slate-100 space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-sm font-bold text-slate-900">🎖️ Enable Member Ranks & Skill Levels</h3>
                <p class="text-xs text-slate-500">Enable customized member ranks (e.g. Novice, Captain, Veteran) to filter and select members across the platform.</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.enable_member_ranks" class="sr-only peer" />
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>

            <div v-if="form.enable_member_ranks" class="space-y-3 pt-2">
              <label class="block text-xs font-bold text-slate-700">Configured Ranks</label>
              <div class="flex flex-wrap gap-2 mb-2">
                <span v-for="(rank, idx) in form.member_ranks" :key="idx" class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-xl text-xs font-bold">
                  🏅 {{ rank }}
                  <button type="button" @click="removeRank(idx)" class="text-indigo-400 hover:text-indigo-900 font-extrabold ml-1">&times;</button>
                </span>
              </div>
              <div class="flex gap-2 max-w-md">
                <input v-model="newRankInput" @keydown.enter.prevent="addRank" type="text" placeholder="Add a new rank (e.g. Master, Coxswain)..." class="flex-1 px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
                <button type="button" @click="addRank" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition">Add Rank</button>
              </div>
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
              <p class="text-[11px]">Add a CNAME record at your DNS provider pointing your subdomain to this server's target hostname.</p>
              <div class="font-mono text-[11px] bg-white p-2.5 rounded-xl border border-amber-200 font-bold">
                Host: members • Type: CNAME • Target: manager.360fusionhosting.co.uk
              </div>
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

              <div class="flex items-center gap-2">
                <select
                  v-if="form.enable_member_ranks"
                  :value="m.rank"
                  @change="updateMemberRank(m.id, $event.target.value)"
                  class="px-3 py-1.5 font-bold rounded-xl border border-indigo-200 bg-indigo-50/50 text-indigo-700 text-xs cursor-pointer outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="">No Rank Assigned</option>
                  <option v-for="r in form.member_ranks" :key="r" :value="r">
                    🏅 {{ r }}
                  </option>
                </select>

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

      </div>

      <!-- TAB 4: SUBSCRIPTIONS & DUES -->
      <div v-if="activeTab === 'subscriptions'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">💳 Subscriptions & Billing Settings</h2>
            <p class="text-xs text-slate-500 mt-1">Configure grace periods, automated dues invoicing, and payment receipt footers.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Overdue Grace Period (Days)</label>
              <input v-model.number="form.dues_grace_period_days" type="number" min="0" max="180" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
              <p class="text-[11px] text-slate-400 mt-1">Days after due date before member status defaults to overdue.</p>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Auto-Invoice Lead Time (Days Before Cycle)</label>
              <input v-model.number="form.auto_invoice_days_before" type="number" min="0" max="90" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
              <p class="text-[11px] text-slate-400 mt-1">Days prior to membership renewal to dispatch automated invoices.</p>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">VAT / Tax Registration Number</label>
              <input v-model="form.tax_registration_number" type="text" placeholder="GB 987 6543 21" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono" />
            </div>

            <div class="sm:col-span-2">
              <label class="block font-bold text-slate-700 mb-1">Receipt & Invoice Footer Notes</label>
              <textarea v-model="form.receipt_footer_notes" rows="3" placeholder="Thank you for supporting our club." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 5: EVENTS & CHECK-INS -->
      <div v-if="activeTab === 'events'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">📅 Events & Attendance Policy</h2>
            <p class="text-xs text-slate-500 mt-1">Set RSVP deadlines, guest policies, and attendance QR code expiration limits.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Default RSVP Cutoff (Hours Before Event)</label>
              <input v-model.number="form.event_rsvp_cutoff_hours" type="number" min="0" max="168" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Max Guests Per Member</label>
              <input v-model.number="form.max_guests_per_member" type="number" min="0" max="20" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Attendance QR Code Expiry (Minutes)</label>
              <input v-model.number="form.qr_code_expiry_minutes" type="number" min="5" max="1440" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
            </div>
          </div>

          <div class="pt-2">
            <label class="flex items-center gap-3 cursor-pointer text-xs">
              <input type="checkbox" v-model="form.notify_event_reminders" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500" />
              <div>
                <div class="font-bold text-slate-900">Automated Event Reminders</div>
                <div class="text-[11px] text-slate-500">Send automated email reminders to confirmed attendees 48 hours before events.</div>
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- TAB 6: DINING & RSVPs -->
      <div v-if="activeTab === 'dining'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">🍽️ Dining & Menu RSVP Policy</h2>
            <p class="text-xs text-slate-500 mt-1">Configure catering deadlines, dietary restriction prompts, and guest meals.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Catering RSVP Cutoff (Hours Prior)</label>
              <input v-model.number="form.dining_rsvp_cutoff_hours" type="number" min="0" max="168" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
              <p class="text-[11px] text-slate-400 mt-1">Locks meal selections for kitchen headcount preparation.</p>
            </div>
          </div>

          <div class="space-y-3 pt-2 text-xs">
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="form.require_dietary_allergens" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500" />
              <div>
                <div class="font-bold text-slate-900">Prompt for Dietary Requirements</div>
                <div class="text-[11px] text-slate-500">Require members to specify vegetarian/vegan/allergen preferences during dining checkout.</div>
              </div>
            </label>

            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="form.allow_guest_meals" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500" />
              <div>
                <div class="font-bold text-slate-900">Allow Guest Meal RSVPs</div>
                <div class="text-[11px] text-slate-500">Permit members to purchase additional guest dining tickets.</div>
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- TAB 7: COMMUNICATIONS -->
      <div v-if="activeTab === 'communications'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">✉️ Email Broadcasts & Sender Settings</h2>
            <p class="text-xs text-slate-500 mt-1">Set default email signatures, reply-to addresses, and notification dispatch rules.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Email Sender From Name</label>
              <input v-model="form.email_from_name" type="text" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Reply-To Email Address</label>
              <input v-model="form.email_reply_to" type="email" placeholder="admin@club.org" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
            </div>

            <div class="sm:col-span-2">
              <label class="block font-bold text-slate-700 mb-1">Email Footer Physical Address / Compliance Text</label>
              <input v-model="form.email_footer_address" type="text" placeholder="100 Boathouse Way, Oxford, UK" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
          </div>

          <div class="pt-2 text-xs">
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="form.notify_dues_overdue" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500" />
              <div>
                <div class="font-bold text-slate-900">Automated Overdue Dues Notices</div>
                <div class="text-[11px] text-slate-500">Send reminder emails when membership dues invoices pass their grace period.</div>
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- TAB 8: WEBSITE BUILDER -->
      <div v-if="activeTab === 'website'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">🌐 Public Website & SEO Metadata</h2>
            <p class="text-xs text-slate-500 mt-1">Configure global search engine optimization (SEO) defaults for your public website.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Page Title Suffix</label>
              <input v-model="form.seo_title_suffix" type="text" placeholder="| Oxford Boating Club" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
            </div>

            <div class="sm:col-span-2">
              <label class="block font-bold text-slate-700 mb-1">Default Meta Description</label>
              <textarea v-model="form.seo_meta_description" rows="3" placeholder="Official homepage for Oxford Boating Club events, membership, and news." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 9: BOOKINGS -->
      <div v-if="activeTab === 'bookings'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">🚣 Equipment & Facility Bookings</h2>
            <p class="text-xs text-slate-500 mt-1">Rules for boat bay, pitch, or clubhouse facility reservations.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Advance Booking Window (Days)</label>
              <input v-model.number="form.booking_window_days" type="number" min="1" max="365" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Max Duration Per Reservation (Hours)</label>
              <input v-model.number="form.max_booking_hours" type="number" min="1" max="24" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
            </div>
          </div>

          <div class="pt-2 text-xs">
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="form.require_coach_approval_equipment" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500" />
              <div>
                <div class="font-bold text-slate-900">Require Coach Approval</div>
                <div class="text-[11px] text-slate-500">Require coach sign-off before high-performance racing shells or specialized gear bookings are confirmed.</div>
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- TAB 10: PERFORMANCE LOGS -->
      <div v-if="activeTab === 'performance'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">📊 Athletic Performance & Erg Scores</h2>
            <p class="text-xs text-slate-500 mt-1">Configure leaderboard visibility and score verification standards.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Leaderboard Visibility</label>
              <select v-model="form.leaderboard_visibility" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold">
                <option value="public">🌐 Public to All Roster Members</option>
                <option value="private">🔒 Private (Individual Members Only)</option>
                <option value="coaches_only">🧢 Coaches & Admins Only</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Default Distance Metric</label>
              <input v-model="form.default_distance_unit" type="text" placeholder="meters" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
            </div>
          </div>

          <div class="pt-2 text-xs">
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="form.require_score_verification" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500" />
              <div>
                <div class="font-bold text-slate-900">Require Coach Score Verification</div>
                <div class="text-[11px] text-slate-500">Require a coach or admin to verify erg score submissions before they appear on official rankings.</div>
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- TAB 11: FEATURE MODULES -->
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

      <!-- Bottom Save Action Bar -->
      <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
        <button
          @click="submitSettings"
          :disabled="form.processing"
          class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/20 transition-all flex items-center gap-2 cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>Save All Settings</span>
        </button>
      </div>

        </div>
      </div>

    </div>
  </AdminLayout>
</template>
