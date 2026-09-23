@if($notice !== '')
    <div
        role="status"
        class="p-4 rounded-2xl border text-xs font-bold flex items-start justify-between gap-3 shadow-sm {{ $noticeType === 'error' ? 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-200' : 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-200' }}"
    >
        <span class="break-words min-w-0">{{ $notice }}</span>
        <button type="button" wire:click="dismissNotice" aria-label="Dismiss" class="shrink-0 opacity-60 hover:opacity-100 cursor-pointer">✕</button>
    </div>
@endif
