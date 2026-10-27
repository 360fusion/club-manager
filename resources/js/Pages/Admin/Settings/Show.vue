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
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import Input from '@/Components/Ui/Input.vue';
import Modal from '@/Components/Ui/Modal.vue';
import Select from '@/Components/Ui/Select.vue';
import ToggleSwitch from '@/Components/Ui/ToggleSwitch.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const showMediaModal = ref(false);

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
  <AdminLayout :title="`${club.name} settings`" :club="club" active-tab="profile">
    <Head :title="`${club.name} settings`" />

    <div class="space-y-6 max-w-6xl mx-auto">
      
      <!-- Top Action Bar & Header -->
      <Card>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex flex-wrap items-center gap-3">
              <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Club settings</h1>
              <Badge variant="info">{{ club.slug }}</Badge>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Configure policies, feature modules, branding, automated notifications, and permission matrices for your club.</p>
          </div>

          <Button class="shrink-0 self-start whitespace-nowrap sm:self-auto" :loading="form.processing" @click="submitSettings">
            <template #icon><UiIcon v-if="!form.processing" name="check" /></template>
            {{ form.processing ? 'Saving...' : (isSavedSuccess ? 'Saved' : 'Save changes') }}
          </Button>
        </div>
      </Card>

      <!-- 2-Column Responsive Settings Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        
        <!-- Left Settings Sidebar Navigation -->
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

        <!-- Right Content Panel -->
        <div class="lg:col-span-3 space-y-6">

      <!-- TAB 1: GENERAL SETTINGS -->
      <GeneralSettingsTab v-if="activeTab === 'general'" :form="form" :hall-label="hallLabel" :halls-only="hallsOnly" :on-hall-change="onHallChange" :on-province-change="onProvinceChange" :open-formula-builder="openFormulaBuilder" :other-venues="otherVenues" :selected-hall-address="selectedHallAddress" :provinces="provinces" />

      <!-- TAB: CLUB POSITIONS & RANKS -->
      <div v-if="activeTab === 'positions'" class="space-y-6">
        <Card>
          <template #header>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Club positions and member ranks</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Configure customizable member positions, ranks, and skill levels to categorize, select, and filter club members across the platform.</p>
          </template>

          <div class="space-y-4">
            <div class="flex items-center justify-between gap-4">
              <div>
                <h3 class="text-sm font-medium text-slate-900 dark:text-white">Member ranks and skill levels</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Enable customized member ranks (e.g. Novice, Captain, Veteran) to filter and select members across the platform.</p>
              </div>
              <ToggleSwitch v-model="form.enable_member_ranks" label="Member ranks and skill levels" on-text="On" off-text="Off" />
            </div>

            <div v-if="form.enable_member_ranks" class="space-y-4 pt-4 border-t border-slate-200 dark:border-slate-800">
              <div>
                <h3 class="text-sm font-medium text-slate-900 dark:text-white">Positions and ranks</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Define the positions or skill ranks available for members in your organization.</p>
              </div>

              <!-- Vertical Ranks List Container -->
              <div class="max-w-lg space-y-2">
                <div
                  v-for="(rank, idx) in form.member_ranks"
                  :key="idx"
                  class="flex items-center justify-between gap-2 p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl hover:border-slate-300 dark:hover:border-slate-700 transition-colors"
                >
                  <!-- Viewing State -->
                  <template v-if="editingRankIndex !== idx">
                    <div class="flex items-center gap-3">
                      <!-- Reorder Controls -->
                      <div class="flex flex-col items-center justify-center gap-0.5">
                        <button
                          type="button"
                          @click="moveRankUp(idx)"
                          :disabled="idx === 0"
                          title="Move up"
                          aria-label="Move up"
                          class="flex items-center justify-center rounded text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 disabled:opacity-20 disabled:hover:text-slate-400 disabled:hover:bg-transparent cursor-pointer transition-colors"
                        >
                          <UiIcon name="chevron-down" :size="14" class="rotate-180" />
                        </button>
                        <button
                          type="button"
                          @click="moveRankDown(idx)"
                          :disabled="idx === form.member_ranks.length - 1"
                          title="Move down"
                          aria-label="Move down"
                          class="flex items-center justify-center rounded text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 disabled:opacity-20 disabled:hover:text-slate-400 disabled:hover:bg-transparent cursor-pointer transition-colors"
                        >
                          <UiIcon name="chevron-down" :size="14" />
                        </button>
                      </div>

                      <span class="w-7 h-7 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/60 flex items-center justify-center">
                        <UiIcon name="award" :size="14" />
                      </span>
                      <span class="text-sm font-medium text-slate-900 dark:text-white">{{ rank }}</span>
                      <Badge v-if="getMemberCountForRank(rank) > 0" variant="info">
                        {{ getMemberCountForRank(rank) }} assigned
                      </Badge>
                    </div>

                    <div class="flex items-center gap-1.5">
                      <Button variant="secondary" size="sm" @click="startEditRank(idx)">Edit</Button>
                      <Button variant="secondary" size="sm" @click="initiateRemoveRank(idx)">Remove</Button>
                    </div>
                  </template>

                  <!-- Editing State -->
                  <template v-else>
                    <div class="flex items-center gap-2 w-full">
                      <span class="w-7 h-7 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center flex-shrink-0">
                        <UiIcon name="pencil" :size="14" />
                      </span>
                      <Input
                        v-model="editingRankValue"
                        @keydown.enter.prevent="saveEditRank(idx)"
                        @keydown.esc="cancelEditRank"
                        aria-label="Position name"
                        class="flex-1"
                      />
                      <Button size="sm" variant="secondary" @click="saveEditRank(idx)">Save</Button>
                      <Button size="sm" variant="ghost" @click="cancelEditRank">Cancel</Button>
                    </div>
                  </template>
                </div>

                <div v-if="form.member_ranks.length === 0" class="p-4 text-center text-sm text-slate-500 dark:text-slate-400 border border-dashed border-slate-300 dark:border-slate-700 rounded-xl">
                  No positions configured yet. Add your first position below.
                </div>
              </div>

              <!-- Add New Rank Form Row -->
              <div class="flex gap-2 max-w-lg pt-1">
                <Input
                  v-model="newRankInput"
                  @keydown.enter.prevent="addRank"
                  placeholder="Add a position or rank (e.g. Captain, Coxswain, Senior)"
                  aria-label="New position or rank"
                  class="flex-1"
                />
                <Button variant="secondary" @click="addRank">
                  <template #icon><UiIcon name="plus" /></template>
                  Add position
                </Button>
              </div>
            </div>
          </div>
        </Card>
      </div>

      <!-- TAB: GRAND & PROVINCIAL RANKS -->
      <div v-if="activeTab === 'ranks'" class="space-y-6">
        <Card>
          <template #header>
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
              <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Grand and Provincial ranks</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">The ranks you can pick from when adding or editing a member. The abbreviation is what appears after their name; the full title helps people choose the right one. Save the page to keep your changes.</p>
              </div>
              <Button variant="secondary" size="sm" class="shrink-0" @click="resetRanks">
                Reset to the {{ ranks.grandLodgeName || 'standard' }} list
              </Button>
            </div>
          </template>

          <div class="space-y-8">

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

          <p class="text-xs text-slate-500 dark:text-slate-400">Removing or renaming a rank here never changes a member who already holds it. Their rank stays selectable on their own record.</p>
          </div>
        </Card>
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
        <Alert title="Payment options for events and bookings">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <p>Bank transfer, card, PayPal, pay later and pay on the night. Set them up and switch them on once here, and every event offers them.</p>
            <a :href="paymentOptionsUrl('payments')" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Manage payment options</a>
          </div>
        </Alert>

        <Card>
          <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Subscription payment provider</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                  Select which payment gateway handles member subscription checkouts and recurring dues for this club. Payments for events and bookings are set up separately, in Payment options above.
                </p>
              </div>

              <div class="flex items-center gap-3 flex-wrap">
                <div class="flex items-center gap-1 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 dark:border-slate-700/60 dark:bg-slate-800/80">
                  <button
                    type="button"
                    @click="switchProvider('stripe')"
                    :disabled="processingProvider"
                    :aria-pressed="selectedProvider === 'stripe'"
                    :class="[
                      'px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer disabled:opacity-50',
                      selectedProvider === 'stripe'
                        ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-700'
                        : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white'
                    ]"
                  >
                    Stripe (direct / cards)
                  </button>

                  <button
                    type="button"
                    @click="switchProvider('paddle')"
                    :disabled="processingProvider"
                    :aria-pressed="selectedProvider === 'paddle'"
                    :class="[
                      'px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer disabled:opacity-50',
                      selectedProvider === 'paddle'
                        ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-700'
                        : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white'
                    ]"
                  >
                    Paddle (MoR / tax compliant)
                  </button>
                </div>

                <a :href="route('billing.portal', { clubSlug: props.club.slug })" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                  <UiIcon name="settings" />
                  Customer portal
                </a>
              </div>
            </div>
          </template>

          <!-- Gateway Specs Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-900 dark:text-white">Stripe</span>
                <Badge :variant="stripeConfigured ? 'success' : 'warning'">
                  {{ stripeConfigured ? 'Keys active' : 'Sandbox demo mode' }}
                </Badge>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                Direct card processing with Stripe Elements &amp; Cashier subscription webhooks. Ideal for direct card payments.
              </p>
            </div>

            <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-900 dark:text-white">Paddle</span>
                <Badge :variant="paddleConfigured ? 'success' : 'warning'">
                  {{ paddleConfigured ? 'Keys active' : 'Sandbox demo mode' }}
                </Badge>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                Merchant of Record billing, automatic international sales tax &amp; VAT remittance for international country members.
              </p>
            </div>
          </div>
        </Card>
      </div>

      <!-- TAB 5: EVENTS & CHECK-INS -->
      <EventsSettingsTab v-if="activeTab === 'events'" :form="form" :payment-options-url="paymentOptionsUrl" />

      <!-- TAB 6: DINING & RSVPs -->
      <DiningSettingsTab v-if="activeTab === 'dining'" :form="form" />

      <!-- TAB 7: COMMUNICATIONS -->
      <CommunicationsSettingsTab v-if="activeTab === 'communications'" :form="form" />



      <!-- TAB: NEWS TAGS -->
      <div v-if="activeTab === 'news-tags'" class="space-y-6">
        <Card>
          <template #header>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">News tags</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">The tags available when writing a news item. Editors can only choose from this list, and members can filter news by them. Tags are saved as you add or edit them; you don't need to press Save changes.</p>
          </template>
          <NewsTagsManager :club-slug="club.slug" :tags="newsTags" />
        </Card>
      </div>

      <!-- TAB 9: BOOKINGS -->
      <BookingsSettingsTab v-if="activeTab === 'bookings'" :form="form" />

      <!-- TAB 11: FEATURE MODULES -->
      <ModulesSettingsTab v-if="activeTab === 'modules'" :form="form" :toggle-module="toggleModule" :all-modules="allModules" />

      <!-- Bottom Save Action Bar -->
      <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
        <Button variant="secondary" :disabled="form.processing" @click="submitSettings">
          <template #icon><UiIcon name="check" /></template>
          Save all settings
        </Button>
      </div>

        </div>
      </div>

    </div>

    <!-- Modal: Position Deletion Warning & Confirmation Modal -->
    <ConfirmDialog
      :open="showDeleteWarningModal"
      title="Remove this position?"
      confirm-label="Remove position"
      @confirm="confirmDeleteRank"
      @close="showDeleteWarningModal = false"
    >
      <div v-if="pendingDeleteRank" class="space-y-4">
        <!-- Case 1: Assigned to active members -->
        <template v-if="pendingDeleteRank.affectedMembers.length > 0">
          <Alert variant="warning" :title="`“${pendingDeleteRank.rank}” is assigned to ${pendingDeleteRank.affectedMembers.length} member(s).`">
            Affected members: <span class="font-medium">{{ pendingDeleteRank.affectedMembers.map(m => m.name).slice(0, 4).join(', ') }}{{ pendingDeleteRank.affectedMembers.length > 4 ? ` +${pendingDeleteRank.affectedMembers.length - 4} more` : '' }}</span>.
          </Alert>

          <Select
            v-model="pendingDeleteRank.reassignRank"
            label="What happens to these members"
            :options="[
              { value: '', label: 'Clear their position' },
              ...form.member_ranks.filter(r => r !== pendingDeleteRank.rank).map(r => ({ value: r, label: `Reassign to ${r}` })),
            ]"
          />
        </template>

        <!-- Case 2: 0 members assigned -->
        <template v-else>
          <p>
            Remove the position <span class="font-medium text-slate-900 dark:text-white">“{{ pendingDeleteRank.rank }}”</span>?
          </p>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            This position will be removed from your club's active positions list. You can undo this action immediately after deleting.
          </p>
        </template>
      </div>
    </ConfirmDialog>

    <!-- Toast Banner: Undo Position Deletion -->
    <div
      v-if="showUndoToast && lastDeletedRank"
      role="status"
      class="fixed bottom-6 right-6 z-50 bg-slate-900 text-white px-5 py-3.5 rounded-2xl shadow-2xl border border-slate-700 flex items-center justify-between gap-4 text-sm"
    >
      <div class="flex items-center gap-2.5">
        <UiIcon name="trash" class="text-slate-400" />
        <span>Position <strong class="font-semibold">“{{ lastDeletedRank.rank }}”</strong> was removed.</span>
      </div>
      <div class="flex items-center gap-3">
        <Button size="sm" @click="undoDeleteRank">
          <template #icon><UiIcon name="restore" :size="14" /></template>
          Undo
        </Button>
        <button
          type="button"
          @click="showUndoToast = false"
          aria-label="Dismiss"
          class="rounded-xl p-1 text-slate-400 hover:text-white cursor-pointer"
        >
          <UiIcon name="x" />
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
      <div v-if="isSavedSuccess" role="status" class="fixed bottom-6 right-6 z-50 bg-emerald-900 text-white px-5 py-3.5 rounded-2xl shadow-2xl border border-emerald-700/80 flex items-center gap-3.5">
        <span class="w-7 h-7 rounded-full bg-emerald-500 text-slate-950 flex items-center justify-center">
          <UiIcon name="check" :size="14" />
        </span>
        <div>
          <div class="text-sm font-semibold text-white">Club settings saved</div>
          <div class="text-xs text-emerald-200">Your organization configuration has been updated.</div>
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
    <Modal
      :open="showFormulaBuilder"
      title="Build the meeting formula rule"
      subtitle="Pick the occurrence, weekday and the months the lodge meets."
      size="lg"
      @close="showFormulaBuilder = false"
    >
      <div class="space-y-5">
        <div class="grid grid-cols-2 gap-4">
          <Select
            v-model="formulaBuilder.occurrence"
            label="Occurrence"
            :options="[
              { value: '1st', label: '1st' },
              { value: '2nd', label: '2nd' },
              { value: '3rd', label: '3rd' },
              { value: '4th', label: '4th' },
              { value: 'last', label: 'Last' },
            ]"
          />

          <Select
            v-model="formulaBuilder.day_of_week"
            label="Day of the week"
            :options="DAY_NAMES.map((d) => ({ value: d, label: d }))"
          />
        </div>

        <fieldset>
          <legend class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">Meeting months (untick the recess)</legend>
          <div class="grid grid-cols-4 gap-2 text-sm">
            <label v-for="(mName, idx) in MONTH_NAMES" :key="idx" class="flex items-center gap-1.5 p-2 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 cursor-pointer text-slate-700 dark:text-slate-200">
              <input type="checkbox" :value="idx + 1" v-model="formulaBuilder.months" class="h-4 w-4 rounded accent-blue-600" />
              <span>{{ mName }}</span>
            </label>
          </div>
        </fieldset>

        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl">
          <span class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Resulting rule</span>
          <span class="font-mono font-medium text-sm text-slate-900 dark:text-white">{{ builtFormula }}</span>
        </div>
      </div>

      <template #footer>
        <Button variant="secondary" @click="showFormulaBuilder = false">Cancel</Button>
        <Button @click="applyFormulaBuilder">Use this rule</Button>
      </template>
    </Modal>

  </AdminLayout>
</template>
