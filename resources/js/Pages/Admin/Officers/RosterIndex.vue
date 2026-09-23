<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  rosters: Array,
  members: Array,
  offices: Array,
  installationMonth: String,
  grandRanks: { type: Array, default: () => [] },
  provincialRanks: { type: Array, default: () => [] },
});

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

// Filter toggle for stewards list: active by default
const onlyNonOfficersFilter = ref(true);

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
  if (confirm(`Install and Activate Officers for ${activeYear.value}? This will update member badges across the portal.`)) {
    router.post(route('admin.officers.install', { clubSlug: props.club.slug, id: currentRoster.value.id }));
  }
};
</script>

<template>
  <AdminLayout title="Annual Officer Rosters & History" :club="club" active-tab="members">
    <div class="space-y-6">

      <!-- Member Management Domain Unified Navigation -->
      <div class="flex items-center gap-2 p-1.5 bg-slate-200/80 dark:bg-slate-700/80 rounded-2xl w-fit text-xs font-bold border border-slate-300/60 dark:border-slate-700/60 shadow-inner">
        <a
          :href="route('admin.club_acc.members.index', { clubSlug: club.slug })"
          class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-slate-900/60"
        >
          <span>👥</span>
          <span>Members Roster</span>
        </a>

        <a
          :href="route('admin.officers.index', { clubSlug: club.slug })"
          class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 bg-slate-900 dark:bg-slate-700 text-white shadow-md font-black"
        >
          <span>👔</span>
          <span>Annual Officer Rosters &amp; History</span>
        </a>

        <a
          :href="route('admin.club_acc.candidates.index', { clubSlug: club.slug })"
          class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-slate-900/60"
        >
          <span>📋</span>
          <span>Candidates (Form P Vetting)</span>
        </a>

        <a
          :href="route('admin.club_acc.subscriptions.index', { clubSlug: club.slug })"
          class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-slate-900/60"
        >
          <span>💳</span>
          <span>Subscriptions &amp; Dues</span>
        </a>
      </div>

      <!-- Header Banner -->
      <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-8 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-6">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="text-xs font-bold text-slate-400">Lodge Governance &amp; Ritual</span>
              <span class="text-slate-300">•</span>
              <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-md bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700/60">
                Installation Month: {{ installationMonth }}
              </span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Annual Officer Rosters &amp; History</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              Manage annual officer rosters, committee approvals, and installation rollovers.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <button @click="openNewYearModal()" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md transition-all cursor-pointer">
              ➕ Add Masonic Year
            </button>
          </div>
        </div>

        <!-- Year Tabs & Historical Selector Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1 text-xs">
          <!-- Recent Active Roster Tabs -->
          <div class="flex items-center gap-2 overflow-x-auto">
            <button
              v-for="r in rosters.slice(0, 6)"
              :key="r.id"
              @click="loadRosterAssignments(r.masonic_year)"
              :class="['px-4 py-2.5 rounded-xl font-extrabold transition-all flex items-center gap-2 cursor-pointer flex-shrink-0', activeYear === r.masonic_year ? 'bg-slate-900 dark:bg-slate-700 text-white shadow-md' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700']"
            >
              <span>{{ r.masonic_year }}</span>
              <span :class="['text-[10px] uppercase font-black px-2 py-0.5 rounded-md', r.status === 'installed' ? 'bg-emerald-400 text-slate-950' : (r.status === 'confirmed' ? 'bg-amber-300 text-slate-950' : 'bg-slate-700 text-slate-200')]">
                {{ r.status }}
              </span>
            </button>

            <button
              v-if="!rosters.some(r => r.masonic_year === activeYear)"
              class="px-4 py-2.5 rounded-xl font-extrabold bg-slate-900 dark:bg-slate-700 text-white shadow-md flex items-center gap-2 flex-shrink-0"
            >
              <span>{{ activeYear }}</span>
              <span class="text-[10px] uppercase font-black px-2 py-0.5 rounded-md bg-slate-700 text-slate-200">
                New Draft
              </span>
            </button>
          </div>

          <!-- Jump to Saved Historical Roster Selector -->
          <div v-if="rosters.length > 0" class="flex items-center gap-2 flex-shrink-0">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">All Saved History:</span>
            <select
              :value="activeYear"
              @change="loadRosterAssignments($event.target.value)"
              class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
            >
              <option v-for="r in rosters" :key="r.id" :value="r.masonic_year">
                📜 {{ r.masonic_year }} ({{ r.status }})
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Validation Error Alert -->
      <div v-if="errorMessage" class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-200 p-4 rounded-2xl text-xs font-bold flex items-center gap-2">
        <span>⚠️</span>
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Main Officers Board -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Progressive Officers Card (Single Select Ladder + Multi-Select Stewards) -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
          <div class="border-b border-slate-100 dark:border-slate-800 pb-3">
            <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
              <span>🪜</span>
              <span>Progressive Officers (Ceremonial Ladder)</span>
            </h2>
          </div>

          <div class="space-y-3">
            <!-- Single Progressive Seats (WM down to Inner Guard) -->
            <div v-for="off in singleProgressiveOffices" :key="off.value" class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <span class="font-extrabold text-slate-900 dark:text-white text-xs sm:w-1/3">{{ off.label }}</span>
              <div class="flex items-center gap-1.5 w-full sm:w-2/3">
                <select
                  v-model="progressiveAssignments[off.value]"
                  class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500"
                >
                  <option :value="null">-- Unassigned --</option>
                  <option v-for="m in localMembers" :key="m.id" :value="m.id">
                    {{ m.name }}
                  </option>
                </select>
                <button
                  type="button"
                  @click="openQuickMemberModal(off.value, 'progressive')"
                  title="Quickly add new historic member"
                  class="px-2 py-2 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 rounded-xl text-xs font-black transition-all cursor-pointer flex-shrink-0 flex items-center justify-center h-[34px] w-[34px] shadow-xs"
                >
                  ➕
                </button>
              </div>
            </div>

            <!-- Stewards (Multi-Select Checklist Grid with Non-Officers Filter) -->
            <div class="p-3.5 bg-blue-50/50 dark:bg-blue-950/50 border border-blue-200/80 dark:border-blue-800/80 rounded-2xl space-y-2.5">
              <div class="flex items-center justify-between">
                <span class="font-extrabold text-blue-950 dark:text-blue-100 text-xs flex items-center gap-1.5">
                  <span>🍷</span>
                  <span>Lodge Stewards (Multi-Select)</span>
                </span>
                <div class="flex items-center gap-2">
                  <span class="text-[10px] bg-blue-600 text-white font-black px-2 py-0.5 rounded-md">
                    {{ stewardAssignments.length }} Appointed
                  </span>
                  <button
                    type="button"
                    @click="openQuickMemberModal(null, 'steward')"
                    title="Quickly add new historic member as Steward"
                    class="px-2.5 py-1 bg-white dark:bg-slate-900 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-blue-700/60 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1 shadow-xs"
                  >
                    <span>➕</span>
                    <span class="text-[10px]">Add Member</span>
                  </button>
                </div>
              </div>

              <!-- Search & Filter Controls -->
              <div class="space-y-2 border-y border-blue-200/60 dark:border-blue-800/60 py-2.5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <div class="relative w-full sm:w-64">
                    <input
                      type="text"
                      v-model="stewardSearchQuery"
                      placeholder="Search steward name..."
                      class="w-full pl-8 pr-3 py-1.5 bg-white dark:bg-slate-900 border border-blue-200/80 dark:border-blue-800/80 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500"
                    />
                    <span class="absolute left-2.5 top-1.5 text-xs text-slate-400">🔍</span>
                  </div>
                  <span class="text-[10px] text-blue-700 dark:text-blue-300 font-semibold">
                    Showing {{ stewardMembers.length }} of {{ localMembers.length }} members
                  </span>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                  <label class="flex items-center gap-1.5 cursor-pointer font-bold text-blue-950 dark:text-blue-100 select-none bg-white dark:bg-slate-900 px-2.5 py-1 rounded-xl border border-blue-200/80 dark:border-blue-800/80 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-all">
                    <input
                      type="checkbox"
                      v-model="excludeProgressiveForStewards"
                      class="rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 cursor-pointer"
                    />
                    <span>Hide Progressive Officers</span>
                  </label>

                  <label class="flex items-center gap-1.5 cursor-pointer font-bold text-blue-950 dark:text-blue-100 select-none bg-white dark:bg-slate-900 px-2.5 py-1 rounded-xl border border-blue-200/80 dark:border-blue-800/80 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-all">
                    <input
                      type="checkbox"
                      v-model="excludeAdminForStewards"
                      class="rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 cursor-pointer"
                    />
                    <span>Hide Administrative &amp; Pastoral Support</span>
                  </label>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 max-h-48 overflow-y-auto pr-1">
                <label
                  v-for="m in stewardMembers"
                  :key="m.id"
                  @click.prevent="toggleSteward(m.id)"
                  :class="['p-2 rounded-xl text-xs font-semibold flex items-center gap-2 cursor-pointer border transition-all', isSteward(m.id) ? 'bg-blue-600 text-white border-blue-700 shadow-xs' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800']"
                >
                  <input type="checkbox" :checked="isSteward(m.id)" class="rounded text-blue-600 dark:text-blue-400 pointer-events-none" />
                  <span class="truncate">{{ m.name }}</span>
                </label>
                <div v-if="!stewardMembers.length" class="col-span-2 text-center text-xs text-slate-500 dark:text-slate-400 py-2">
                  No non-officer members found.
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Column 2: Administrative Officers + Lodge Committee Members -->
        <div class="space-y-6">
          <!-- Administrative & Pastoral Officers Card (Grouped Principals & Assistants) -->
          <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>📜</span>
                <span>Administrative &amp; Pastoral Support</span>
              </h2>

              <!-- Filter Toggle: Active by default -->
              <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-200 select-none bg-slate-50 dark:bg-slate-800/50 px-3 py-1 rounded-xl border border-slate-200/80 dark:border-slate-800/80 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                <input
                  type="checkbox"
                  v-model="onlyNonOfficersAdminFilter"
                  class="rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 cursor-pointer"
                />
                <span>Only show non-officers</span>
              </label>
            </div>

            <div class="space-y-3">
              <div
                v-for="off in administrativeOffices"
                :key="off.value"
                :class="['p-3 rounded-2xl space-y-1.5 transition-all', off.is_assistant ? 'ml-6 bg-blue-50/60 dark:bg-blue-950/60 border border-blue-200/70 dark:border-blue-800/70' : 'bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80']"
              >
                <div class="flex items-center justify-between">
                  <span :class="['font-extrabold text-xs flex items-center gap-1.5', off.is_assistant ? 'text-blue-900 dark:text-blue-200' : 'text-slate-900 dark:text-white']">
                    <span v-if="off.is_assistant" class="text-blue-500 font-black">↳</span>
                    <span>{{ off.label }}</span>
                  </span>
                  <span v-if="getPreviousYearOfficerName(off.value)" class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold bg-white dark:bg-slate-900 px-2 py-0.5 rounded-md border border-slate-200/60 dark:border-slate-800/60">
                    Prev Year: {{ getPreviousYearOfficerName(off.value) }}
                  </span>
                </div>

                <div class="flex items-center gap-1.5 w-full">
                  <select
                    v-model="adminAssignments[off.value]"
                    class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-emerald-500"
                  >
                    <option :value="null">-- Unassigned --</option>
                    <option v-for="m in getAdminMemberOptions(off.value)" :key="m.id" :value="m.id">
                      {{ m.name }}
                    </option>
                  </select>
                  <button
                    type="button"
                    @click="openQuickMemberModal(off.value, 'admin')"
                    title="Quickly add new historic member"
                    class="px-2 py-2 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 rounded-xl text-xs font-black transition-all cursor-pointer flex-shrink-0 flex items-center justify-center h-[34px] w-[34px] shadow-xs"
                  >
                    ➕
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Lodge Committee Members Card (Multi-Select Checklist Grid with Non-Officers Filter) -->
          <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm space-y-4">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
              <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>🏛️</span>
                <span>Lodge Committee Members (Multi-Select)</span>
              </h2>
              <div class="flex items-center gap-2">
                <span class="text-[10px] bg-blue-600 text-white font-black px-2.5 py-0.5 rounded-md">
                  {{ committeeAssignments.length }} Appointed
                </span>
                <button
                  type="button"
                  @click="openQuickMemberModal(null, 'committee')"
                  title="Quickly add new historic member to Committee"
                  class="px-2.5 py-1 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-1 shadow-xs"
                >
                  <span>➕</span>
                  <span class="text-[10px]">Add Member</span>
                </button>
              </div>
            </div>

            <div class="space-y-3">
              <!-- Search & Filter Controls -->
              <div class="space-y-2 border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <div class="relative w-full sm:w-64">
                    <input
                      type="text"
                      v-model="committeeSearchQuery"
                      placeholder="Search committee member name..."
                      class="w-full pl-8 pr-3 py-1.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500"
                    />
                    <span class="absolute left-2.5 top-1.5 text-xs text-slate-400">🔍</span>
                  </div>
                  <span class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold">
                    Showing {{ committeeMembers.length }} of {{ localMembers.length }} members
                  </span>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                  <label class="flex items-center gap-1.5 cursor-pointer font-bold text-slate-800 dark:text-slate-100 select-none bg-slate-50 dark:bg-slate-800/50 px-2.5 py-1 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                    <input
                      type="checkbox"
                      v-model="onlyOfficersForCommittee"
                      class="rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 cursor-pointer"
                    />
                    <span>Only show officers (Progressive &amp; Administrative)</span>
                  </label>
                </div>
              </div>

              <!-- Member Checklist Grid -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-52 overflow-y-auto pr-1">
                <label
                  v-for="m in committeeMembers"
                  :key="m.id"
                  @click.prevent="toggleCommitteeMember(m.id)"
                  :class="['p-2.5 rounded-xl text-xs font-semibold flex items-center gap-2 cursor-pointer border transition-all', isCommitteeMember(m.id) ? 'bg-blue-600 text-white border-blue-700 shadow-xs' : 'bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800']"
                >
                  <input type="checkbox" :checked="isCommitteeMember(m.id)" class="rounded text-blue-600 dark:text-blue-400 pointer-events-none" />
                  <span class="truncate">{{ m.name }}</span>
                </label>
                <div v-if="!committeeMembers.length" class="col-span-2 text-center text-xs text-slate-400 py-3">
                  No matching members found for committee appointment.
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Action & Rollover Bar -->
      <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Selected Masonic Year: <strong class="text-slate-900 dark:text-white">{{ activeYear }}</strong></span>
          <p class="text-[11px] text-slate-400">Save drafts for committee discussions or execute installation rollover.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button @click="saveRoster('draft')" :disabled="isSaving" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition-all cursor-pointer">
            💾 Save Draft
          </button>

          <button @click="saveRoster('confirmed')" :disabled="isSaving" class="px-4 py-2.5 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700/60 font-bold text-xs rounded-xl transition-all cursor-pointer">
            📜 Approve in Committee
          </button>

          <button v-if="currentRoster" @click="installCurrentRoster" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-black text-xs rounded-xl shadow-md transition-all cursor-pointer">
            ✨ Install &amp; Activate Officers
          </button>
        </div>
      </div>

      <!-- Add New Masonic Year Modal (Strict Start Year Dropdown) -->
      <div v-if="showNewYearModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Add New Masonic Year Roster</h3>
            <button @click="showNewYearModal = false" class="text-slate-400 text-lg font-bold">✕</button>
          </div>

          <div class="space-y-4">
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase">Installation Start Year</label>
                <span class="text-[10px] text-slate-400 font-semibold">1717 to {{ currentYearNum + 3 }}</span>
              </div>

              <!-- Direct Number Input + Filter Input Row -->
              <div class="flex items-center gap-2">
                <input
                  type="number"
                  v-model.number="selectedStartYear"
                  :min="1717"
                  :max="currentYearNum + 3"
                  placeholder="e.g. 1849"
                  class="w-32 px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"
                />
                <input
                  type="text"
                  v-model="yearSearchInput"
                  placeholder="Search year (e.g. 1850)..."
                  class="flex-1 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <!-- Compact Scrollable List (Fixed Max Height 160px) -->
              <div class="max-h-40 overflow-y-auto border border-slate-200 dark:border-slate-800 rounded-xl p-1 bg-slate-50 dark:bg-slate-800/50 space-y-0.5">
                <button
                  v-for="y in filteredStartYearOptions"
                  :key="y"
                  type="button"
                  @click="selectedStartYear = y"
                  :class="[
                    'w-full text-left px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center justify-between cursor-pointer',
                    selectedStartYear === y ? 'bg-blue-600 text-white shadow-xs' : 'hover:bg-slate-200/80 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200'
                  ]"
                >
                  <span>{{ y }} (Installation Start)</span>
                  <span v-if="selectedStartYear === y" class="text-[10px] uppercase font-black bg-white/20 dark:bg-slate-900/20 px-1.5 py-0.5 rounded">Selected</span>
                </button>
                <div v-if="!filteredStartYearOptions.length" class="p-3 text-center text-xs text-slate-400">
                  No available start year matching "{{ yearSearchInput }}"
                </div>
              </div>
            </div>

            <!-- Dynamic Preview Box -->
            <div class="p-3.5 bg-blue-50/70 dark:bg-blue-950/70 border border-blue-200/80 dark:border-blue-800/80 rounded-2xl space-y-1">
              <div class="flex items-center justify-between text-xs font-black text-blue-950 dark:text-blue-100">
                <span>Calculated Masonic Year:</span>
                <span class="text-sm font-black text-blue-700 dark:text-blue-300 bg-white dark:bg-slate-900 px-2.5 py-0.5 rounded-lg border border-blue-200/60 dark:border-blue-800/60">
                  {{ calculatedMasonicYear }}
                </span>
              </div>
              <p class="text-[11px] text-blue-800 dark:text-blue-200 font-medium">
                Period: 1st {{ installationMonth }} {{ selectedStartYear }} – 30th {{ installationMonth }} {{ calculatedEndYear }}
              </p>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
            <button type="button" @click="showNewYearModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl">Cancel</button>
            <button type="button" @click="createNewMasonicYear" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md cursor-pointer">
              Start Roster Draft
            </button>
          </div>
        </div>
      </div>

      <!-- Add Quick Historic Member Modal -->
      <div v-if="showQuickMemberModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div>
              <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                <span>👤</span>
                <span>Add Historic Member</span>
              </h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Quickly add a member to assign to roster entries.</p>
            </div>
            <button @click="showQuickMemberModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg font-bold cursor-pointer">✕</button>
          </div>

          <div v-if="quickMemberError" class="bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-200 p-3 rounded-xl text-xs font-bold">
            {{ quickMemberError }}
          </div>

          <form @submit.prevent="submitQuickMember" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <label class="block text-[11px] font-extrabold uppercase text-slate-700 dark:text-slate-200">First Name <span class="text-rose-500">*</span></label>
                <input
                  type="text"
                  v-model="quickMemberForm.first_name"
                  placeholder="e.g. Thomas"
                  required
                  class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <div class="space-y-1">
                <label class="block text-[11px] font-extrabold uppercase text-slate-700 dark:text-slate-200">Surname <span class="text-rose-500">*</span></label>
                <input
                  type="text"
                  v-model="quickMemberForm.last_name"
                  placeholder="e.g. Dunckerley"
                  required
                  class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <label class="block text-[11px] font-extrabold uppercase text-slate-700 dark:text-slate-200">Member Status</label>
                <select
                  v-model="quickMemberForm.membership_status"
                  class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
                >
                  <option value="historical">Historical Member (Default)</option>
                  <option value="active">Active Member</option>
                  <option value="honorary">Honorary Member</option>
                  <option value="resigned">Resigned</option>
                  <option value="excluded_rule_181">Excluded (Rule 181)</option>
                  <option value="deceased">Deceased</option>
                </select>
              </div>

              <div class="space-y-1">
                <label class="block text-[11px] font-extrabold uppercase text-slate-700 dark:text-slate-200">Title / Rank <span class="text-slate-400 font-normal">(Optional)</span></label>
                <select
                  v-model="quickMemberForm.masonic_rank"
                  class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
                >
                  <option value="Bro">Bro (Brother)</option>
                  <option value="WBro">WBro (Worshipful Brother)</option>
                  <option value="VWBro">VWBro (Very Worshipful Brother)</option>
                  <option value="RWBro">RWBro (Right Worshipful Brother)</option>
                  <option value="MWBro">MWBro (Most Worshipful Brother)</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <label class="block text-[11px] font-extrabold uppercase text-slate-700 dark:text-slate-200">Grand Rank <span class="text-slate-400 font-normal">(Optional)</span></label>
                <select v-model="quickMemberForm.grand_rank" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="">None</option>
                  <option v-for="rank in grandRanks" :key="rank.value" :value="rank.value">{{ rank.label }}</option>
                </select>
              </div>
              <div class="space-y-1">
                <label class="block text-[11px] font-extrabold uppercase text-slate-700 dark:text-slate-200">Provincial Rank <span class="text-slate-400 font-normal">(Optional)</span></label>
                <select v-model="quickMemberForm.provincial_rank" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="">None</option>
                  <option v-for="rank in provincialRanks" :key="rank.value" :value="rank.value">{{ rank.label }}</option>
                </select>
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
              <button type="button" @click="showQuickMemberModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl cursor-pointer">Cancel</button>
              <button type="submit" :disabled="isCreatingMember" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md cursor-pointer flex items-center gap-1.5">
                <span v-if="isCreatingMember">Saving...</span>
                <span v-else>Save &amp; Select Member</span>
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
