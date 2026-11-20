<script setup>
// The lodge's sister and reciprocal visit lists (LodgeLinks): the hall's lodges are sister lodges unless unticked.
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import ToggleSwitch from '@/Components/Ui/ToggleSwitch.vue';

const props = defineProps({
    club: { type: Object, required: true },
    sister: { type: Array, required: true },
    reciprocal: { type: Array, required: true },
    hall: { type: Object, required: true },
    search: { type: String, default: '' },
    results: { type: Array, default: () => [] },
});

const query = ref(props.search);

function change(lodge, relation, action) {
    router.post(route('member.pass_on.lists.update', { slug: props.club.slug }), { lodge_slug: lodge.slug, relation, action }, { preserveScroll: true });
}

function find() {
    router.get(route('member.pass_on.lists', { slug: props.club.slug }), { q: query.value }, { preserveState: true, preserveScroll: true, replace: true, only: ['search', 'results'] });
}

const onList = (lodge, list) => list.some((row) => row.id === lodge.id);
const extraSisters = () => props.sister.filter((lodge) => !props.hall.lodges.some((row) => row.id === lodge.id));
</script>

<template>
    <MembersLayout title="Lodge lists" :club="club" active-tab="lodges">
        <div class="mx-auto max-w-2xl space-y-5">
            <div>
                <Link :href="route('member.pass_on', { slug: club.slug })" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Back to passing on</Link>
                <h2 class="mt-2 font-display text-2xl font-semibold text-slate-900 dark:text-white">Sister and reciprocal lodges</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">You can pass on these lodges' summonses and notices. Members choose which lists they hear from.</p>
            </div>

            <Card padding="none" class="divide-y divide-slate-100 dark:divide-slate-800">
                <div class="px-4 py-3">
                    <h3 class="font-medium text-slate-900 dark:text-white">Sister lodges in {{ hall.name || 'your hall' }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Every lodge in the hall is on the list unless you switch it off.</p>
                </div>
                <p v-if="!hall.lodges.length" class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">The directory has no other lodges in your hall.</p>
                <div v-for="lodge in hall.lodges" :key="lodge.id" class="flex items-center justify-between gap-3 px-4 py-2">
                    <span class="min-w-0 text-sm text-slate-800 dark:text-slate-100">{{ lodge.name }}</span>
                    <ToggleSwitch :model-value="!lodge.excluded" :label="`${lodge.name} is a sister lodge`" on-text="On" off-text="Off" @update:model-value="(on) => change(lodge, 'sister', on ? 'add' : 'remove')" />
                </div>
                <div v-for="lodge in extraSisters()" :key="`extra-${lodge.id}`" class="flex items-center justify-between gap-3 px-4 py-2">
                    <span class="min-w-0 text-sm text-slate-800 dark:text-slate-100">{{ lodge.name }}</span>
                    <Button variant="secondary" size="sm" @click="change(lodge, 'sister', 'remove')">Remove</Button>
                </div>
            </Card>

            <Card padding="none" class="divide-y divide-slate-100 dark:divide-slate-800">
                <div class="px-4 py-3">
                    <h3 class="font-medium text-slate-900 dark:text-white">Reciprocal visit lodges</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Lodges you have a fraternal visiting arrangement with.</p>
                </div>
                <p v-if="!reciprocal.length" class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">None yet. Find a lodge below to add it.</p>
                <div v-for="lodge in reciprocal" :key="lodge.id" class="flex items-center justify-between gap-3 px-4 py-2">
                    <span class="min-w-0 text-sm text-slate-800 dark:text-slate-100">{{ lodge.name }}</span>
                    <Button variant="secondary" size="sm" @click="change(lodge, 'reciprocal', 'remove')">Remove</Button>
                </div>
            </Card>

            <Card class="space-y-3">
                <form class="flex items-end gap-2" @submit.prevent="find">
                    <Input v-model="query" label="Add a lodge" placeholder="Name or number" class="flex-1" />
                    <Button type="submit" variant="secondary">Find</Button>
                </form>
                <p v-if="search && !results.length" class="text-sm text-slate-500 dark:text-slate-400">No lodge in the directory matches "{{ search }}".</p>
                <div v-for="lodge in results" :key="lodge.id" class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-2 dark:border-slate-800">
                    <span class="min-w-0 text-sm text-slate-800 dark:text-slate-100">{{ lodge.name }}<span v-if="lodge.detail" class="block text-xs text-slate-500 dark:text-slate-400">{{ lodge.detail }}</span></span>
                    <div class="flex gap-2">
                        <Button v-if="!onList(lodge, sister)" variant="secondary" size="sm" @click="change(lodge, 'sister', 'add')">Add as sister</Button>
                        <Button v-if="!onList(lodge, reciprocal)" variant="secondary" size="sm" @click="change(lodge, 'reciprocal', 'add')">Add as reciprocal</Button>
                    </div>
                </div>
            </Card>
        </div>
    </MembersLayout>
</template>
