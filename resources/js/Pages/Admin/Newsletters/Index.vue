<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  newsletters: Array,
  types: Array,
});

const searchQuery = ref('');
const selectedTypeId = ref('all');
const selectedStatus = ref('all');
const selectedRole = ref('all');
const selectedDateRange = ref('all');

const availableRoles = [
  { id: 'member', label: 'Members / Brethren' },
  { id: 'coach', label: 'Coaches & Officers' },
  { id: 'admin', label: 'Admins & Board' },
  { id: 'treasurer', label: 'Treasurers' },
];

const filteredNewsletters = computed(() => {
  return (props.newsletters || []).filter(item => {
    // 1. Search Query Filter
    const query = searchQuery.value.toLowerCase().trim();
    if (query) {
      const matchesSubject = item.subject && item.subject.toLowerCase().includes(query);
      const matchesContent = item.content && item.content.toLowerCase().includes(query);
      if (!matchesSubject && !matchesContent) return false;
    }

    // 2. Channel Type Filter
    if (selectedTypeId.value !== 'all' && Number(item.newsletter_type_id) !== Number(selectedTypeId.value)) {
      return false;
    }

    // 3. Status Filter (sent / draft)
    if (selectedStatus.value !== 'all' && item.status !== selectedStatus.value) {
      return false;
    }

    // 4. Target Role Filter
    if (selectedRole.value !== 'all') {
      const roles = item.target_roles || [];
      if (!roles.includes(selectedRole.value)) return false;
    }

    // 5. Date Range Filter
    if (selectedDateRange.value !== 'all' && item.sent_at) {
      const sentDate = new Date(item.sent_at);
      const now = new Date();
      if (selectedDateRange.value === '30_days') {
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(now.getDate() - 30);
        if (sentDate < thirtyDaysAgo) return false;
      } else if (selectedDateRange.value === 'this_year') {
        if (sentDate.getFullYear() !== now.getFullYear()) return false;
      }
    }

    return true;
  });
});

const sendBroadcast = (id) => {
  if (confirm('Send this newsletter broadcast to all targeted member roles and external subscribers now?')) {
    router.post(route('admin.newsletters.send', { clubSlug: props.club.slug, id }));
  }
};

const deleteNewsletter = (id) => {
  if (confirm('Are you sure you want to delete this newsletter?')) {
    router.delete(route('admin.newsletters.destroy', { clubSlug: props.club.slug, id }));
  }
};
</script>

<template>
  <AdminLayout title="Newsletters & Email Broadcasts" :club="club" active-tab="newsletters">
    
    <div class="space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900">Club Newsletters & Targeted Broadcasts</h2>
          <p class="text-xs text-slate-500 mt-1">Compose and send email broadcasts targeted by channel, role, or visiting subscribers.</p>
        </div>
        <div class="flex items-center gap-3">
          <Link :href="route('admin.newsletters.subscribers', { clubSlug: club.slug })" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition-all text-center flex items-center gap-1">
            👥 Manage Subscribers
          </Link>
          <Link :href="route('admin.newsletters.types', { clubSlug: club.slug })" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition-all text-center flex items-center gap-1">
            ⚙️ Channels & Settings
          </Link>
          <Link :href="route('admin.newsletters.create', { clubSlug: club.slug })" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all text-center">
            + Compose Broadcast
          </Link>
        </div>
      </div>

      <!-- Stats Cards Row -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80 space-y-1">
          <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Total Broadcasts Sent</div>
          <div class="text-2xl font-black text-slate-900">
            {{ newsletters.filter(n => n.status === 'sent').length }}
          </div>
        </div>

        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80 space-y-1">
          <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Drafts Pending</div>
          <div class="text-2xl font-black text-amber-600">
            {{ newsletters.filter(n => n.status === 'draft').length }}
          </div>
        </div>

        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-200/80 space-y-1">
          <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Active Channels</div>
          <div class="text-2xl font-black text-indigo-600">
            {{ (types || []).length }} Channels
          </div>
        </div>
      </div>

      <!-- Filter Toolbar Card -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <!-- Status Pill Tabs -->
          <div class="flex items-center gap-2 overflow-x-auto">
            <button
              @click="selectedStatus = 'all'"
              :class="['px-3.5 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer whitespace-nowrap inline-flex items-center gap-1.5', selectedStatus === 'all' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100']"
            >
              <span>All Broadcasts</span>
              <span :class="['px-1.5 py-0.5 rounded-md text-[10px] font-extrabold', selectedStatus === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200/80 text-slate-700']">{{ newsletters.length }}</span>
            </button>
            <button
              @click="selectedStatus = 'sent'"
              :class="['px-3.5 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer whitespace-nowrap inline-flex items-center gap-1.5', selectedStatus === 'sent' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100']"
            >
              <span>🚀 Sent</span>
              <span :class="['px-1.5 py-0.5 rounded-md text-[10px] font-extrabold', selectedStatus === 'sent' ? 'bg-white/20 text-white' : 'bg-slate-200/80 text-slate-700']">{{ newsletters.filter(n => n.status === 'sent').length }}</span>
            </button>
            <button
              @click="selectedStatus = 'draft'"
              :class="['px-3.5 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer whitespace-nowrap inline-flex items-center gap-1.5', selectedStatus === 'draft' ? 'bg-amber-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100']"
            >
              <span>✏️ Drafts</span>
              <span :class="['px-1.5 py-0.5 rounded-md text-[10px] font-extrabold', selectedStatus === 'draft' ? 'bg-white/20 text-white' : 'bg-slate-200/80 text-slate-700']">{{ newsletters.filter(n => n.status === 'draft').length }}</span>
            </button>
          </div>

          <!-- Dropdown Filters & Search Bar -->
          <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 text-xs">
            <!-- Channel Filter -->
            <select
              v-model="selectedTypeId"
              class="px-3 py-1.5 border border-slate-200 rounded-xl bg-slate-50 font-medium text-slate-800"
            >
              <option value="all">All Channels</option>
              <option v-for="t in types" :key="t.id" :value="t.id">{{ t.icon }} {{ t.name }}</option>
            </select>

            <!-- Target Role Filter -->
            <select
              v-model="selectedRole"
              class="px-3 py-1.5 border border-slate-200 rounded-xl bg-slate-50 font-medium text-slate-800"
            >
              <option value="all">All Target Roles</option>
              <option v-for="r in availableRoles" :key="r.id" :value="r.id">{{ r.label }}</option>
            </select>

            <!-- Date Range Filter -->
            <select
              v-model="selectedDateRange"
              class="px-3 py-1.5 border border-slate-200 rounded-xl bg-slate-50 font-medium text-slate-800"
            >
              <option value="all">All Time</option>
              <option value="30_days">Last 30 Days</option>
              <option value="this_year">This Year</option>
            </select>

            <!-- Search Keyword -->
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search subject or text..."
              class="px-3 py-1.5 border border-slate-200 rounded-xl bg-slate-50 text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
            />
          </div>
        </div>

        <!-- Newsletters List Grid -->
        <div v-if="filteredNewsletters.length" class="grid grid-cols-1 gap-4 pt-2">
          <div v-for="item in filteredNewsletters" :key="item.id" class="bg-slate-50/50 rounded-2xl p-6 border border-slate-200 hover:border-slate-300 transition-all flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-3">
              <div class="flex items-center gap-2 flex-wrap">
                <span :class="['px-2.5 py-0.5 rounded text-xs font-bold border uppercase tracking-wider', item.status === 'sent' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200']">
                  {{ item.status === 'sent' ? `SENT (${item.sent_at})` : 'DRAFT' }}
                </span>

                <span
                  :style="{ backgroundColor: item.type_color + '15', color: item.type_color, borderColor: item.type_color + '30' }"
                  class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border tracking-wider flex items-center gap-1"
                >
                  {{ item.type_icon }} {{ item.type_name }}
                </span>

                <!-- Target Roles Badges -->
                <span v-for="role in item.target_roles" :key="role" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-white text-slate-600 border border-slate-200">
                  {{ role }}
                </span>

                <!-- Audience Badge -->
                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-white text-slate-600 border border-slate-200 flex items-center gap-1">
                  <span>Audience: <strong class="text-slate-900">{{ item.recipient_count }} contacts</strong></span>
                  <span v-if="item.external_recipient_count > 0" class="text-indigo-600 font-bold">({{ item.external_recipient_count }} visiting)</span>
                </span>
              </div>

              <h3 class="text-lg font-bold text-slate-900">{{ item.subject }}</h3>
            </div>

            <div class="flex items-center gap-3 self-start md:self-auto">
              <button v-if="item.status === 'draft'" @click="sendBroadcast(item.id)" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 transition-all cursor-pointer">
                🚀 Send Broadcast
              </button>
              <Link :href="route('admin.newsletters.edit', { clubSlug: club.slug, id: item.id })" class="px-4 py-2 bg-white hover:bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition-all">
                ✏️ Edit Draft
              </Link>
              <button @click="deleteNewsletter(item.id)" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold rounded-xl border border-rose-200 transition-all cursor-pointer">
                🗑️ Delete
              </button>
            </div>
          </div>
        </div>

        <div v-else class="p-12 text-center text-slate-500 text-sm font-medium">
          No broadcasts found matching your filter criteria.
        </div>
      </div>

    </div>

  </AdminLayout>
</template>
