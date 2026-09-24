<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({
    lodge: { type: Object, required: true },
    roles: { type: Object, required: true },
});

const form = useForm({ claimant_role: '', message: '', phone: '', evidence: '', confirm: false });

function submit() {
    form.post(route('lodges.claim.store', props.lodge.slug));
}

const inputClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900';
</script>

<template>
    <PublicLayout :title="`Claim ${lodge.name}`">
        <div class="mx-auto max-w-2xl space-y-6">
            <div>
                <Link :href="route('lodges.show', lodge.slug)" class="text-sm text-blue-600 hover:underline dark:text-blue-400">← {{ lodge.name }}</Link>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight">Claim {{ lodge.name }}</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ lodge.order }}<span v-if="lodge.number"> · No. {{ lodge.number }}</span><span v-if="lodge.province"> · {{ lodge.province }}</span><span v-if="lodge.hall"> · {{ lodge.hall }}</span>
                </p>
            </div>

            <p class="rounded-lg bg-slate-100 p-4 text-sm text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                Managing a lodge here lets you keep its meetings, members, news and summonses in one place. Because it gives access to a lodge's records, we check every request
                before it is approved, and we may ask you for more. Only ask to manage a lodge you belong to.
            </p>

            <form class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" @submit.prevent="submit">
                <p v-if="form.errors.lodge" role="alert" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">{{ form.errors.lodge }}</p>

                <div>
                    <label class="mb-1 block text-sm font-medium" for="role">Your part in the lodge</label>
                    <select id="role" v-model="form.claimant_role" required :class="inputClass">
                        <option value="" disabled>Choose one</option>
                        <option v-for="(label, key) in roles" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p v-if="form.errors.claimant_role" class="mt-1 text-xs text-rose-600">{{ form.errors.claimant_role }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium" for="message">Why you are asking</label>
                    <textarea id="message" v-model="form.message" rows="4" required :class="inputClass" placeholder="For example: I am the Secretary and would like to publish our summonses and keep our members' details here." />
                    <p v-if="form.errors.message" class="mt-1 text-xs text-rose-600">{{ form.errors.message }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium" for="evidence">Anything that helps us check (optional)</label>
                    <textarea id="evidence" v-model="form.evidence" rows="3" :class="inputClass" placeholder="For example: the year you were installed as an officer, a lodge email address, or the name of your Provincial Secretary." />
                    <p v-if="form.errors.evidence" class="mt-1 text-xs text-rose-600">{{ form.errors.evidence }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium" for="phone">A phone number we can reach you on (optional)</label>
                    <input id="phone" v-model="form.phone" type="tel" :class="inputClass" />
                    <p v-if="form.errors.phone" class="mt-1 text-xs text-rose-600">{{ form.errors.phone }}</p>
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <input v-model="form.confirm" type="checkbox" class="mt-1" />
                    <span>I belong to this lodge and I am allowed to ask to manage it.</span>
                </label>
                <p v-if="form.errors.confirm" class="text-xs text-rose-600">Please tick to confirm.</p>

                <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-60">Send my claim</button>
            </form>
        </div>
    </PublicLayout>
</template>
