<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useForm, Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';

import { formatMoney } from '@/Utils/currency';
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

const addGrantToAgenda = (grant) => {
  const proposerName = grant.proposer ? `${grant.proposer.first_name} ${grant.proposer.last_name}` : '';
  const seconderName = grant.seconder ? `${grant.seconder.first_name} ${grant.seconder.last_name}` : '';
  let desc = `To consider and, if approved, pass a resolution proposing a Charity Grant of ${formatMoney(grant.amount)} to ${grant.recipient_name} (${grant.purpose}).`;
  if (proposerName) desc += ` Proposed by ${proposerName}.`;
  if (seconderName) desc += ` Seconded by ${seconderName}.`;

  form.agenda_items.push({
    title: `Charity Grant Proposition: ${formatMoney(grant.amount)} to ${grant.recipient_name}`,
    description: desc,
  });
};

const removeAgendaItem = (index) => {
  form.agenda_items.splice(index, 1);
};

const submit = () => {
  form.post(route('admin.meetings.store', { clubSlug: props.club.slug }));
};

const SECTIONS = [
  { value: 'general', label: 'General and intro letter', short: 'General and intro' },
  { value: 'agenda', label: 'Order of business (agenda)', short: 'Order of business' },
  { value: 'festive', label: 'Festive board and banking', short: 'Festive board' },
  { value: 'visits', label: 'Fraternal visits and honorary', short: 'Fraternal and honorary' },
  { value: 'front_page', label: 'Front page', short: 'Front page' },
  { value: 'officers', label: 'Officers for the year', short: 'Officers for the year' },
];

// Fields in repeating rows (agenda items, officers) stay native but take the kit's field look.
const cell = 'rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
</script>

<template>
  <AdminLayout :title="`${meeting.id ? 'Edit summons' : 'Create summons'}`" :club="club" active-tab="meetings">
    <div class="mx-auto max-w-6xl space-y-6">
      <Card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Edit summons</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Customise the formal summons sections, business agenda, officers, festive board and fraternal visits.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <Button v-if="meeting.id" variant="secondary" size="sm" :href="route('admin.meetings.pdf', { clubSlug: club.slug, id: meeting.id })" target="_blank">Preview PDF</Button>
            <Button size="sm" :loading="form.processing" @click="submit">Save changes</Button>
          </div>
        </div>
      </Card>

      <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-4">
        <div class="space-y-4 lg:col-span-1">
          <Card padding="sm" class="lg:hidden">
            <Select v-model="activeSection" label="Summons section" :options="SECTIONS.map((s, i) => ({ value: s.value, label: `${i + 1}. ${s.label}` }))" />
          </Card>

          <Card padding="sm" class="sticky top-6 hidden lg:block">
            <nav aria-label="Summons sections">
              <p class="mb-1.5 px-3 text-xs font-medium text-slate-500 dark:text-slate-400">Summons sections</p>
              <div class="space-y-0.5">
                <button
                  v-for="section in SECTIONS"
                  :key="section.value"
                  type="button"
                  :aria-current="activeSection === section.value ? 'true' : undefined"
                  :class="['w-full rounded-xl px-3.5 py-2.5 text-left text-sm font-medium transition-colors', activeSection === section.value ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800/50 dark:hover:text-white']"
                  @click="activeSection = section.value"
                >
                  {{ section.short }}
                </button>
              </div>
            </nav>
          </Card>
        </div>

        <div class="space-y-6 lg:col-span-3">
          <form class="space-y-6" @submit.prevent="submit">
            <Card v-show="activeSection === 'front_page'">
              <template #header>
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                  <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Front page: PDF cover</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Configure the emblem, province header, lodge title, motto and Worshipful Master details shown on page 1 (the cover).</p>
                  </div>
                  <Button variant="secondary" size="sm" class="self-start whitespace-nowrap sm:self-auto" @click="importProvincialRulersFromSettings">Import provincial rulers</Button>
                </div>
              </template>

              <div class="space-y-4">
                <div>
                  <label for="front-page-logo" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Logo or emblem image</label>
                  <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-300 bg-white dark:border-slate-700 dark:bg-slate-900">
                      <img v-if="logoPreview" :src="logoPreview" class="h-full w-full object-contain p-1" alt="Logo preview" />
                      <span v-else class="text-xs text-slate-400">No logo</span>
                    </div>
                    <div class="flex-1 space-y-1.5">
                      <input
                        id="front-page-logo"
                        type="file"
                        accept=".jpg,.jpeg,.png,.gif,.webp"
                        class="block w-full cursor-pointer text-sm text-slate-500 file:mr-3 file:rounded-xl file:border-0 file:bg-blue-600 file:px-3.5 file:py-2 file:text-xs file:font-medium file:text-white hover:file:bg-blue-500 dark:text-slate-400"
                        @change="handleLogoUpload"
                      />
                      <div class="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                        <span>Upload a logo (PNG, JPG, GIF or WEBP, up to 5 MB)</span>
                        <Button v-if="logoPreview" variant="ghost" size="sm" @click="clearLogo">Remove logo</Button>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <Input v-model="form.front_page_title" label="Title (province or organisation header)" placeholder="PROVINCIAL GRAND LODGE" />
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <Input v-model="form.provincial_grand_master" label="Provincial Grand Master" placeholder="R WBro John David Watts" />
                  <Input v-model="form.deputy_provincial_grand_master" label="Deputy Provincial Grand Master" placeholder="WBro Andrew Peter Faul Foster PSGD" />
                </div>

                <Textarea v-model="form.assistant_provincial_grand_masters" label="Assistant Provincial Grand Masters" hint="One per line." :rows="4" placeholder="WBro Dr. Rakesh Bhalla PSGD..." class="font-mono" />

                <div class="space-y-4 border-t border-slate-200 pt-4 dark:border-slate-800">
                  <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Lodge or club branding header</h4>

                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Input v-model="form.cover_club_name" label="Club name (large text)" placeholder="LODGE OF FRATERNITY" />
                    <Input v-model="form.cover_club_number" label="Club number (smaller text)" placeholder="1418" />
                  </div>

                  <Input v-model="form.cover_motto" label="Club motto" placeholder="Fraternus Amor Maneto" />

                  <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <Input v-model="form.cover_worshipful_master" class="flex-1" label="Worshipful Master" placeholder="WBro KD Lord" />
                    <select aria-label="Pull the Worshipful Master from a member's rank" :class="cell" class="sm:w-auto" @change="e => form.cover_worshipful_master = e.target.value">
                      <option value="">Pull from member rank...</option>
                      <option v-for="m in members" :key="m.id" :value="`${m.pivot?.rank_prefix || 'WBro'} ${m.name}`">
                        {{ m.pivot?.rank_prefix || 'WBro' }} {{ m.name }} ({{ m.pivot?.rank || 'Member' }})
                      </option>
                    </select>
                  </div>
                </div>
              </div>
            </Card>

            <Card v-show="activeSection === 'general'">
              <template #header>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Meeting intro and schedule details</h3>
              </template>

              <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <Input v-model="form.title" label="Meeting title" required :error="form.errors.title" />
                  <Input v-model="form.meeting_date" type="date" label="Meeting date" required :error="form.errors.meeting_date" />
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                  <Input v-model="form.starts_at" label="Meeting start time" required placeholder="7:00pm" :error="form.errors.starts_at" />
                  <Input v-model="form.rehearsal_starts_at" label="Rehearsal time" placeholder="6:00pm" :error="form.errors.rehearsal_starts_at" />
                  <Input v-model="form.dress_code" label="Dress code" required placeholder="Dinner Jacket, White Gloves" :error="form.errors.dress_code" />
                </div>

                <Input v-model="form.venue" label="Venue address" required :error="form.errors.venue" />
                <Input v-model="form.salutation" label="Formal salutation" placeholder="Dear Sir and Brother," />
                <Textarea v-model="form.intro_text" label="Intro request text (page 2)" :rows="3" />
                <Input v-model="form.rehearsal_text" label="Rehearsal notice text" placeholder="The rehearsal should it be necessary will be at 6:00pm on Friday..." />
              </div>
            </Card>

            <Card v-show="activeSection === 'agenda'">
              <template #header>
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Order of business</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Numbered items shown directly under the BUSINESS header on page 2.</p>
                  </div>
                  <Button variant="secondary" size="sm" @click="addAgendaItem">Add an agenda item</Button>
                </div>
              </template>

              <div class="space-y-4">
                <div v-for="(item, idx) in form.agenda_items" :key="idx" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Item {{ idx + 1 }}</span>
                    <Button variant="ghost" size="sm" @click="removeAgendaItem(idx)">Delete item</Button>
                  </div>
                  <input v-model="item.title" type="text" required :aria-label="`Item ${idx + 1} title`" placeholder="To confirm the minutes / ballot for candidate..." :class="[cell, 'w-full font-medium']" />
                  <textarea v-model="item.description" rows="2" :aria-label="`Item ${idx + 1} notes`" placeholder="Optional notes or details..." :class="[cell, 'w-full']"></textarea>
                </div>

                <div v-if="charityGrants && charityGrants.length > 0" class="space-y-3 border-t border-slate-200 pt-6 dark:border-slate-800">
                  <div class="flex items-center justify-between gap-3">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Charitable donation proposals and alms voting</h4>
                    <Badge>{{ charityGrants.length }} proposal(s) logged</Badge>
                  </div>
                  <div class="space-y-2">
                    <div v-for="grant in charityGrants" :key="grant.id" class="flex flex-col justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-sm dark:border-slate-800 dark:bg-slate-800/50 sm:flex-row sm:items-center">
                      <div>
                        <div class="font-semibold text-slate-900 dark:text-white">{{ $money(grant.amount) }} — {{ grant.recipient_name }}</div>
                        <div class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">{{ grant.purpose }}</div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-slate-500 dark:text-slate-400">
                          <span v-if="grant.proposer">Proposed by: <strong class="font-semibold">{{ grant.proposer.first_name }} {{ grant.proposer.last_name }}</strong></span>
                          <span v-if="grant.seconder">Seconded by: <strong class="font-semibold">{{ grant.seconder.first_name }} {{ grant.seconder.last_name }}</strong></span>
                        </div>
                      </div>
                      <div class="flex items-center gap-2 self-start sm:self-center">
                        <Badge variant="warning" class="capitalize">{{ (grant.approval_status || 'proposed').replace('_', ' ') }}</Badge>
                        <Button variant="secondary" size="sm" @click="addGrantToAgenda(grant)">Add to agenda</Button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </Card>

            <Card v-show="activeSection === 'officers'">
              <template #header>
                <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                  <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Officers for the year</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Officers listed below will appear on the summons PDF. You can manage and edit officers on the club settings page.</p>
                  </div>
                  <Button variant="secondary" size="sm" :href="route('admin.settings.show', { clubSlug: club.slug }) + '#officers'" title="Go to club settings to manage officers">Manage officers in settings</Button>
                </div>
              </template>

              <div class="space-y-6">
                <Input v-model="form.officers_year_label" class="sm:w-96" label="Section header label" placeholder="OFFICERS FOR 2025-2026" />

                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                  <table class="w-full border-collapse text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                      <tr>
                        <th scope="col" class="w-12 px-3 py-3 text-center font-medium">#</th>
                        <th scope="col" class="px-4 py-3 font-medium">Officer role</th>
                        <th scope="col" class="px-4 py-3 font-medium">Member or officer name</th>
                        <th v-if="isEditingRoster" scope="col" class="w-28 px-3 py-3 text-right font-medium">Actions</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                      <tr v-for="(item, idx) in form.officers_roster" :key="idx" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50">
                        <td class="px-3 py-2.5 text-center font-medium text-slate-400">{{ idx + 1 }}</td>

                        <td class="px-4 py-2.5">
                          <input v-if="isEditingRoster" v-model="item.role" type="text" :aria-label="`Officer ${idx + 1} role`" placeholder="e.g. Worshipful Master" :class="[cell, 'w-full']" />
                          <span v-else class="font-semibold text-slate-800 dark:text-slate-100">{{ item.role }}</span>
                        </td>

                        <td class="px-4 py-2.5">
                          <div v-if="isEditingRoster" class="flex items-center gap-2">
                            <input v-model="item.name" type="text" :aria-label="`Officer ${idx + 1} name`" placeholder="e.g. W. Bro. K. D. Lord" :class="[cell, 'w-full']" />
                            <select :aria-label="`Pick a member for officer ${idx + 1}`" :class="cell" @change="e => { if (e.target.value) item.name = e.target.value }">
                              <option value="">Quick select...</option>
                              <option v-for="m in members" :key="m.id" :value="`${m.pivot?.rank_prefix || 'W. Bro.'} ${m.name}`">
                                {{ m.name }} ({{ m.pivot?.rank || 'Member' }})
                              </option>
                            </select>
                          </div>
                          <span v-else class="text-slate-600 dark:text-slate-300">{{ item.name }}</span>
                        </td>

                        <td v-if="isEditingRoster" class="whitespace-nowrap px-3 py-2.5 text-right">
                          <Button variant="ghost" size="sm" :disabled="idx === 0" aria-label="Move up" title="Move up" @click="moveOfficerUp(idx)">↑</Button>
                          <Button variant="ghost" size="sm" :disabled="idx === form.officers_roster.length - 1" aria-label="Move down" title="Move down" @click="moveOfficerDown(idx)">↓</Button>
                          <Button variant="ghost" size="sm" @click="removeOfficerRow(idx)">Remove</Button>
                        </td>
                      </tr>
                      <tr v-if="!form.officers_roster.length">
                        <td :colspan="isEditingRoster ? 4 : 3" class="py-6 text-center text-slate-500 dark:text-slate-400">
                          No officers added yet. Click "Reload from settings" to import the master roster.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </Card>

            <Card v-show="activeSection === 'festive'">
              <template #header>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Festive board and payment information</h3>
              </template>

              <div class="space-y-4">
                <Textarea v-model="form.festive_board_theme" label="Festive board theme or notice text" :rows="2" placeholder="The Lodge of Fraternity will be holding their Annual Burns Night..." />
                <Textarea v-model="form.festive_board_menu" label="Menu" hint="Optional. If entered, this menu is listed on the summons PDF under the festive board section." :rows="3" :placeholder="'Starter: Tomato Soup\nMain: Roast Beef & Seasonal Vegetables\nDessert: Apple Crumble & Custard'" class="font-mono" />

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                  <Input v-model.number="form.dining_cost_member" type="number" step="0.01" required label="Price per head" :prefix="$cs" :error="form.errors.dining_cost_member" />
                  <Input v-model="form.bank_sort_code" label="Bank sort code" placeholder="20-82-18" />
                  <Input v-model="form.bank_account_number" label="Bank account number" placeholder="80288373" />
                  <Input v-model="form.payment_reference_prefix" label="Payment reference convention" placeholder="your name or names" />
                </div>

                <Input v-model="form.payment_link" type="url" label="Direct online payment link" hint='Optional. If provided, members will see a direct "Pay online now" button on their passwordless RSVP page.' placeholder="https://buy.stripe.com/..." :error="form.errors.payment_link" />
              </div>
            </Card>

            <Card v-show="activeSection === 'visits'">
              <template #header>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Fraternal visits and honorary members</h3>
              </template>

              <div class="space-y-4">
                <Textarea v-model="form.fraternal_visits_text" label="Fraternal visits text (page 2, right)" :rows="3" placeholder="The Worshipful Master and Brethren of Haven of Rest Lodge No 4350..." />
                <Textarea v-model="form.honorary_members_text" label="Honorary member text (page 2, left)" :rows="2" placeholder="RW Bro Sir David Hugh Wootton Past Deputy Grand Master" />
                <Textarea v-model="form.sick_distressed_notes" label="Pastoral or almoner emergency contact notice" :rows="2" placeholder="Should you be aware of any illness..." />
              </div>
            </Card>

            <div class="flex items-center justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
              <Button variant="secondary" :href="route('admin.meetings.index', { clubSlug: club.slug })">Cancel</Button>
              <Button type="submit" :loading="form.processing">Save summons changes</Button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
