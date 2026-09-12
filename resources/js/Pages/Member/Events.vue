<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import MemberLayout from '@/Layouts/MemberLayout.vue';

const props = defineProps({
  club: { type: Object, required: true },
  memberRole: { type: String, default: 'member' },
  events: { type: Array, default: () => [] },
});

const selectedEvent = ref(null);
const showDinnerModal = ref(false);

const rsvpForm = useForm({
  attendance_status: 'attending',
  attending_dining: false,
  menu_selections: { starter: '', main: '', dessert: '' },
  dietary_requirements: '',
});

const openRsvpModal = (event) => {
  selectedEvent.value = event;
  rsvpForm.attendance_status = event.user_rsvp?.attendance_status || 'attending';
  rsvpForm.attending_dining = event.user_rsvp?.attending_dining || false;
  rsvpForm.menu_selections = event.user_rsvp?.menu_selections || { starter: '', main: '', dessert: '' };
  rsvpForm.dietary_requirements = event.user_rsvp?.dietary_requirements || '';
  showDinnerModal.value = true;
};

const submitRsvp = () => {
  if (!selectedEvent.value) return;
  rsvpForm.post(route('member.rsvp', { slug: props.club.slug, id: selectedEvent.value.id }), {
    onSuccess: () => {
      showDinnerModal.value = false;
    }
  });
};
</script>

<template>
  <MemberLayout title="My Events & RSVPs" :club="club" :member-role="memberRole" active-tab="events">
    
    <div class="space-y-6">
      
      <!-- Top Action & Summary Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-xl font-bold text-slate-900">Events, Regattas & Training Schedule</h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-sky-50 text-sky-700 border border-sky-200">
              {{ events.length }} Upcoming
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">View upcoming outings, confirm attendance, choose 3-course dining selections, and view your ticket barcodes.</p>
        </div>
      </div>

      <!-- Events List Cards Grid -->
      <div v-if="events.length > 0" class="space-y-4">
        <div
          v-for="event in events"
          :key="event.id"
          class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 hover:border-slate-300 transition-all space-y-4"
        >
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1.5">
              <div class="flex items-center gap-2">
                <span v-if="event.user_rsvp?.checked_in_at" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                  ✓ CHECKED IN @ {{ event.user_rsvp.checked_in_at }}
                </span>
                <span v-else-if="event.user_rsvp?.attendance_status === 'attending'" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                  RSVP CONFIRMED
                </span>
                <span v-if="event.has_dining" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-sky-50 text-sky-700 border border-sky-200">
                  🍽️ 3-COURSE DINING
                </span>
              </div>
              <h3 class="text-lg font-bold text-slate-900">{{ event.title }}</h3>
              <p class="text-xs text-slate-500">📍 {{ event.location }} • 🕒 {{ event.starts_at }}</p>
              <p v-if="event.description" class="text-xs text-slate-600 mt-1 leading-relaxed">{{ event.description }}</p>
            </div>

            <button
              @click="openRsvpModal(event)"
              class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition-all self-start sm:self-auto"
            >
              {{ event.user_rsvp ? 'Edit RSVP & Dining' : 'RSVP & Select Menu' }}
            </button>
          </div>
        </div>
      </div>

      <div v-else class="bg-white rounded-2xl p-12 text-center border border-slate-200/80 text-slate-500 text-sm">
        No upcoming events or regattas scheduled for {{ club.name }} at this time.
      </div>

    </div>

    <!-- RSVP & 3-Course Dining Modal -->
    <div v-if="showDinnerModal && selectedEvent" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full space-y-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div>
            <h3 class="text-lg font-bold text-slate-900">RSVP & Meal Selection</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ selectedEvent.title }}</p>
          </div>
          <button @click="showDinnerModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>

        <form @submit.prevent="submitRsvp" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Attendance Status</label>
            <select v-model="rsvpForm.attendance_status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-emerald-500">
              <option value="attending">I will attend</option>
              <option value="declined">Unable to attend</option>
              <option value="tentative">Tentative</option>
            </select>
          </div>

          <!-- Dining options if event has dining -->
          <div v-if="selectedEvent.has_dining" class="space-y-3 pt-2 border-t border-slate-100">
            <div class="flex items-center gap-3">
              <input v-model="rsvpForm.attending_dining" type="checkbox" id="attending_dining_modal" class="w-4 h-4 rounded text-emerald-600 border-slate-300" />
              <label for="attending_dining_modal" class="font-bold text-slate-900 text-xs">Attending Formal 3-Course Dinner</label>
            </div>

            <div v-if="rsvpForm.attending_dining" class="space-y-3 pl-2">
              <div>
                <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Starter Choice</label>
                <input v-model="rsvpForm.menu_selections.starter" type="text" placeholder="e.g. Smoked Salmon" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900" />
              </div>
              <div>
                <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Main Course Choice</label>
                <input v-model="rsvpForm.menu_selections.main" type="text" placeholder="e.g. Pan-Seared Duck Breast" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900" />
              </div>
              <div>
                <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Dessert Choice</label>
                <input v-model="rsvpForm.menu_selections.dessert" type="text" placeholder="e.g. Dark Chocolate Fondant" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900" />
              </div>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Dietary Requirements</label>
            <textarea v-model="rsvpForm.dietary_requirements" rows="2" placeholder="Gluten-free, Nut allergy, Vegetarian..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-emerald-500"></textarea>
          </div>

          <div class="flex items-center gap-3 pt-4">
            <button type="submit" :disabled="rsvpForm.processing" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition-all">
              Confirm RSVP
            </button>
            <button type="button" @click="showDinnerModal = false" class="px-4 py-3 bg-slate-100 text-slate-700 font-bold text-xs rounded-xl border border-slate-200">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>

  </MemberLayout>
</template>
