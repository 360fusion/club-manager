<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MeetingsTabs from '@/Components/MeetingsTabs.vue';

const props = defineProps({
    club: { type: Object, required: true },
    requests: { type: Array, default: () => [] },
    visibility: { type: String, required: true },
    visibilityOptions: { type: Object, required: true },
});

const STATUS = {
    pending: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    declined: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    revoked: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};

function act(request, action) {
    if (action === 'revoke' && !confirm(`Stop sharing your summonses with ${request.name}?`)) {
        return;
    }

    router.post(route(`admin.visitors.${action}`, { clubSlug: props.club.slug, id: request.id }), {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Visitors" :club="club" active-tab="meetings">
        <Head title="Visitors" />

        <MeetingsTabs :club="club" active-tab="visitors" />

        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white">Visiting brethren</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Right now your lodge shares its meetings with: <strong>{{ visibilityOptions[visibility] }}</strong>.
                    <Link :href="route('admin.settings.show', { clubSlug: club.slug })" class="text-blue-600 hover:underline dark:text-blue-400">Change this in Settings</Link>.
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Approved visitors see only the date, time, venue, dress and dining for each meeting. They never see your sick list, candidates, dues or the rest of the summons.</p>
            </div>

            <p v-if="visibility !== 'approved_visitors'" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                Visitors can only ask to receive your summonses when your lodge shares with <em>{{ visibilityOptions.approved_visitors }}</em>.
            </p>

            <p v-if="!requests.length" class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No requests yet.</p>

            <ul v-else class="space-y-3">
                <li v-for="request in requests" :key="request.id" class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-slate-900 dark:text-white">{{ request.name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ request.home_lodge_name }}<span v-if="request.home_lodge_number"> No. {{ request.home_lodge_number }}</span><span v-if="request.rank"> · {{ request.rank }}</span>
                            </p>
                            <p class="text-xs"><a :href="`mailto:${request.email}`" class="text-blue-600 hover:underline dark:text-blue-400">{{ request.email }}</a> · asked {{ request.requested_at }}</p>
                        </div>
                        <span :class="['rounded-full px-2.5 py-1 text-[11px] font-bold', STATUS[request.status]]">{{ request.status }}</span>
                    </div>

                    <p v-if="request.message" class="mt-3 whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800/60">{{ request.message }}</p>
                    <p v-if="request.decided_by" class="mt-2 text-xs text-slate-500 dark:text-slate-400">Answered by {{ request.decided_by }} on {{ request.decided_at }}</p>

                    <div class="mt-3 flex gap-2">
                        <template v-if="request.status === 'pending'">
                            <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-500" @click="act(request, 'approve')">Approve</button>
                            <button class="rounded-lg bg-slate-200 px-3 py-1.5 text-xs font-bold text-slate-800 hover:bg-slate-300 dark:bg-slate-700 dark:text-slate-100" @click="act(request, 'decline')">Decline</button>
                        </template>
                        <button v-else-if="request.status === 'approved'" class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 dark:bg-rose-950/50 dark:text-rose-400" @click="act(request, 'revoke')">Stop sharing</button>
                        <button v-else class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-500" @click="act(request, 'approve')">Approve after all</button>
                    </div>
                </li>
            </ul>
        </div>
    </AdminLayout>
</template>
