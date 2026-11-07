<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    club: { type: Object, required: true },
    // { id, url, description, events, active }
    endpoints: { type: Array, required: true },
    // The last 20 sent to any endpoint: { id, url, event, status, attempts, response_code, last_error, created_at }
    deliveries: { type: Array, required: true },
    // What an endpoint can subscribe to: { key, label }
    events: { type: Array, required: true },
    // The new endpoint's signing secret, only on the page load right after adding it.
    newSecret: { type: String, default: null },
});

const form = useForm({ url: '', description: '', events: [] });

function add() {
    form.post(route('admin.webhooks.store', { clubSlug: props.club.slug }), { preserveScroll: true, onSuccess: () => form.reset() });
}

function act(endpoint, action) {
    if (action === 'destroy') {
        if (!confirm(`Delete the endpoint ${endpoint.url}? Nothing more will be sent to it.`)) {
            return;
        }
        router.delete(route('admin.webhooks.destroy', { clubSlug: props.club.slug, id: endpoint.id }), { preserveScroll: true });

        return;
    }

    router.post(route(`admin.webhooks.${action}`, { clubSlug: props.club.slug, id: endpoint.id }), {}, { preserveScroll: true });
}

const when = (iso) => (iso ? new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '');
const label = (key) => props.events.find((e) => e.key === key)?.label ?? key;
</script>

<template>
    <AdminLayout title="Webhooks" :club="club" active-tab="webhooks">
        <Head title="Webhooks" />

        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white">Webhooks</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Send what happens in the club (members, meetings, bookings, payments) to another system, as a signed POST.</p>
            </div>

            <div v-if="newSecret" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-700 dark:bg-amber-950">
                <p class="font-bold text-amber-900 dark:text-amber-200">Copy the signing secret now. It will not be shown again.</p>
                <code class="mt-2 block break-all rounded bg-white px-2 py-1 text-slate-900 dark:bg-slate-900 dark:text-slate-100">{{ newSecret }}</code>
            </div>

            <form class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="add">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Add an endpoint</h2>
                <div>
                    <label for="url" class="block text-sm font-semibold">URL</label>
                    <input id="url" v-model="form.url" type="url" required placeholder="https://" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950" />
                    <p v-if="form.errors.url" class="mt-1 text-sm text-rose-600">{{ form.errors.url }}</p>
                </div>
                <div>
                    <label for="description" class="block text-sm font-semibold">Description <span class="font-normal text-slate-500">(optional)</span></label>
                    <input id="description" v-model="form.description" type="text" maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 dark:border-slate-700 dark:bg-slate-950" />
                </div>
                <fieldset>
                    <legend class="text-sm font-semibold">Events to send</legend>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <label v-for="event in events" :key="event.key" class="flex items-center gap-2 text-sm">
                            <input v-model="form.events" type="checkbox" :value="event.key" class="rounded border-slate-300" />
                            {{ event.label }}
                        </label>
                    </div>
                    <p v-if="form.errors.events" class="mt-1 text-sm text-rose-600">{{ form.errors.events }}</p>
                </fieldset>
                <button type="submit" :disabled="form.processing" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700 disabled:opacity-50">Add endpoint</button>
            </form>

            <section class="space-y-3">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Endpoints</h2>
                <p v-if="!endpoints.length" class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No endpoints yet.</p>
                <ul v-else class="divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                    <li v-for="endpoint in endpoints" :key="endpoint.id" class="flex flex-wrap items-start justify-between gap-3 px-4 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="break-all font-semibold text-slate-900 dark:text-white">{{ endpoint.url }}</p>
                            <p v-if="endpoint.description" class="text-slate-500 dark:text-slate-400">{{ endpoint.description }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ endpoint.events.map(label).join(', ') }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button type="button" class="rounded-xl border border-slate-300 px-3 py-1.5 dark:border-slate-700" @click="act(endpoint, 'toggle')">{{ endpoint.active ? 'Switch off' : 'Switch on' }}</button>
                            <button type="button" :disabled="!endpoint.active" class="rounded-xl border border-slate-300 px-3 py-1.5 disabled:opacity-50 dark:border-slate-700" @click="act(endpoint, 'test')">Send test</button>
                            <button type="button" class="rounded-xl border border-rose-300 px-3 py-1.5 text-rose-700 dark:border-rose-800 dark:text-rose-400" @click="act(endpoint, 'destroy')">Delete</button>
                        </div>
                    </li>
                </ul>
            </section>

            <section v-if="deliveries.length" class="space-y-3">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Sent lately</h2>
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase text-slate-500">
                            <tr><th class="px-4 py-2">When</th><th class="px-4 py-2">Event</th><th class="px-4 py-2">To</th><th class="px-4 py-2">Result</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <tr v-for="d in deliveries" :key="d.id">
                                <td class="whitespace-nowrap px-4 py-2">{{ when(d.created_at) }}</td>
                                <td class="px-4 py-2">{{ d.event }}</td>
                                <td class="break-all px-4 py-2">{{ d.url }}</td>
                                <td class="px-4 py-2">{{ d.status }}<span v-if="d.response_code"> ({{ d.response_code }})</span><span v-if="d.last_error" class="block text-xs text-rose-600">{{ d.last_error }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
