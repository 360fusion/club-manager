<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MemberLayout from '@/Layouts/MemberLayout.vue';

const props = defineProps({
  clubMatrix: Array,
  broadcastFeed: Array,
  user: Object,
});

const activeTab = ref('matrix'); // matrix or feed

const toggleChannel = (clubSlug, typeId) => {
  router.post(
    route('portal.subscriptions.toggle', { clubSlug, typeId }),
    {},
    { preserveScroll: true }
  );
};
</script>

<template>
  <MemberLayout title="My Subscriptions & News Hub">
    <Head title="My Subscriptions & News Hub" />

    <div class="space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900">
            📬 Multi-Club Subscriptions & Broadcast Feed
          </h2>
          <p class="text-xs text-slate-500 mt-1">
            Manage your newsletter & summons subscriptions across all your enrolled clubs and visiting brethren memberships.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <Link
            :href="route('directory.index')"
            class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-200 transition-all flex items-center gap-1"
          >
            🏛️ Find Other Lodges in Directory
          </Link>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex items-center gap-2 border-b border-slate-200 pb-3">
        <button
          @click="activeTab = 'matrix'"
          :class="['px-4 py-2 text-xs font-bold rounded-xl transition-all cursor-pointer', activeTab === 'matrix' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100']"
        >
          ⚙️ Subscription Preferences Matrix
        </button>
        <button
          @click="activeTab = 'feed'"
          :class="['px-4 py-2 text-xs font-bold rounded-xl transition-all cursor-pointer', activeTab === 'feed' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100']"
        >
          📰 Unified Broadcast Inbox ({{ broadcastFeed.length }})
        </button>
      </div>

      <!-- Tab 1: Preferences Matrix -->
      <div v-if="activeTab === 'matrix'" class="space-y-6">
        <div
          v-for="club in clubMatrix"
          :key="club.id"
          class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4"
        >
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-base font-bold text-slate-900">{{ club.name }}</h3>
                <span v-if="club.lodge_number" class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                  No. {{ club.lodge_number }}
                </span>
              </div>
              <span v-if="club.is_member" class="text-[11px] font-semibold text-emerald-600">🏠 Enrolled Club Member</span>
              <span v-else class="text-[11px] font-semibold text-purple-600">🌐 Visiting Brother Subscription</span>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div
              v-for="channel in club.channels"
              :key="channel.id"
              class="bg-slate-50 p-4 rounded-xl border border-slate-200 flex items-center justify-between"
            >
              <div class="space-y-0.5">
                <div class="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                  <span>{{ channel.icon }}</span>
                  <span>{{ channel.name }}</span>
                </div>
                <p class="text-[11px] text-slate-500">{{ channel.description }}</p>
              </div>

              <div>
                <div v-if="channel.is_mandatory" class="px-3 py-1 bg-purple-50 text-purple-700 font-bold rounded-lg text-[10px] border border-purple-200">
                  🔒 Mandatory Notice
                </div>
                <button
                  v-else
                  @click="toggleChannel(club.slug, channel.id)"
                  :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer', channel.is_active ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm' : 'bg-slate-200 hover:bg-slate-300 text-slate-700']"
                >
                  {{ channel.is_active ? '✓ Subscribed' : '+ Subscribe' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 2: Unified News Inbox Feed -->
      <div v-if="activeTab === 'feed'" class="space-y-4">
        <div v-if="broadcastFeed.length" class="space-y-4">
          <div
            v-for="item in broadcastFeed"
            :key="item.id"
            class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-3"
          >
            <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-2">
                <span class="font-bold text-slate-900">{{ item.club_name }}</span>
                <span
                  :style="{ backgroundColor: item.channel_color + '15', color: item.channel_color, borderColor: item.channel_color + '30' }"
                  class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wider"
                >
                  {{ item.channel_icon }} {{ item.channel_name }}
                </span>
              </div>
              <span class="text-slate-400 font-mono text-[11px]">{{ item.sent_at }}</span>
            </div>

            <h3 class="text-lg font-bold text-slate-900">{{ item.subject }}</h3>
            
            <div
              class="text-xs text-slate-700 leading-relaxed bg-slate-50 p-4 rounded-xl border border-slate-100 prose max-w-none"
              v-html="item.content"
            ></div>
          </div>
        </div>

        <div v-else class="bg-white p-12 text-center rounded-2xl border border-slate-200">
          <p class="text-slate-500 text-sm">No recent newsletter broadcasts found in your inbox.</p>
        </div>
      </div>

    </div>
  </MemberLayout>
</template>
