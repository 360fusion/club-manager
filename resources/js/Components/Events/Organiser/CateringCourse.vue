<script setup>
import Card from '@/Components/Ui/Card.vue';

// One course of the catering numbers: each dish with its count and a bar against the covers.
defineProps({
    label: { type: String, required: true },
    dishes: { type: Array, required: true },
    covers: { type: Number, default: 0 },
});
</script>

<template>
    <Card padding="sm">
        <p class="mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ label }}</p>
        <ul class="space-y-2">
            <li v-for="dish in dishes" :key="dish.name" class="grid grid-cols-[minmax(0,1fr)_6rem_2rem] items-center gap-3 text-sm tabular-nums sm:grid-cols-[minmax(0,1fr)_12rem_2rem]">
                <span class="text-slate-700 dark:text-slate-300">{{ dish.name }}</span>
                <span class="h-2 overflow-hidden rounded-full border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800" aria-hidden="true">
                    <span class="block h-full bg-blue-300 dark:bg-blue-700" :style="{ width: `${covers ? Math.round((dish.count / covers) * 100) : 0}%` }"></span>
                </span>
                <b class="text-right text-slate-900 dark:text-white">{{ dish.count }}</b>
            </li>
        </ul>
    </Card>
</template>
