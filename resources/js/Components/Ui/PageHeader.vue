<script setup>
// The top of a page: breadcrumbs ([{ label, href }], the last one is this page), the title, one line on what the page is
// for, and room for a status badge beside the title and actions on the right.
import { Link } from '@inertiajs/vue3';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    breadcrumbs: { type: Array, default: () => [] },
});
</script>

<template>
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <nav v-if="breadcrumbs.length" aria-label="Breadcrumb" class="mb-1 flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                <template v-for="(crumb, index) in breadcrumbs" :key="crumb.label">
                    <span v-if="index > 0" aria-hidden="true">/</span>
                    <Link v-if="crumb.href && index < breadcrumbs.length - 1" :href="crumb.href" class="hover:text-slate-900 dark:hover:text-white">{{ crumb.label }}</Link>
                    <span v-else class="text-slate-900 dark:text-white" aria-current="page">{{ crumb.label }}</span>
                </template>
            </nav>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ title }}</h1>
                <slot name="badge" />
            </div>
            <p v-if="description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
