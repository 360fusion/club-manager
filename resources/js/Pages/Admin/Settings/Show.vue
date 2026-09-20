<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MediaLibraryModal from '@/Components/MediaLibraryModal.vue';

const showMediaModal = ref(false);

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
  provinces: {
    type: Array,
    default: () => [],
  },
  availableRoles: {
    type: Array,
    default: () => [],
  },
  activeProvider: {
    type: String,
    default: 'stripe',
  },
  stripeConfigured: {
    type: Boolean,
    default: false,
  },
  paddleConfigured: {
    type: Boolean,
    default: false,
  },
});

const selectedProvider = ref(props.activeProvider || 'stripe');
const processingProvider = ref(false);

const switchProvider = (provider) => {
  selectedProvider.value = provider;
  processingProvider.value = true;
  router.post(route('billing.provider.update', { clubSlug: props.club.slug }), { provider }, {
    preserveScroll: true,
    onFinish: () => { processingProvider.value = false; }
  });
};

const validTabs = [
  'general', 'positions', 'officers', 'branding', 'roles', 'modules',
  'accounting', 'subscriptions', 'payments', 'events', 'dining',
  'communications', 'website', 'bookings', 'performance'
];

const getTabFromUrl = () => {
  const hash = typeof window !== 'undefined' ? window.location.hash.replace('#', '').trim() : '';
  if (hash && validTabs.includes(hash)) {
    return hash;
  }
  const searchParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
  const tabParam = searchParams ? searchParams.get('tab') : null;
  if (tabParam && validTabs.includes(tabParam)) {
    return tabParam;
  }
  return 'general';
};

const activeTab = ref(getTabFromUrl());

const syncTabWithUrl = () => {
  const tabFromUrl = getTabFromUrl();
  if (tabFromUrl !== activeTab.value) {
    activeTab.value = tabFromUrl;
  }
};

watch(activeTab, (newTab) => {
  if (typeof window !== 'undefined' && window.location.hash.replace('#', '') !== newTab) {
    history.replaceState(null, '', '#' + newTab);
  }
});

onMounted(() => {
  activeTab.value = getTabFromUrl();
  if (typeof window !== 'undefined') {
    window.addEventListener('hashchange', syncTabWithUrl);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('hashchange', syncTabWithUrl);
  }
});

// Primary Settings Form
const form = useForm({
  name: props.club.name || '',
  province_id: props.club.province_id || '',
  lodge_number: props.club.lodge_number || props.settings.lodge_number || '1418',
  lodge_status: props.settings.lodge_status || 'Normal',
  installed_masters: props.settings.installed_masters || 'No',
  ritual: props.settings.ritual || '-',
  consecration_date: props.settings.consecration_date || '3rd April 1873',
  constitution_date: props.settings.constitution_date || '21st October 1872',
  subscription_month: props.settings.subscription_month || 'April',
  installation_month: props.settings.installation_month || 'April',
  provincial_ar_month: props.settings.provincial_ar_month || 'March',
  meeting_formula: props.settings.meeting_formula || '4th Thu. 1 To 11 Ex. 6, 7, 8',
  tagline: props.settings.tagline || '',
  logo_url: props.club.logo_url || '',
  primary_color: props.settings.primary_color || '#0369a1',
  sidebar_theme: props.settings.sidebar_theme || 'dark_slate',
  currency: props.settings.currency || 'GBP',
  timezone: props.settings.timezone || 'Europe/London',
  contact_email: props.settings.contact_email || '',
  phone: props.settings.phone || '',
  address: props.settings.address || '',
  address_line_1: props.settings.address_line_1 || '',
  address_line_2: props.settings.address_line_2 || '',
  city: props.settings.city || '',
  county: props.settings.county || '',
  postcode: props.settings.postcode || '',
  country: props.settings.country || 'United Kingdom',
  social_facebook: props.settings.social_facebook || '',
  social_instagram: props.settings.social_instagram || '',
  social_twitter: props.settings.social_twitter || '',
  registration_mode: props.settings.registration_mode || 'open',
  member_prefix: props.settings.member_prefix || '',
  default_role: props.settings.default_role || 'member',
  invite_expiration_days: props.settings.invite_expiration_days ?? 14,
  membership_year_start: props.settings.membership_year_start || '2026-10-01',
  enable_member_ranks: props.settings.enable_member_ranks ?? true,
  member_ranks: props.settings.member_ranks || [
    'Worshipful Master (WM)',
    'Senior Warden (SW)',
    'Junior Warden (JW)',
    'Chaplain (Chap)',
    'Treasurer (Treas)',
    'Secretary (Sec)',
    'Director of Ceremonies (DC)',
    'Almoner (Alm)',
    'Charity Steward (ChStwd)',
    'Membership Officer (MO)',
    'Mentor (Mentor)',
    'Senior Deacon (SD)',
    'Junior Deacon (JD)',
    'Asst Dir of Ceremonies (ADC)',
    'Organist (Org)',
    'Assistant Secretary (ASec)',
    'Inner Guard (IG)',
    'Steward (Stwd)',
    'Tyler (Tyler)',
    'Immediate Past Master (IPM)',
    'Royal Arch Representative (Royal Arch Rep)',
    'Durham FC Representative (Durham FC Rep)',
    'Mentoring & Members Co-ordinator (MenCo-ord)',
  ],
  provincial_name: props.settings.provincial_name || 'Provincial Grand Lodge of Durham',
  provincial_grand_master: props.settings.provincial_grand_master || 'R WBro John David Watts',
  deputy_provincial_grand_master: props.settings.deputy_provincial_grand_master || 'WBro Andrew Peter Faul Foster PSGD',
  assistant_provincial_grand_masters: props.settings.assistant_provincial_grand_masters || "WBro Dr. Rakesh Bhalla PSGD\nWBro Thomas Fred Gittins PSGD\nWBro Martin Rankin PJGD\nWBro Michael Stuart Shaw PJGD\nWBro Lt Col John William Henry",
  officers_year_label: props.settings.officers_year_label || 'OFFICERS FOR 2025-2026',
  officers_roster: props.settings.officers_roster || [
    { role: 'Worshipful Master', name: 'W. Bro. K. D. Lord' },
    { role: 'Senior Warden', name: 'Bro. A. Smith' },
    { role: 'Junior Warden', name: 'Bro. M. Johnson' },
    { role: 'Chaplain', name: 'W. Bro. P. Davies' },
    { role: 'Treasurer', name: 'W. Bro. M. Brown' },
    { role: 'Secretary', name: 'W. Bro. R. Wilson' },
    { role: 'Director of Ceremonies', name: 'W. Bro. T. Anderson' },
    { role: 'Almoner', name: 'W. Bro. G. Martin' },
    { role: 'Charity Steward', name: 'W. Bro. E. Clark' },
    { role: 'Senior Deacon', name: 'Bro. C. White' },
    { role: 'Junior Deacon', name: 'Bro. D. Harris' },
    { role: 'Assistant Director of Ceremonies', name: 'W. Bro. P. Lewis' },
    { role: 'Organist', name: 'Bro. S. Walker' },
    { role: 'Assistant Secretary', name: 'Bro. A. Hall' },
    { role: 'Inner Guard', name: 'Bro. M. Allen' },
    { role: 'Steward', name: 'Bro. J. Young' },
    { role: 'Tyler', name: 'Bro. D. King' },
  ],
  custom_domain: props.club.custom_domain || '',
  enabled_modules: props.settings.enabled_modules || [],
  permission_matrix: props.settings.permission_matrix || {},

  // Subscriptions & Dues
  dues_grace_period_days: props.settings.dues_grace_period_days ?? 14,
  auto_invoice_days_before: props.settings.auto_invoice_days_before ?? 7,
  tax_registration_number: props.settings.tax_registration_number || '',
  receipt_footer_notes: props.settings.receipt_footer_notes || '',

  // Accounting & ERP Configuration
  fiscal_year_start_month: props.settings.fiscal_year_start_month || 'January',
  accounting_method: props.settings.accounting_method || 'accrual',
  lock_accounting_date: props.settings.lock_accounting_date || '',
  standard_vat_rate: props.settings.standard_vat_rate ?? 20.0,
  invoice_prefix: props.settings.invoice_prefix || 'INV-2026-',
  invoice_due_terms: props.settings.invoice_due_terms || 'Net 14',
  default_ar_account_code: props.settings.default_ar_account_code || '1200',
  default_revenue_account_code: props.settings.default_revenue_account_code || '4000',
  bill_prefix: props.settings.bill_prefix || 'BILL-2026-',
  default_ap_account_code: props.settings.default_ap_account_code || '2000',
  default_expense_account_code: props.settings.default_expense_account_code || '5000',
  require_bill_approval: props.settings.require_bill_approval ?? false,
  default_bank_account_code: props.settings.default_bank_account_code || '1000',
  bank_sort_code: props.settings.bank_sort_code || '20-65-18',
  bank_account_number: props.settings.bank_account_number || '83920145',
  enforce_balanced_journals: props.settings.enforce_balanced_journals ?? true,

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

const isSavedSuccess = ref(false);

const submitSettings = () => {
  form.put(route('admin.settings.update', { clubSlug: props.club.slug }), {
    preserveScroll: true,
    onSuccess: () => {
      isSavedSuccess.value = true;
      setTimeout(() => {
        isSavedSuccess.value = false;
      }, 3500);
    },
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

const moveRankUp = (index) => {
  if (index <= 0) return;
  const item = form.member_ranks.splice(index, 1)[0];
  form.member_ranks.splice(index - 1, 0, item);
};

const moveRankDown = (index) => {
  if (index >= form.member_ranks.length - 1) return;
  const item = form.member_ranks.splice(index, 1)[0];
  form.member_ranks.splice(index + 1, 0, item);
};

const editingRankIndex = ref(null);
const editingRankValue = ref('');

// Deletion Warning & Undo State
const showDeleteWarningModal = ref(false);
const pendingDeleteRank = ref(null);
const showUndoToast = ref(false);
const lastDeletedRank = ref(null);
const undoTimer = ref(null);

const getMemberCountForRank = (rankName) => {
  return props.members.filter(m => m.rank === rankName).length;
};

const initiateRemoveRank = (index) => {
  const rankToDelete = form.member_ranks[index];
  const affectedMembers = props.members.filter(m => m.rank === rankToDelete);

  pendingDeleteRank.value = {
    index,
    rank: rankToDelete,
    affectedMembers,
    reassignRank: '', // default to unassigned
  };
  showDeleteWarningModal.value = true;
};

const confirmDeleteRank = () => {
  if (!pendingDeleteRank.value) return;
  const { index, rank, affectedMembers, reassignRank } = pendingDeleteRank.value;

  const affectedMemberRanks = affectedMembers.map(m => ({
    id: m.id,
    oldRank: m.rank,
  }));

  // Reassign or clear rank for affected members locally
  affectedMembers.forEach(m => {
    m.rank = reassignRank;
    updateMemberRank(m.id, reassignRank);
  });

  executeRemoveRank(index, rank, affectedMemberRanks);
  showDeleteWarningModal.value = false;
  pendingDeleteRank.value = null;
};

const executeRemoveRank = (index, rankToDelete, affectedMemberRanks) => {
  form.member_ranks.splice(index, 1);

  lastDeletedRank.value = {
    index,
    rank: rankToDelete,
    affectedMemberRanks,
  };

  showUndoToast.value = true;
  if (undoTimer.value) clearTimeout(undoTimer.value);
  undoTimer.value = setTimeout(() => {
    showUndoToast.value = false;
  }, 10000);
};

const undoDeleteRank = () => {
  if (!lastDeletedRank.value) return;
  const { index, rank, affectedMemberRanks } = lastDeletedRank.value;

  if (!form.member_ranks.includes(rank)) {
    form.member_ranks.splice(Math.min(index, form.member_ranks.length), 0, rank);
  }

  if (affectedMemberRanks && affectedMemberRanks.length > 0) {
    affectedMemberRanks.forEach(item => {
      const member = props.members.find(m => m.id === item.id);
      if (member) {
        member.rank = item.oldRank;
        updateMemberRank(member.id, item.oldRank);
      }
    });
  }

  showUndoToast.value = false;
  lastDeletedRank.value = null;
};

const startEditRank = (index) => {
  editingRankIndex.value = index;
  editingRankValue.value = form.member_ranks[index];
};

const saveEditRank = (index) => {
  const oldRank = form.member_ranks[index];
  const trimmed = editingRankValue.value.trim();

  if (trimmed && trimmed !== oldRank) {
    form.member_ranks[index] = trimmed;

    // Update members using oldRank
    props.members.forEach(m => {
      if (m.rank === oldRank) {
        m.rank = trimmed;
        updateMemberRank(m.id, trimmed);
      }
    });
  }

  editingRankIndex.value = null;
  editingRankValue.value = '';
};

const cancelEditRank = () => {
  editingRankIndex.value = null;
  editingRankValue.value = '';
};

const updateMemberRank = (userId, newRank) => {
  router.post(
    route('admin.users.rank.update', { clubSlug: props.club.slug, userId }),
    { rank: newRank },
    { preserveScroll: true }
  );
};

// Officer Roster Helper Functions
const addOfficerRow = () => {
  form.officers_roster.push({ role: '', name: '' });
};

const removeOfficerRow = (index) => {
  form.officers_roster.splice(index, 1);
};

const moveOfficerUp = (index) => {
  if (index <= 0) return;
  const item = form.officers_roster.splice(index, 1)[0];
  form.officers_roster.splice(index - 1, 0, item);
};

const moveOfficerDown = (index) => {
  if (index >= form.officers_roster.length - 1) return;
  const item = form.officers_roster.splice(index, 1)[0];
  form.officers_roster.splice(index + 1, 0, item);
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
          :class="[
            'px-5 py-2.5 font-bold text-xs rounded-xl shadow-md transition-all duration-300 flex items-center gap-2 self-start sm:self-auto cursor-pointer',
            form.processing ? 'bg-indigo-500 text-white cursor-wait opacity-80' :
            isSavedSuccess ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-600/30 scale-105 ring-2 ring-emerald-400/50' :
            'bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-600/20'
          ]"
        >
          <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg v-else-if="isSavedSuccess" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>{{ form.processing ? 'Saving...' : (isSavedSuccess ? '✓ Saved Successfully!' : 'Save Changes') }}</span>
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
                <option value="positions">🎖️ Club Positions & Ranks</option>
                <option value="officers">👔 Provincial & Officers Roster</option>
                <option value="branding">🎨 Branding & Styling</option>
                <option value="roles">🛡️ Roles & Permissions</option>
                <option value="modules">⚡ Active Feature Modules</option>
              </optgroup>
              <optgroup label="Operations & Finance">
                <option value="accounting">📊 Accounting Settings</option>
                <option value="subscriptions">💳 Subscriptions & Dues</option>
                <option value="payments">💳 Payment Gateways</option>
                <option value="events">📅 Events & Check-Ins</option>
                <option value="dining">🍽️ Dining & Catering RSVPs</option>
                <option value="communications">✉️ Communications & Emails</option>
              </optgroup>
              <optgroup label="Module Policies">
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
                  @click="activeTab = 'positions'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'positions' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🎖️</span> Club Positions</span>
                </button>

                <button
                  @click="activeTab = 'officers'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'officers' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>👔</span> Officers Roster</span>
                </button>

                <button
                  @click="activeTab = 'branding'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'branding' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🎨</span> Branding & Styling</span>
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
                  @click="activeTab = 'accounting'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'accounting' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>📊</span> Accounting</span>
                </button>

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
                  @click="activeTab = 'payments'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'payments' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>💳</span> Payment Gateways</span>
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

        <!-- Lodge Details Card (Matches Lodge Particulars Specs) -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div class="border-b border-slate-100 pb-3">
            <h2 class="text-2xl font-extrabold text-slate-900">Details</h2>
          </div>

          <!-- 2-Column Responsive Layout -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-1 text-sm font-semibold text-slate-800">
            <!-- Left Column -->
            <div>
              <div class="flex items-center justify-between border-b border-slate-100 py-3.5">
                <span class="font-black text-slate-900">Status</span>
                <span class="px-3 py-0.5 rounded border border-emerald-500 text-emerald-600 bg-white text-xs font-medium">
                  {{ form.lodge_status || 'Normal' }}
                </span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 py-3.5">
                <span class="font-black text-slate-900">Installed Masters</span>
                <span class="text-slate-800 font-normal">{{ form.installed_masters || 'No' }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 py-3.5">
                <span class="font-black text-slate-900">Ritual</span>
                <span class="text-slate-800 font-normal">{{ form.ritual || '-' }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 py-3.5">
                <span class="font-black text-slate-900">Consecration Date</span>
                <span class="text-slate-800 font-normal">{{ form.consecration_date || '3rd April 1873' }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 py-3.5">
                <span class="font-black text-slate-900">Constitution Date</span>
                <span class="text-slate-800 font-normal">{{ form.constitution_date || '21st October 1872' }}</span>
              </div>
            </div>

            <!-- Right Column -->
            <div>
              <div class="flex items-center justify-between border-b border-slate-100 py-3.5">
                <span class="font-black text-slate-900">Subscription Month</span>
                <span class="text-slate-800 font-normal">{{ form.subscription_month || 'April' }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 py-3.5">
                <span class="font-black text-slate-900">Installation Month</span>
                <span class="text-slate-800 font-normal">{{ form.installation_month || 'April' }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 py-3.5">
                <span class="font-black text-slate-900">Provincial AR Month</span>
                <span class="text-slate-800 font-normal">{{ form.provincial_ar_month || 'March' }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 py-3.5">
                <span class="font-black text-slate-900">Meeting Formula</span>
                <span class="text-slate-800 font-normal">{{ form.meeting_formula || '4th Thu. 1 To 11 Ex. 6, 7, 8' }}</span>
              </div>
            </div>
          </div>

          <!-- Edit Particulars Inputs Grid -->
          <div class="border-t border-slate-100 pt-5 space-y-4">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-500">Edit Lodge Particulars & Dates</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Status</label>
                <select v-model="form.lodge_status" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="Normal">Normal</option>
                  <option value="Active">Active</option>
                  <option value="Suspended">Suspended</option>
                  <option value="Erased">Erased</option>
                  <option value="Amalgamated">Amalgamated</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1">Installed Masters</label>
                <select v-model="form.installed_masters" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-indigo-500">
                  <option value="No">No</option>
                  <option value="Yes">Yes</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1">Ritual</label>
                <input v-model="form.ritual" type="text" placeholder="e.g. Emulation / Stability" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1">Consecration Date</label>
                <input v-model="form.consecration_date" type="text" placeholder="e.g. 3rd April 1873" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1">Constitution Date</label>
                <input v-model="form.constitution_date" type="text" placeholder="e.g. 21st October 1872" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1">Subscription Month</label>
                <select v-model="form.subscription_month" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-indigo-500">
                  <option v-for="m in ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']" :key="m" :value="m">{{ m }}</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1">Installation Month</label>
                <select v-model="form.installation_month" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-indigo-500">
                  <option v-for="m in ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']" :key="m" :value="m">{{ m }}</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1">Provincial AR Month</label>
                <select v-model="form.provincial_ar_month" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-indigo-500">
                  <option v-for="m in ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']" :key="m" :value="m">{{ m }}</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1">Meeting Formula Rule</label>
                <input v-model="form.meeting_formula" type="text" placeholder="e.g. 4th Thu. 1 To 11 Ex. 6, 7, 8" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">🏢 Organization Profile</h2>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Club / Organization Name</label>
              <input v-model="form.name" type="text" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Lodge / Club Number</label>
              <input v-model="form.lodge_number" type="text" placeholder="e.g. 1418" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Assigned Masonic Province</label>
              <select v-model="form.province_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-bold outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">No Province Selected</option>
                <option v-for="p in provinces" :key="p.id" :value="p.id">
                  🏛️ {{ p.name }}
                </option>
              </select>
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

            <div class="sm:col-span-2 border-t border-slate-100 pt-4 mt-2">
              <label class="block font-bold text-slate-900 mb-3 text-xs flex items-center gap-1.5">
                <span>📍</span> Club Headquarters & Physical Address
              </label>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                  <label class="block font-semibold text-slate-600 mb-1">Address Line 1</label>
                  <input v-model="form.address_line_1" type="text" placeholder="Building name, street number & name" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>

                <div class="sm:col-span-2">
                  <label class="block font-semibold text-slate-600 mb-1">Address Line 2 (Optional)</label>
                  <input v-model="form.address_line_2" type="text" placeholder="Suite, unit, floor, or department" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>

                <div>
                  <label class="block font-semibold text-slate-600 mb-1">Town / City</label>
                  <input v-model="form.city" type="text" placeholder="e.g. Oxford" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>

                <div>
                  <label class="block font-semibold text-slate-600 mb-1">County / State / Region</label>
                  <input v-model="form.county" type="text" placeholder="e.g. Oxfordshire" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>

                <div>
                  <label class="block font-semibold text-slate-600 mb-1">Postcode / ZIP Code</label>
                  <input v-model="form.postcode" type="text" placeholder="e.g. OX1 1AA" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 uppercase font-mono" />
                </div>

                <div>
                  <label class="block font-semibold text-slate-600 mb-1">Country</label>
                  <input v-model="form.country" type="text" placeholder="e.g. United Kingdom" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">⚙️ Onboarding & Access Control</h2>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-xs">
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

            <div>
              <label class="block font-bold text-slate-700 mb-1">Invite Expiry (Days)</label>
              <input v-model="form.invite_expiration_days" type="number" min="1" max="365" placeholder="14" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Membership Year Start Date</label>
              <input v-model="form.membership_year_start" type="date" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
            </div>
          </div>
        </div>
      </div>

      <!-- TAB: CLUB POSITIONS & RANKS -->
      <div v-if="activeTab === 'positions'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">🎖️ Club Positions & Member Ranks</h2>
            <p class="text-xs text-slate-500 mt-1">Configure customizable member positions, ranks, and skill levels to categorize, select, and filter club members across the platform.</p>
          </div>

          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-sm font-bold text-slate-900">Enable Member Ranks & Skill Levels</h3>
                <p class="text-xs text-slate-500">Enable customized member ranks (e.g. Novice, Captain, Veteran) to filter and select members across the platform.</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.enable_member_ranks" class="sr-only peer" />
                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
              </label>
            </div>

            <div v-if="form.enable_member_ranks" class="space-y-4 pt-4 border-t border-slate-100">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-0.5">Configured Ranks & Positions List</label>
                <p class="text-[11px] text-slate-400">Define the positions or skill ranks available for members in your organization.</p>
              </div>

              <!-- Vertical Ranks List Container -->
              <div class="max-w-lg space-y-2">
                <div
                  v-for="(rank, idx) in form.member_ranks"
                  :key="idx"
                  class="flex items-center justify-between p-3 bg-slate-50 border border-slate-200/90 rounded-2xl hover:border-slate-300 transition-all group"
                >
                  <!-- Viewing State -->
                  <template v-if="editingRankIndex !== idx">
                    <div class="flex items-center gap-3">
                      <!-- Reorder Controls -->
                      <div class="flex flex-col items-center justify-center gap-0.5 mr-0.5">
                        <button
                          type="button"
                          @click="moveRankUp(idx)"
                          :disabled="idx === 0"
                          title="Move Up"
                          aria-label="Move Up"
                          class="w-5 h-4 flex items-center justify-center text-[10px] font-black text-slate-400 hover:text-indigo-600 hover:bg-slate-200/60 rounded disabled:opacity-20 disabled:hover:text-slate-400 disabled:hover:bg-transparent cursor-pointer transition-all"
                        >
                          ▲
                        </button>
                        <button
                          type="button"
                          @click="moveRankDown(idx)"
                          :disabled="idx === form.member_ranks.length - 1"
                          title="Move Down"
                          aria-label="Move Down"
                          class="w-5 h-4 flex items-center justify-center text-[10px] font-black text-slate-400 hover:text-indigo-600 hover:bg-slate-200/60 rounded disabled:opacity-20 disabled:hover:text-slate-400 disabled:hover:bg-transparent cursor-pointer transition-all"
                        >
                          ▼
                        </button>
                      </div>

                      <span class="w-7 h-7 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-200/60 font-bold flex items-center justify-center text-xs shadow-xs">
                        🏅
                      </span>
                      <span class="text-xs font-bold text-slate-800">{{ rank }}</span>
                      <span
                        v-if="getMemberCountForRank(rank) > 0"
                        class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 text-[10px] font-extrabold border border-indigo-200/60"
                      >
                        {{ getMemberCountForRank(rank) }} assigned
                      </span>
                    </div>

                    <div class="flex items-center gap-1.5">
                      <button
                        type="button"
                        @click="startEditRank(idx)"
                        class="px-2.5 py-1 text-[11px] font-bold text-slate-700 hover:text-indigo-600 bg-white hover:bg-slate-100 border border-slate-200 rounded-xl transition-all cursor-pointer"
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        @click="initiateRemoveRank(idx)"
                        class="px-2.5 py-1 text-[11px] font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-xl transition-all cursor-pointer"
                      >
                        Remove
                      </button>
                    </div>
                  </template>

                  <!-- Editing State -->
                  <template v-else>
                    <div class="flex items-center gap-2 w-full">
                      <span class="w-7 h-7 rounded-xl bg-indigo-100 text-indigo-700 font-bold flex items-center justify-center text-xs flex-shrink-0">
                        ✏️
                      </span>
                      <input
                        v-model="editingRankValue"
                        @keydown.enter.prevent="saveEditRank(idx)"
                        @keydown.esc="cancelEditRank"
                        type="text"
                        class="flex-1 px-3 py-1.5 bg-white border border-indigo-300 rounded-xl text-xs font-bold text-slate-900 outline-none focus:ring-2 focus:ring-indigo-500"
                      />
                      <button
                        type="button"
                        @click="saveEditRank(idx)"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer"
                      >
                        Save
                      </button>
                      <button
                        type="button"
                        @click="cancelEditRank"
                        class="px-2.5 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs rounded-xl cursor-pointer"
                      >
                        Cancel
                      </button>
                    </div>
                  </template>
                </div>

                <div v-if="form.member_ranks.length === 0" class="p-4 text-center text-xs text-slate-400 border border-dashed border-slate-200 rounded-2xl">
                  No positions configured yet. Add your first position below.
                </div>
              </div>

              <!-- Add New Rank Form Row -->
              <div class="flex gap-2 max-w-lg pt-1">
                <input
                  v-model="newRankInput"
                  @keydown.enter.prevent="addRank"
                  type="text"
                  placeholder="Add a new position/rank (e.g. Captain, Coxswain, Senior)..."
                  class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs outline-none focus:ring-2 focus:ring-indigo-500 font-semibold"
                />
                <button
                  type="button"
                  @click="addRank"
                  class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20 transition-all cursor-pointer flex items-center gap-1"
                >
                  <span>+</span> Add Position
                </button>
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
              <div class="flex items-center justify-between mb-1">
                <label class="block font-bold text-slate-700">Logo Image URL</label>
                <button
                  type="button"
                  @click="showMediaModal = true"
                  class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-200 transition-all flex items-center gap-1 cursor-pointer"
                >
                  📁 Choose from Media Library
                </button>
              </div>
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

      <!-- TAB: PROVINCIAL & OFFICERS ROSTER -->
      <div v-if="activeTab === 'officers'" class="space-y-6">
        
        <!-- Provincial Executive Card -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">🏛️ Provincial Executive & Rulers</h2>
            <p class="text-xs text-slate-500 mt-1">Configure annual Provincial Grand Lodge rulers and executive officers. These settings automatically populate new meeting summonses.</p>
          </div>

          <div class="space-y-4 text-xs">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Provincial Title / Name</label>
                <input v-model="form.provincial_name" type="text" placeholder="Provincial Grand Lodge of Durham" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1">Provincial Grand Master</label>
                <input v-model="form.provincial_grand_master" type="text" placeholder="R WBro John David Watts" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Deputy Provincial Grand Master</label>
                <input v-model="form.deputy_provincial_grand_master" type="text" placeholder="WBro Andrew Peter Faul Foster PSGD" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1">Assistant Provincial Grand Masters (One per line)</label>
                <textarea v-model="form.assistant_provincial_grand_masters" rows="4" placeholder="WBro Dr. Rakesh Bhalla PSGD..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-xs"></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- Annual Officers Roster Managed Card -->
        <div class="bg-indigo-50/60 rounded-2xl p-6 border border-indigo-200/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold text-indigo-600">👔 Lodge Governance</span>
              <span class="text-xs px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-extrabold text-[10px]">Active Domain</span>
            </div>
            <h3 class="text-base font-black text-slate-900">Annual Officer Rosters &amp; History</h3>
            <p class="text-xs text-slate-600">
              Lodge Officer rosters, multi-year installation dates, progressive ladder seats, and committee approvals are now managed centrally in the Member Directory.
            </p>
          </div>
          <a
            :href="route('admin.officers.index', { clubSlug: club.slug })"
            class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition-all cursor-pointer flex-shrink-0 flex items-center gap-1.5"
          >
            <span>Manage Officers Roster</span>
            <span>→</span>
          </a>
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

      <!-- TAB: ACCOUNTING SETTINGS -->
      <div v-if="activeTab === 'accounting'" class="space-y-6">
        <!-- 1. Fiscal Year & Accounting Controls -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">📊 Financial Year & Tax Controls</h2>
            <p class="text-xs text-slate-500 mt-1">Configure fiscal year boundaries, accounting basis, lock dates, and tax rules.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Fiscal Year Start Month</label>
              <select v-model="form.fiscal_year_start_month" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold">
                <option value="January">January</option>
                <option value="February">February</option>
                <option value="March">March</option>
                <option value="April">April (UK Tax Year)</option>
                <option value="May">May</option>
                <option value="June">June</option>
                <option value="July">July</option>
                <option value="August">August</option>
                <option value="September">September</option>
                <option value="October">October (Club Dues Cycle)</option>
                <option value="November">November</option>
                <option value="December">December</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Accounting Basis / Method</label>
              <select v-model="form.accounting_method" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold">
                <option value="accrual">Accrual Basis (Recognize when billed)</option>
                <option value="cash">Cash Basis (Recognize when paid)</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Standard VAT Rate (%)</label>
              <input v-model.number="form.standard_vat_rate" type="number" step="0.1" min="0" max="100" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Closed Period Lock Date</label>
              <input v-model="form.lock_accounting_date" type="date" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
              <p class="text-[11px] text-slate-400 mt-1">Prevents posting journals before this date.</p>
            </div>
          </div>
        </div>

        <!-- 2. Sales Invoicing & Member Receivables -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">🧾 Sales Invoicing & Receivables Rules</h2>
            <p class="text-xs text-slate-500 mt-1">Set invoice number prefixes, default revenue accounts, payment terms, and lead times.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Invoice Number Prefix</label>
              <input v-model="form.invoice_prefix" type="text" placeholder="INV-2026-" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Default Payment Due Terms</label>
              <select v-model="form.invoice_due_terms" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold">
                <option value="Due on Receipt">Due on Receipt</option>
                <option value="Net 7">Net 7 Days</option>
                <option value="Net 14">Net 14 Days</option>
                <option value="Net 30">Net 30 Days</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Default A/R Account Code</label>
              <input v-model="form.default_ar_account_code" type="text" placeholder="1200" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Default Revenue Account Code</label>
              <input v-model="form.default_revenue_account_code" type="text" placeholder="4000" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Auto-Invoice Lead Time (Days)</label>
              <input v-model.number="form.auto_invoice_days_before" type="number" min="0" max="90" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Overdue Grace Period (Days)</label>
              <input v-model.number="form.dues_grace_period_days" type="number" min="0" max="180" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
            </div>
          </div>
        </div>

        <!-- 3. Purchases & Vendor Bills Payables -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">📄 Purchases & Vendor Payables Rules</h2>
            <p class="text-xs text-slate-500 mt-1">Set bill prefixes, default expense accounts, and vendor approval workflows.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Bill Number Prefix</label>
              <input v-model="form.bill_prefix" type="text" placeholder="BILL-2026-" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Default A/P Account Code</label>
              <input v-model="form.default_ap_account_code" type="text" placeholder="2000" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Default Expense Account Code</label>
              <input v-model="form.default_expense_account_code" type="text" placeholder="5000" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold" />
            </div>
          </div>

          <div class="pt-2 border-t border-slate-100">
            <label class="flex items-center gap-3 cursor-pointer text-xs">
              <input type="checkbox" v-model="form.require_bill_approval" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500" />
              <div>
                <div class="font-bold text-slate-900">Vendor Bill Approval Workflow</div>
                <div class="text-[11px] text-slate-500">Require explicit Administrator approval before a vendor bill can be marked as paid.</div>
              </div>
            </label>
          </div>
        </div>

        <!-- 4. General Ledger, Currency & Company Address Profile -->
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900">🏢 Organization Address & General Ledger Controls</h2>
            <p class="text-xs text-slate-500 mt-1">Base accounting currency, double-entry validation, and company registration address.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Base Accounting Currency</label>
              <select v-model="form.currency" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold">
                <option value="GBP">GBP (£) - British Pound Sterling</option>
                <option value="USD">USD ($) - US Dollar</option>
                <option value="EUR">EUR (€) - Euro</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Operating Bank Account Code</label>
              <input v-model="form.default_bank_account_code" type="text" placeholder="1000" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Bank Sort Code (Summons Dues/Dining)</label>
              <input v-model="form.bank_sort_code" type="text" placeholder="20-65-18" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Bank Account Number (Summons Dues/Dining)</label>
              <input v-model="form.bank_account_number" type="text" placeholder="83920145" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">VAT / Tax Registration Number</label>
              <input v-model="form.tax_registration_number" type="text" placeholder="GB 987 6543 21" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-bold" />
            </div>

            <div class="sm:col-span-2">
              <label class="block font-bold text-slate-700 mb-1">Company Registered Billing Address (Line 1)</label>
              <input v-model="form.address_line_1" type="text" placeholder="100 Boathouse Way" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">City / Town</label>
              <input v-model="form.city" type="text" placeholder="Oxford" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Postcode / ZIP</label>
              <input v-model="form.postcode" type="text" placeholder="OX1 1AA" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-mono font-semibold" />
            </div>

            <div class="sm:col-span-2">
              <label class="block font-bold text-slate-700 mb-1">Receipt & Invoice Footer Terms</label>
              <textarea v-model="form.receipt_footer_notes" rows="3" placeholder="Thank you for supporting our club. Fees support equipment & clubhouse operations." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
          </div>

          <div class="pt-2 border-t border-slate-100">
            <label class="flex items-center gap-3 cursor-pointer text-xs">
              <input type="checkbox" v-model="form.enforce_balanced_journals" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500" />
              <div>
                <div class="font-bold text-slate-900">Enforce Double-Entry Balance Validation</div>
                <div class="text-[11px] text-slate-500">Require total debits to exactly equal total credits before manual journal entries can be posted.</div>
              </div>
            </label>
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
              <label class="block font-bold text-slate-700 mb-1">Membership Year Start Date</label>
              <input v-model="form.membership_year_start" type="date" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 font-bold" />
              <p class="text-[11px] text-slate-400 mt-1">Official annual start date for club membership dues and subscription billing.</p>
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

      <!-- TAB: PAYMENT GATEWAYS & PROVIDERS -->
      <div v-if="activeTab === 'payments'" class="space-y-6">
        <div class="bg-white rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 pb-6">
            <div>
              <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <span>💳 Active Payment Gateway Provider</span>
              </h2>
              <p class="text-xs text-slate-500 mt-1">
                Select which payment gateway handles member subscription checkouts, card payments, and recurring dues for this club.
              </p>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
              <div class="flex items-center gap-2 bg-slate-100 p-1.5 rounded-xl border border-slate-200">
                <button
                  type="button"
                  @click="switchProvider('stripe')"
                  :disabled="processingProvider"
                  :class="[
                    'px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2 cursor-pointer',
                    selectedProvider === 'stripe'
                      ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                      : 'text-slate-600 hover:text-slate-900'
                  ]"
                >
                  <span class="w-2 h-2 rounded-full bg-indigo-300"></span>
                  Stripe (Direct / Cards)
                </button>

                <button
                  type="button"
                  @click="switchProvider('paddle')"
                  :disabled="processingProvider"
                  :class="[
                    'px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2 cursor-pointer',
                    selectedProvider === 'paddle'
                      ? 'bg-cyan-600 text-white shadow-md shadow-cyan-600/20'
                      : 'text-slate-600 hover:text-slate-900'
                  ]"
                >
                  <span class="w-2 h-2 rounded-full bg-cyan-300"></span>
                  Paddle (MoR / Tax Compliant)
                </button>
              </div>

              <a :href="route('billing.portal', { clubSlug: props.club.slug })" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-300 transition-all flex items-center gap-1.5">
                <span>⚙️ Customer Portal</span>
              </a>
            </div>
          </div>

          <!-- Gateway Specs Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-5 rounded-2xl border border-slate-200/80 bg-slate-50 space-y-3">
              <div class="flex items-center justify-between">
                <span class="font-extrabold text-slate-900 text-xs">Stripe Integration</span>
                <span :class="stripeConfigured ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-amber-700 bg-amber-50 border-amber-200'" class="px-2.5 py-1 rounded-lg text-[10px] font-bold border">
                  {{ stripeConfigured ? 'Keys Active' : 'Sandbox Demo Mode' }}
                </span>
              </div>
              <p class="text-xs text-slate-500 leading-relaxed">
                Direct card processing with Stripe Elements &amp; Cashier subscription webhooks. Ideal for direct card payments.
              </p>
            </div>

            <div class="p-5 rounded-2xl border border-slate-200/80 bg-slate-50 space-y-3">
              <div class="flex items-center justify-between">
                <span class="font-extrabold text-slate-900 text-xs">Paddle Integration</span>
                <span :class="paddleConfigured ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 'text-amber-700 bg-amber-50 border-amber-200'" class="px-2.5 py-1 rounded-lg text-[10px] font-bold border">
                  {{ paddleConfigured ? 'Keys Active' : 'Sandbox Demo Mode' }}
                </span>
              </div>
              <p class="text-xs text-slate-500 leading-relaxed">
                Merchant of Record billing, automatic international sales tax &amp; VAT remittance for international country members.
              </p>
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

    <!-- Modal: Position Deletion Warning & Confirmation Modal -->
    <div
      v-if="showDeleteWarningModal"
      class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5 border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div class="flex items-center gap-2 text-amber-600 font-bold text-sm">
            <span class="text-base">⚠️</span>
            <h3>Confirm Position Deletion</h3>
          </div>
          <button @click="showDeleteWarningModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">
            &times;
          </button>
        </div>

        <div v-if="pendingDeleteRank" class="space-y-4 text-xs">
          <!-- Case 1: Assigned to active members -->
          <template v-if="pendingDeleteRank.affectedMembers.length > 0">
            <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-2xl text-amber-900 space-y-1">
              <p class="font-bold">
                Warning: The position <span class="font-black text-slate-900">"{{ pendingDeleteRank.rank }}"</span> is assigned to {{ pendingDeleteRank.affectedMembers.length }} member(s).
              </p>
              <p class="text-[11px] text-amber-700">
                Affected members: <span class="font-semibold">{{ pendingDeleteRank.affectedMembers.map(m => m.name).slice(0, 4).join(', ') }}{{ pendingDeleteRank.affectedMembers.length > 4 ? ` +${pendingDeleteRank.affectedMembers.length - 4} more` : '' }}</span>.
              </p>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1.5">Action for Affected Members</label>
              <select
                v-model="pendingDeleteRank.reassignRank"
                class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500"
              >
                <option value="">Clear Position (Unassign Rank)</option>
                <option
                  v-for="r in form.member_ranks.filter(r => r !== pendingDeleteRank.rank)"
                  :key="r"
                  :value="r"
                >
                  Reassign to: 🏅 {{ r }}
                </option>
              </select>
            </div>
          </template>

          <!-- Case 2: 0 members assigned -->
          <template v-else>
            <div class="p-3.5 bg-amber-50/80 border border-amber-200 rounded-2xl text-amber-900">
              <p class="font-bold">
                Are you sure you want to delete the position <span class="font-black text-slate-900">"{{ pendingDeleteRank.rank }}"</span>?
              </p>
              <p class="text-[11px] text-amber-700 mt-1">
                This position will be removed from your club's active positions list. You can undo this action immediately after deleting.
              </p>
            </div>
          </template>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 text-xs">
          <button
            type="button"
            @click="showDeleteWarningModal = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="confirmDeleteRank"
            class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl shadow-md shadow-rose-600/20 cursor-pointer"
          >
            Confirm Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Toast Banner: Undo Position Deletion -->
    <div
      v-if="showUndoToast && lastDeletedRank"
      class="fixed bottom-6 right-6 z-50 bg-slate-900 text-white px-5 py-3.5 rounded-2xl shadow-2xl border border-slate-700 flex items-center justify-between gap-4 text-xs"
    >
      <div class="flex items-center gap-2.5">
        <span class="text-sm">🗑️</span>
        <span>Position <strong class="text-amber-400">"{{ lastDeletedRank.rank }}"</strong> was removed.</span>
      </div>
      <div class="flex items-center gap-3">
        <button
          @click="undoDeleteRank"
          class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs rounded-xl shadow-sm transition-all cursor-pointer flex items-center gap-1.5"
        >
          <span>↩️</span> Undo
        </button>
        <button
          @click="showUndoToast = false"
          class="text-slate-400 hover:text-white font-bold text-sm"
        >
          &times;
        </button>
      </div>
    </div>

    <!-- Toast Feedback Banner -->
    <Transition
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="isSavedSuccess" class="fixed bottom-6 right-6 z-50 bg-emerald-900 text-white px-5 py-3.5 rounded-2xl shadow-2xl border border-emerald-700/80 flex items-center gap-3.5">
        <span class="w-7 h-7 rounded-full bg-emerald-500 text-slate-950 font-black flex items-center justify-center text-xs shadow-md">✓</span>
        <div>
          <div class="text-xs font-extrabold text-white">Club Settings Saved!</div>
          <div class="text-[11px] text-emerald-200">Your organization configuration has been updated.</div>
        </div>
      </div>
    </Transition>
    <!-- Spatie Media Library Modal Component -->
    <MediaLibraryModal
      :show="showMediaModal"
      :club-slug="club.slug"
      default-folder="logos"
      @close="showMediaModal = false"
      @select="(item) => { form.logo_url = item.url; showMediaModal = false; }"
    />

  </AdminLayout>
</template>
