<script setup>
// What the member wants passed on to them, and how (PassedOnPreferences). The same choices on the website and in the app.
import { router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import ToggleSwitch from '@/Components/Ui/ToggleSwitch.vue';

defineProps({
    kinds: { type: Array, required: true },
    muted: { type: Array, required: true },
    calendar: { type: Boolean, required: true },
});

const save = (data) => router.post(route('members.passed_on.choices.update'), data, { preserveScroll: true });

function toggle(kind, channel, on) {
    const channels = on ? [...kind.channels, channel] : kind.channels.filter((value) => value !== channel);
    save({ kinds: { [kind.key]: channels } });
}
</script>

<template>
    <MembersLayout title="What you hear about" active-tab="inbox">
        <div class="mx-auto max-w-xl space-y-5">
            <div>
                <h2 class="font-display text-2xl font-semibold text-slate-900 dark:text-white">What you hear about</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">What your lodge's Secretary passes on from other lodges, Province and Grand Lodge. Your own lodge's summonses and notices always reach you.</p>
            </div>

            <Card padding="none" class="divide-y divide-slate-100 dark:divide-slate-800">
                <div v-for="kind in kinds" :key="kind.key" class="space-y-2 px-4 py-3">
                    <p class="text-sm font-medium text-slate-900 dark:text-white">{{ kind.label }}</p>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        <ToggleSwitch :model-value="kind.channels.includes('app')" :label="`${kind.label} in the app`" on-text="In the app" off-text="In the app" @update:model-value="(on) => toggle(kind, 'app', on)" />
                        <ToggleSwitch :model-value="kind.channels.includes('email')" :label="`${kind.label} by email`" on-text="By email" off-text="By email" @update:model-value="(on) => toggle(kind, 'email', on)" />
                    </div>
                </div>
            </Card>

            <Card padding="sm" class="flex items-start justify-between gap-3">
                <div class="text-sm">
                    <p class="font-medium text-slate-900 dark:text-white">Meetings in my calendar</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Put the meetings from passed-on summonses in your calendar.</p>
                </div>
                <ToggleSwitch :model-value="calendar" label="Meetings in my calendar" on-text="On" off-text="Off" @update:model-value="(on) => save({ calendar: on })" />
            </Card>

            <Card v-if="muted.length" padding="none" class="divide-y divide-slate-100 dark:divide-slate-800">
                <p class="px-4 py-3 text-sm font-medium text-slate-900 dark:text-white">Lodges you don't hear about</p>
                <div v-for="lodge in muted" :key="lodge.id" class="flex items-center justify-between gap-3 px-4 py-2">
                    <span class="text-sm text-slate-700 dark:text-slate-200">{{ lodge.name }}</span>
                    <Button variant="secondary" size="sm" @click="save({ unmute: lodge.id })">Hear about it again</Button>
                </div>
            </Card>
        </div>
    </MembersLayout>
</template>
