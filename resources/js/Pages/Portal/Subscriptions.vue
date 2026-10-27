<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';

defineProps({
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

const tabClass = (isActive) => [
  'inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold whitespace-nowrap transition-colors cursor-pointer',
  isActive
    ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-700'
    : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white',
];
</script>

<template>
  <MembersLayout title="Subscriptions" active-tab="subscriptions">
    <Head title="My subscriptions and news" />

    <div class="space-y-6">

      <!-- Top Action Bar -->
      <Card class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">
            Subscriptions and broadcast feed
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Manage your newsletter and summons subscriptions across all your enrolled clubs and visiting brethren memberships.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <Button variant="secondary" size="sm" :href="route('members.dashboard', { tab: 'directory' })">
            <UiIcon name="building" /> Find other lodges in directory
          </Button>
        </div>
      </Card>

      <!-- Navigation Tabs -->
      <nav aria-label="Subscriptions" class="max-w-full overflow-x-auto">
        <div class="flex w-fit items-center gap-1 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 dark:border-slate-700/60 dark:bg-slate-800/80">
          <button
            type="button"
            @click="activeTab = 'matrix'"
            :class="tabClass(activeTab === 'matrix')"
            :aria-pressed="activeTab === 'matrix'"
          >
            <UiIcon name="settings" /> Subscription preferences
          </button>
          <button
            type="button"
            @click="activeTab = 'feed'"
            :class="tabClass(activeTab === 'feed')"
            :aria-pressed="activeTab === 'feed'"
          >
            <UiIcon name="document" /> Unified broadcast inbox ({{ broadcastFeed.length }})
          </button>
        </div>
      </nav>

      <!-- Tab 1: Preferences Matrix -->
      <div v-if="activeTab === 'matrix'" class="space-y-6">
        <Card
          v-for="club in clubMatrix"
          :key="club.id"
          class="space-y-4"
        >
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ club.name }}</h3>
                <Badge v-if="club.lodge_number">No. {{ club.lodge_number }}</Badge>
              </div>
              <Badge v-if="club.is_member" variant="success">Enrolled member</Badge>
              <Badge v-else variant="info">Visiting subscription</Badge>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div
              v-for="channel in club.channels"
              :key="channel.id"
              class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3"
            >
              <div class="space-y-0.5">
                <div class="font-semibold text-xs text-slate-900 dark:text-white flex items-center gap-1.5">
                  <span>{{ channel.icon }}</span>
                  <span>{{ channel.name }}</span>
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ channel.description }}</p>
              </div>

              <div class="shrink-0">
                <Badge v-if="channel.is_mandatory" variant="info">
                  <UiIcon name="lock" :size="12" /> Mandatory notice
                </Badge>
                <Button
                  v-else
                  variant="secondary"
                  size="sm"
                  @click="toggleChannel(club.slug, channel.id)"
                >
                  <UiIcon :name="channel.is_active ? 'check' : 'plus'" /> {{ channel.is_active ? 'Subscribed' : 'Subscribe' }}
                </Button>
              </div>
            </div>
          </div>
        </Card>
      </div>

      <!-- Tab 2: Unified News Inbox Feed -->
      <div v-if="activeTab === 'feed'" class="space-y-4">
        <div v-if="broadcastFeed.length" class="space-y-4">
          <Card
            v-for="item in broadcastFeed"
            :key="item.id"
            class="space-y-3"
          >
            <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-2">
                <span class="font-semibold text-slate-900 dark:text-white">{{ item.club_name }}</span>
                <span
                  :style="{ backgroundColor: item.channel_color + '15', color: item.channel_color, borderColor: item.channel_color + '30' }"
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-medium border uppercase tracking-wider"
                >
                  {{ item.channel_icon }} {{ item.channel_name }}
                </span>
              </div>
              <span class="text-slate-400 font-mono text-[11px]">{{ item.sent_at }}</span>
            </div>

            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ item.subject }}</h3>

            <div
              class="text-xs text-slate-700 dark:text-slate-200 leading-relaxed bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-100 dark:border-slate-800 prose max-w-none"
              v-html="item.content"
            ></div>
          </Card>
        </div>

        <Card v-else padding="lg" class="text-center">
          <p class="text-slate-500 dark:text-slate-400 text-sm">No recent newsletter broadcasts found in your inbox.</p>
        </Card>
      </div>

    </div>
  </MembersLayout>
</template>
