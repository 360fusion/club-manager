<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import RankListEditor from '@/Components/RankListEditor.vue';

const props = defineProps({
  grandLodge: { type: Object, required: true },
  grandRanks: { type: Array, default: () => [] },
  provincialRanks: { type: Array, default: () => [] },
});

const form = useForm({
  grand_ranks: props.grandRanks.map((r) => ({ ...r })),
  provincial_ranks: props.provincialRanks.map((r) => ({ ...r })),
});

const submit = () => {
  form.put(route('superadmin.grand_lodges.ranks.update', props.grandLodge.id), { preserveScroll: true });
};
</script>

<template>
  <Head :title="`${grandLodge.short_name || grandLodge.name} ranks`" />

  <SuperAdminLayout :title="`${grandLodge.name} ranks`">
    <div class="max-w-4xl space-y-6">
      <div class="space-y-1">
        <Link :href="route('superadmin.grand_lodges.index')" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">&larr; Back to Grand Lodges</Link>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ grandLodge.name }}: Grand &amp; Provincial ranks</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          The master lists for the {{ grandLodge.clubs_count }} {{ grandLodge.clubs_count === 1 ? 'lodge' : 'lodges' }} under this Grand Lodge.
          A new lodge copies these when it is created, and a lodge can reset to them from its settings.
          Lodges that already have their own copy are not changed when you save.
        </p>
      </div>

      <form @submit.prevent="submit" class="bg-white dark:bg-slate-900 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-8">
        <RankListEditor
          v-model="form.grand_ranks"
          label="Grand ranks"
          help="Ranks conferred by the Grand Lodge."
          id-prefix="grand_ranks"
          :errors="form.errors"
        />

        <RankListEditor
          v-model="form.provincial_ranks"
          label="Provincial ranks"
          help="Ranks conferred by a Province."
          id-prefix="provincial_ranks"
          :errors="form.errors"
        />

        <div class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
          <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-bold rounded-xl text-xs shadow-md transition cursor-pointer">
            Save rank lists
          </button>
        </div>
      </form>
    </div>
  </SuperAdminLayout>
</template>
