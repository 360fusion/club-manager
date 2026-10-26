<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({
    lodge: { type: Object, required: true },
    halls: { type: Array, default: () => [] },
    statuses: { type: Object, required: true },
});

const current = props.lodge.current;
const form = useForm({
    meets_text: current.meets_text ?? '',
    masonic_hall_id: current.masonic_hall_id ?? '',
    hall_text: '',
    status: current.status,
    name: current.name ?? '',
    number: current.number ?? '',
    website_url: current.website_url ?? '',
    note: '',
});

const hallNotListed = computed(() => form.masonic_hall_id === 'other');

// Only what the person changed is sent, so an untouched box never counts as a suggestion.
function submit() {
    form.transform((data) => {
        const changed = {};

        for (const field of ['meets_text', 'status', 'name', 'number', 'website_url']) {
            if ((data[field] ?? '') !== (current[field] ?? '')) {
                changed[field] = data[field];
            }
        }

        if (hallNotListed.value) {
            changed.hall_text = data.hall_text;
        } else if (data.masonic_hall_id !== (current.masonic_hall_id ?? '')) {
            changed.masonic_hall_id = data.masonic_hall_id;
        }

        return { ...changed, note: data.note };
    }).post(route('lodges.correction.store', props.lodge.slug));
}

const inputClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900';
</script>

<template>
    <PublicLayout :title="`Suggest a fix: ${lodge.name}`">
        <div class="mx-auto max-w-2xl space-y-6">
            <div>
                <Link :href="route('lodges.show', lodge.slug)" class="text-sm text-blue-600 hover:underline dark:text-blue-400">← {{ lodge.name }}</Link>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight">Suggest a fix</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ lodge.order }}<span v-if="lodge.province"> · {{ lodge.province }}</span></p>
            </div>

            <p class="rounded-lg bg-slate-100 p-4 text-sm text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                Change whatever is wrong and leave the rest as it is. Our team checks every suggestion before the listing changes, and we will let you know what happens.
            </p>

            <form class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="submit">
                <p v-if="form.errors.lodge || form.errors.changes" role="alert" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">{{ form.errors.lodge || form.errors.changes }}</p>

                <div>
                    <label class="mb-1 block text-sm font-medium" for="meets_text">When it meets</label>
                    <textarea id="meets_text" v-model="form.meets_text" rows="3" :class="inputClass" placeholder="For example: 2nd Tuesday, September to May, at 6.30pm. Installation in October." />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Say the day, the months, the time, and the installation month<span v-if="lodge.installation_month"> (we have {{ lodge.installation_month }})</span>.</p>
                    <p v-if="form.errors.meets_text" class="mt-1 text-xs text-rose-600">{{ form.errors.meets_text }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium" for="hall">Where it meets</label>
                    <select id="hall" v-model="form.masonic_hall_id" :class="inputClass">
                        <option value="">Not known</option>
                        <option v-for="hall in halls" :key="hall.id" :value="hall.id">{{ hall.name }}<template v-if="hall.town">, {{ hall.town }}</template></option>
                        <option value="other">It meets somewhere not on this list</option>
                    </select>
                    <input v-if="hallNotListed" v-model="form.hall_text" type="text" :class="[inputClass, 'mt-2']" placeholder="The hall's name and town" aria-label="The hall's name and town" />
                    <p v-if="form.errors.masonic_hall_id || form.errors.hall_text" class="mt-1 text-xs text-rose-600">{{ form.errors.masonic_hall_id || form.errors.hall_text }}</p>
                </div>

                <fieldset>
                    <legend class="mb-1 text-sm font-medium">Does it still meet?</legend>
                    <label v-for="(label, key) in statuses" :key="key" class="flex items-center gap-2 text-sm">
                        <input v-model="form.status" type="radio" :value="key" /> {{ label }}
                    </label>
                    <p v-if="form.errors.status" class="mt-1 text-xs text-rose-600">{{ form.errors.status }}</p>
                </fieldset>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium" for="name">Name</label>
                        <input id="name" v-model="form.name" type="text" :class="inputClass" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-rose-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium" for="number">Number</label>
                        <input id="number" v-model="form.number" type="text" :class="inputClass" />
                        <p v-if="form.errors.number" class="mt-1 text-xs text-rose-600">{{ form.errors.number }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium" for="website_url">Website</label>
                    <input id="website_url" v-model="form.website_url" type="url" :class="inputClass" placeholder="https://" />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Empty the number or website box if it is wrong and there should be none.</p>
                    <p v-if="form.errors.website_url" class="mt-1 text-xs text-rose-600">{{ form.errors.website_url }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium" for="note">Anything else, and how you know (optional)</label>
                    <textarea id="note" v-model="form.note" rows="3" :class="inputClass" placeholder="For example: I am a member, or the province's website changed in May." />
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Please don't include anyone's email address or phone number.</p>
                    <p v-if="form.errors.note" class="mt-1 text-xs text-rose-600">{{ form.errors.note }}</p>
                </div>

                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-60">Send my suggestion</button>
            </form>
        </div>
    </PublicLayout>
</template>
