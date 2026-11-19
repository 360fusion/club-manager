<script setup>
// How a notice shows on a member's phone: the lodge's name, the title and the start of the message.
defineProps({
  clubName: { type: String, default: '' },
  title: { type: String, default: '' },
  body: { type: String, default: '' },
});
</script>

<template>
  <div class="flex gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50">
    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-900 text-amber-400 dark:bg-slate-950" aria-hidden="true">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18M5 21V10M19 21V10M9.5 21V10M14.5 21V10M2 10l10-6 10 6z" /></svg>
    </div>
    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-2">
        <span class="min-w-0 flex-1 truncate text-[13px] font-semibold text-slate-900 dark:text-white">{{ clubName }}</span>
        <span class="text-xs text-slate-500 dark:text-slate-400">now</span>
      </div>
      <div class="truncate text-[13px] leading-[18px] text-slate-900 dark:text-white">{{ title || 'Your title' }}</div>
      <div class="line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ body || 'Your message' }}</div>
    </div>
  </div>
</template>
