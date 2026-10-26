<script setup>
import { Head } from '@inertiajs/vue3';
import Card from '@/Components/Ui/Card.vue';

defineProps({
  message: String,
});
</script>

<template>
  <Head title="RSVP link expired" />

  <div class="min-h-screen bg-slate-100 dark:bg-slate-950 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
    <Card padding="lg" class="max-w-md w-full mx-auto text-center space-y-3">
      <h1 class="text-xl font-bold text-slate-900 dark:text-white">RSVP link expired or invalid</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">{{ message || 'This summons RSVP link is no longer valid. Please contact your Secretary for an updated link.' }}</p>
    </Card>
  </div>
</template>
