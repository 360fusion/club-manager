<script setup>
import { ref, computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
  clubs: {
    type: Array,
    default: () => [],
  },
});

const searchQuery = ref('');

const adminClubs = computed(() => {
  return props.clubs.filter(c => ['admin', 'owner'].includes(c.role));
});

const memberClubs = computed(() => {
  return props.clubs.filter(c => !['admin', 'owner'].includes(c.role));
});

const filteredAdminClubs = computed(() => {
  return adminClubs.value.filter(c => {
    const matchesSearch = c.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          c.type_name.toLowerCase().includes(searchQuery.value.toLowerCase());
    return matchesSearch;
  });
});

const filteredMemberClubs = computed(() => {
  return memberClubs.value.filter(c => {
    const matchesSearch = c.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          c.type_name.toLowerCase().includes(searchQuery.value.toLowerCase());
    return matchesSearch;
  });
});
</script>

<template>
  <AdminLayout title="Clubs" active-tab="clubs">

    <div class="space-y-8">

      <!-- Top action and summary header -->
      <Card>
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div>
            <div class="flex flex-wrap items-center gap-3">
              <h2 class="text-xl font-bold text-slate-900 dark:text-white">My clubs and workspaces</h2>
              <Badge variant="info">{{ clubs.length }} joined clubs</Badge>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage and access all sports clubs, rowing boathouses, and community organizations linked to your account.</p>
          </div>

          <Button href="/">
            <template #icon><UiIcon name="plus" /></template>
            Join or register a club
          </Button>
        </div>
      </Card>

      <!-- Search bar -->
      <Card padding="sm" class="flex flex-col sm:flex-row gap-3 items-center justify-between">
        <div class="relative w-full sm:w-80">
          <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><UiIcon name="search" /></span>
          <input
            v-model="searchQuery"
            type="text"
            aria-label="Search clubs"
            placeholder="Search clubs by name or type..."
            class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 placeholder-slate-400 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder-slate-500"
          />
        </div>

        <div class="text-xs text-slate-500 dark:text-slate-400">
          Showing <strong class="text-slate-900 dark:text-white">{{ filteredAdminClubs.length + filteredMemberClubs.length }}</strong> of <strong class="text-slate-900 dark:text-white">{{ clubs.length }}</strong> clubs
        </div>
      </Card>

      <!-- Section 1: clubs you manage (admin / owner) -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-semibold text-slate-900 dark:text-white">Clubs you manage (admin role)</h3>
          <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ filteredAdminClubs.length }} clubs</span>
        </div>

        <div v-if="filteredAdminClubs.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <Card
            v-for="club in filteredAdminClubs"
            :key="club.id"
            class="flex flex-col justify-between space-y-6 transition-colors hover:border-blue-300 dark:hover:border-blue-700/60"
          >
            <div class="space-y-4">
              <div class="flex items-center justify-between gap-2">
                <Badge>{{ club.type_name }}</Badge>
                <Badge variant="info" class="capitalize">{{ club.role }}</Badge>
              </div>

              <div>
                <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ club.name }}</h4>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Slug: <code class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded text-slate-600 dark:text-slate-300 font-mono">{{ club.slug }}</code></p>
              </div>

              <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200 dark:border-slate-800 text-xs">
                <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800">
                  <div class="text-[10px] uppercase tracking-wider font-medium text-slate-500 dark:text-slate-400">Total members</div>
                  <div class="text-sm font-semibold text-slate-900 dark:text-white mt-0.5">{{ club.members_count }}</div>
                </div>
                <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800">
                  <div class="text-[10px] uppercase tracking-wider font-medium text-slate-500 dark:text-slate-400">Scheduled events</div>
                  <div class="text-sm font-semibold text-slate-900 dark:text-white mt-0.5">{{ club.events_count }}</div>
                </div>
              </div>
            </div>

            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center gap-2">
              <Button variant="secondary" size="sm" class="flex-1" :href="route('admin.analytics', { slug: club.slug })">Admin portal &rarr;</Button>
              <Button variant="secondary" size="sm" class="flex-1" :href="route('member.dashboard', { slug: club.slug })">Member view &rarr;</Button>
            </div>
          </Card>
        </div>

        <Card v-else class="text-center">
          <p class="text-sm text-slate-500 dark:text-slate-400">No clubs managed with administrative permissions found.</p>
        </Card>
      </div>

      <!-- Section 2: clubs you belong to (member / coach / treasurer) -->
      <div class="space-y-4 pt-4">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-semibold text-slate-900 dark:text-white">Clubs you belong to (member / athlete role)</h3>
          <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ filteredMemberClubs.length }} clubs</span>
        </div>

        <div v-if="filteredMemberClubs.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <Card
            v-for="club in filteredMemberClubs"
            :key="club.id"
            class="flex flex-col justify-between space-y-6 transition-colors hover:border-blue-300 dark:hover:border-blue-700/60"
          >
            <div class="space-y-4">
              <div class="flex items-center justify-between gap-2">
                <Badge>{{ club.type_name }}</Badge>
                <Badge class="capitalize">{{ club.role }}</Badge>
              </div>

              <div>
                <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ club.name }}</h4>
                <div class="flex items-center gap-2 mt-1.5">
                  <span v-if="club.member_number" class="text-xs font-mono px-2 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-md border border-slate-200 dark:border-slate-800">
                    {{ club.member_number }}
                  </span>
                  <Badge :variant="club.status === 'active' ? 'success' : 'neutral'" class="capitalize">{{ club.status }}</Badge>
                </div>
              </div>
            </div>

            <div class="pt-4 border-t border-slate-200 dark:border-slate-800">
              <Button variant="secondary" size="sm" block :href="route('member.dashboard', { slug: club.slug })">Enter member portal &rarr;</Button>
            </div>
          </Card>
        </div>

        <Card v-else class="text-center">
          <p class="text-sm text-slate-500 dark:text-slate-400">No additional member clubs found.</p>
        </Card>
      </div>

    </div>

  </AdminLayout>
</template>
