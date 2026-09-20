<script setup>
import MembersLayout from '@/Layouts/MembersLayout.vue';
import ClubDirectory from '@/Components/ClubDirectory.vue';

defineProps({
    directory: { type: Object, required: true },
});
</script>

<template>
    <MembersLayout title="Directory" active-tab="directory">
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Find a club</h1>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Search every lodge and club, and subscribe to their public bulletins.</p>
            </div>
            <ClubDirectory :directory="directory" />
        </div>
    </MembersLayout>
</template>
