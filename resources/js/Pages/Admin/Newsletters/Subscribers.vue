<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  types: Array,
  subscriptions: Array,
});

const activeTab = ref('all'); // all, active, pending_approval, external
const searchQuery = ref('');
const selectedTypeId = ref('all');

const filteredSubscriptions = computed(() => {
  return (props.subscriptions || []).filter(sub => {
    const query = searchQuery.value.toLowerCase().trim();
    const matchesQuery = !query ||
      (sub.name && sub.name.toLowerCase().includes(query)) ||
      (sub.email && sub.email.toLowerCase().includes(query)) ||
      (sub.home_club_name && sub.home_club_name.toLowerCase().includes(query)) ||
      (sub.rank && sub.rank.toLowerCase().includes(query));

    if (!matchesQuery) return false;

    if (selectedTypeId.value !== 'all' && Number(sub.newsletter_type_id) !== Number(selectedTypeId.value)) {
      return false;
    }

    if (activeTab.value === 'active') return sub.status === 'active';
    if (activeTab.value === 'pending_approval') return sub.status === 'pending_approval';
    if (activeTab.value === 'external') return !sub.is_internal_member && sub.status === 'active';

    return true;
  });
});

const pendingCount = computed(() => {
  return (props.subscriptions || []).filter(s => s.status === 'pending_approval').length;
});

const updateStatus = (subId, status) => {
  router.post(
    route('admin.newsletters.subscribers.status', { clubSlug: props.club.slug, id: subId }),
    { status },
    { preserveScroll: true }
  );
};
</script>

<template>
  <AdminLayout title="Newsletter Subscribers Roster" :club="club" active-tab="newsletters">
    <Head title="Newsletter Subscribers Roster" />

    <div class="space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <Link
              :href="route('admin.newsletters.index', club.slug)"
              class="inline-flex items-center text-xs font-bold text-slate-500 hover:text-indigo-600 transition-colors"
            >
              ← Back to Broadcasts
            </Link>
          </div>
          <h2 class="text-xl font-bold text-slate-900">
            👥 Subscriber Roster & Approvals
          </h2>
          <p class="text-xs text-slate-500 mt-1">
            Manage subscriber lists across all channels, including external visiting brethren and pending subscription requests.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <Link
            :href="route('admin.newsletters.types', club.slug)"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition-all flex items-center gap-1"
          >
            ⚙️ Channel Settings
          </Link>
        </div>
      </div>

      <!-- Live KPI Cards Grid -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Subscribers</div>
          <div class="text-2xl font-black text-indigo-600">{{ subscriptions.length }}</div>
          <div class="text-[11px] text-slate-500 font-medium">All active & visiting contacts</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pending Approvals</div>
          <div class="text-2xl font-black text-amber-600">{{ pendingCount }}</div>
          <div class="text-[11px] text-slate-500 font-medium">Visiting brethren awaiting review</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active Visiting Brethren</div>
          <div class="text-2xl font-black text-emerald-600">
            {{ subscriptions.filter(s => !s.is_internal_member && s.status === 'active').length }}
          </div>
          <div class="text-[11px] text-slate-500 font-medium">Subscribed from other lodges</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Internal Members</div>
          <div class="text-2xl font-black text-sky-600">
            {{ subscriptions.filter(s => s.is_internal_member && s.status === 'active').length }}
          </div>
          <div class="text-[11px] text-slate-500 font-medium">Active club roster members</div>
        </div>
      </div>

      <!-- Filter Tabs & Subscribers Table -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
          <!-- Filter Tabs -->
          <div class="flex items-center gap-2 overflow-x-auto">
            <button
              @click="activeTab = 'all'"
              :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer', activeTab === 'all' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100']"
            >
              All Contact Roster ({{ subscriptions.length }})
            </button>
            <button
              @click="activeTab = 'pending_approval'"
              :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer relative', activeTab === 'pending_approval' ? 'bg-amber-600 text-white' : 'text-slate-600 hover:bg-slate-100']"
            >
              Pending Approval ({{ pendingCount }})
              <span v-if="pendingCount > 0" class="w-2 h-2 rounded-full bg-amber-400 absolute -top-0.5 -right-0.5 animate-ping"></span>
            </button>
            <button
              @click="activeTab = 'external'"
              :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer', activeTab === 'external' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100']"
            >
              Visiting Brethren
            </button>
          </div>

          <!-- Channel Filter & Search -->
          <div class="flex items-center gap-2">
            <select
              v-model="selectedTypeId"
              class="text-xs font-medium border border-slate-200 rounded-xl px-3 py-1.5 bg-slate-50 text-slate-800"
            >
              <option value="all">All Channels</option>
              <option v-for="t in types" :key="t.id" :value="t.id">{{ t.icon }} {{ t.name }}</option>
            </select>

            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search subscriber, email, lodge..."
              class="w-48 sm:w-64 pl-3 pr-4 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
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
                <th class="p-3">Channel / Type</th>
                <th class="p-3">Type Source</th>
                <th class="p-3">Status</th>
                <th class="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="sub in filteredSubscriptions"
                :key="sub.id"
                class="hover:bg-indigo-50/30 transition-all"
              >
                <!-- Subscriber Name & Rank -->
                <td class="p-3">
                  <div class="font-bold text-slate-900">
                    <span v-if="sub.rank" class="text-slate-500 font-normal mr-1">{{ sub.rank }}</span>
                    {{ sub.name }}
                  </div>
                  <div class="text-[10px] text-slate-400 mt-0.5">Subscribed: {{ sub.subscribed_at }}</div>
                </td>

                <!-- Home Lodge -->
                <td class="p-3 font-semibold text-slate-800">
                  <span v-if="sub.home_club_name">🏛️ {{ sub.home_club_name }} {{ sub.home_club_number ? '#' + sub.home_club_number : '' }}</span>
                  <span v-else class="text-slate-400 italic">Internal Member</span>
                </td>

                <!-- Email -->
                <td class="p-3 font-mono text-[11px] text-slate-600">
                  {{ sub.email }}
                </td>

                <!-- Channel -->
                <td class="p-3">
                  <span
                    :style="{ backgroundColor: sub.type_color + '15', color: sub.type_color, borderColor: sub.type_color + '30' }"
                    class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider inline-flex items-center gap-1"
                  >
                    {{ sub.type_icon }} {{ sub.type_name }}
                  </span>
                </td>

                <!-- Source -->
                <td class="p-3">
                  <span v-if="sub.is_internal_member" class="px-2 py-0.5 rounded text-[10px] font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                    🏠 Member Roster
                  </span>
                  <span v-else class="px-2 py-0.5 rounded text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                    🌐 Visiting Brother
                  </span>
                </td>

                <!-- Status -->
                <td class="p-3">
                  <span v-if="sub.status === 'active'" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                    🟢 Active
                  </span>
                  <span v-else-if="sub.status === 'pending_approval'" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200">
                    ⏳ Pending Review
                  </span>
                  <span v-else class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600 border border-slate-200">
                    ⚪ Unsubscribed
                  </span>
                </td>

                <!-- Actions -->
                <td class="p-3 text-right">
                  <div v-if="sub.status === 'pending_approval'" class="flex items-center justify-end gap-1">
                    <button
                      @click="updateStatus(sub.id, 'active')"
                      class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg text-[10px] transition-all"
                    >
                      ✓ Approve
                    </button>
                    <button
                      @click="updateStatus(sub.id, 'rejected')"
                      class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-lg text-[10px] border border-rose-200 transition-all"
                    >
                      ✕ Reject
                    </button>
                  </div>
                  <div v-else class="flex items-center justify-end gap-1">
                    <button
                      v-if="sub.status === 'active'"
                      @click="updateStatus(sub.id, 'unsubscribed')"
                      class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded text-[10px] transition-all"
                    >
                      Unsubscribe
                    </button>
                    <button
                      v-else
                      @click="updateStatus(sub.id, 'active')"
                      class="px-2 py-0.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-semibold rounded text-[10px] border border-emerald-200 transition-all"
                    >
                      Re-activate
                    </button>
                  </div>
                </td>
              </tr>

              <tr v-if="filteredSubscriptions.length === 0">
                <td colspan="7" class="p-8 text-center text-slate-500 font-medium">
                  No subscribers found matching your criteria.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
