<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
  message: String,
});
</script>

<template>
  <Head title="RSVP Link Expired" />

  <div class="min-h-screen bg-slate-100 dark:bg-slate-950 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full mx-auto bg-white dark:bg-slate-900 rounded-3xl p-8 shadow-xl border border-slate-200 dark:border-slate-800 text-center space-y-4">
      <div class="w-16 h-16 bg-rose-50 dark:bg-rose-950/40 text-rose-500 rounded-2xl flex items-center justify-center text-3xl mx-auto">⚠️</div>
      <h1 class="text-xl font-bold text-slate-900 dark:text-white">RSVP Link Expired or Invalid</h1>
      <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">{{ message || 'This summons RSVP token is no longer valid. Please contact your Secretary for an updated access link.' }}</p>
    </div>
  </div>
</template>
