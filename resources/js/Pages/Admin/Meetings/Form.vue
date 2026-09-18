<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useForm, Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  meeting: Object,
  members: Array,
  charityGrants: {
    type: Array,
    default: () => [],
  },
});

const sectionHashMap = {
  'general': 'general-intro',
  'general-intro': 'general-intro',
  'intro': 'general-intro',

  'front_page': 'front-page',
  'front-page': 'front-page',
  'cover': 'front-page',

  'agenda': 'order-of-business',
  'order-of-business': 'order-of-business',
  'business': 'order-of-business',

  'officers': 'officers-for-year',
  'officers-for-year': 'officers-for-year',
  'officers_for_year': 'officers-for-year',
  'officers': 'officers-for-year',
  'roster': 'officers-for-year',

  'festive': 'festive-board',
  'festive-board': 'festive-board',
  'dining': 'festive-board',

  'visits': 'fraternal-honorary',
  'fraternal-honorary': 'fraternal-honorary',
  'fraternal-and-honorary': 'fraternal-honorary',
  'honorary': 'fraternal-honorary',
};

const hashToSectionKey = {
  'general-intro': 'general',
  'general': 'general',
  'intro': 'general',

  'front-page': 'front_page',
  'front_page': 'front_page',
  'cover': 'front_page',

  'order-of-business': 'agenda',
  'agenda': 'agenda',
  'business': 'agenda',

  'officers-for-year': 'officers',
  'officers_for_year': 'officers',
  'officers': 'officers',
  'roster': 'officers',

  'festive-board': 'festive',
  'festive': 'festive',
  'dining': 'festive',

  'fraternal-honorary': 'visits',
  'fraternal-and-honorary': 'visits',
  'visits': 'visits',
  'honorary': 'visits',
};

const getSectionFromHash = () => {
  if (typeof window === 'undefined') return 'general';
  const hash = window.location.hash.replace('#', '').trim().toLowerCase();
  return hashToSectionKey[hash] || 'general';
};

const activeSection = ref(getSectionFromHash());

const syncHashWithSection = (sectionKey) => {
  if (typeof window !== 'undefined') {
    const canonicalHash = sectionHashMap[sectionKey] || sectionKey;
    if (window.location.hash.replace('#', '') !== canonicalHash) {
      history.replaceState(null, '', '#' + canonicalHash);
    }
  }
};

watch(activeSection, (newSection) => {
  syncHashWithSection(newSection);
});

const handleHashChange = () => {
  const section = getSectionFromHash();
  if (section !== activeSection.value) {
    activeSection.value = section;
  }
};

onMounted(() => {
  syncHashWithSection(activeSection.value);
  window.addEventListener('hashchange', handleHashChange);
});

onUnmounted(() => {
  window.removeEventListener('hashchange', handleHashChange);
});

const getInitialRoster = () => {
  if (Array.isArray(props.meeting?.officers_roster) && props.meeting.officers_roster.length > 0) {
    return props.meeting.officers_roster.map(o => ({ role: o.role || '', name: o.name || '' }));
  }
  if (Array.isArray(props.club?.settings?.officers_roster) && props.club.settings.officers_roster.length > 0) {
    return props.club.settings.officers_roster.map(o => ({ role: o.role || '', name: o.name || '' }));
  }
  return [
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
    { role: 'Inner Guard', name: 'Bro. M. Allen' },
    { role: 'Tyler', name: 'Bro. D. King' },
  ];
};

const form = useForm({
  id: props.meeting.id || null,
  title: props.meeting.title || 'Meeting',
  meeting_number: props.meeting.meeting_number || null,
  meeting_date: props.meeting.meeting_date ? String(props.meeting.meeting_date).split('T')[0] : '',
  starts_at: props.meeting.starts_at || '19:00',
  rehearsal_starts_at: props.meeting.rehearsal_starts_at || '18:00',
  venue: props.meeting.venue || 'Masonic Hall, Wellington Street, Stockton-on-Tees',
  dress_code: props.meeting.dress_code || 'Dinner Jacket, White Gloves',
  salutation: props.meeting.salutation || 'Dear Sir and Brother,',
  intro_text: props.meeting.intro_text || 'You are respectfully requested to attend the Regular Meeting of this Lodge of Free and Accepted Masons.',
  rehearsal_text: props.meeting.rehearsal_text || 'The rehearsal should it be necessary will be at 6:00pm on Friday at the Masonic Hall.',
  festive_board_theme: props.meeting.festive_board_theme || 'The Lodge will be holding their Festive Board.',
  festive_board_menu: props.meeting.festive_board_menu || '',
  dining_cost_member: props.meeting.dining_cost_member || 20.00,
  dining_cost_guest: props.meeting.dining_cost_guest || 20.00,
  bank_sort_code: props.meeting.bank_sort_code || '20-82-18',
  bank_account_number: props.meeting.bank_account_number || '80288373',
  payment_reference_prefix: props.meeting.payment_reference_prefix || 'SUMMONS',
  payment_link: props.meeting.payment_link || '',
  almoner_notice: props.meeting.almoner_notice || '',
  sick_distressed_notes: props.meeting.sick_distressed_notes || 'Should you be aware of any illness or unusual circumstances, please contact the Almoner.',
  honorary_members_text: props.meeting.honorary_members_text || 'RW Bro Sir David Hugh Wootton Past Deputy Grand Master',
  provincial_header_text: props.meeting.provincial_header_text || 'PROVINCIAL GRAND LODGE\nProvincial Grand Master\nR WBro John David Watts',
  fraternal_visits_text: props.meeting.fraternal_visits_text || 'The Worshipful Master and Brethren of Haven of Rest Lodge No 4350 will be making their fraternal visit.',
  officers_year_label: props.meeting.officers_year_label || props.club.settings?.officers_year_label || 'OFFICERS FOR 2025-2026',
  officers_roster: getInitialRoster(),

  // Front Page Cover Fields
  front_page_logo: props.meeting.front_page_logo || '',
  front_page_logo_file: null,
  front_page_title: props.meeting.front_page_title || props.club.settings?.provincial_name?.toUpperCase() || 'PROVINCIAL GRAND LODGE',
  provincial_grand_master: props.meeting.provincial_grand_master || props.club.settings?.provincial_grand_master || 'R WBro John David Watts',
  deputy_provincial_grand_master: props.meeting.deputy_provincial_grand_master || props.club.settings?.deputy_provincial_grand_master || 'WBro Andrew Peter Faul Foster PSGD',
  assistant_provincial_grand_masters: props.meeting.assistant_provincial_grand_masters || props.club.settings?.assistant_provincial_grand_masters || "WBro Dr. Rakesh Bhalla PSGD\nWBro Thomas Fred Gittins PSGD\nWBro Martin Rankin PJGD\nWBro Michael Stuart Shaw PJGD\nWBro Lt Col John William Henry",
  cover_club_name: props.meeting.cover_club_name || props.club.name,
  cover_club_number: props.meeting.cover_club_number || (props.club.lodge_number || '1418'),
  cover_motto: props.meeting.cover_motto || (props.club.motto || 'Fraternus Amor Maneto'),
  cover_worshipful_master: props.meeting.cover_worshipful_master || '',

  status: props.meeting.status || 'draft',
  agenda_items: props.meeting.agenda_items?.length ? props.meeting.agenda_items : [
    { title: 'To confirm the minutes of the Regular Meeting held previously.', description: '' },
    { title: 'To report on the proceedings of Grand Lodge.', description: '' },
    { title: 'To transact any other lawful Masonic Business.', description: '' },
  ],
});

const logoPreview = ref(props.meeting.front_page_logo || '');

const handleLogoUpload = (event) => {
  const file = event.target.files[0];
  if (file) {
    form.front_page_logo_file = file;
    logoPreview.value = URL.createObjectURL(file);
  }
};

const clearLogo = () => {
  form.front_page_logo = '';
  form.front_page_logo_file = null;
  logoPreview.value = '';
};

const importProvincialRulersFromSettings = () => {
  const s = props.club?.settings || {};
  if (s.provincial_name) form.front_page_title = s.provincial_name.toUpperCase();
  if (s.provincial_grand_master) form.provincial_grand_master = s.provincial_grand_master;
  if (s.deputy_provincial_grand_master) form.deputy_provincial_grand_master = s.deputy_provincial_grand_master;
  if (s.assistant_provincial_grand_masters) form.assistant_provincial_grand_masters = s.assistant_provincial_grand_masters;
};

const reloadingRoster = ref(false);
const isEditingRoster = ref(false);

// Snapshot the initial roster for dirty-checking
const originalRosterSnapshot = ref(JSON.stringify(form.officers_roster));

const rosterHasChanges = computed(() => {
  return JSON.stringify(form.officers_roster) !== originalRosterSnapshot.value;
});

const importOfficersFromSettings = () => {
  reloadOfficersFromSettings();
};

const applyRosterFromSettings = (settingsObj) => {
  const s = settingsObj || props.club?.settings || {};
  if (s.officers_year_label) {
    form.officers_year_label = s.officers_year_label;
  }
  if (Array.isArray(s.officers_roster) && s.officers_roster.length > 0) {
    form.officers_roster = s.officers_roster.map(o => ({ role: o.role || '', name: o.name || '' }));
    const master = s.officers_roster.find(o => o.role && o.role.toLowerCase().includes('master'));
    if (master && master.name) {
      form.cover_worshipful_master = master.name;
    }
  }
  // Update the snapshot after reload so dirty-check resets
  originalRosterSnapshot.value = JSON.stringify(form.officers_roster);
  isEditingRoster.value = false;
};

const reloadOfficersFromSettings = async () => {
  reloadingRoster.value = true;
  try {
    const url = route('admin.meetings.settings_json', { clubSlug: props.club.slug });
    const response = await fetch(url, {
      headers: { 'Accept': 'application/json' },
    });
    if (response.ok) {
      const data = await response.json();
      applyRosterFromSettings(data);
    } else {
      applyRosterFromSettings(props.club?.settings);
    }
  } catch {
    applyRosterFromSettings(props.club?.settings);
  } finally {
    reloadingRoster.value = false;
  }
};

const addOfficerRow = () => {
  form.officers_roster.push({ role: '', name: '' });
};

const removeOfficerRow = (idx) => {
  form.officers_roster.splice(idx, 1);
};

const moveOfficerUp = (idx) => {
  if (idx > 0) {
    const item = form.officers_roster.splice(idx, 1)[0];
    form.officers_roster.splice(idx - 1, 0, item);
  }
};

const moveOfficerDown = (idx) => {
  if (idx < form.officers_roster.length - 1) {
    const item = form.officers_roster.splice(idx, 1)[0];
    form.officers_roster.splice(idx + 1, 0, item);
  }
};

const addAgendaItem = () => {
  form.agenda_items.push({ title: '', description: '' });
};

const removeAgendaItem = (index) => {
  form.agenda_items.splice(index, 1);
};

const submit = () => {
  form.post(route('admin.meetings.store', { clubSlug: props.club.slug }));
};
</script>

<template>
  <AdminLayout :title="`${meeting.id ? 'Edit Summons' : 'Create Summons'}`" :club="club" active-tab="meetings">
    
    <div class="max-w-6xl mx-auto space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Edit Summons</h2>
          <p class="text-xs sm:text-sm text-slate-500 mt-1">Customize formal summons sections, business agenda, officers, festive board, and fraternal visits.</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
          <a v-if="meeting.id" :href="route('admin.meetings.pdf', { clubSlug: club.slug, id: meeting.id })" target="_blank" class="px-4 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-300 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5">
            🖨️ Preview PDF
          </a>
          <button @click="submit" :disabled="form.processing" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20 transition-all flex items-center gap-2 cursor-pointer">
            Save Changes
          </button>
        </div>
      </div>

      <!-- 2-Column Settings-Style Responsive Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        
        <!-- Left Sidebar Navigation -->
        <div class="lg:col-span-1 space-y-4">
          <!-- Mobile Dropdown Selector -->
          <div class="lg:hidden bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm space-y-2">
            <label class="block text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">Summons Section</label>
            <select v-model="activeSection" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer">
              <option value="general">📜 1. General & Intro Letter</option>
              <option value="agenda">📋 2. Order of Business (Agenda)</option>
              <option value="festive">🍽️ 3. Festive Board & Banking</option>
              <option value="visits">🏛️ 4. Fraternal Visits & Honorary</option>
              <option value="front_page">🏛️ 5. Front page</option>
              <option value="officers">👔 6. Officers for Year</option>
            </select>
          </div>

          <!-- Desktop Vertical Sidebar Navigation Card -->
          <div class="hidden lg:block bg-white p-3.5 rounded-3xl border border-slate-200/80 shadow-sm space-y-5 sticky top-6">
            <div>
              <div class="px-3 text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Summons Sections</div>
              <div class="space-y-0.5 text-xs font-bold">
                
                <button
                  type="button"
                  @click="activeSection = 'general'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeSection === 'general' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>📜</span> General & Intro</span>
                </button>

                <button
                  type="button"
                  @click="activeSection = 'agenda'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeSection === 'agenda' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>📋</span> Order of Business</span>
                </button>

                <button
                  type="button"
                  @click="activeSection = 'festive'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeSection === 'festive' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🍽️</span> Festive Board</span>
                </button>

                <button
                  type="button"
                  @click="activeSection = 'visits'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeSection === 'visits' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🏛️</span> Fraternal & Honorary</span>
                </button>

                <button
                  type="button"
                  @click="activeSection = 'front_page'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeSection === 'front_page' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>🏛️</span> Front page</span>
                </button>

                <button
                  type="button"
                  @click="activeSection = 'officers'"
                  :class="[
                    'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center justify-between cursor-pointer text-left',
                    activeSection === 'officers' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'
                  ]"
                >
                  <span class="flex items-center gap-2.5"><span>👔</span> Officers for Year</span>
                </button>

              </div>
            </div>
          </div>
        </div>

        <!-- Right Main Form Body -->
        <div class="lg:col-span-3 space-y-6">
          <form @submit.prevent="submit" class="space-y-6">
            
            <!-- SECTION 0: Front Page Cover Page -->
            <div v-show="activeSection === 'front_page'" class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-4">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-3">
                <div>
                  <h3 class="text-base font-bold text-slate-900">🏛️ Front Page — PDF Cover Configuration</h3>
                  <p class="text-xs text-slate-500">Configure the emblem, province header, lodge title, motto, and Worshipful Master details rendered on Page 1 (Cover Page).</p>
                </div>
                <button
                  type="button"
                  @click="importProvincialRulersFromSettings"
                  class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs rounded-xl transition-all flex items-center gap-1.5 cursor-pointer whitespace-nowrap self-start sm:self-auto"
                >
                  👔 Import Provincial Rulers
                </button>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Logo / Emblem Image Upload</label>
                
                <div class="flex items-center gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                  <div class="w-16 h-16 rounded-xl border border-slate-300 bg-white flex items-center justify-center overflow-hidden flex-shrink-0 shadow-sm">
                    <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-contain p-1" alt="Logo Preview" />
                    <span v-else class="text-2xl text-slate-400">🏛️</span>
                  </div>

                  <div class="space-y-1.5 flex-1">
                    <input
                      type="file"
                      accept="image/*"
                      @change="handleLogoUpload"
                      class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 cursor-pointer"
                    />
                    <div class="flex items-center gap-3 text-[11px] text-slate-400">
                      <span>Upload logo image (PNG, JPG, SVG, WEBP up to 5MB)</span>
                      <button v-if="logoPreview" type="button" @click="clearLogo" class="text-rose-600 font-bold hover:underline">
                        Remove Logo
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Title (Province / Organization Header)</label>
                  <input v-model="form.front_page_title" type="text" placeholder="PROVINCIAL GRAND LODGE" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Provincial Grand Master</label>
                  <input v-model="form.provincial_grand_master" type="text" placeholder="R WBro John David Watts" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                </div>

                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Deputy Provincial Grand Master</label>
                  <input v-model="form.deputy_provincial_grand_master" type="text" placeholder="WBro Andrew Peter Faul Foster PSGD" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Assistant Provincial Grand Masters (One per line)</label>
                <textarea v-model="form.assistant_provincial_grand_masters" rows="4" placeholder="WBro Dr. Rakesh Bhalla PSGD..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono"></textarea>
              </div>

              <div class="border-t border-slate-100 pt-4 space-y-4">
                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Lodge / Club Branding Header</h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Club Name (Large Text)</label>
                    <input v-model="form.cover_club_name" type="text" placeholder="LODGE OF FRATERNITY" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold" />
                  </div>

                  <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Club Number (Smaller Text)</label>
                    <input v-model="form.cover_club_number" type="text" placeholder="1418" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Club Motto</label>
                  <input v-model="form.cover_motto" type="text" placeholder="Fraternus Amor Maneto" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs italic" />
                </div>

                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Worshipful Master (Pull from Rank / Master Name)</label>
                  <div class="flex items-center gap-2">
                    <input v-model="form.cover_worshipful_master" type="text" placeholder="WBro KD Lord" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold" />
                    <select @change="e => form.cover_worshipful_master = e.target.value" class="px-3 py-2.5 bg-slate-100 border border-slate-300 rounded-xl text-xs font-medium cursor-pointer">
                      <option value="">Pull from Member Rank...</option>
                      <option v-for="m in members" :key="m.id" :value="`${m.pivot?.rank_prefix || 'WBro'} ${m.name}`">
                        {{ m.pivot?.rank_prefix || 'WBro' }} {{ m.name }} ({{ m.pivot?.rank || 'Member' }})
                      </option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <!-- SECTION 1: General & Intro Letter -->
            <div v-show="activeSection === 'general'" class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-4">
              <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">📜 Meeting Intro & Schedule Details</h3>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Meeting Title</label>
                  <input v-model="form.title" type="text" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                </div>

                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Meeting Date</label>
                  <input v-model="form.meeting_date" type="date" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Meeting Start Time</label>
                  <input v-model="form.starts_at" type="text" required placeholder="7:00pm" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                </div>

                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Rehearsal Time</label>
                  <input v-model="form.rehearsal_starts_at" type="text" placeholder="6:00pm" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                </div>

                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Dress Code</label>
                  <input v-model="form.dress_code" type="text" required placeholder="Dinner Jacket, White Gloves" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Venue Address</label>
                <input v-model="form.venue" type="text" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Formal Salutation</label>
                <input v-model="form.salutation" type="text" placeholder="Dear Sir and Brother," class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Intro Request Text (Page 2)</label>
                <textarea v-model="form.intro_text" rows="3" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs"></textarea>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Rehearsal Notice Text</label>
                <input v-model="form.rehearsal_text" type="text" placeholder="The rehearsal should it be necessary will be at 6:00pm on Friday..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
              </div>
            </div>

            <!-- SECTION 2: Order of Business (Agenda) -->
            <div v-show="activeSection === 'agenda'" class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-4">
              <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                  <h3 class="text-base font-bold text-slate-900">📋 BUSINESS — Order of Business</h3>
                  <p class="text-xs text-slate-500">Numbered items rendered directly under the BUSINESS header on Page 2.</p>
                </div>
                <button type="button" @click="addAgendaItem" class="px-3.5 py-2 bg-indigo-50 text-indigo-700 font-bold text-xs rounded-xl hover:bg-indigo-100 transition-all cursor-pointer">
                  + Add Agenda Item
                </button>
              </div>

              <div v-for="(item, idx) in form.agenda_items" :key="idx" class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-slate-700">Item #{{ idx + 1 }}</span>
                  <button type="button" @click="removeAgendaItem(idx)" class="text-xs font-bold text-rose-600 cursor-pointer">Delete Item</button>
                </div>
                <input v-model="item.title" type="text" required placeholder="To confirm the minutes / ballot for candidate..." class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-xs font-semibold" />
                <textarea v-model="item.description" rows="2" placeholder="Optional notes or details..." class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-xs"></textarea>
              </div>

              <!-- Charitable Grants linked or active for this club -->
              <div v-if="charityGrants && charityGrants.length > 0" class="mt-6 pt-6 border-t border-slate-200/80 space-y-3">
                <div class="flex items-center justify-between">
                  <h4 class="text-xs font-black text-amber-900 uppercase tracking-wider flex items-center gap-1.5">
                    <span>❤️</span> Charitable Donation Proposals & Alms Voting
                  </h4>
                  <span class="text-[11px] font-bold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200">
                    {{ charityGrants.length }} Proposal(s) Logged
                  </span>
                </div>
                <div class="space-y-2">
                  <div v-for="grant in charityGrants" :key="grant.id" class="p-3.5 bg-amber-50/70 rounded-2xl border border-amber-200/80 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                      <div class="font-bold text-slate-900">
                        £{{ parseFloat(grant.amount).toFixed(2) }} — {{ grant.recipient_name }}
                      </div>
                      <div class="text-slate-600 text-[11px] mt-0.5">{{ grant.purpose }}</div>
                      <div class="flex flex-wrap items-center gap-x-3 text-[11px] text-slate-500 mt-1 font-medium">
                        <span v-if="grant.proposer">Proposed by: <strong>{{ grant.proposer.first_name }} {{ grant.proposer.last_name }}</strong></span>
                        <span v-if="grant.seconder">Seconded by: <strong>{{ grant.seconder.first_name }} {{ grant.seconder.last_name }}</strong></span>
                      </div>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase bg-amber-100 text-amber-900 border border-amber-300 self-start sm:self-center">
                      {{ (grant.approval_status || 'proposed').replace('_', ' ') }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- SECTION 3: Officers for Year -->
            <div v-show="activeSection === 'officers'" class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-6">
              <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                  <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span>👔</span> OFFICERS FOR THE YEAR
                  </h3>
                  <p class="text-xs text-slate-500 mt-1">
                    Officers listed below will appear on the summons PDF. You can manage and edit officers on the Club Settings page.
                  </p>
                </div>
                
                <div class="flex flex-wrap items-center gap-2">
                  <Link
                    :href="route('admin.settings.show', { clubSlug: club.slug }) + '#officers'"
                    class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all flex items-center gap-1.5 cursor-pointer border border-slate-200"
                    title="Go to Club Settings to manage officers"
                  >
                    ⚙️ Manage Officers in Settings
                  </Link>
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Section Header Label</label>
                <input v-model="form.officers_year_label" type="text" placeholder="OFFICERS FOR 2025-2026" class="w-full sm:w-96 px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold" />
              </div>

              <!-- Officers Table -->
              <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                <table class="w-full text-left border-collapse">
                  <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
                      <th class="py-3 px-3 w-12 text-center">#</th>
                      <th class="py-3 px-4">Officer Role Title</th>
                      <th class="py-3 px-4">Assigned Member / Officer Name</th>
                      <th v-if="isEditingRoster" class="py-3 px-3 text-right w-28">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 text-xs">
                    <tr v-for="(item, idx) in form.officers_roster" :key="idx" class="hover:bg-slate-50/50">
                      <td class="py-2.5 px-3 text-center font-bold text-slate-400">
                        {{ idx + 1 }}
                      </td>

                      <td class="py-2.5 px-4">
                        <input
                          v-if="isEditingRoster"
                          v-model="item.role"
                          type="text"
                          placeholder="e.g. Worshipful Master"
                          class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <span v-else class="text-xs font-bold text-slate-800">{{ item.role }}</span>
                      </td>

                      <td class="py-2.5 px-4">
                        <div v-if="isEditingRoster" class="flex items-center gap-2">
                          <input
                            v-model="item.name"
                            type="text"
                            placeholder="e.g. W. Bro. K. D. Lord"
                            class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold outline-none focus:ring-2 focus:ring-indigo-500"
                          />
                          <select
                            @change="e => { if (e.target.value) item.name = e.target.value }"
                            class="px-2 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-[11px] font-medium cursor-pointer text-slate-600"
                          >
                            <option value="">Quick Select...</option>
                            <option v-for="m in members" :key="m.id" :value="`${m.pivot?.rank_prefix || 'W. Bro.'} ${m.name}`">
                              {{ m.name }} ({{ m.pivot?.rank || 'Member' }})
                            </option>
                          </select>
                        </div>
                        <span v-else class="text-xs font-semibold text-slate-600">{{ item.name }}</span>
                      </td>

                      <td v-if="isEditingRoster" class="py-2.5 px-3 text-right space-x-1">
                        <button
                          type="button"
                          @click="moveOfficerUp(idx)"
                          :disabled="idx === 0"
                          class="px-1.5 py-1 text-slate-400 hover:text-slate-700 disabled:opacity-30 cursor-pointer font-bold"
                          title="Move Up"
                        >
                          ↑
                        </button>
                        <button
                          type="button"
                          @click="moveOfficerDown(idx)"
                          :disabled="idx === form.officers_roster.length - 1"
                          class="px-1.5 py-1 text-slate-400 hover:text-slate-700 disabled:opacity-30 cursor-pointer font-bold"
                          title="Move Down"
                        >
                          ↓
                        </button>
                        <button
                          type="button"
                          @click="removeOfficerRow(idx)"
                          class="px-1.5 py-1 text-rose-500 hover:text-rose-700 font-bold cursor-pointer"
                          title="Remove"
                        >
                          ✕
                        </button>
                      </td>
                    </tr>
                    <tr v-if="!form.officers_roster.length">
                      <td :colspan="isEditingRoster ? 4 : 3" class="py-6 text-center text-slate-400 italic">
                        No officers added yet. Click "Reload from Settings" to import the master roster.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- SECTION 4: Festive Board & Banking -->
            <div v-show="activeSection === 'festive'" class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-4">
              <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">🍽️ FESTIVE BOARD & Payment Information</h3>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Festive Board Theme / Notice Text</label>
                <textarea v-model="form.festive_board_theme" rows="2" placeholder="The Lodge of Fraternity will be holding their Annual Burns Night..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs"></textarea>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Menu (Optional)</label>
                <textarea v-model="form.festive_board_menu" rows="3" placeholder="Starter: Tomato Soup&#10;Main: Roast Beef & Seasonal Vegetables&#10;Dessert: Apple Crumble & Custard" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono"></textarea>
                <p class="text-[11px] text-slate-400 mt-1">If entered, this menu will be listed on the summons PDF under the Festive Board section.</p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Price Per Head (£)</label>
                  <input v-model="form.dining_cost_member" type="number" step="0.01" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                </div>

                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Bank Sort Code</label>
                  <input v-model="form.bank_sort_code" type="text" placeholder="20-82-18" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                </div>

                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Bank Account Number</label>
                  <input v-model="form.bank_account_number" type="text" placeholder="80288373" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                </div>

                <div>
                  <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Payment Reference Convention</label>
                  <input v-model="form.payment_reference_prefix" type="text" placeholder="your name or names" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Direct Online Payment Link / URL (Optional)</label>
                <input v-model="form.payment_link" type="url" placeholder="https://buy.stripe.com/..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
                <p class="text-[11px] text-slate-400 mt-1">If provided, members will see a direct "💳 Pay Online Now" button on their passwordless RSVP page.</p>
              </div>
            </div>

            <!-- SECTION 5: Fraternal Visits & Honorary Members -->
            <div v-show="activeSection === 'visits'" class="bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-200/80 space-y-4">
              <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">🏛️ FRATERNAL VISITS & HONORARY MEMBERS</h3>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">FRATERNAL VISITS Text (Page 2 Right)</label>
                <textarea v-model="form.fraternal_visits_text" rows="3" placeholder="The Worshipful Master and Brethren of Haven of Rest Lodge No 4350..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs"></textarea>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">HONORARY MEMBER Text (Page 2 Left)</label>
                <textarea v-model="form.honorary_members_text" rows="2" placeholder="RW Bro Sir David Hugh Wootton Past Deputy Grand Master" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs"></textarea>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Pastoral / Almoner Emergency Contact Notice</label>
                <textarea v-model="form.sick_distressed_notes" rows="2" placeholder="Should you be aware of any illness..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs"></textarea>
              </div>
            </div>

            <!-- Action Footer -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
              <Link :href="route('admin.meetings.index', { clubSlug: club.slug })" class="px-5 py-2.5 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl">Cancel</Link>
              <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md cursor-pointer">
                Save Summons Changes
              </button>
            </div>

          </form>
        </div>

      </div>

    </div>
  </AdminLayout>
</template>
