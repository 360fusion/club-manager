<script setup>
import RosterQuickMemberModal from '@/Components/Officers/RosterQuickMemberModal.vue';
import RosterNewYearModal from '@/Components/Officers/RosterNewYearModal.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Tabs from '@/Components/Ui/Tabs.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
  club: Object,
  rosters: Array,
  members: Array,
  offices: Array,
  installationMonth: String,
  grandRanks: { type: Array, default: () => [] },
  provincialRanks: { type: Array, default: () => [] },
});

// The other three pages are Livewire, so they need a full page load. The same list is in livewire/members/_tabs.
const memberAreaTabs = [
  { key: 'members', label: 'Members roster', href: route('admin.club_acc.members.index', { clubSlug: props.club.slug }), reload: true },
  { key: 'officers', label: 'Annual officer rosters & history', href: route('admin.officers.index', { clubSlug: props.club.slug }) },
  { key: 'candidates', label: 'Candidates (Form P vetting)', href: route('admin.club_acc.candidates.index', { clubSlug: props.club.slug }), reload: true },
  { key: 'subscriptions', label: 'Subscriptions & dues', href: route('admin.club_acc.subscriptions.index', { clubSlug: props.club.slug }), reload: true },
];

const localMembers = ref([...(props.members || [])]);

const activeYear = ref(props.rosters.length ? props.rosters[0].masonic_year : '2026-2027');
const showNewYearModal = ref(false);
const currentYearNum = new Date().getFullYear();

// Quick Member Modal State
const showQuickMemberModal = ref(false);
const quickMemberTargetOffice = ref(null);
const quickMemberTargetType = ref(null);

const quickMemberForm = ref({
  first_name: '',
  last_name: '',
  membership_status: 'historical',
  masonic_rank: 'Bro',
  grand_rank: '',
  provincial_rank: '',
});

const isCreatingMember = ref(false);
const quickMemberError = ref('');

const openQuickMemberModal = (officeCode = null, officeType = null) => {
  quickMemberTargetOffice.value = officeCode;
  quickMemberTargetType.value = officeType;
  quickMemberForm.value = {
    first_name: '',
    last_name: '',
    membership_status: 'historical',
    masonic_rank: 'Bro',
    grand_rank: '',
    provincial_rank: '',
  };
  quickMemberError.value = '';
  showQuickMemberModal.value = true;
};

const submitQuickMember = async () => {
  if (!quickMemberForm.value.first_name.trim() || !quickMemberForm.value.last_name.trim()) {
    quickMemberError.value = 'First name and surname are required.';
    return;
  }

  isCreatingMember.value = true;
  quickMemberError.value = '';

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const response = await fetch(route('admin.officers.quick_member', { clubSlug: props.club.slug }), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken || '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify(quickMemberForm.value),
    });

    const data = await response.json();

    if (response.ok && data.success && data.member) {
      const newMember = data.member;
      localMembers.value.push(newMember);

      // Auto-assign to target office if opened from a specific office '+' button
      if (quickMemberTargetType.value === 'progressive' && quickMemberTargetOffice.value) {
        progressiveAssignments.value[quickMemberTargetOffice.value] = newMember.id;
      } else if (quickMemberTargetType.value === 'admin' && quickMemberTargetOffice.value) {
        adminAssignments.value[quickMemberTargetOffice.value] = newMember.id;
      } else if (quickMemberTargetType.value === 'steward') {
        if (!stewardAssignments.value.includes(newMember.id)) {
          stewardAssignments.value.push(newMember.id);
        }
      } else if (quickMemberTargetType.value === 'committee') {
        if (!committeeAssignments.value.includes(newMember.id)) {
          committeeAssignments.value.push(newMember.id);
        }
      }

      showQuickMemberModal.value = false;
    } else {
      quickMemberError.value = data.message || 'Failed to create member.';
    }
  } catch (err) {
    quickMemberError.value = err.message || 'Failed to create member.';
  } finally {
    isCreatingMember.value = false;
  }
};

// Set of start years that have already been created in rosters
const existingStartYears = computed(() => {
  const set = new Set();
  (props.rosters || []).forEach(r => {
    if (r.start_year) {
      set.add(Number(r.start_year));
    } else if (r.masonic_year) {
      const parts = r.masonic_year.split('-');
      if (parts.length > 0 && !isNaN(parts[0])) {
        set.add(Number(parts[0]));
      }
    }
  });
  return set;
});

const startYearOptions = computed(() => {
  const years = [];
  const existing = existingStartYears.value;
  // Allow going back to 1717 (Premier Grand Lodge founding), and up to 3 years into the future, excluding existing rosters
  for (let y = currentYearNum + 3; y >= 1717; y--) {
    if (!existing.has(y)) {
      years.push(y);
    }
  }
  return years;
});

const yearSearchInput = ref('');

const filteredStartYearOptions = computed(() => {
  if (!yearSearchInput.value.trim()) {
    return startYearOptions.value;
  }
  const query = yearSearchInput.value.trim();
  return startYearOptions.value.filter(y => y.toString().includes(query));
});

const getNextSequentialStartYear = () => {
  const existing = existingStartYears.value;
  let maxExistingYear = null;
  existing.forEach(y => {
    if (maxExistingYear === null || y > maxExistingYear) {
      maxExistingYear = y;
    }
  });

  if (maxExistingYear !== null) {
    const candidateNext = maxExistingYear + 1;
    if (!existing.has(candidateNext) && candidateNext <= currentYearNum + 3) {
      return candidateNext;
    }
  }

  if (!existing.has(currentYearNum + 1) && (currentYearNum + 1) <= currentYearNum + 3) {
    return currentYearNum + 1;
  }

  if (!existing.has(currentYearNum)) {
    return currentYearNum;
  }

  return startYearOptions.value.length ? startYearOptions.value[0] : currentYearNum;
};

const selectedStartYear = ref(getNextSequentialStartYear());

const calculatedEndYear = computed(() => Number(selectedStartYear.value) + 1);
const calculatedMasonicYear = computed(() => `${selectedStartYear.value}-${calculatedEndYear.value}`);

const openNewYearModal = () => {
  yearSearchInput.value = '';
  selectedStartYear.value = getNextSequentialStartYear();
  showNewYearModal.value = true;
};

// Filter toggle for administrative offices: active by default
const onlyNonOfficersAdminFilter = ref(true);

const singleProgressiveOffices = computed(() => (props.offices || []).filter(o => o.is_progressive && o.value !== 'steward'));

const adminOrder = ['chaplain', 'treasurer', 'secretary', 'assistant_sec', 'dc', 'assistant_dc', 'almoner', 'charity_steward', 'organist', 'tyler'];

const administrativeOffices = computed(() => {
  const list = (props.offices || []).filter(o => o.is_administrative);
  return list.map(o => ({
    ...o,
    is_assistant: o.value.startsWith('assistant_'),
  })).sort((a, b) => {
    const idxA = adminOrder.indexOf(a.value);
    const idxB = adminOrder.indexOf(b.value);
    return (idxA !== -1 ? idxA : 99) - (idxB !== -1 ? idxB : 99);
  });
});

const currentRoster = computed(() => {
  return (props.rosters || []).find(r => r.masonic_year === activeYear.value) || null;
});

// Helper to get previous year's roster object
const getPreviousYearRoster = (yearStr) => {
  const parts = yearStr.split('-');
  if (parts.length === 2) {
    const prevStart = parseInt(parts[0]) - 1;
    const prevEnd = parseInt(parts[1]) - 1;
    const prevYearStr = `${prevStart}-${prevEnd}`;
    return (props.rosters || []).find(r => r.masonic_year === prevYearStr);
  }
  return null;
};

// Helper to get previous year's officer member ID for an office
const getPreviousYearOfficerId = (officeCode) => {
  const prevRoster = getPreviousYearRoster(activeYear.value);
  if (!prevRoster || !prevRoster.assignments) return null;
  const assignment = prevRoster.assignments.find(a => a.office === officeCode);
  return assignment ? assignment.member_id : null;
};

// Helper to get previous year's stewards member IDs
const getPreviousYearStewardIds = () => {
  const prevRoster = getPreviousYearRoster(activeYear.value);
  if (!prevRoster || !prevRoster.assignments) return [];
  return prevRoster.assignments.filter(a => a.office === 'steward').map(a => a.member_id);
};

// Helper to get previous year's committee members member IDs
const getPreviousYearCommitteeMemberIds = () => {
  const prevRoster = getPreviousYearRoster(activeYear.value);
  if (!prevRoster || !prevRoster.assignments) return [];
  return prevRoster.assignments.filter(a => a.office === 'committee_member').map(a => a.member_id);
};

// Helper to get previous year's officer member display name
const getPreviousYearOfficerName = (officeCode) => {
  const prevRoster = getPreviousYearRoster(activeYear.value);
  if (!prevRoster || !prevRoster.assignments) return null;
  const assignment = prevRoster.assignments.find(a => a.office === officeCode);
  if (!assignment || !assignment.member) return null;
  return assignment.member.formatted_rank_name || `${assignment.member.first_name} ${assignment.member.last_name}`;
};

// Map of office_code -> member_id for Single Progressive Offices
const progressiveAssignments = ref({});

// Array of member_ids for Stewards (Multi-select)
const stewardAssignments = ref([]);

// Array of member_ids for Committee Members (Multi-select)
const committeeAssignments = ref([]);

// Map of office_code -> member_id for Administrative Offices
const adminAssignments = ref({});

// Search query filter for stewards list
const stewardSearchQuery = ref('');

// Search query filter for committee list
const committeeSearchQuery = ref('');

// Filter toggle for committee members candidate list: only show officers by default
const onlyOfficersForCommittee = ref(true);

// Filter toggles for stewards candidate list: active by default
const excludeProgressiveForStewards = ref(true);
const excludeAdminForStewards = ref(true);

// Set of member IDs currently assigned to single progressive offices
const progressiveOfficerIds = computed(() => {
  const ids = new Set();
  Object.values(progressiveAssignments.value).forEach(id => {
    if (id) ids.add(Number(id));
  });
  return ids;
});

// Set of member IDs currently assigned to administrative offices
const adminOfficerIds = computed(() => {
  const ids = new Set();
  Object.values(adminAssignments.value).forEach(id => {
    if (id) ids.add(Number(id));
  });
  return ids;
});

// Set of all member IDs currently assigned to any officer role (progressive or administrative)
const allOfficerIds = computed(() => {
  const ids = new Set();
  Object.values(progressiveAssignments.value).forEach(id => {
    if (id) ids.add(Number(id));
  });
  Object.values(adminAssignments.value).forEach(id => {
    if (id) ids.add(Number(id));
  });
  return ids;
});

// Steward candidate list filtered by search query and officer toggles
const stewardMembers = computed(() => {
  const query = stewardSearchQuery.value.trim().toLowerCase();
  return (localMembers.value || []).filter(m => {
    if (query) {
      const matchText = `${m.name || ''} ${m.full_name || ''}`.toLowerCase();
      if (!matchText.includes(query)) {
        return false;
      }
    }

    if (isSteward(m.id)) return true;

    if (excludeProgressiveForStewards.value && progressiveOfficerIds.value.has(m.id)) {
      return false;
    }

    if (excludeAdminForStewards.value && adminOfficerIds.value.has(m.id)) {
      return false;
    }

    return true;
  });
});

// Committee candidate list filtered by search query and onlyOfficers toggle
const committeeMembers = computed(() => {
  const query = committeeSearchQuery.value.trim().toLowerCase();
  return (localMembers.value || []).filter(m => {
    if (query) {
      const matchText = `${m.name || ''} ${m.full_name || ''}`.toLowerCase();
      if (!matchText.includes(query)) {
        return false;
      }
    }

    if (isCommitteeMember(m.id)) return true;

    if (onlyOfficersForCommittee.value && !allOfficerIds.value.has(m.id)) {
      return false;
    }

    return true;
  });
});

// Options list for an Administrative office filtered by non-officers
const getAdminMemberOptions = (officeCode) => {
  if (!onlyNonOfficersAdminFilter.value) {
    return localMembers.value || [];
  }

  const currentAssignedId = Number(adminAssignments.value[officeCode]);

  const otherAssignedIds = new Set();

  // Progressive offices
  Object.values(progressiveAssignments.value).forEach(id => {
    if (id) otherAssignedIds.add(Number(id));
  });

  // Other administrative offices
  Object.entries(adminAssignments.value).forEach(([off, id]) => {
    if (off !== officeCode && id) {
      otherAssignedIds.add(Number(id));
    }
  });

  return (localMembers.value || []).filter(m => {
    return !otherAssignedIds.has(m.id) || m.id === currentAssignedId;
  });
};

const notes = ref('');

const loadRosterAssignments = (year) => {
  activeYear.value = year;
  const roster = (props.rosters || []).find(r => r.masonic_year === year);
  
  const progMap = {};
  const adminMap = {};
  const stewList = [];
  const commList = [];
  
  if (roster && roster.assignments) {
    notes.value = roster.notes || '';
    roster.assignments.forEach(a => {
      if (a.office === 'committee_member') {
        commList.push(a.member_id);
      } else if (a.category === 'progressive') {
        if (a.office === 'steward') {
          stewList.push(a.member_id);
        } else {
          progMap[a.office] = a.member_id;
        }
      } else {
        adminMap[a.office] = a.member_id;
      }
    });
  } else {
    notes.value = '';
  }

  // Pre-populate unassigned administrative offices with previous year's officer as default
  administrativeOffices.value.forEach(off => {
    if (!adminMap[off.value]) {
      const prevMemberId = getPreviousYearOfficerId(off.value);
      if (prevMemberId) {
        adminMap[off.value] = prevMemberId;
      }
    }
  });

  // Default stewards from previous year if empty
  if (stewList.length === 0) {
    const prevStewards = getPreviousYearStewardIds();
    if (prevStewards.length > 0) {
      stewList.push(...prevStewards);
    }
  }

  // Default committee members from previous year if empty
  if (commList.length === 0) {
    const prevCommittee = getPreviousYearCommitteeMemberIds();
    if (prevCommittee.length > 0) {
      commList.push(...prevCommittee);
    }
  }

  progressiveAssignments.value = progMap;
  stewardAssignments.value = stewList;
  adminAssignments.value = adminMap;
  committeeAssignments.value = commList;
};

// Initialize with first roster
if (props.rosters.length) {
  loadRosterAssignments(props.rosters[0].masonic_year);
}

const toggleSteward = (memberId) => {
  const idx = stewardAssignments.value.indexOf(memberId);
  if (idx >= 0) {
    stewardAssignments.value.splice(idx, 1);
  } else {
    stewardAssignments.value.push(memberId);
  }
};

const isSteward = (memberId) => {
  return stewardAssignments.value.includes(memberId);
};

const toggleCommitteeMember = (memberId) => {
  const idx = committeeAssignments.value.indexOf(memberId);
  if (idx >= 0) {
    committeeAssignments.value.splice(idx, 1);
  } else {
    committeeAssignments.value.push(memberId);
  }
};

const isCommitteeMember = (memberId) => {
  return committeeAssignments.value.includes(memberId);
};

const buildAssignmentPayload = () => {
  const payload = [];

  // Single Progressive Offices
  Object.keys(progressiveAssignments.value).forEach(officeCode => {
    const memId = progressiveAssignments.value[officeCode];
    if (memId) {
      payload.push({ member_id: Number(memId), office: officeCode });
    }
  });

  // Stewards (Multi-select Progressive)
  stewardAssignments.value.forEach(memId => {
    payload.push({ member_id: Number(memId), office: 'steward' });
  });

  // Committee Members (Multi-select)
  committeeAssignments.value.forEach(memId => {
    payload.push({ member_id: Number(memId), office: 'committee_member' });
  });

  // Administrative Offices
  Object.keys(adminAssignments.value).forEach(officeCode => {
    const memId = adminAssignments.value[officeCode];
    if (memId) {
      payload.push({ member_id: Number(memId), office: officeCode });
    }
  });

  return payload;
};

const isSaving = ref(false);
const errorMessage = ref('');

const saveRoster = (status = 'proposed') => {
  isSaving.value = true;
  errorMessage.value = '';

  const form = useForm({
    masonic_year: activeYear.value,
    assignments: buildAssignmentPayload(),
    notes: notes.value,
    status: status,
  });

  form.post(route('admin.officers.store', { clubSlug: props.club.slug }), {
    onFinish: () => {
      isSaving.value = false;
    },
    onError: (errors) => {
      errorMessage.value = errors.assignments || 'Validation error while saving roster.';
    },
  });
};

const createNewMasonicYear = () => {
  activeYear.value = calculatedMasonicYear.value;
  progressiveAssignments.value = {};
  adminAssignments.value = {};
  
  // Default administrative offices & stewards & committee members from previous year for new draft
  administrativeOffices.value.forEach(off => {
    const prevMemberId = getPreviousYearOfficerId(off.value);
    if (prevMemberId) {
      adminAssignments.value[off.value] = prevMemberId;
    }
  });

  stewardAssignments.value = [...getPreviousYearStewardIds()];
  committeeAssignments.value = [...getPreviousYearCommitteeMemberIds()];

  notes.value = '';
  showNewYearModal.value = false;
};

const installCurrentRoster = () => {
  if (!currentRoster.value) return;
  if (confirm(`Install and activate the officers for ${activeYear.value}? This will update member badges across the portal.`)) {
    router.post(route('admin.officers.install', { clubSlug: props.club.slug, id: currentRoster.value.id }));
  }
};
</script>

<template>
  <AdminLayout title="Annual officer rosters and history" :club="club" active-tab="members">
    <div class="space-y-6">

      <Tabs :items="memberAreaTabs" active="officers" label="Members" />

      <Card class="space-y-4">
        <div class="border-b border-slate-200 dark:border-slate-800 pb-6">
          <PageHeader title="Annual officer rosters and history" description="Manage annual officer rosters, committee approvals and installation rollovers.">
            <template #badge>
              <Badge variant="info">Installation month: {{ installationMonth }}</Badge>
            </template>
            <template #actions>
              <Button variant="secondary" @click="openNewYearModal()">
                <template #icon><UiIcon name="plus" /></template>
                Add a Masonic year
              </Button>
            </template>
          </PageHeader>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
          <div class="flex items-center gap-2 overflow-x-auto">
            <button
              v-for="r in rosters.slice(0, 6)"
              :key="r.id"
              @click="loadRosterAssignments(r.masonic_year)"
              :class="['px-4 py-2 rounded-xl text-sm font-medium transition-colors flex items-center gap-2 cursor-pointer flex-shrink-0 border', activeYear === r.masonic_year ? 'bg-slate-900 dark:bg-slate-700 text-white border-transparent' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800']"
            >
              <span>{{ r.masonic_year }}</span>
              <Badge :variant="r.status === 'installed' ? 'success' : (r.status === 'confirmed' ? 'warning' : 'neutral')" class="capitalize">
                {{ r.status }}
              </Badge>
            </button>

            <button
              v-if="!rosters.some(r => r.masonic_year === activeYear)"
              class="px-4 py-2 rounded-xl text-sm font-medium bg-slate-900 dark:bg-slate-700 text-white flex items-center gap-2 flex-shrink-0"
            >
              <span>{{ activeYear }}</span>
              <Badge>New draft</Badge>
            </button>
          </div>

          <Select
            v-if="rosters.length > 0"
            :model-value="activeYear"
            @update:model-value="loadRosterAssignments"
            label="All saved years"
            :options="rosters.map(r => ({ value: r.masonic_year, label: `${r.masonic_year} (${r.status})` }))"
            class="flex-shrink-0 sm:w-56"
          />
        </div>
      </Card>

      <Alert v-if="errorMessage" variant="danger">
        {{ errorMessage }}
      </Alert>

      <!-- Main Officers Board -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Progressive Officers Card (Single Select Ladder + Multi-Select Stewards) -->
        <Card class="space-y-4">
          <div class="border-b border-slate-200 dark:border-slate-800 pb-3">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Progressive officers (ceremonial ladder)</h2>
          </div>

          <div class="space-y-3">
            <!-- Single Progressive Seats (WM down to Inner Guard) -->
            <div v-for="off in singleProgressiveOffices" :key="off.value" class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <span class="text-sm font-medium text-slate-900 dark:text-white sm:w-1/3">{{ off.label }}</span>
              <div class="flex items-center gap-1.5 w-full sm:w-2/3">
                <select
                  v-model="progressiveAssignments[off.value]"
                  :aria-label="off.label"
                  class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm text-slate-900 dark:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option :value="null">Unassigned</option>
                  <option v-for="m in localMembers" :key="m.id" :value="m.id">
                    {{ m.name }}
                  </option>
                </select>
                <Button
                  variant="secondary"
                  @click="openQuickMemberModal(off.value, 'progressive')"
                  title="Add a historic member"
                  aria-label="Add a historic member"
                  class="flex-shrink-0"
                >
                  <UiIcon name="user-add" />
                </Button>
              </div>
            </div>

            <!-- Stewards (Multi-Select Checklist Grid with Non-Officers Filter) -->
            <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-2.5">
              <div class="flex items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Lodge Stewards</h3>
                <div class="flex items-center gap-2">
                  <Badge variant="info">{{ stewardAssignments.length }} appointed</Badge>
                  <Button
                    variant="secondary"
                    size="sm"
                    @click="openQuickMemberModal(null, 'steward')"
                    title="Add a historic member as a Steward"
                  >
                    <template #icon><UiIcon name="user-add" :size="14" /></template>
                    Add member
                  </Button>
                </div>
              </div>

              <!-- Search & Filter Controls -->
              <div class="space-y-2 border-y border-slate-200 dark:border-slate-800 py-2.5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <Input
                    v-model="stewardSearchQuery"
                    placeholder="Search by name"
                    aria-label="Search Stewards by name"
                    class="w-full sm:w-64"
                  />
                  <span class="text-xs text-slate-500 dark:text-slate-400">
                    Showing {{ stewardMembers.length }} of {{ localMembers.length }} members
                  </span>
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                  <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 select-none">
                    <input
                      type="checkbox"
                      v-model="excludeProgressiveForStewards"
                      class="h-4 w-4 rounded accent-blue-600 cursor-pointer"
                    />
                    <span>Hide progressive officers</span>
                  </label>

                  <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 select-none">
                    <input
                      type="checkbox"
                      v-model="excludeAdminForStewards"
                      class="h-4 w-4 rounded accent-blue-600 cursor-pointer"
                    />
                    <span>Hide administrative and pastoral officers</span>
                  </label>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 max-h-48 overflow-y-auto pr-1">
                <label
                  v-for="m in stewardMembers"
                  :key="m.id"
                  @click.prevent="toggleSteward(m.id)"
                  :class="['p-2 rounded-xl text-sm font-medium flex items-center gap-2 cursor-pointer border transition-colors', isSteward(m.id) ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800']"
                >
                  <input type="checkbox" :checked="isSteward(m.id)" class="h-4 w-4 rounded accent-blue-600 pointer-events-none" />
                  <span class="truncate">{{ m.name }}</span>
                </label>
                <div v-if="!stewardMembers.length" class="col-span-2 text-center text-sm text-slate-500 dark:text-slate-400 py-2">
                  No non-officer members found.
                </div>
              </div>
            </div>

          </div>
        </Card>

        <!-- Column 2: Administrative Officers + Lodge Committee Members -->
        <div class="space-y-6">
          <!-- Administrative & Pastoral Officers Card (Grouped Principals & Assistants) -->
          <Card class="space-y-4">
            <div class="border-b border-slate-200 dark:border-slate-800 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <h2 class="text-base font-semibold text-slate-900 dark:text-white">Administrative and pastoral officers</h2>

              <!-- Filter Toggle: Active by default -->
              <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 select-none">
                <input
                  type="checkbox"
                  v-model="onlyNonOfficersAdminFilter"
                  class="h-4 w-4 rounded accent-blue-600 cursor-pointer"
                />
                <span>Only show non-officers</span>
              </label>
            </div>

            <div class="space-y-3">
              <div
                v-for="off in administrativeOffices"
                :key="off.value"
                :class="['p-3 rounded-xl space-y-1.5 border', off.is_assistant ? 'ml-6 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800']"
              >
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <span class="text-sm font-medium text-slate-900 dark:text-white flex items-center gap-1.5">
                    <span v-if="off.is_assistant" class="text-slate-400" aria-hidden="true">↳</span>
                    <span>{{ off.label }}</span>
                  </span>
                  <Badge v-if="getPreviousYearOfficerName(off.value)">
                    Last year: {{ getPreviousYearOfficerName(off.value) }}
                  </Badge>
                </div>

                <div class="flex items-center gap-1.5 w-full">
                  <select
                    v-model="adminAssignments[off.value]"
                    :aria-label="off.label"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm text-slate-900 dark:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option :value="null">Unassigned</option>
                    <option v-for="m in getAdminMemberOptions(off.value)" :key="m.id" :value="m.id">
                      {{ m.name }}
                    </option>
                  </select>
                  <Button
                    variant="secondary"
                    @click="openQuickMemberModal(off.value, 'admin')"
                    title="Add a historic member"
                    aria-label="Add a historic member"
                    class="flex-shrink-0"
                  >
                    <UiIcon name="user-add" />
                  </Button>
                </div>
              </div>
            </div>
          </Card>

          <!-- Lodge Committee Members Card (Multi-Select Checklist Grid with Non-Officers Filter) -->
          <Card class="space-y-4">
            <div class="border-b border-slate-200 dark:border-slate-800 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <h2 class="text-base font-semibold text-slate-900 dark:text-white">Lodge committee members</h2>
              <div class="flex items-center gap-2">
                <Badge variant="info">{{ committeeAssignments.length }} appointed</Badge>
                <Button
                  variant="secondary"
                  size="sm"
                  @click="openQuickMemberModal(null, 'committee')"
                  title="Add a historic member to the committee"
                >
                  <template #icon><UiIcon name="user-add" :size="14" /></template>
                  Add member
                </Button>
              </div>
            </div>

            <div class="space-y-3">
              <!-- Search & Filter Controls -->
              <div class="space-y-2 border-b border-slate-200 dark:border-slate-800 pb-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <Input
                    v-model="committeeSearchQuery"
                    placeholder="Search by name"
                    aria-label="Search committee members by name"
                    class="w-full sm:w-64"
                  />
                  <span class="text-xs text-slate-500 dark:text-slate-400">
                    Showing {{ committeeMembers.length }} of {{ localMembers.length }} members
                  </span>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                  <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 select-none">
                    <input
                      type="checkbox"
                      v-model="onlyOfficersForCommittee"
                      class="h-4 w-4 rounded accent-blue-600 cursor-pointer"
                    />
                    <span>Only show officers (progressive and administrative)</span>
                  </label>
                </div>
              </div>

              <!-- Member Checklist Grid -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-52 overflow-y-auto pr-1">
                <label
                  v-for="m in committeeMembers"
                  :key="m.id"
                  @click.prevent="toggleCommitteeMember(m.id)"
                  :class="['p-2.5 rounded-xl text-sm font-medium flex items-center gap-2 cursor-pointer border transition-colors', isCommitteeMember(m.id) ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800']"
                >
                  <input type="checkbox" :checked="isCommitteeMember(m.id)" class="h-4 w-4 rounded accent-blue-600 pointer-events-none" />
                  <span class="truncate">{{ m.name }}</span>
                </label>
                <div v-if="!committeeMembers.length" class="col-span-2 text-center text-sm text-slate-500 dark:text-slate-400 py-3">
                  No matching members found for committee appointment.
                </div>
              </div>
            </div>
          </Card>
        </div>

      </div>

      <!-- Action & Rollover Bar -->
      <Card class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Selected Masonic year: <strong class="font-semibold text-slate-900 dark:text-white">{{ activeYear }}</strong></span>
          <p class="text-xs text-slate-500 dark:text-slate-400">Save drafts for committee discussions or install the officers for the year.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <Button variant="secondary" @click="saveRoster('draft')" :disabled="isSaving">
            <template #icon><UiIcon name="save" /></template>
            Save draft
          </Button>

          <Button variant="secondary" @click="saveRoster('confirmed')" :disabled="isSaving">
            <template #icon><UiIcon name="check" /></template>
            Approve in committee
          </Button>

          <Button v-if="currentRoster" @click="installCurrentRoster">
            <template #icon><UiIcon name="award" /></template>
            Install and activate officers
          </Button>
        </div>
      </Card>

      <!-- Add New Masonic Year Modal (Strict Start Year Dropdown) -->
      <RosterNewYearModal v-if="showNewYearModal" :calculated-end-year="calculatedEndYear" :calculated-masonic-year="calculatedMasonicYear" :create-new-masonic-year="createNewMasonicYear" :current-year-num="currentYearNum" :filtered-start-year-options="filteredStartYearOptions" :installation-month="installationMonth" v-model:selected-start-year="selectedStartYear" v-model:year-search-input="yearSearchInput" @close="showNewYearModal = false" />

      <!-- Add Quick Historic Member Modal -->
      <RosterQuickMemberModal v-if="showQuickMemberModal" :grand-ranks="grandRanks" :is-creating-member="isCreatingMember" :provincial-ranks="provincialRanks" :quick-member-error="quickMemberError" :quick-member-form="quickMemberForm" :submit-quick-member="submitQuickMember" @close="showQuickMemberModal = false" />

    </div>
  </AdminLayout>
</template>
