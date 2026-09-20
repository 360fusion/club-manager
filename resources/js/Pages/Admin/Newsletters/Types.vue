<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  types: Array,
});

const deleteType = (typeId) => {
  if (confirm('Are you sure you want to delete this newsletter channel? Existing broadcasts will remain.')) {
    router.delete(route('admin.newsletters.types.destroy', { clubSlug: props.club.slug, id: typeId }));
  }
};
</script>

<template>
  <AdminLayout title="Newsletter Channels & Settings" :club="club" active-tab="newsletters">
    <Head title="Newsletter Channels & Settings" />

    <div class="space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <Link
              :href="route('admin.newsletters.index', club.slug)"
              class="inline-flex items-center text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
            >
              ← Back to Broadcasts
            </Link>
          </div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">
            ⚙️ Newsletter Channels & Settings
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Manage communication types (Summonses, News, Social Bulletins) and configure external visiting subscriptions & approval rules.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <Link
            :href="route('admin.newsletters.subscribers', club.slug)"
            class="px-4 py-2 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs font-bold rounded-xl border border-blue-200 dark:border-blue-800/60 transition-all flex items-center gap-1"
          >
            👥 Manage Subscribers
          </Link>
          <Link
            :href="route('admin.newsletters.types.create', club.slug)"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1"
          >
            + Create New Channel
          </Link>
        </div>
      </div>

      <!-- Channel Grid -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div
          v-for="type in types"
          :key="type.id"
          class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all flex flex-col justify-between"
        >
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-2xl">{{ type.icon }}</span>
              <div class="flex items-center gap-1">
                <span
                  v-if="type.is_mandatory"
                  class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60"
                >
                  🔒 Mandatory Notice
                </span>
                <span
                  v-if="type.is_external_subscribable"
                  class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60"
                >
                  🌐 External Subs Allowed
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800"
                >
                  🔒 Members Only
                </span>
              </div>
            </div>

            <div>
              <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span :style="{ backgroundColor: type.color }" class="w-3 h-3 rounded-full inline-block"></span>
                {{ type.name }}
              </h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ type.description || 'No description provided.' }}</p>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
              <div class="bg-slate-50 dark:bg-slate-800/50 p-2 rounded-xl">
                <div class="text-[10px] font-bold text-slate-400 uppercase">Total Subs</div>
                <div class="text-base font-bold text-blue-600 dark:text-blue-400">{{ type.total_subscribers || 0 }}</div>
              </div>
              <div class="bg-slate-50 dark:bg-slate-800/50 p-2 rounded-xl">
                <div class="text-[10px] font-bold text-slate-400 uppercase">Visiting Brethren</div>
                <div class="text-base font-bold text-emerald-600 dark:text-emerald-400">{{ type.external_subscribers || 0 }}</div>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
            <Link
              :href="route('admin.newsletters.types.edit', { clubSlug: club.slug, id: type.id })"
              class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 transition-all"
            >
              ⚙️ Configure Channel
            </Link>
            <button
              @click="deleteType(type.id)"
              class="px-2.5 py-1.5 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl text-xs font-bold transition-all cursor-pointer"
            >
              🗑️
            </button>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
