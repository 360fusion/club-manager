<script setup>
// One thing passed on to the member from another lodge, Province or Grand Lodge (PassedOnController::show).
import { Link, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import SanitizedHtml from '@/Components/Ui/SanitizedHtml.vue';

const props = defineProps({
    item: { type: Object, required: true },
});

function setMuted(muted) {
    router.post(route('members.passed_on.mute', { delivery: props.item.id }), { muted }, { preserveScroll: true });
}
</script>

<template>
    <MembersLayout :title="item.title" active-tab="inbox">
        <div class="mx-auto max-w-2xl space-y-4">
            <div>
                <Link :href="route('members.notifications')" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Back to the inbox</Link>
                <p class="mt-3 text-xs font-medium text-slate-500 dark:text-slate-400">{{ item.kind }}</p>
                <h2 class="font-display text-2xl font-semibold text-slate-900 dark:text-white">{{ item.title }}</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ item.passed_on_by }}</p>
            </div>

            <Alert v-if="item.cancelled" variant="warning">This meeting has been cancelled.</Alert>

            <Card v-if="item.meeting" padding="sm" :class="['text-sm text-slate-800 dark:text-slate-100', item.cancelled ? 'line-through opacity-70' : '']">
                <p class="font-semibold">{{ item.meeting.date }}<template v-if="item.meeting.time">, {{ item.meeting.time }}</template></p>
                <p v-if="item.meeting.venue">{{ item.meeting.venue }}</p>
                <p v-if="item.meeting.dress_code">Dress: {{ item.meeting.dress_code }}</p>
                <template v-if="item.dining">
                    <p v-if="item.dining.theme" class="mt-2">Dining: {{ item.dining.theme }}</p>
                    <p v-if="item.dining.menu" class="whitespace-pre-line">{{ item.dining.menu }}</p>
                    <p v-if="item.dining.guest_cost">Guests: {{ item.dining.guest_cost }}</p>
                </template>
            </Card>

            <!-- Sanitised when it was saved (SanitizedHtml). -->
            <Card v-if="item.body"><SanitizedHtml class="prose prose-sm max-w-none dark:prose-invert" :html="item.body" /></Card>

            <div class="flex flex-wrap gap-2">
                <a v-if="item.attachment_url" :href="item.attachment_url" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-700">Open the PDF</a>
                <Link v-if="item.lodge?.url && item.meeting && !item.cancelled" :href="item.lodge.url" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Ask to visit</Link>
            </div>

            <Card v-if="item.lodge" padding="sm" class="flex items-center justify-between gap-3">
                <p class="text-sm text-slate-600 dark:text-slate-300">{{ item.lodge.muted ? `You don't hear about ${item.lodge.name}.` : `Not interested in ${item.lodge.name}?` }}</p>
                <Button variant="secondary" size="sm" @click="setMuted(!item.lodge.muted)">{{ item.lodge.muted ? 'Hear about it again' : 'Stop hearing about it' }}</Button>
            </Card>

            <Link :href="route('members.passed_on.choices')" class="inline-block text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Choose what is passed on to you from other lodges</Link>
        </div>
    </MembersLayout>
</template>
