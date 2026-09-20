<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { orderColour } from '@/Utils/orderColour';

const props = defineProps({
    // [{ name, slug }]
    options: { type: Array, default: () => [] },
    current: { type: String, default: null },
    routeName: { type: String, required: true },
});

// On /members/{club}/… the club is already fixed by the URL, so there is nothing to choose.
const fixedByUrl = computed(() => Boolean(route().params.slug));
const chip = (active) => ['rounded-full border px-3 py-1 text-xs font-medium transition-colors', active ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300' : 'border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'];
</script>

<template>
    <div v-if="!fixedByUrl && options.length > 1" class="flex flex-wrap gap-2" role="group" aria-label="Filter by club">
        <Link :href="route(routeName)" :class="chip(!current)" preserve-scroll>All clubs</Link>
        <Link v-for="club in options" :key="club.slug" :href="route(routeName)" :data="{ club: club.slug }" :class="[chip(current === club.slug), 'inline-flex items-center gap-1.5']" preserve-scroll><span :class="['h-1.5 w-1.5 rounded-full', orderColour(club.colour).dot]" aria-hidden="true" />{{ club.name }}</Link>
    </div>
</template>
