<script setup>
import { ref } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  meeting: Object,
  members: Array,
});

const activeSection = ref('general');

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
  officers_year_label: props.meeting.officers_year_label || 'OFFICERS FOR 2025-2026',
  status: props.meeting.status || 'draft',
  agenda_items: props.meeting.agenda_items?.length ? props.meeting.agenda_items : [
    { title: 'To confirm the minutes of the Regular Meeting held previously.', description: '' },
    { title: 'To report on the proceedings of Grand Lodge.', description: '' },
    { title: 'To transact any other lawful Masonic Business.', description: '' },
  ],
});

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
  <AdminLayout :title="`${meeting.id ? 'Edit Summons Builder' : 'Create Summons'}`" :club="club" active-tab="meetings">
    
    <div class="max-w-5xl mx-auto space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900">Summons Page Builder</h2>
          <p class="text-xs text-slate-500 mt-0.5">Customize formal summons sections, business agenda, officers, festive board, and fraternal visits.</p>
        </div>
        
        <div class="flex items-center gap-3">
          <a v-if="meeting.id" :href="route('admin.meetings.pdf', { clubSlug: club.slug, id: meeting.id })" target="_blank" class="px-4 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-300 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5">
            🖨️ Preview & Download Printable Summons PDF
          </a>
          <Link :href="route('admin.meetings.index', { clubSlug: club.slug })" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl transition-all">
            &larr; Back to Meetings
          </Link>
        </div>
      </div>

      <!-- Builder Navigation Tabs -->
      <div class="flex items-center gap-2 border-b border-slate-200 pb-2 overflow-x-auto">
        <button @click="activeSection = 'general'" :class="['px-4 py-2 text-xs font-bold rounded-xl transition-all', activeSection === 'general' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white']">
          📜 1. General & Intro Letter
        </button>
        <button @click="activeSection = 'agenda'" :class="['px-4 py-2 text-xs font-bold rounded-xl transition-all', activeSection === 'agenda' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white']">
          📋 2. Order of Business (Agenda)
        </button>
        <button @click="activeSection = 'officers'" :class="['px-4 py-2 text-xs font-bold rounded-xl transition-all', activeSection === 'officers' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white']">
          👔 3. Officers for Year
        </button>
        <button @click="activeSection = 'festive'" :class="['px-4 py-2 text-xs font-bold rounded-xl transition-all', activeSection === 'festive' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white']">
          🍽️ 4. Festive Board & Banking
        </button>
        <button @click="activeSection = 'visits'" :class="['px-4 py-2 text-xs font-bold rounded-xl transition-all', activeSection === 'visits' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-white']">
          🏛️ 5. Fraternal Visits & Honorary
        </button>
      </div>

      <!-- Form Body -->
      <form @submit.prevent="submit" class="space-y-6">
        
        <!-- SECTION 1: General & Intro Letter -->
        <div v-show="activeSection === 'general'" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
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

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Formal Salutation</label>
              <input v-model="form.salutation" type="text" placeholder="Dear Sir and Brother," class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Publish Status</label>
              <select v-model="form.status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="completed">Completed</option>
              </select>
            </div>
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
        <div v-show="activeSection === 'agenda'" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
              <h3 class="text-base font-bold text-slate-900">📋 BUSINESS — Order of Business</h3>
              <p class="text-xs text-slate-500">Numbered items rendered directly under the BUSINESS header on Page 2.</p>
            </div>
            <button type="button" @click="addAgendaItem" class="px-3 py-1.5 bg-indigo-50 text-indigo-700 font-bold text-xs rounded-xl hover:bg-indigo-100 transition-all">
              + Add Agenda Item
            </button>
          </div>

          <div v-for="(item, idx) in form.agenda_items" :key="idx" class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-700">Item #{{ idx + 1 }}</span>
              <button type="button" @click="removeAgendaItem(idx)" class="text-xs font-bold text-rose-600">Delete Item</button>
            </div>
            <input v-model="item.title" type="text" required placeholder="To confirm the minutes / ballot for candidate..." class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-xs font-semibold" />
            <textarea v-model="item.description" rows="2" placeholder="Optional notes or details..." class="w-full px-3.5 py-2 bg-white border border-slate-300 rounded-xl text-xs"></textarea>
          </div>
        </div>

        <!-- SECTION 3: Officers for Year -->
        <div v-show="activeSection === 'officers'" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
          <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">👔 OFFICERS FOR 2025-2026</h3>
          <p class="text-xs text-slate-500">This section renders on the left column of Page 2. Member ranks and assigned lodge officer roles populate automatically.</p>

          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Section Header Label</label>
            <input v-model="form.officers_year_label" type="text" placeholder="OFFICERS FOR 2025-2026" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold" />
          </div>
        </div>

        <!-- SECTION 4: Festive Board & Banking -->
        <div v-show="activeSection === 'festive'" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
          <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">🍽️ FESTIVE BOARD & Payment Information</h3>

          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Festive Board Theme / Notice Text</label>
            <textarea v-model="form.festive_board_theme" rows="2" placeholder="The Lodge of Fraternity will be holding their Annual Burns Night..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs"></textarea>
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
        <div v-show="activeSection === 'visits'" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
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
        <div class="flex items-center justify-between pt-4 border-t border-slate-200">
          <a v-if="meeting.id" :href="route('admin.meetings.pdf', { clubSlug: club.slug, id: meeting.id })" target="_blank" class="text-xs font-bold text-emerald-700 hover:underline flex items-center gap-1">
            🖨️ Open Printable PDF View
          </a>
          <div class="flex items-center gap-3">
            <Link :href="route('admin.meetings.index', { clubSlug: club.slug })" class="px-5 py-2.5 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl">Cancel</Link>
            <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md">
              Save Summons Changes
            </button>
          </div>
        </div>

      </form>

    </div>
  </AdminLayout>
</template>
