<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Select from '@/Components/Ui/Select.vue';
import Card from '@/Components/Ui/Card.vue';

const props = defineProps({
  club: Object,
  event: Object,
  visibilityOptions: { type: Array, default: () => [] },
});

const form = useForm({
  id: props.event.id || null,
  title: props.event.title || '',
  slug: props.event.slug || '',
  description: props.event.description || '',
  location: props.event.location || '',
  address_line_1: props.event.address_line_1 || '',
  address_line_2: props.event.address_line_2 || '',
  city: props.event.city || '',
  county: props.event.county || '',
  postcode: props.event.postcode || '',
  starts_at: props.event.starts_at || '',
  booking_cutoff_days: props.event.booking_cutoff_days ?? 7,
  requires_payment: props.event.requires_payment ?? true,
  price: props.event.price || 0,
  has_dining: props.event.has_dining ?? false,
  dining_price: props.event.dining_price || 0,
  status: props.event.status || 'upcoming',
  visibility: props.event.visibility || 'club',
  rsvp_audience: props.event.rsvp_audience || 'club',
  ticket_tiers: props.event.ticket_tiers || [
    { name: 'General Admission', price: 25.00, max_quantity: 100 }
  ],
  promos: props.event.promos || [
    { code: 'EARLYBIRD10', discount_amount: 10, max_uses: 50 }
  ],
  menu_items: props.event.menu_items || [
    { category: 'starter', name: 'Smoked Salmon Tartine', description: 'With dill caper cream' },
    { category: 'main', name: 'Roasted Sirloin Beef', description: 'With dauphinoise potatoes' },
    { category: 'dessert', name: 'Dark Chocolate Fondant', description: 'With vanilla ice cream' }
  ],
});

// Options run narrowest to widest, so an option's position is its breadth.
const breadth = (value) => props.visibilityOptions.findIndex((o) => o.value === value);

const rsvpOptions = computed(() => props.visibilityOptions.filter((o) => breadth(o.value) <= breadth(form.visibility)));

const hintFor = (value) => props.visibilityOptions.find((o) => o.value === value)?.description ?? '';

watch(() => form.visibility, () => {
  if (breadth(form.rsvp_audience) > breadth(form.visibility)) {
    form.rsvp_audience = form.visibility;
  }
});

const addTier = () => {
  form.ticket_tiers.push({ name: 'VIP Pass', price: 50.00, max_quantity: 20 });
};

const removeTier = (index) => {
  form.ticket_tiers.splice(index, 1);
};

const addPromo = () => {
  form.promos.push({ code: 'SUMMER20', discount_amount: 20, max_uses: 30 });
};

const removePromo = (index) => {
  form.promos.splice(index, 1);
};

const addMenuItem = () => {
  form.menu_items.push({ category: 'main', name: 'Vegetarian Option', description: '' });
};

const removeMenuItem = (index) => {
  form.menu_items.splice(index, 1);
};

const submit = () => {
  form.post(route('admin.events.store', { clubSlug: props.club.slug }));
};
</script>

<template>
  <AdminLayout :title="`${event.id ? 'Edit' : 'Create'} Event`" :club="club" active-tab="events">
    
    <div class="max-w-4xl mx-auto space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex items-center justify-between bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ event.id ? 'Edit Event Details' : 'Create New Event' }}</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Configure event schedule, address details, booking cutoff rules, ticketing tiers, and menu items.</p>
        </div>
        <Link :href="route('admin.events.index', { clubSlug: club.slug })" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl transition-all">
          &larr; Back to Events
        </Link>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-6">
        
        <!-- Audience -->
        <Card>
          <template #header>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Who can see this event and RSVP</h3>
          </template>
          <div class="grid sm:grid-cols-2 gap-5">
            <Select v-model="form.visibility" label="Who can see this event" :options="visibilityOptions" :hint="hintFor(form.visibility)" :error="form.errors.visibility" />
            <Select v-model="form.rsvp_audience" label="Who can RSVP" :options="rsvpOptions" :hint="hintFor(form.rsvp_audience)" :error="form.errors.rsvp_audience" />
          </div>
        </Card>

        <!-- General Details -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
          <h3 class="text-base font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">Event General Details</h3>

          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Event Title</label>
            <input v-model="form.title" type="text" required placeholder="Annual Boat Club Dinner" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500" />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Description</label>
            <textarea v-model="form.description" rows="3" placeholder="Event details and RSVP instructions..." class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"></textarea>
          </div>
        </div>

        <!-- Venue Address Section -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
          <h3 class="text-base font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">📍 Venue Address & Location Details</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Venue / Building Name (Line 1)</label>
              <input v-model="form.address_line_1" type="text" placeholder="e.g. Christ Church Great Hall" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Street Address (Line 2)</label>
              <input v-model="form.address_line_2" type="text" placeholder="e.g. St Aldate's" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500" />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">City / Town</label>
              <input v-model="form.city" type="text" placeholder="e.g. Oxford" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">County / Region</label>
              <input v-model="form.county" type="text" placeholder="e.g. Oxfordshire" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Postcode</label>
              <input v-model="form.postcode" type="text" placeholder="e.g. OX1 1DP" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500" />
            </div>
          </div>
        </div>

        <!-- Schedule & Booking Cutoff Settings -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
          <h3 class="text-base font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">⏰ Schedule & Booking Cutoff Rules</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Event Start Date & Time</label>
              <input v-model="form.starts_at" type="datetime-local" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Booking Close Cutoff (Days Before Event)</label>
              <input v-model="form.booking_cutoff_days" type="number" min="0" placeholder="e.g. 7" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500" />
              <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Number of days prior to event start date after which new bookings & RSVPs are closed (e.g. 7 = no bookings within 7 days of event).</p>
            </div>
          </div>
        </div>

        <!-- Ticket Tiers -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">🎟️ Ticket Tiers</h3>
            <button type="button" @click="addTier" class="px-3 py-1.5 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/60 text-xs font-semibold rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-all">+ Add Tier</button>
          </div>

          <div v-for="(tier, index) in form.ticket_tiers" :key="index" class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200/80 dark:border-slate-800/80 grid grid-cols-1 sm:grid-cols-4 gap-3 items-center">
            <input v-model="tier.name" type="text" placeholder="Tier Name (e.g. Early Bird)" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-100" />
            <input v-model="tier.price" type="number" step="0.01" :placeholder="'Price (' + $cs + ')'" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-100" />
            <input v-model="tier.max_quantity" type="number" placeholder="Capacity" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-100" />
            <button type="button" @click="removeTier(index)" class="text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 text-xs font-semibold">Remove</button>
          </div>
        </div>

        <!-- Promos -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">🏷️ Promo Codes</h3>
            <button type="button" @click="addPromo" class="px-3 py-1.5 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/60 text-xs font-semibold rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-all">+ Add Promo</button>
          </div>

          <div v-for="(promo, index) in form.promos" :key="index" class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200/80 dark:border-slate-800/80 grid grid-cols-1 sm:grid-cols-4 gap-3 items-center">
            <input v-model="promo.code" type="text" placeholder="CODE (e.g. EARLYBIRD10)" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-100 font-mono uppercase" />
            <input v-model="promo.discount_amount" type="number" placeholder="Discount %" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-100" />
            <input v-model="promo.max_uses" type="number" placeholder="Max Uses" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-100" />
            <button type="button" @click="removePromo(index)" class="text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 text-xs font-semibold">Remove</button>
          </div>
        </div>

        <!-- Dining Menu -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-3">
              <input v-model="form.has_dining" type="checkbox" id="has_dining" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 border-slate-300 dark:border-slate-700 focus:ring-blue-500" />
              <label for="has_dining" class="font-bold text-slate-900 dark:text-white text-sm">Enable 3-Course Dining & Summons</label>
            </div>
            <button v-if="form.has_dining" type="button" @click="addMenuItem" class="px-3 py-1.5 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/60 text-xs font-semibold rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-all">+ Add Menu Item</button>
          </div>

          <div v-if="form.has_dining" class="space-y-3 pt-2">
            <div v-for="(item, index) in form.menu_items" :key="index" class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200/80 dark:border-slate-800/80 grid grid-cols-1 sm:grid-cols-4 gap-3 items-center">
              <select v-model="item.category" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-100">
                <option value="starter">Starter</option>
                <option value="main">Main Course</option>
                <option value="dessert">Dessert</option>
              </select>
              <input v-model="item.name" type="text" placeholder="Dish Name" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-100" />
              <input v-model="item.description" type="text" placeholder="Description" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-100" />
              <button type="button" @click="removeMenuItem(index)" class="text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 text-xs font-semibold">Remove</button>
            </div>
          </div>
        </div>

        <button type="submit" :disabled="form.processing" class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs transition-all shadow-md shadow-blue-600/20">
          {{ form.processing ? 'Saving Event...' : 'Save Event & Publish Tiers' }}
        </button>

      </form>

    </div>

  </AdminLayout>
</template>
