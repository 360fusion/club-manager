<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  meeting: Object,
});

const form = useForm({
  id: props.meeting.id || null,
  title: props.meeting.title || 'Regular Meeting',
  meeting_number: props.meeting.meeting_number || 452,
  meeting_date: props.meeting.meeting_date || '',
  starts_at: props.meeting.starts_at || '18:30',
  rehearsal_starts_at: props.meeting.rehearsal_starts_at || '17:30',
  venue: props.meeting.venue || 'Masonic Hall, Oxford',
  dress_code: props.meeting.dress_code || 'Dark Suit, Craft Regalia',
  festive_board_theme: props.meeting.festive_board_theme || 'Harvest Festival Dinner',
  dining_cost_member: props.meeting.dining_cost_member || 35.00,
  dining_cost_guest: props.meeting.dining_cost_guest || 35.00,
  bank_sort_code: props.meeting.bank_sort_code || '20-65-18',
  bank_account_number: props.meeting.bank_account_number || '83920145',
  payment_reference_prefix: props.meeting.payment_reference_prefix || 'SUMMONS',
  almoner_notice: props.meeting.almoner_notice || '',
  status: props.meeting.status || 'draft',
});

const submit = () => {
  form.post(route('admin.meetings.store', { clubSlug: props.club.slug }));
};
</script>

<template>
  <AdminLayout :title="`${meeting.id ? 'Edit' : 'Create'} Meeting`" :club="club" active-tab="meetings">
    
    <div class="max-w-4xl mx-auto space-y-6">
      
      <div class="flex items-center justify-between bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900">{{ meeting.id ? 'Edit Meeting Details' : 'Create New Meeting' }}</h2>
          <p class="text-xs text-slate-500 mt-0.5">Configure schedule, venue, dress code, and festive board dining details.</p>
        </div>
        <Link :href="route('admin.meetings.index', { clubSlug: club.slug })" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl transition-all">
          &larr; Back to Meetings
        </Link>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        
        <!-- General Details -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
          <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Meeting Schedule & Venue</h3>

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
              <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Start Time</label>
              <input v-model="form.starts_at" type="text" required placeholder="18:30" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Rehearsal Time</label>
              <input v-model="form.rehearsal_starts_at" type="text" placeholder="17:30" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Dress Code</label>
              <input v-model="form.dress_code" type="text" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Venue Address</label>
            <input v-model="form.venue" type="text" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
          </div>
        </div>

        <!-- Festive Board / Dining & Payments -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
          <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">Festive Board & Bank Transfer Details</h3>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Dining Cost (Member)</label>
              <input v-model="form.dining_cost_member" type="number" step="0.01" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Dining Cost (Guest)</label>
              <input v-model="form.dining_cost_guest" type="number" step="0.01" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Status</label>
              <select v-model="form.status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Bank Sort Code</label>
              <input v-model="form.bank_sort_code" type="text" placeholder="20-65-18" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Bank Account Number</label>
              <input v-model="form.bank_account_number" type="text" placeholder="83920145" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase mb-1">Payment Reference Prefix</label>
              <input v-model="form.payment_reference_prefix" type="text" placeholder="SUMMONS" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs" />
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <Link :href="route('admin.meetings.index', { clubSlug: club.slug })" class="px-5 py-2.5 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl">Cancel</Link>
          <button type="submit" :disabled="form.processing" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md">
            Save Meeting Details
          </button>
        </div>

      </form>

    </div>
  </AdminLayout>
</template>
