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
import PositionsSettingsTab from '@/Components/Settings/PositionsSettingsTab.vue';
import PositionRemovalNotices from '@/Components/Settings/PositionRemovalNotices.vue';
import RanksSettingsTab from '@/Components/Settings/RanksSettingsTab.vue';
import PaymentsSettingsTab from '@/Components/Settings/PaymentsSettingsTab.vue';
import NewsTagsSettingsTab from '@/Components/Settings/NewsTagsSettingsTab.vue';
import MeetingFormulaBuilderModal from '@/Components/Settings/MeetingFormulaBuilderModal.vue';
import SettingsSectionNav from '@/Components/Settings/SettingsSectionNav.vue';
import { ref, computed, reactive } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MediaLibraryModal from '@/Components/MediaLibraryModal.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { useClubSettingsForm } from '@/Composables/useClubSettingsForm';
import { useSettingsTab } from '@/Composables/useSettingsTab';
import { useClubHalls } from '@/Composables/useClubHalls';
import { useClubPositions } from '@/Composables/useClubPositions';

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

const activeTab = useSettingsTab(validTabs);

// Primary Settings Form
const form = useClubSettingsForm(props);

const { hallsOnly, otherVenues, hallLabel, selectedHallAddress, onProvinceChange, onHallChange } = useClubHalls(form, () => props.masonicHalls);

// Kept on the page so the undo notice outlasts a switch to another tab.
const positions = reactive(useClubPositions(form, props));

const showFormulaBuilder = ref(false);
const openFormulaBuilder = () => {
  showFormulaBuilder.value = true;
};

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
              <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Club settings</h2>
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
        <SettingsSectionNav v-model="activeTab" />

        <!-- Right Content Panel -->
        <div class="lg:col-span-3 space-y-6">

      <!-- TAB 1: GENERAL SETTINGS -->
      <GeneralSettingsTab v-if="activeTab === 'general'" :form="form" :hall-label="hallLabel" :halls-only="hallsOnly" :on-hall-change="onHallChange" :on-province-change="onProvinceChange" :open-formula-builder="openFormulaBuilder" :other-venues="otherVenues" :selected-hall-address="selectedHallAddress" :provinces="provinces" />

      <!-- TAB: CLUB POSITIONS & RANKS -->
      <PositionsSettingsTab v-if="activeTab === 'positions'" :form="form" :positions="positions" />

      <!-- TAB: GRAND & PROVINCIAL RANKS -->
      <RanksSettingsTab v-if="activeTab === 'ranks'" :form="form" :ranks="ranks" :reset-ranks="resetRanks" />

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
      <PaymentsSettingsTab v-if="activeTab === 'payments'" :club="club" :selected-provider="selectedProvider" :processing-provider="processingProvider" :switch-provider="switchProvider" :stripe-configured="stripeConfigured" :paddle-configured="paddleConfigured" :payment-options-url="paymentOptionsUrl" />

      <!-- TAB 5: EVENTS & CHECK-INS -->
      <EventsSettingsTab v-if="activeTab === 'events'" :form="form" :payment-options-url="paymentOptionsUrl" />

      <!-- TAB 6: DINING & RSVPs -->
      <DiningSettingsTab v-if="activeTab === 'dining'" :form="form" />

      <!-- TAB 7: COMMUNICATIONS -->
      <CommunicationsSettingsTab v-if="activeTab === 'communications'" :form="form" />


      <!-- TAB: NEWS TAGS -->
      <NewsTagsSettingsTab v-if="activeTab === 'news-tags'" :club="club" :news-tags="newsTags" />

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

    <!-- Position removal warning and undo notice -->
    <PositionRemovalNotices :form="form" :positions="positions" />

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
    <MeetingFormulaBuilderModal v-model:open="showFormulaBuilder" :form="form" />

  </AdminLayout>
</template>
