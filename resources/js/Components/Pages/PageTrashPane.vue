<script setup>
// Recently deleted custom pages of the website builder, with restore and delete-forever.
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
    club: { type: [Object, Array], default: undefined },
    trashedPages: { type: [Object, Array], default: undefined },
    restorePage: { type: Function, default: undefined },
    forceDeletePage: { type: Function, default: undefined }
});
</script>

<template>
<Card v-if="trashedPages.length" padding="none" class="overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-800">
        <h2 class="flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-white"><UiIcon name="trash" class="text-slate-400" /> Trash</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Deleted pages stay here until restored or removed permanently.</p>
    </div>
    <div class="divide-y divide-slate-200 dark:divide-slate-800">
        <div v-for="tp in trashedPages" :key="tp.id" class="p-4 flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-medium text-slate-900 dark:text-white truncate">{{ tp.title }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono truncate">/site/{{ club.slug }}/{{ tp.slug }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <Button variant="secondary" size="sm" @click="restorePage(tp)">
                    <template #icon><UiIcon name="restore" /></template>
                    Restore
                </Button>
                <Button variant="danger" size="sm" @click="forceDeletePage(tp)">Delete forever</Button>
            </div>
        </div>
    </div>
</Card>
</template>
