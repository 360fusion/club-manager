<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CommunicationsTabs from '@/Components/CommunicationsTabs.vue';

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

const sendBroadcast = (item) => {
  if (confirm(`Email "${item.subject}" to ${item.recipient_count} ${item.recipient_count === 1 ? 'person' : 'people'} now? It cannot be changed or sent again afterwards.`)) {
    router.post(route('admin.newsletters.send', { clubSlug: props.club.slug, id: item.id }));
  }
};

const duplicateNewsletter = (id) => router.post(route('admin.newsletters.duplicate', { clubSlug: props.club.slug, id }));
const retryFailed = (id) => router.post(route('admin.newsletters.retry', { clubSlug: props.club.slug, id }), {}, { preserveScroll: true });

const deleteNewsletter = (id) => {
  if (confirm('Are you sure you want to delete this newsletter?')) {
    router.delete(route('admin.newsletters.destroy', { clubSlug: props.club.slug, id }));
  }
};
</script>

<template>
  <AdminLayout title="Newsletters & Email Broadcasts" :club="club" active-tab="newsletters">
    <Head title="Newsletters & Email Broadcasts" />

    <div class="max-w-6xl mx-auto space-y-6">
      <CommunicationsTabs :club="club" active-tab="newsletters" />
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Club Newsletters & Targeted Broadcasts</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Compose and send email broadcasts targeted by channel, role, or visiting subscribers.</p>
        </div>
        <div class="flex items-center gap-3">
          <Link :href="route('admin.newsletters.subscribers', { clubSlug: club.slug })" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 transition-all text-center flex items-center gap-1">
            👥 Manage Subscribers
          </Link>
          <Link :href="route('admin.newsletters.types', { clubSlug: club.slug })" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 transition-all text-center flex items-center gap-1">
            ⚙️ Channels & Settings
          </Link>
          <Link :href="route('admin.newsletters.create', { clubSlug: club.slug })" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md shadow-blue-600/20 transition-all text-center">
            + Compose Broadcast
          </Link>
        </div>
      </div>

      <!-- Stats Cards Row -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-1">
          <div class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Broadcasts Sent</div>
          <div class="text-2xl font-black text-slate-900 dark:text-white">
            {{ newsletters.filter(n => n.status === 'sent').length }}
          </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-1">
          <div class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Drafts Pending</div>
          <div class="text-2xl font-black text-amber-600 dark:text-amber-400">
            {{ newsletters.filter(n => n.status === 'draft').length }}
          </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-1">
          <div class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active Channels</div>
          <div class="text-2xl font-black text-blue-600 dark:text-blue-400">
            {{ (types || []).length }} Channels
          </div>
        </div>
      </div>

      <!-- Filter Toolbar Card -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <!-- Prominent Search Bar (Left) -->
          <div class="relative w-full lg:w-96">
            <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-700 dark:text-slate-200 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search broadcasts by subject line or content..."
              class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white placeholder-slate-600 font-bold focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition-all shadow-sm"
            />
          </div>

          <!-- Dropdown Filters (Right) -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs w-full lg:w-auto">
            <!-- Status Dropdown -->
            <select
              v-model="selectedStatus"
              class="px-3 py-2.5 border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 cursor-pointer shadow-sm"
            >
              <option value="all">All Statuses ({{ newsletters.length }})</option>
              <option value="sent">🚀 Sent ({{ newsletters.filter(n => n.status === 'sent').length }})</option>
              <option value="draft">✏️ Drafts ({{ newsletters.filter(n => n.status === 'draft').length }})</option>
            </select>

            <!-- Channel Filter -->
            <select
              v-model="selectedTypeId"
              class="px-3 py-2.5 border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 cursor-pointer shadow-sm"
            >
              <option value="all">All Channels</option>
              <option v-for="t in types" :key="t.id" :value="t.id">{{ t.icon }} {{ t.name }}</option>
            </select>

            <!-- Target Role Filter -->
            <select
              v-model="selectedRole"
              class="px-3 py-2.5 border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 cursor-pointer shadow-sm"
            >
              <option value="all">All Target Roles</option>
              <option v-for="r in availableRoles" :key="r.id" :value="r.id">{{ r.label }}</option>
            </select>

            <!-- Date Range Filter -->
            <select
              v-model="selectedDateRange"
              class="px-3 py-2.5 border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 cursor-pointer shadow-sm"
            >
              <option value="all">All Time</option>
              <option value="30_days">Last 30 Days</option>
              <option value="this_year">This Year</option>
            </select>
          </div>
        </div>

        <!-- Newsletters List Grid -->
        <div v-if="filteredNewsletters.length" class="grid grid-cols-1 gap-4 pt-2">
          <div v-for="item in filteredNewsletters" :key="item.id" class="bg-slate-50/50 dark:bg-slate-800/50 rounded-2xl p-6 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-all flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-3">
              <div class="flex items-center gap-2 flex-wrap">
                <span :class="['px-2.5 py-0.5 rounded text-xs font-bold border uppercase tracking-wider', item.status === 'sent' ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60' : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60']">
                  {{ item.status === 'sent' ? `SENT (${item.sent_at})` : 'DRAFT' }}
                </span>

                <span
                  :style="{ backgroundColor: item.type_color + '15', color: item.type_color, borderColor: item.type_color + '30' }"
                  class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border tracking-wider flex items-center gap-1"
                >
                  {{ item.type_icon }} {{ item.type_name }}
                </span>

                <!-- Target Roles Badges -->
                <span v-for="role in item.target_roles" :key="role" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800">
                  {{ role }}
                </span>

                <!-- Audience Badge -->
                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 flex items-center gap-1">
                  <span>Audience: <strong class="text-slate-900 dark:text-white">{{ item.recipient_count }} {{ item.recipient_count === 1 ? 'person' : 'people' }}</strong></span>
                  <span v-if="item.external_recipient_count > 0" class="text-blue-600 dark:text-blue-400 font-bold">({{ item.external_recipient_count }} visiting)</span>
                </span>

                <!-- Delivery progress once sent -->
                <span v-if="item.status === 'sent'" class="flex items-center gap-1.5 rounded border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-bold dark:border-slate-800 dark:bg-slate-900">
                  <span class="text-emerald-600">✓ {{ item.deliveries.sent }} delivered</span>
                  <span v-if="item.deliveries.queued" class="text-slate-500">· {{ item.deliveries.queued }} waiting</span>
                  <span v-if="item.deliveries.failed" class="text-rose-600">· {{ item.deliveries.failed }} failed</span>
                </span>

                <!-- Attachments Count Badge -->
                <span v-if="item.attachments && item.attachments.length" class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 flex items-center gap-1">
                  📎 {{ item.attachments.length }} {{ item.attachments.length === 1 ? 'Attachment' : 'Attachments' }}
                </span>
              </div>

              <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ item.subject }}</h3>

              <!-- Attached Files Pills -->
              <div v-if="item.attachments && item.attachments.length" class="flex flex-wrap gap-2 pt-1">
                <a
                  v-for="(att, aIdx) in item.attachments"
                  :key="aIdx"
                  :href="att.url"
                  target="_blank"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 text-[11px] font-bold text-slate-700 dark:text-slate-200 hover:text-blue-600 dark:hover:text-blue-400 shadow-sm transition-all"
                >
                  <span>📎</span>
                  <span>{{ att.name }}</span>
                  <span class="text-[9px] text-slate-400 font-normal">({{ att.size }})</span>
                </a>
              </div>
            </div>

            <div class="flex items-center gap-3 self-start md:self-auto">
              <button v-if="item.status === 'draft'" @click="sendBroadcast(item)" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 transition-all cursor-pointer">
                🚀 Send Broadcast
              </button>
              <button v-if="item.status === 'sent' && item.deliveries.failed" @click="retryFailed(item.id)" class="px-4 py-2 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 text-amber-800 dark:text-amber-200 text-xs font-bold rounded-xl border border-amber-200 dark:border-amber-800/60 transition-all cursor-pointer">
                ↻ Retry {{ item.deliveries.failed }} failed
              </button>
              <button v-if="item.status === 'sent'" @click="duplicateNewsletter(item.id)" class="px-4 py-2 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-800 transition-all cursor-pointer">
                ⧉ Duplicate
              </button>
              <Link v-if="item.status === 'draft'" :href="route('admin.newsletters.edit', { clubSlug: club.slug, id: item.id })" class="px-4 py-2 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-800 transition-all">
                ✏️ Edit Draft
              </Link>
              <button @click="deleteNewsletter(item.id)" class="px-3 py-2 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-600 dark:text-rose-400 text-xs font-semibold rounded-xl border border-rose-200 dark:border-rose-800/60 transition-all cursor-pointer">
                🗑️ Delete
              </button>
            </div>
          </div>
        </div>

        <div v-else class="p-12 text-center text-slate-500 dark:text-slate-400 text-sm font-medium">
          No broadcasts found matching your filter criteria.
        </div>
      </div>

    </div>

  </AdminLayout>
</template>
