<script setup>
// Grand and Provincial ranks tab of the club settings. `form` is the page's form: this tab edits it in place and the
// page saves it. `resetRanks` is the page's, because it reads the page's settings once the reset comes back.
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import RankListEditor from '@/Components/RankListEditor.vue';

defineProps({
    form: { type: Object, required: true },
    ranks: { type: Object, required: true },
    resetRanks: { type: Function, required: true },
});
</script>

<template>
      <div class="space-y-6">
        <Card>
          <template #header>
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
              <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Grand and Provincial ranks</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">The ranks you can pick from when adding or editing a member. The abbreviation is what appears after their name; the full title helps people choose the right one. Save the page to keep your changes.</p>
              </div>
              <Button variant="secondary" size="sm" class="shrink-0" @click="resetRanks">
                Reset to the {{ ranks.grandLodgeName || 'standard' }} list
              </Button>
            </div>
          </template>

          <div class="space-y-8">

          <RankListEditor
            v-model="form.grand_ranks"
            label="Grand ranks"
            help="Ranks conferred by the Grand Lodge, for example PAGDC."
            id-prefix="grand_ranks"
            :usage="ranks.usage.grand"
            :errors="form.errors"
          />

          <RankListEditor
            v-model="form.provincial_ranks"
            label="Provincial ranks"
            help="Ranks conferred by the Province, for example PPrSGD."
            id-prefix="provincial_ranks"
            :usage="ranks.usage.provincial"
            :errors="form.errors"
          />

          <p class="text-xs text-slate-500 dark:text-slate-400">Removing or renaming a rank here never changes a member who already holds it. Their rank stays selectable on their own record.</p>
          </div>
        </Card>
      </div>
</template>
