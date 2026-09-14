<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  newsletters: Array,
  types: Array,
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

      <!-- Newsletters List -->
      <div v-if="newsletters.length" class="grid grid-cols-1 gap-4">
        <div v-for="item in newsletters" :key="item.id" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 hover:border-slate-300 transition-all flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="space-y-3">
            <div class="flex items-center gap-2">
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
              <span v-for="role in item.target_roles" :key="role" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-600 border border-slate-200">
                {{ role }}
              </span>
            </div>

            <h3 class="text-lg font-bold text-slate-900">{{ item.subject }}</h3>
            <div class="text-xs text-slate-500 line-clamp-2 prose max-w-none" v-html="item.content"></div>
            <div class="text-[11px] text-slate-400 font-medium flex items-center gap-2">
              <span>Audience: <strong>{{ item.recipient_count }} contacts</strong></span>
              <span v-if="item.external_recipient_count > 0" class="text-indigo-600">({{ item.external_recipient_count }} visiting brethren)</span>
            </div>
          </div>

          <div class="flex items-center gap-3 self-start md:self-auto">
            <button v-if="item.status === 'draft'" @click="sendBroadcast(item.id)" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 transition-all">
              🚀 Send Broadcast
            </button>
            <Link :href="route('admin.newsletters.edit', { clubSlug: club.slug, id: item.id })" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition-all">
              ✏️ Edit Draft
            </Link>
            <button @click="deleteNewsletter(item.id)" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold rounded-xl border border-rose-200 transition-all">
              🗑️ Delete
            </button>
          </div>
        </div>
      </div>

      <div v-else class="bg-white rounded-2xl p-12 text-center shadow-sm border border-slate-200/80 space-y-4">
        <p class="text-slate-500 text-sm">No newsletters created yet.</p>
        <Link :href="route('admin.newsletters.create', { clubSlug: club.slug })" class="inline-block px-5 py-2.5 bg-indigo-600 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20">
          Compose First Broadcast
        </Link>
      </div>

    </div>

  </AdminLayout>
</template>
