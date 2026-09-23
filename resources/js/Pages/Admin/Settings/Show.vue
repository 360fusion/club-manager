<script setup>
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
  member_prefix: props.settings.member_prefix || '',
  default_role: props.settings.default_role || 'member',
  invite_expiration_days: props.settings.invite_expiration_days ?? 14,
  invite_reminder_days: props.settings.invite_reminder_days ?? 7,
  grand_ranks: (props.settings.grand_ranks || []).map((r) => ({ ...r })),
  provincial_ranks: (props.settings.provincial_ranks || []).map((r) => ({ ...r })),
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
          <span>{{ form.processing ? 'Saving...' : (isSavedSuccess ? '✓ Saved Successfully!' : 'Save Changes') }}</span>
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
                  <span class="flex items-center gap-2.5"><span>🎖️</span> Club Positions</span>
                </button>

                <button
                  @click="activeTab = 'ranks'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'ranks' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🏅</span> Grand &amp; Provincial Ranks</span>
                </button>

                <button
                  @click="activeTab = 'officers'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'officers' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>👔</span> Officers Roster</span>
                </button>

                <button
                  @click="activeTab = 'branding'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'branding' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🎨</span> Branding & Styling</span>
                </button>

                <button
                  @click="activeTab = 'roles'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'roles' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🛡️</span> Roles & Permissions</span>
                </button>

                <button
                  @click="activeTab = 'modules'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'modules' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
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
                  <span class="flex items-center gap-2.5"><span>💳</span> Subscriptions & Dues</span>
                </button>

                <button
                  @click="activeTab = 'payments'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'payments' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>💳</span> Payment Gateways</span>
                </button>

                <button
                  @click="activeTab = 'events'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeTab === 'events' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>📅</span> Events & Check-Ins</span>
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
                  <span class="flex items-center gap-2.5"><span>🏷️</span> News Tags</span>
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
                  <span class="flex items-center gap-2.5"><span>🚣</span> Equipment Bookings</span>
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
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
            <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white">Details</h2>
          </div>

          <!-- 2-Column Responsive Layout -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-1 text-sm font-semibold text-slate-800 dark:text-slate-100">
            <!-- Left Column -->
            <div>
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
                <span class="font-black text-slate-900 dark:text-white">Status</span>
                <span class="px-3 py-0.5 rounded border border-emerald-500 text-emerald-600 dark:text-emerald-400 bg-white dark:bg-slate-900 text-xs font-medium">
                  {{ form.lodge_status || 'Normal' }}
                </span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
                <span class="font-black text-slate-900 dark:text-white">Installed Masters</span>
                <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.installed_masters || 'No' }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
                <span class="font-black text-slate-900 dark:text-white">Ritual</span>
                <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.ritual || '-' }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
                <span class="font-black text-slate-900 dark:text-white">Consecration Date</span>
                <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.consecration_date || '3rd April 1873' }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
                <span class="font-black text-slate-900 dark:text-white">Constitution Date</span>
                <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.constitution_date || '21st October 1872' }}</span>
              </div>
            </div>

            <!-- Right Column -->
            <div>
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
                <span class="font-black text-slate-900 dark:text-white">Subscription Month</span>
                <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.subscription_month || 'April' }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
                <span class="font-black text-slate-900 dark:text-white">Installation Month</span>
                <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.installation_month || 'April' }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
                <span class="font-black text-slate-900 dark:text-white">Provincial AR Month</span>
                <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.provincial_ar_month || 'March' }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 py-3.5">
                <span class="font-black text-slate-900 dark:text-white">Meeting Formula</span>
                <span class="text-slate-800 dark:text-slate-100 font-normal">{{ form.meeting_formula || '4th Thu. 1 To 11 Ex. 6, 7, 8' }}</span>
              </div>
            </div>
          </div>

          <!-- Edit Particulars Inputs Grid -->
          <div class="border-t border-slate-100 dark:border-slate-800 pt-5 space-y-4">
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Edit Lodge Particulars & Dates</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Status</label>
                <select v-model="form.lodge_status" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="Normal">Normal</option>
                  <option value="Active">Active</option>
                  <option value="Suspended">Suspended</option>
                  <option value="Erased">Erased</option>
                  <option value="Amalgamated">Amalgamated</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Installed Masters</label>
                <select v-model="form.installed_masters" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="No">No</option>
                  <option value="Yes">Yes</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Ritual</label>
                <input v-model="form.ritual" type="text" placeholder="e.g. Emulation / Stability" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
              </div>

              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Consecration Date</label>
                <input v-model="form.consecration_date" type="text" placeholder="e.g. 3rd April 1873" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
              </div>

              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Constitution Date</label>
                <input v-model="form.constitution_date" type="text" placeholder="e.g. 21st October 1872" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
              </div>

              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Subscription Month</label>
                <select v-model="form.subscription_month" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-blue-500">
                  <option v-for="m in ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']" :key="m" :value="m">{{ m }}</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Installation Month</label>
                <select v-model="form.installation_month" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-blue-500">
                  <option v-for="m in ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']" :key="m" :value="m">{{ m }}</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Provincial AR Month</label>
                <select v-model="form.provincial_ar_month" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-semibold outline-none focus:ring-2 focus:ring-blue-500">
                  <option v-for="m in ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']" :key="m" :value="m">{{ m }}</option>
                </select>
              </div>

              <div>
                <label for="meeting-formula" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Meeting Formula Rule</label>
                <div class="flex items-center gap-2">
                  <input id="meeting-formula" v-model="form.meeting_formula" type="text" placeholder="e.g. 4th Thu. 1 To 11 Ex. 6, 7, 8" class="flex-1 px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
                  <button
                    type="button"
                    @click="openFormulaBuilder"
                    class="px-3.5 py-2.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 rounded-xl text-xs font-bold whitespace-nowrap transition-colors cursor-pointer"
                  >
                    ⚡ Build rule
                  </button>
                </div>
                <p class="text-[11px] text-slate-400 mt-1">Type it directly, or build it from the same occurrence / weekday / months picker the meetings page uses.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <h2 class="text-lg font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">🏢 Organization Profile</h2>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Club / Organization Name</label>
              <input v-model="form.name" type="text" required class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Lodge / Club Number</label>
              <input v-model="form.lodge_number" type="text" placeholder="e.g. 1418" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Assigned Masonic Province</label>
              <select v-model="form.province_id" @change="onProvinceChange" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-bold outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">No Province Selected</option>
                <option v-for="p in provinces" :key="p.id" :value="p.id">
                  🏛️ {{ p.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Masonic Hall</label>
              <select v-model="form.masonic_hall_id" @change="onHallChange" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-bold outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">No Masonic Hall Selected</option>
                <option v-for="h in availableHalls" :key="h.id" :value="h.id">
                  {{ h.name }}{{ h.town ? ' – ' + h.town : '' }}
                </option>
              </select>
              <p v-if="selectedHallAddress" class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">📍 {{ selectedHallAddress }}</p>
              <p v-else class="text-[11px] text-slate-400 mt-1">Where your lodge or chapter meets. Choose a province first to narrow the list.</p>
              <p v-if="form.errors.masonic_hall_id" class="text-[11px] text-rose-600 mt-1">{{ form.errors.masonic_hall_id }}</p>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Tagline or Motto</label>
              <input v-model="form.tagline" type="text" placeholder="e.g. Excellence on the Isis & Thames" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Contact Email</label>
              <input v-model="form.contact_email" type="email" placeholder="admin@club.org" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Support Phone Number</label>
              <input v-model="form.phone" type="text" placeholder="+44 20 7946 0912" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
            </div>

            <div class="sm:col-span-2 border-t border-slate-100 dark:border-slate-800 pt-4 mt-2">
              <label class="block font-bold text-slate-900 dark:text-white mb-3 text-xs flex items-center gap-1.5">
                <span>📍</span> Club Headquarters & Physical Address
              </label>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                  <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Address Line 1</label>
                  <input v-model="form.address_line_1" type="text" placeholder="Building name, street number & name" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
                </div>

                <div class="sm:col-span-2">
                  <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Address Line 2 (Optional)</label>
                  <input v-model="form.address_line_2" type="text" placeholder="Suite, unit, floor, or department" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
                </div>

                <div>
                  <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Town / City</label>
                  <input v-model="form.city" type="text" placeholder="e.g. Oxford" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
                </div>

                <div>
                  <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">County / State / Region</label>
                  <input v-model="form.county" type="text" placeholder="e.g. Oxfordshire" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
                </div>

                <div>
                  <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Postcode / ZIP Code</label>
                  <input v-model="form.postcode" type="text" placeholder="e.g. OX1 1AA" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 uppercase font-mono" />
                </div>

                <div>
                  <label class="block font-semibold text-slate-600 dark:text-slate-300 mb-1">Country</label>
                  <input v-model="form.country" type="text" placeholder="e.g. United Kingdom" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <h2 class="text-lg font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">⚙️ Onboarding & Access Control</h2>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Registration Mode</label>
              <select v-model="form.registration_mode" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold">
                <option value="open">🔓 Public Open Join</option>
                <option value="invite_only">🔒 Invite-Only / Admin Approval</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Member Number Prefix</label>
              <input v-model="form.member_prefix" type="text" placeholder="OUBC-" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default New Member Role</label>
              <select v-model="form.default_role" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold">
                <option value="member">Member</option>
                <option value="coach">Coach</option>
                <option value="treasurer">Treasurer</option>
                <option value="admin">Admin</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Invite Expiry (Days)</label>
              <input v-model="form.invite_expiration_days" type="number" min="1" max="365" placeholder="14" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Invite Reminder (Days)</label>
              <input v-model="form.invite_reminder_days" type="number" min="0" max="365" placeholder="7" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
              <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">One reminder is emailed to anyone who has not accepted after this many days. Use 0 to switch reminders off.</p>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Membership Year Start Date</label>
              <input v-model="form.membership_year_start" type="date" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            </div>
          </div>
        </div>
      </div>

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
                  <span>+</span> Add Position
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
      <div v-if="activeTab === 'branding'" class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <h2 class="text-lg font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">🎨 Logo & Visual Assets</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block font-bold text-slate-700 dark:text-slate-200">Logo Image URL</label>
                <button
                  type="button"
                  @click="showMediaModal = true"
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

      <!-- TAB: PROVINCIAL & OFFICERS ROSTER -->
      <div v-if="activeTab === 'officers'" class="space-y-6">
        
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
            <span>Manage Officers Roster</span>
            <span>→</span>
          </a>
        </div>

      </div>

      <!-- TAB 3: ROLES & PERMISSIONS -->
      <div v-if="activeTab === 'roles'" class="space-y-6">
        
        <!-- Role Permission Matrix Grid -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">🛡️ Granular Admin Permission Matrix</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Define which administrative actions each club role is allowed to perform.</p>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-[11px] font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                  <th class="py-3 px-4">Administrative Action</th>
                  <th v-for="r in visibleRoles" :key="r.code" class="py-3 px-3 text-center">
                    <span :class="['px-2 py-0.5 rounded text-[10px] font-extrabold border', r.badge]">
                      {{ r.name }}
                    </span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                <tr v-for="(perm, permKey) in form.permission_matrix" :key="permKey" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                  <td class="py-3.5 px-4">
                    <div class="font-bold text-slate-900 dark:text-white">{{ perm.label }}</div>
                    <div class="text-[11px] text-slate-400">{{ perm.description }}</div>
                  </td>

                  <td v-for="r in visibleRoles" :key="r.code" class="py-3.5 px-3 text-center">
                    <input
                      type="checkbox"
                      :checked="perm.roles && perm.roles.includes(r.code)"
                      @change="togglePermissionRole(permKey, r.code)"
                      :disabled="r.code === 'owner'"
                      class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 cursor-pointer disabled:opacity-50"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Role Directory & Quick Assignment -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
              <h2 class="text-lg font-bold text-slate-900 dark:text-white">👥 Member Administrative Role Directory</h2>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Assign or change each member's admin permission level (e.g. who can edit the website or manage billing).</p>
            </div>
            <a
              :href="route('admin.officers.index', { clubSlug: club.slug })"
              class="text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:underline whitespace-nowrap"
            >
              Manage lodge offices →
            </a>
          </div>

          <div class="divide-y divide-slate-100 dark:divide-slate-800">
            <div v-for="m in members" :key="m.id" class="py-3 flex items-center justify-between gap-4 text-xs">
              <div class="overflow-hidden">
                <div class="font-bold text-slate-900 dark:text-white truncate">{{ m.name }}</div>
                <div class="text-[11px] text-slate-400 truncate">{{ m.email }} • <span class="font-mono">{{ m.member_number }}</span></div>
              </div>

              <div class="flex items-center gap-2">
                <!-- Office is read-only here: it's owned by the annual officer roster, not this page. -->
                <span
                  v-if="m.current_office_label"
                  :class="['px-2.5 py-1 rounded-xl border text-[11px]', m.current_office_badge_class]"
                  :title="`${m.current_office_label} — set from the officer roster, not editable here`"
                >
                  {{ m.current_office_label }}
                </span>

                <select
                  :value="m.role"
                  @change="updateMemberRole(m.id, $event.target.value)"
                  class="px-3 py-1.5 font-bold rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 text-xs cursor-pointer outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="member">Media Manager</option>
                  <option value="coach">Secretary</option>
                  <option value="treasurer">Treasurer</option>
                  <option value="admin">Admin</option>
                  <option v-if="isSuperAdmin || m.role === 'owner'" value="owner">Owner</option>
                </select>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- TAB: ACCOUNTING SETTINGS -->
      <div v-if="activeTab === 'accounting'" class="space-y-6">
        <!-- 1. Fiscal Year & Accounting Controls -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">📊 Financial Year & Tax Controls</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configure fiscal year boundaries, accounting basis, lock dates, and tax rules.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Fiscal Year Start Month</label>
              <select v-model="form.fiscal_year_start_month" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold">
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
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Accounting Basis / Method</label>
              <select v-model="form.accounting_method" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold">
                <option value="accrual">Accrual Basis (Recognize when billed)</option>
                <option value="cash">Cash Basis (Recognize when paid)</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Standard VAT Rate (%)</label>
              <input v-model.number="form.standard_vat_rate" type="number" step="0.1" min="0" max="100" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Closed Period Lock Date</label>
              <input v-model="form.lock_accounting_date" type="date" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
              <p class="text-[11px] text-slate-400 mt-1">Prevents posting journals before this date.</p>
            </div>
          </div>
        </div>

        <!-- 2. Sales Invoicing & Member Receivables -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">🧾 Sales Invoicing & Receivables Rules</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Set invoice number prefixes, default revenue accounts, payment terms, and lead times.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Invoice Number Prefix</label>
              <input v-model="form.invoice_prefix" type="text" placeholder="INV-2026-" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default Payment Due Terms</label>
              <select v-model="form.invoice_due_terms" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold">
                <option value="Due on Receipt">Due on Receipt</option>
                <option value="Net 7">Net 7 Days</option>
                <option value="Net 14">Net 14 Days</option>
                <option value="Net 30">Net 30 Days</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default A/R Account Code</label>
              <input v-model="form.default_ar_account_code" type="text" placeholder="1200" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default Revenue Account Code</label>
              <input v-model="form.default_revenue_account_code" type="text" placeholder="4000" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Auto-Invoice Lead Time (Days)</label>
              <input v-model.number="form.auto_invoice_days_before" type="number" min="0" max="90" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Overdue Grace Period (Days)</label>
              <input v-model.number="form.dues_grace_period_days" type="number" min="0" max="180" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            </div>
          </div>
        </div>

        <!-- 3. Purchases & Vendor Bills Payables -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">📄 Purchases & Vendor Payables Rules</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Set bill prefixes, default expense accounts, and vendor approval workflows.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Bill Number Prefix</label>
              <input v-model="form.bill_prefix" type="text" placeholder="BILL-2026-" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default A/P Account Code</label>
              <input v-model="form.default_ap_account_code" type="text" placeholder="2000" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default Expense Account Code</label>
              <input v-model="form.default_expense_account_code" type="text" placeholder="5000" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
            </div>
          </div>

          <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
            <label class="flex items-center gap-3 cursor-pointer text-xs">
              <input type="checkbox" v-model="form.require_bill_approval" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
              <div>
                <div class="font-bold text-slate-900 dark:text-white">Vendor Bill Approval Workflow</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400">Require explicit Administrator approval before a vendor bill can be marked as paid.</div>
              </div>
            </label>
          </div>
        </div>

        <!-- 4. General Ledger, Currency & Company Address Profile -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">🏢 Organization Address & General Ledger Controls</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Base accounting currency, double-entry validation, and company registration address.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Base Accounting Currency</label>
              <select v-model="form.currency" :disabled="currencyLocked" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold disabled:opacity-60">
                <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }} ({{ c.symbol.trim() }}) - {{ c.name }}</option>
              </select>
              <p v-if="currencyLocked" class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Locked: this club already has financial records, and amounts are not converted between currencies.</p>
              <p v-else class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Every amount in this club's accounts uses this currency. It is locked once you record any money.</p>
              <p v-if="form.errors?.currency" class="mt-1 text-[11px] font-semibold text-rose-600">{{ form.errors.currency }}</p>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Operating Bank Account Code</label>
              <input v-model="form.default_bank_account_code" type="text" placeholder="1000" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Bank Sort Code (Summons Dues/Dining)</label>
              <input v-model="form.bank_sort_code" type="text" placeholder="20-65-18" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Bank Account Number (Summons Dues/Dining)</label>
              <input v-model="form.bank_account_number" type="text" placeholder="83920145" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">VAT / Tax Registration Number</label>
              <input v-model="form.tax_registration_number" type="text" placeholder="GB 987 6543 21" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
            </div>

            <div class="sm:col-span-2">
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Company Registered Billing Address (Line 1)</label>
              <input v-model="form.address_line_1" type="text" placeholder="100 Boathouse Way" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">City / Town</label>
              <input v-model="form.city" type="text" placeholder="Oxford" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Postcode / ZIP</label>
              <input v-model="form.postcode" type="text" placeholder="OX1 1AA" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-semibold" />
            </div>

            <div class="sm:col-span-2">
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Receipt & Invoice Footer Terms</label>
              <textarea v-model="form.receipt_footer_notes" rows="3" placeholder="Thank you for supporting our club. Fees support equipment & clubhouse operations." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
          </div>

          <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
            <label class="flex items-center gap-3 cursor-pointer text-xs">
              <input type="checkbox" v-model="form.enforce_balanced_journals" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
              <div>
                <div class="font-bold text-slate-900 dark:text-white">Enforce Double-Entry Balance Validation</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400">Require total debits to exactly equal total credits before manual journal entries can be posted.</div>
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- TAB 4: SUBSCRIPTIONS & DUES -->
      <div v-if="activeTab === 'subscriptions'" class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">💳 Subscriptions & Billing Settings</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configure grace periods, automated dues invoicing, and payment receipt footers.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Overdue Grace Period (Days)</label>
              <input v-model.number="form.dues_grace_period_days" type="number" min="0" max="180" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
              <p class="text-[11px] text-slate-400 mt-1">Days after due date before member status defaults to overdue.</p>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Auto-Invoice Lead Time (Days Before Cycle)</label>
              <input v-model.number="form.auto_invoice_days_before" type="number" min="0" max="90" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
              <p class="text-[11px] text-slate-400 mt-1">Days prior to membership renewal to dispatch automated invoices.</p>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Membership Year Start Date</label>
              <input v-model="form.membership_year_start" type="date" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
              <p class="text-[11px] text-slate-400 mt-1">Official annual start date for club membership dues and subscription billing.</p>
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">VAT / Tax Registration Number</label>
              <input v-model="form.tax_registration_number" type="text" placeholder="GB 987 6543 21" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono" />
            </div>

            <div class="sm:col-span-2">
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Receipt & Invoice Footer Notes</label>
              <textarea v-model="form.receipt_footer_notes" rows="3" placeholder="Thank you for supporting our club." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
          </div>
        </div>
      </div>

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
                    'px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2 cursor-pointer',
                    selectedProvider === 'stripe'
                      ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20'
                      : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="w-2 h-2 rounded-full bg-blue-300"></span>
                  Stripe (Direct / Cards)
                </button>

                <button
                  type="button"
                  @click="switchProvider('paddle')"
                  :disabled="processingProvider"
                  :class="[
                    'px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2 cursor-pointer',
                    selectedProvider === 'paddle'
                      ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20'
                      : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                  ]"
                >
                  <span class="w-2 h-2 rounded-full bg-blue-300"></span>
                  Paddle (MoR / Tax Compliant)
                </button>
              </div>

              <a :href="route('billing.portal', { clubSlug: props.club.slug })" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 transition-all flex items-center gap-1.5">
                <span>⚙️ Customer Portal</span>
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
      <div v-if="activeTab === 'events'" class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-800/60 dark:bg-blue-950/30">
          <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">💳 Payment options for events and bookings</h3>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">Bank transfer, card, PayPal, pay later and pay on the night. Set them up and switch them on once here, and every event offers them.</p>
          </div>
          <a :href="paymentOptionsUrl('events')" class="rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-blue-700">Manage payment options</a>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">📅 Events & Attendance Policy</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Set RSVP deadlines, guest policies, and attendance QR code expiration limits.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default RSVP Cutoff (Hours Before Event)</label>
              <input v-model.number="form.event_rsvp_cutoff_hours" type="number" min="0" max="168" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Max Guests Per Member</label>
              <input v-model.number="form.max_guests_per_member" type="number" min="0" max="20" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Attendance QR Code Expiry (Minutes)</label>
              <input v-model.number="form.qr_code_expiry_minutes" type="number" min="5" max="1440" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            </div>
          </div>

          <div class="pt-2">
            <label class="flex items-center gap-3 cursor-pointer text-xs">
              <input type="checkbox" v-model="form.notify_event_reminders" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
              <div>
                <div class="font-bold text-slate-900 dark:text-white">Automated Event Reminders</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400">Send automated email reminders to confirmed attendees 48 hours before events.</div>
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- TAB 6: DINING & RSVPs -->
      <div v-if="activeTab === 'dining'" class="space-y-6">
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

      <!-- TAB 7: COMMUNICATIONS -->
      <div v-if="activeTab === 'communications'" class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">✉️ Email Broadcasts & Sender Settings</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Set default email signatures, reply-to addresses, and notification dispatch rules.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Email Sender From Name</label>
              <input v-model="form.email_from_name" type="text" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Reply-To Email Address</label>
              <input v-model="form.email_reply_to" type="email" placeholder="admin@club.org" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-semibold" />
            </div>

            <div class="sm:col-span-2">
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Email Footer Physical Address / Compliance Text</label>
              <input v-model="form.email_footer_address" type="text" placeholder="100 Boathouse Way, Oxford, UK" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>

          <div class="pt-2 text-xs">
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="form.notify_dues_overdue" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
              <div>
                <div class="font-bold text-slate-900 dark:text-white">Automated Overdue Dues Notices</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400">Send reminder emails when membership dues invoices pass their grace period.</div>
              </div>
            </label>
          </div>
        </div>
      </div>



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
      <div v-if="activeTab === 'bookings'" class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">🚣 Equipment & Facility Bookings</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Rules for boat bay, pitch, or clubhouse facility reservations.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Advance Booking Window (Days)</label>
              <input v-model.number="form.booking_window_days" type="number" min="1" max="365" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Max Duration Per Reservation (Hours)</label>
              <input v-model.number="form.max_booking_hours" type="number" min="1" max="24" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-bold" />
            </div>
          </div>

          <div class="pt-2 text-xs">
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="form.require_coach_approval_equipment" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
              <div>
                <div class="font-bold text-slate-900 dark:text-white">Require Coach Approval</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400">Require coach sign-off before high-performance racing shells or specialized gear bookings are confirmed.</div>
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- TAB 11: FEATURE MODULES -->
      <div v-if="activeTab === 'modules'" class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">⚡ Active Club Feature Modules</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Toggle interactive tools enabled for administrators and members in this club workspace.</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="mod in allModules"
              :key="mod.code"
              @click="toggleModule(mod.code)"
              :class="[
                'p-4 rounded-2xl border transition-all cursor-pointer flex items-start justify-between gap-4',
                form.enabled_modules.includes(mod.code)
                  ? 'bg-blue-50/50 dark:bg-blue-950/50 border-blue-300 dark:border-blue-700/60 ring-2 ring-blue-500/20'
                  : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 opacity-60'
              ]"
            >
              <div class="flex items-start gap-3">
                <span class="text-2xl">{{ mod.icon }}</span>
                <div>
                  <div class="font-bold text-slate-900 dark:text-white text-xs sm:text-sm">{{ mod.name }}</div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ mod.description }}</div>
                </div>
              </div>

              <div :class="['w-10 h-6 rounded-full transition-colors flex items-center p-1', form.enabled_modules.includes(mod.code) ? 'bg-blue-600' : 'bg-slate-300']">
                <div :class="['w-4 h-4 rounded-full bg-white shadow-md transform transition-transform', form.enabled_modules.includes(mod.code) ? 'translate-x-4' : 'translate-x-0']"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

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
            <label v-for="(mName, idx) in MONTH_NAMES" :key="idx" class="flex items-center gap-1.5 p-2 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-200 dark:border-slate-800 cursor-pointer">
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
