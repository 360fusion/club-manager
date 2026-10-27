<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import RankListEditor from '@/Components/RankListEditor.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';

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
      <PageHeader
        :title="`${grandLodge.name}: Grand and Provincial ranks`"
        :breadcrumbs="[{ label: 'Grand Lodges', href: route('superadmin.grand_lodges.index') }, { label: 'Ranks' }]"
        :description="`The master lists for the ${grandLodge.clubs_count} ${grandLodge.clubs_count === 1 ? 'lodge' : 'lodges'} under this Grand Lodge. A new lodge copies these when it is created, and a lodge can reset to them from its settings. Lodges that already have their own copy are not changed when you save.`"
      />

      <Card padding="lg">
        <form class="space-y-8" @submit.prevent="submit">
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

          <div class="flex justify-end pt-4 border-t border-slate-200 dark:border-slate-800">
            <Button type="submit" :loading="form.processing">Save rank lists</Button>
          </div>
        </form>
      </Card>
    </div>
  </SuperAdminLayout>
</template>
