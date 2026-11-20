<script setup>
// The lodge's Masonic anniversaries in the next three months (MasonicAnniversaries), for the Master, Secretary and
// Almoner to mark: round numbers first in attention, everything else listed too.
import { computed } from 'vue';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';

const props = defineProps({
    club: { type: Object, required: true },
    anniversaries: { type: Array, required: true },
});

const milestones = computed(() => props.anniversaries.filter((row) => row.milestone));
const when = (days) => (days === 0 ? 'Today' : days === 1 ? 'Tomorrow' : `In ${days} days`);
</script>

<template>
    <MembersLayout title="Anniversaries" :club="club" active-tab="lodges">
        <div class="mx-auto max-w-2xl space-y-5">
            <div>
                <h2 class="font-display text-2xl font-semibold text-slate-900 dark:text-white">Masonic anniversaries</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">The next three months, from the dates on the roll: years since each brother was initiated, and since he joined the lodge if he came from another.</p>
            </div>

            <Card v-if="!anniversaries.length"><p class="text-sm text-slate-600 dark:text-slate-300">None in the next three months, or the roll has no initiation dates yet.</p></Card>

            <template v-else>
                <Card v-if="milestones.length" padding="sm" class="space-y-1 bg-amber-50 dark:bg-amber-950/30">
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ milestones.length }} {{ milestones.length === 1 ? 'milestone' : 'milestones' }} to mark</p>
                    <p v-for="(row, index) in milestones" :key="index" class="text-sm text-amber-900 dark:text-amber-200">{{ row.name }}: {{ row.label }}, {{ row.date }}</p>
                </Card>

                <Card padding="none" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="(row, index) in anniversaries" :key="index" class="flex items-start justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ row.name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.label }} · {{ row.date }}</p>
                        </div>
                        <Badge :variant="row.milestone ? 'warning' : 'neutral'">{{ when(row.in_days) }}</Badge>
                    </div>
                </Card>
            </template>
        </div>
    </MembersLayout>
</template>
