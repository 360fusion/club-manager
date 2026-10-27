<script setup>
import OfficersSettingsTab from '@/Components/Settings/OfficersSettingsTab.vue';
import BookingsSettingsTab from '@/Components/Settings/BookingsSettingsTab.vue';
import CommunicationsSettingsTab from '@/Components/Settings/CommunicationsSettingsTab.vue';
import DiningSettingsTab from '@/Components/Settings/DiningSettingsTab.vue';
import EventsSettingsTab from '@/Components/Settings/EventsSettingsTab.vue';
import SubscriptionsSettingsTab from '@/Components/Settings/SubscriptionsSettingsTab.vue';
import BrandingSettingsTab from '@/Components/Settings/BrandingSettingsTab.vue';
import GeneralSettingsTab from '@/Components/Settings/GeneralSettingsTab.vue';
import RolesSettingsTab from '@/Components/Settings/RolesSettingsTab.vue';
import ModulesSettingsTab from '@/Components/Settings/ModulesSettingsTab.vue';
import AccountingSettingsTab from '@/Components/Settings/AccountingSettingsTab.vue';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MediaLibraryModal from '@/Components/MediaLibraryModal.vue';
import NewsTagsManager from '@/Components/NewsTagsManager.vue';
import RankListEditor from '@/Components/RankListEditor.vue';

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
  ranks: {
    type: Object,
    default: () => ({ grandLodgeName: null, usage: { grand: {}, provincial: {} } }),
  },
  newsTags: {
    type: Array,
    default: () => [],
  },
  currencies: {
    type: Array,
    default: () => [],
  },
  currencyLocked: {
    type: Boolean,
    default: false,
  },
  members: {
    type: Array,
    default: () => [],
  },
  provinces: {
    type: Array,
    default: () => [],
  },
  masonicHalls: {
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
  storage: {
    type: Object,
    default: () => ({ used_bytes: 0, quota_bytes: null, used_human: '0 KB', quota_human: null, percent: null }),
  },
  platformDefaultStorageQuotaMb: {
    type: Number,
    default: null,
  },
});

const isSuperAdmin = computed(() => !!usePage().props.auth?.user?.is_super_admin);

// Owner holds every permission implicitly and isn't a club-level choice, so only platform
// admins see that column in the matrix.
const visibleRoles = computed(() => props.availableRoles.filter(r => r.code !== 'owner' || isSuperAdmin.value));

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
  'general', 'positions', 'ranks', 'officers', 'branding', 'roles', 'modules',
  'accounting', 'subscriptions', 'payments', 'events', 'dining',
  'communications', 'news-tags', 'website', 'bookings'
];

// Event and booking payment options are set up once for the lodge on their own page, which comes back here.
const paymentOptionsUrl = (tab) => `${route('admin.payment_options.index', { clubSlug: props.club.slug })}?return=${encodeURIComponent(`/${props.club.slug}/admin/settings?tab=${tab}`)}`;

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

// Halls offered for the chosen province (all of them until a province is picked).
const availableHalls = computed(() => props.masonicHalls.filter(
  (h) => !form.province_id || !h.province_id || h.province_id === Number(form.province_id)
));

const hallsOnly = computed(() => availableHalls.value.filter((h) => h.kind === 'hall'));
const otherVenues = computed(() => availableHalls.value.filter((h) => h.kind !== 'hall'));

const hallLabel = (h) => (h.town && !h.name.toLowerCase().includes(h.town.toLowerCase()) ? `${h.name} – ${h.town}` : h.name);

const selectedHall = computed(() => props.masonicHalls.find((h) => h.id === Number(form.masonic_hall_id)) || null);

const selectedHallAddress = computed(() => selectedHall.value
  ? [selectedHall.value.address_line_1, selectedHall.value.address_line_2, selectedHall.value.town, selectedHall.value.county, selectedHall.value.postcode].filter(Boolean).join(', ')
  : '');

function onProvinceChange() {
  if (selectedHall.value && selectedHall.value.province_id && selectedHall.value.province_id !== Number(form.province_id)) {
    form.masonic_hall_id = '';
  }
}

function onHallChange() {
  if (selectedHall.value?.province_id && !form.province_id) {
    form.province_id = selectedHall.value.province_id;
  }
}

// Primary Settings Form
const form = useForm({
  name: props.club.name || '',
  province_id: props.club.province_id || '',
  masonic_hall_id: props.club.masonic_hall_id || '',
  lodge_number: props.club.lodge_number || props.settings.lodge_number || '',
  lodge_status: props.settings.lodge_status || 'Normal',
  installed_masters: props.settings.installed_masters || 'No',
  ritual: props.settings.ritual || '-',
  consecration_date: props.settings.consecration_date || '',
  constitution_date: props.settings.constitution_date || '',
  subscription_month: props.settings.subscription_month || 'April',
  installation_month: props.settings.installation_month || 'April',
  provincial_ar_month: props.settings.provincial_ar_month || 'March',
  meeting_formula: props.settings.meeting_formula || '',
  tagline: props.settings.tagline || '',
  logo_url: props.club.logo_url || '',
  primary_color: props.settings.primary_color || '#0369a1',
  sidebar_theme: props.settings.sidebar_theme || 'dark_slate',
  currency: props.settings.currency || props.currencies?.[0]?.code || 'GBP',
  timezone: props.settings.timezone || 'Europe/London',
  storage_quota_mb: props.settings.storage_quota_mb ?? null,
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
  summons_visibility: props.settings.summons_visibility || 'members',
  member_prefix: props.settings.member_prefix || '',
  default_role: props.settings.default_role || 'member',
  invite_expiration_days: props.settings.invite_expiration_days ?? 14,
  invite_reminder_days: props.settings.invite_reminder_days ?? 7,
  grand_ranks: (props.settings.grand_ranks || []).map((r) => ({ ...r })),
  provincial_ranks: (props.settings.provincial_ranks || []).map((r) => ({ ...r })),
  membership_year_start: props.settings.membership_year_start || '',
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
    'Mentoring & Members Co-ordinator (MenCo-ord)',
  ],
  provincial_name: props.settings.provincial_name || '',
  provincial_grand_master: props.settings.provincial_grand_master || '',
  deputy_provincial_grand_master: props.settings.deputy_provincial_grand_master || '',
  assistant_provincial_grand_masters: props.settings.assistant_provincial_grand_masters || '',
  officers_year_label: props.settings.officers_year_label || '',
  officers_roster: props.settings.officers_roster || [],
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
  bank_sort_code: props.settings.bank_sort_code || '',
  bank_account_number: props.settings.bank_account_number || '',
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

const resetRanks = () => {
  const name = props.ranks.grandLodgeName || 'standard';
  if (!window.confirm(`Replace both rank lists with the ${name} lists? Any changes you have made to them here will be lost.`)) return;
  router.post(route('admin.settings.ranks.reset', { clubSlug: props.club.slug }), {}, {
    preserveScroll: true,
    onSuccess: () => {
      form.grand_ranks = (props.settings.grand_ranks || []).map((r) => ({ ...r }));
      form.provincial_ranks = (props.settings.provincial_ranks || []).map((r) => ({ ...r }));
    },
  });
};

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

// --- Meeting formula builder -------------------------------------------------
// Same rule picker as "Generate Season Meeting Rules" on the meetings page, but instead of
// creating meetings it writes the rule back into the Meeting Formula Rule field, e.g.
// [4th] [Thursday] with Jan–May + Sep–Nov ticked  ->  "4th Thu. 1 To 11 Ex. 6, 7, 8".
const DAY_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const showFormulaBuilder = ref(false);
const formulaBuilder = ref({ occurrence: '4th', day_of_week: 'Thursday', months: [1, 2, 3, 4, 5, 9, 10, 11] });

// Read an existing rule back into the picker so opening it doesn't discard what's already set.
const parseFormula = (formula) => {
  const parsed = { occurrence: '4th', day_of_week: 'Thursday', months: [1, 2, 3, 4, 5, 9, 10, 11] };
  if (!formula) return parsed;

  const occurrence = formula.match(/^\s*(1st|2nd|3rd|4th|last)/i);
  if (occurrence) parsed.occurrence = occurrence[1].toLowerCase() === 'last' ? 'last' : occurrence[1];

  const day = DAY_NAMES.find(d => new RegExp(`\\b${d.slice(0, 3)}`, 'i').test(formula));
  if (day) parsed.day_of_week = day;

  const range = formula.match(/(\d{1,2})\s*to\s*(\d{1,2})/i);
  if (range) {
    const from = Number(range[1]);
    const to = Number(range[2]);
    const except = (formula.split(/ex\.?/i)[1] || '').match(/\d{1,2}/g)?.map(Number) ?? [];
    parsed.months = [];
    for (let m = from; m <= to; m++) {
      if (!except.includes(m)) parsed.months.push(m);
    }
  }

  return parsed;
};

const openFormulaBuilder = () => {
  formulaBuilder.value = parseFormula(form.meeting_formula);
  showFormulaBuilder.value = true;
};

// Months are expressed as a span with the gaps listed as exceptions, matching how lodges write it.
const builtFormula = computed(() => {
  const months = [...formulaBuilder.value.months].sort((a, b) => a - b);
  const dayShort = formulaBuilder.value.day_of_week.slice(0, 3) + '.';
  const occurrence = formulaBuilder.value.occurrence === 'last' ? 'Last' : formulaBuilder.value.occurrence;

  if (!months.length) return `${occurrence} ${dayShort}`;

  const from = months[0];
  const to = months[months.length - 1];
  const except = [];
  for (let m = from; m <= to; m++) {
    if (!months.includes(m)) except.push(m);
  }

  const span = from === to ? `${from}` : `${from} To ${to}`;

  return except.length ? `${occurrence} ${dayShort} ${span} Ex. ${except.join(', ')}` : `${occurrence} ${dayShort} ${span}`;
});

const applyFormulaBuilder = () => {
  form.meeting_formula = builtFormula.value;
  showFormulaBuilder.value = false;
};

const newRankInput = ref('');

const addRank = () => {
  const val = newRankInput.value.trim();
  if (val && !form.member_ranks.includes(val)) {
    form.member_ranks.push(val);
    newRankInput.value = '';
  }
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

</script>

<template>
  <AdminLayout :title="`${club.name} Settings`" :club="club" active-tab="profile">
    <Head :title="`${club.name} Settings`" />

    <div class="space-y-6 max-w-6xl mx-auto">
      
      <!-- Top Action Bar & Header -->
      <div class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">Club Feature & Configuration Settings</h1>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
              {{ club.slug }}
            </span>
          </div>
          <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">Configure policies, feature modules, branding, automated notifications, and permission matrices for your club.</p>
        </div>

        <button
          @click="submitSettings"
          :disabled="form.processing"
          :class="[
            'px-5 py-2.5 font-bold text-xs rounded-xl shadow-md transition-all duration-300 flex items-center gap-2 self-start sm:self-auto cursor-pointer',
            form.processing ? 'bg-blue-500 text-white cursor-wait opacity-80' :
            isSavedSuccess ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-600/30 scale-105 ring-2 ring-emerald-400/50' :
            'bg-blue-600 hover:bg-blue-700 text-white shadow-blue-600/20'
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
          <span>{{ form.processing ? 'Saving...' : (isSavedSuccess ? '✓ Saved successfully!' : 'Save changes') }}</span>
        </button>
      </div>

      <!-- 2-Column Responsive Settings Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        
        <!-- Left Settings Sidebar Navigation -->
        <div class="lg:col-span-1 space-y-4">
          <!-- Mobile Category Dropdown Selector -->
          <div class="lg:hidden bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-2">
            <label class="block text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Settings Category</label>
            <select v-model="activeTab" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
              <optgroup label="Workspace & Security">
                <option value="general">🏢 General Profile & Access</option>
                <option value="positions">🎖️ Club Positions & Ranks</option>
                <option value="ranks">🏅 Grand & Provincial Ranks</option>
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
                <option value="news-tags">🏷️ News Tags</option>
              </optgroup>
              <optgroup label="Module Policies">
                <option value="bookings">🚣 Pitch & Equipment Bookings</option>
              </optgroup>
            </select>
          </div>

          <!-- Desktop Vertical Sidebar Navigation Card -->
          <div class="hidden lg:block bg-white dark:bg-slate-900 p-3.5 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-5 sticky top-6">
            
            <!-- Group 1: Workspace -->
            <div>
              <div class="px-3 text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Workspace</div>
              <div class="space-y-0.5 text-xs font-bold">
                <button
                  @click="activeTab = 'general'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'general' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🏢</span> General</span>
                </button>

                <button
                  @click="activeTab = 'positions'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'positions' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🎖️</span> Club positions</span>
                </button>

                <button
                  @click="activeTab = 'ranks'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'ranks' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🏅</span> Grand &amp; Provincial ranks</span>
                </button>

                <button
                  @click="activeTab = 'officers'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'officers' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>👔</span> Officers roster</span>
                </button>

                <button
                  @click="activeTab = 'branding'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'branding' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🎨</span> Branding & styling</span>
                </button>

                <button
                  @click="activeTab = 'roles'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'roles' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🛡️</span> Roles & permissions</span>
                </button>

                <button
                  @click="activeTab = 'modules'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'modules' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>⚡</span> Feature modules</span>
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
                    activeTab === 'accounting' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>📊</span> Accounting</span>
                </button>

                <button
                  @click="activeTab = 'subscriptions'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'subscriptions' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>💳</span> Subscriptions & dues</span>
                </button>

                <button
                  @click="activeTab = 'payments'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'payments' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>💳</span> Payment gateways</span>
                </button>

                <button
                  @click="activeTab = 'events'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'events' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>📅</span> Events & check-ins</span>
                </button>

                <button
                  @click="activeTab = 'dining'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'dining' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🍽️</span> Dining & RSVPs</span>
                </button>

                <button
                  @click="activeTab = 'communications'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'communications' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>✉️</span> Communications</span>
                </button>

                <button
                  @click="activeTab = 'news-tags'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'news-tags' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🏷️</span> News tags</span>
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
                    activeTab === 'bookings' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🚣</span> Equipment bookings</span>
                </button>
              </div>
            </div>

          </div>
        </div>

        <!-- Right Content Panel -->
        <div class="lg:col-span-3 space-y-6">

      <!-- TAB 1: GENERAL SETTINGS -->
      <GeneralSettingsTab v-if="activeTab === 'general'" :form="form" :hall-label="hallLabel" :halls-only="hallsOnly" :on-hall-change="onHallChange" :on-province-change="onProvinceChange" :open-formula-builder="openFormulaBuilder" :other-venues="otherVenues" :selected-hall-address="selectedHallAddress" :provinces="provinces" />

      <!-- TAB: CLUB POSITIONS & RANKS -->
      <div v-if="activeTab === 'positions'" class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">🎖️ Club Positions & Member Ranks</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configure customizable member positions, ranks, and skill levels to categorize, select, and filter club members across the platform.</p>
          </div>

          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Enable Member Ranks & Skill Levels</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Enable customized member ranks (e.g. Novice, Captain, Veteran) to filter and select members across the platform.</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="form.enable_member_ranks" class="sr-only peer" />
                <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 dark:after:border-slate-700 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
              </label>
            </div>

            <div v-if="form.enable_member_ranks" class="space-y-4 pt-4 border-t border-slate-100 dark:border-slate-800">
              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-0.5">Configured Ranks & Positions List</label>
                <p class="text-[11px] text-slate-400">Define the positions or skill ranks available for members in your organization.</p>
              </div>

              <!-- Vertical Ranks List Container -->
              <div class="max-w-lg space-y-2">
                <div
                  v-for="(rank, idx) in form.member_ranks"
                  :key="idx"
                  class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/90 dark:border-slate-800/90 rounded-2xl hover:border-slate-300 dark:hover:border-slate-700 transition-all group"
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
                          class="w-5 h-4 flex items-center justify-center text-[10px] font-black text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 rounded disabled:opacity-20 disabled:hover:text-slate-400 disabled:hover:bg-transparent cursor-pointer transition-all"
                        >
                          ▲
                        </button>
                        <button
                          type="button"
                          @click="moveRankDown(idx)"
                          :disabled="idx === form.member_ranks.length - 1"
                          title="Move Down"
                          aria-label="Move Down"
                          class="w-5 h-4 flex items-center justify-center text-[10px] font-black text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 rounded disabled:opacity-20 disabled:hover:text-slate-400 disabled:hover:bg-transparent cursor-pointer transition-all"
                        >
                          ▼
                        </button>
                      </div>

                      <span class="w-7 h-7 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200/60 dark:border-blue-800/60 font-bold flex items-center justify-center text-xs shadow-xs">
                        🏅
                      </span>
                      <span class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ rank }}</span>
                      <span
                        v-if="getMemberCountForRank(rank) > 0"
                        class="px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 text-[10px] font-extrabold border border-blue-200/60 dark:border-blue-800/60"
                      >
                        {{ getMemberCountForRank(rank) }} assigned
                      </span>
                    </div>

                    <div class="flex items-center gap-1.5">
                      <button
                        type="button"
                        @click="startEditRank(idx)"
                        class="px-2.5 py-1 text-[11px] font-bold text-slate-700 dark:text-slate-200 hover:text-blue-600 dark:hover:text-blue-400 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 rounded-xl transition-all cursor-pointer"
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        @click="initiateRemoveRank(idx)"
                        class="px-2.5 py-1 text-[11px] font-bold text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 border border-rose-200 dark:border-rose-800/60 rounded-xl transition-all cursor-pointer"
                      >
                        Remove
                      </button>
                    </div>
                  </template>

                  <!-- Editing State -->
                  <template v-else>
                    <div class="flex items-center gap-2 w-full">
                      <span class="w-7 h-7 rounded-xl bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-bold flex items-center justify-center text-xs flex-shrink-0">
                        ✏️
                      </span>
                      <input
                        v-model="editingRankValue"
                        @keydown.enter.prevent="saveEditRank(idx)"
                        @keydown.esc="cancelEditRank"
                        type="text"
                        class="flex-1 px-3 py-1.5 bg-white dark:bg-slate-900 border border-blue-300 dark:border-blue-700/60 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"
                      />
                      <button
                        type="button"
                        @click="saveEditRank(idx)"
                        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs cursor-pointer"
                      >
                        Save
                      </button>
                      <button
                        type="button"
                        @click="cancelEditRank"
                        class="px-2.5 py-1.5 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl cursor-pointer"
                      >
                        Cancel
                      </button>
                    </div>
                  </template>
                </div>

                <div v-if="form.member_ranks.length === 0" class="p-4 text-center text-xs text-slate-400 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl">
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
                  class="flex-1 px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs outline-none focus:ring-2 focus:ring-blue-500 font-semibold"
                />
                <button
                  type="button"
                  @click="addRank"
                  class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-600/20 transition-all cursor-pointer flex items-center gap-1"
                >
                  <span>+</span> Add position
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB: GRAND & PROVINCIAL RANKS -->
      <div v-if="activeTab === 'ranks'" class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-8">
          <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
            <div>
              <h2 class="text-lg font-bold text-slate-900 dark:text-white">🏅 Grand &amp; Provincial Ranks</h2>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">The ranks you can pick from when adding or editing a member. The abbreviation is what appears after their name; the full title helps people choose the right one. Save the page to keep your changes.</p>
            </div>
            <button
              type="button"
              @click="resetRanks"
              class="shrink-0 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-800 text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white cursor-pointer"
            >
              Reset to the {{ ranks.grandLodgeName || 'standard' }} list
            </button>
          </div>

          <RankListEditor
            v-model="form.grand_ranks"
            label="Grand ranks"
            help="Ranks conferred by the Grand Lodge, for example PAGDC."
            id-prefix="grand_ranks"
            :usage="ranks.usage.grand"
            :errors="form.errors"
          />

          <RankListEditor
            v-model="form.provincial_ranks"
            label="Provincial ranks"
            help="Ranks conferred by the Province, for example PPrSGD."
            id-prefix="provincial_ranks"
            :usage="ranks.usage.provincial"
            :errors="form.errors"
          />

          <p class="text-[11px] text-slate-500 dark:text-slate-400">Removing or renaming a rank here never changes a member who already holds it. Their rank stays selectable on their own record.</p>
        </div>
      </div>

      <!-- TAB 2: BRANDING & THEME -->
      <BrandingSettingsTab v-if="activeTab === 'branding'" :form="form" :is-super-admin="isSuperAdmin" @open-media="showMediaModal = true" :club="club" :platform-default-storage-quota-mb="platformDefaultStorageQuotaMb" :storage="storage" />

      <!-- TAB: PROVINCIAL & OFFICERS ROSTER -->
      <OfficersSettingsTab v-if="activeTab === 'officers'" :form="form" :club="club" />

      <!-- TAB 3: ROLES & PERMISSIONS -->
      <RolesSettingsTab v-if="activeTab === 'roles'" :form="form" :is-super-admin="isSuperAdmin" :toggle-permission-role="togglePermissionRole" :update-member-role="updateMemberRole" :visible-roles="visibleRoles" :club="club" :members="members" />

      <!-- TAB: ACCOUNTING SETTINGS -->
      <AccountingSettingsTab v-if="activeTab === 'accounting'" :form="form" :currencies="currencies" :currency-locked="currencyLocked" />

      <!-- TAB 4: SUBSCRIPTIONS & DUES -->
      <SubscriptionsSettingsTab v-if="activeTab === 'subscriptions'" :form="form" />

      <!-- TAB: PAYMENT GATEWAYS & PROVIDERS -->
      <div v-if="activeTab === 'payments'" class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-800/60 dark:bg-blue-950/30">
          <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">💳 Payment options for events and bookings</h3>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Bank transfer, card, PayPal, pay later and pay on the night. Set them up and switch them on once here, and every event offers them.</p>
          </div>
          <a :href="paymentOptionsUrl('payments')" class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-blue-700">Manage payment options</a>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-6">
            <div>
              <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span>💳 Active Payment Gateway Provider</span>
              </h2>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Select which payment gateway handles member subscription checkouts and recurring dues for this club. Payments for events and bookings are set up separately, in Payment options above.
              </p>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
              <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 p-1.5 rounded-xl border border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  @click="switchProvider('stripe')"
                  :disabled="processingProvider"
                  :class="[
                    'px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer',
                    selectedProvider === 'stripe'
                      ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20'
                      : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="w-2 h-2 rounded-full bg-blue-300"></span>
                  Stripe (direct / cards)
                </button>

                <button
                  type="button"
                  @click="switchProvider('paddle')"
                  :disabled="processingProvider"
                  :class="[
                    'px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer',
                    selectedProvider === 'paddle'
                      ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20'
                      : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="w-2 h-2 rounded-full bg-blue-300"></span>
                  Paddle (MoR / tax compliant)
                </button>
              </div>

              <a :href="route('billing.portal', { clubSlug: props.club.slug })" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 transition-all flex items-center gap-1.5">
                <span>⚙️ Customer portal</span>
              </a>
            </div>
          </div>

          <!-- Gateway Specs Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-slate-50 dark:bg-slate-800/50 space-y-3">
              <div class="flex items-center justify-between">
                <span class="font-extrabold text-slate-900 dark:text-white text-xs">Stripe Integration</span>
                <span :class="stripeConfigured ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/60' : 'text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/60'" class="px-2.5 py-1 rounded-lg text-[10px] font-bold border">
                  {{ stripeConfigured ? 'Keys Active' : 'Sandbox Demo Mode' }}
                </span>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                Direct card processing with Stripe Elements &amp; Cashier subscription webhooks. Ideal for direct card payments.
              </p>
            </div>

            <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-slate-50 dark:bg-slate-800/50 space-y-3">
              <div class="flex items-center justify-between">
                <span class="font-extrabold text-slate-900 dark:text-white text-xs">Paddle Integration</span>
                <span :class="paddleConfigured ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/60' : 'text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/60'" class="px-2.5 py-1 rounded-lg text-[10px] font-bold border">
                  {{ paddleConfigured ? 'Keys Active' : 'Sandbox Demo Mode' }}
                </span>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                Merchant of Record billing, automatic international sales tax &amp; VAT remittance for international country members.
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 5: EVENTS & CHECK-INS -->
      <EventsSettingsTab v-if="activeTab === 'events'" :form="form" :payment-options-url="paymentOptionsUrl" />

      <!-- TAB 6: DINING & RSVPs -->
      <DiningSettingsTab v-if="activeTab === 'dining'" :form="form" />

      <!-- TAB 7: COMMUNICATIONS -->
      <CommunicationsSettingsTab v-if="activeTab === 'communications'" :form="form" />



      <!-- TAB: NEWS TAGS -->
      <div v-if="activeTab === 'news-tags'" class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">🏷️ News Tags</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">The tags available when writing a news item. Editors can only choose from this list, and members can filter news by them. Tags are saved as you add or edit them; you don't need to press Save Changes.</p>
          </div>
          <NewsTagsManager :club-slug="club.slug" :tags="newsTags" />
        </div>
      </div>

      <!-- TAB 9: BOOKINGS -->
      <BookingsSettingsTab v-if="activeTab === 'bookings'" :form="form" />

      <!-- TAB 11: FEATURE MODULES -->
      <ModulesSettingsTab v-if="activeTab === 'modules'" :form="form" :toggle-module="toggleModule" :all-modules="allModules" />

      <!-- Bottom Save Action Bar -->
      <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
        <button
          @click="submitSettings"
          :disabled="form.processing"
          class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-lg shadow-blue-600/20 transition-all flex items-center gap-2 cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span>Save all settings</span>
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
      <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-5 border border-slate-100 dark:border-slate-800">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
          <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400 font-bold text-sm">
            <span class="text-base">⚠️</span>
            <h3>Confirm Position Deletion</h3>
          </div>
          <button @click="showDeleteWarningModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg font-bold">
            &times;
          </button>
        </div>

        <div v-if="pendingDeleteRank" class="space-y-4 text-xs">
          <!-- Case 1: Assigned to active members -->
          <template v-if="pendingDeleteRank.affectedMembers.length > 0">
            <div class="p-3.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-2xl text-amber-900 dark:text-amber-200 space-y-1">
              <p class="font-bold">
                Warning: The position <span class="font-black text-slate-900 dark:text-white">"{{ pendingDeleteRank.rank }}"</span> is assigned to {{ pendingDeleteRank.affectedMembers.length }} member(s).
              </p>
              <p class="text-[11px] text-amber-700 dark:text-amber-300">
                Affected members: <span class="font-semibold">{{ pendingDeleteRank.affectedMembers.map(m => m.name).slice(0, 4).join(', ') }}{{ pendingDeleteRank.affectedMembers.length > 4 ? ` +${pendingDeleteRank.affectedMembers.length - 4} more` : '' }}</span>.
              </p>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1.5">Action for Affected Members</label>
              <select
                v-model="pendingDeleteRank.reassignRank"
                class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-800 dark:text-slate-100 outline-none focus:ring-2 focus:ring-blue-500"
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
            <div class="p-3.5 bg-amber-50/80 dark:bg-amber-950/80 border border-amber-200 dark:border-amber-800/60 rounded-2xl text-amber-900 dark:text-amber-200">
              <p class="font-bold">
                Are you sure you want to delete the position <span class="font-black text-slate-900 dark:text-white">"{{ pendingDeleteRank.rank }}"</span>?
              </p>
              <p class="text-[11px] text-amber-700 dark:text-amber-300 mt-1">
                This position will be removed from your club's active positions list. You can undo this action immediately after deleting.
              </p>
            </div>
          </template>
        </div>

        <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs">
          <button
            type="button"
            @click="showDeleteWarningModal = false"
            class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-xl"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="confirmDeleteRank"
            class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl shadow-md shadow-rose-600/20 cursor-pointer"
          >
            Confirm delete
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
          class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-500 text-white font-extrabold text-xs rounded-xl shadow-sm transition-all cursor-pointer flex items-center gap-1.5"
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

    <!-- Meeting Formula Rule Builder -->
    <div v-if="showFormulaBuilder" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="formula-builder-title"
        v-focus-trap="() => { showFormulaBuilder = false; }"
        class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-lg w-full p-6 space-y-5 max-h-[90vh] overflow-y-auto"
      >
        <div class="flex items-start justify-between gap-4">
          <div>
            <h3 id="formula-builder-title" class="text-lg font-bold text-slate-900 dark:text-white">Generate Meeting Formula Rule</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Pick the occurrence, weekday and the months the lodge meets.</p>
          </div>
          <button type="button" @click="showFormulaBuilder = false" aria-label="Close" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg font-bold cursor-pointer">✕</button>
        </div>

        <div class="grid grid-cols-2 gap-4 text-xs">
          <div>
            <label for="formula-occurrence" class="block font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Occurrence</label>
            <select id="formula-occurrence" v-model="formulaBuilder.occurrence" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs">
              <option value="1st">1st</option>
              <option value="2nd">2nd</option>
              <option value="3rd">3rd</option>
              <option value="4th">4th</option>
              <option value="last">Last</option>
            </select>
          </div>

          <div>
            <label for="formula-day" class="block font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">Day of Week</label>
            <select id="formula-day" v-model="formulaBuilder.day_of_week" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs">
              <option v-for="d in DAY_NAMES" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>
        </div>

        <div class="text-xs">
          <span class="block font-semibold text-slate-600 dark:text-slate-300 uppercase mb-2">Meeting Months (Recess Unticked)</span>
          <div class="grid grid-cols-4 gap-2">
            <label v-for="(mName, idx) in MONTH_NAMES" :key="idx" class="flex items-center gap-1.5 p-2 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 cursor-pointer">
              <input type="checkbox" :value="idx + 1" v-model="formulaBuilder.months" class="rounded text-blue-600 dark:text-blue-400" />
              <span>{{ mName }}</span>
            </label>
          </div>
        </div>

        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl">
          <span class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-1">Resulting rule</span>
          <span class="font-mono font-bold text-sm text-slate-900 dark:text-white">{{ builtFormula }}</span>
        </div>

        <div class="flex justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
          <button type="button" @click="showFormulaBuilder = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl cursor-pointer">Cancel</button>
          <button type="button" @click="applyFormulaBuilder" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md cursor-pointer">
            Use this rule
          </button>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>
