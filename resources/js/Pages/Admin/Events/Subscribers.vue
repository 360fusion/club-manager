<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  event: Object,
  subscribers: Array,
});

const activeTab = ref('all'); // all, paid, unpaid, dining
const searchQuery = ref('');

const filteredSubscribers = computed(() => {
  return (props.subscribers || []).filter(sub => {
    const query = searchQuery.value.toLowerCase().trim();
    const matchesQuery = !query || 
      (sub.name && sub.name.toLowerCase().includes(query)) ||
      (sub.email && sub.email.toLowerCase().includes(query)) ||
      (sub.home_club_lodge && sub.home_club_lodge.toLowerCase().includes(query)) ||
      (sub.rank && sub.rank.toLowerCase().includes(query));

    if (!matchesQuery) return false;

    if (activeTab.value === 'paid') return sub.payment_status === 'paid';
    if (activeTab.value === 'unpaid') return sub.payment_status === 'unpaid' || sub.payment_status === 'pending';
    if (activeTab.value === 'dining') return sub.has_food_choice;

    return true;
  });
});

const stats = computed(() => {
  const total = (props.subscribers || []).length;
  const paidCount = (props.subscribers || []).filter(s => s.payment_status === 'paid').length;
  const unpaidCount = (props.subscribers || []).filter(s => s.payment_status === 'unpaid' || s.payment_status === 'pending').length;
  const diningCount = (props.subscribers || []).filter(s => s.has_food_choice).length;

  return { total, paidCount, unpaidCount, diningCount };
});

const setPaymentStatus = (subscriberId, status) => {
  router.post(
    route('admin.events.subscribers.payment_status', {
      clubSlug: props.club.slug,
      id: props.event.id,
      userId: subscriberId,
    }),
    { payment_status: status },
    { preserveScroll: true }
  );
};
</script>

<template>
  <AdminLayout title="Event Subscriptions & RSVPs" :club="club" active-tab="events">
    <Head :title="`Event Subscriptions - ${event.title}`" />

    <div class="space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <Link
              :href="route('admin.events.index', club.slug)"
              class="inline-flex items-center text-xs font-bold text-slate-500 hover:text-indigo-600 transition-colors"
            >
              ← Back to Events
            </Link>
          </div>
          <h2 class="text-xl font-bold text-slate-900">
            👥 {{ event.title }} — Subscriptions & RSVPs
          </h2>
          <div class="flex items-center gap-3 text-xs mt-1.5 flex-wrap text-slate-500 font-medium">
            <span>📅 {{ event.starts_at || 'Date TBD' }}</span>
            <span v-if="event.location">📍 {{ event.location }}</span>
            <span v-if="event.has_dining" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-sky-50 text-sky-700 border border-sky-200">
              🍽️ 3-Course Dining (£{{ event.dining_price }})
            </span>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <Link
            :href="route('admin.events.edit', { clubSlug: club.slug, id: event.id })"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition-all flex items-center gap-1"
          >
            ✏️ Edit Event Details
          </Link>
        </div>
      </div>

      <!-- Live KPI Cards Grid -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Subscribed</div>
          <div class="text-2xl font-black text-indigo-600">{{ stats.total }}</div>
          <div class="text-[11px] text-slate-500 font-medium">Registered Attendees</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Dining Attendees</div>
          <div class="text-2xl font-black text-emerald-600">{{ stats.diningCount }}</div>
          <div class="text-[11px] text-slate-500 font-medium">3-Course Meal Selections</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Payments Received</div>
          <div class="text-2xl font-black text-emerald-600">{{ stats.paidCount }}</div>
          <div class="text-[11px] text-slate-500 font-medium">Paid Subscriptions</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Awaiting Payment</div>
          <div class="text-2xl font-black text-amber-600">{{ stats.unpaidCount }}</div>
          <div class="text-[11px] text-slate-500 font-medium">Unpaid Subscriptions</div>
        </div>
      </div>

      <!-- Filter Tabs & Subscribers Table Card -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
          <!-- Filter Tabs -->
          <div class="flex items-center gap-2 overflow-x-auto">
            <button
              @click="activeTab = 'all'"
              :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer', activeTab === 'all' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100']"
            >
              All Subscribers ({{ subscribers.length }})
            </button>
            <button
              @click="activeTab = 'paid'"
              :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer', activeTab === 'paid' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100']"
            >
              Paid ({{ stats.paidCount }})
            </button>
            <button
              @click="activeTab = 'unpaid'"
              :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer', activeTab === 'unpaid' ? 'bg-amber-600 text-white' : 'text-slate-600 hover:bg-slate-100']"
            >
              Unpaid ({{ stats.unpaidCount }})
            </button>
            <button
              @click="activeTab = 'dining'"
              :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer', activeTab === 'dining' ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-100']"
            >
              Dining ({{ stats.diningCount }})
            </button>
          </div>

          <!-- Search Input -->
          <div class="relative w-full sm:w-72">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search subscriber, email, lodge..."
              class="w-full pl-3 pr-4 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
            />
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-xs text-left text-slate-700">
            <thead class="bg-indigo-50/80 text-indigo-900 uppercase font-bold text-[10px] tracking-wider">
              <tr>
                <th class="p-3">Subscriber Name & Rank</th>
                <th class="p-3">Home Lodge / Club</th>
                <th class="p-3">Email Address</th>
                <th class="p-3">Ticket Tier</th>
                <th class="p-3">Food Choices & Menu</th>
                <th class="p-3">Dietary Notes</th>
                <th class="p-3">Payment Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="sub in filteredSubscribers"
                :key="sub.user_id"
                class="hover:bg-indigo-50/30 transition-all"
              >
                <!-- Subscriber Name & Rank -->
                <td class="p-3">
                  <div class="font-bold text-slate-900">
                    <span v-if="sub.rank" class="text-slate-500 font-normal mr-1">{{ sub.rank }}</span>
                    {{ sub.name }}
                  </div>
                  <div v-if="sub.member_number" class="text-[10px] text-slate-400 font-mono mt-0.5">
                    Mem #: {{ sub.member_number }}
                  </div>
                </td>

                <!-- Home Lodge / Club -->
                <td class="p-3 font-semibold text-slate-800">
                  <span v-if="sub.home_club_lodge">🏛️ {{ sub.home_club_lodge }}</span>
                  <span v-else class="text-slate-400 italic">Not specified</span>
                </td>

                <!-- Email Address -->
                <td class="p-3 text-slate-600 font-mono text-[11px]">
                  {{ sub.email }}
                </td>

                <!-- Ticket Tier -->
                <td class="p-3">
                  <span v-if="sub.ticket_tier" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200">
                    🎟️ {{ sub.ticket_tier.name }} (£{{ sub.ticket_tier.price }})
                  </span>
                  <span v-else class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600 border border-slate-200">
                    Standard Admission
                  </span>
                </td>

                <!-- Food Choices -->
                <td class="p-3">
                  <div v-if="sub.has_food_choice" class="space-y-0.5 text-[11px]">
                    <div v-if="sub.menu_selections.starter" class="text-slate-700">
                      <span class="font-semibold text-slate-400">Starter:</span> {{ sub.menu_selections.starter }}
                    </div>
                    <div v-if="sub.menu_selections.main" class="text-slate-700">
                      <span class="font-semibold text-slate-400">Main:</span> {{ sub.menu_selections.main }}
                    </div>
                    <div v-if="sub.menu_selections.dessert" class="text-slate-700">
                      <span class="font-semibold text-slate-400">Dessert:</span> {{ sub.menu_selections.dessert }}
                    </div>
                  </div>
                  <span v-else class="text-slate-400 italic text-[11px]">No Dining</span>
                </td>

                <!-- Dietary Notes -->
                <td class="p-3">
                  <span v-if="sub.dietary_requirements" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200">
                    ⚠️ {{ sub.dietary_requirements }}
                  </span>
                  <span v-else class="text-slate-400 italic text-[11px]">Standard</span>
                </td>

                <!-- Payment Status Action -->
                <td class="p-3">
                  <button 
                    @click="setPaymentStatus(sub.user_id, sub.payment_status === 'paid' ? 'unpaid' : 'paid')"
                    :class="['px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider border cursor-pointer transition-all flex items-center gap-1 w-fit', 
                      sub.payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100' :
                      sub.payment_status === 'waived' ? 'bg-purple-50 text-purple-700 border-purple-300 hover:bg-purple-100' :
                      sub.payment_status === 'refunded' ? 'bg-rose-50 text-rose-700 border-rose-300 hover:bg-rose-100' :
                      'bg-amber-50 text-amber-800 border-amber-300 hover:bg-amber-100']"
                    :title="'Click to toggle paid/unpaid status'"
                  >
                    <span v-if="sub.payment_status === 'paid'">✅ Paid</span>
                    <span v-else-if="sub.payment_status === 'waived'">🎁 Waived</span>
                    <span v-else-if="sub.payment_status === 'refunded'">↩️ Refunded</span>
                    <span v-else>💳 Unpaid</span>
                  </button>
                </td>
              </tr>

              <tr v-if="filteredSubscribers.length === 0">
                <td colspan="7" class="p-8 text-center text-slate-500 font-medium">
                  No subscribers found matching your filter criteria.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
