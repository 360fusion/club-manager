{{-- The Blade twin of Components/Ui/Alert.vue. `dismiss` names a Livewire method that clears the notice; leave it out
     for a notice that stays. --}}
@props(['variant' => 'info', 'title' => null, 'dismiss' => null])

@php
    $variants = [
        'info' => 'bg-blue-50 border-blue-200 text-blue-900 dark:bg-blue-950/40 dark:border-blue-800/60 dark:text-blue-200',
        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-900 dark:bg-emerald-950/40 dark:border-emerald-800/50 dark:text-emerald-200',
        'warning' => 'bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-950/30 dark:border-amber-800/40 dark:text-amber-200',
        'danger' => 'bg-rose-50 border-rose-200 text-rose-900 dark:bg-rose-950/40 dark:border-rose-800/50 dark:text-rose-200',
    ];
@endphp

<div role="status" {{ $attributes->class(['flex items-start justify-between gap-3 rounded-xl border px-4 py-3 text-sm', $variants[$variant] ?? $variants['info']]) }}>
    <div class="min-w-0">
        @if ($title)
            <p class="font-medium mb-0.5">{{ $title }}</p>
        @endif
        <div class="text-[13px] leading-relaxed opacity-90 break-words">{{ $slot }}</div>
    </div>
    @if ($dismiss)
        <button type="button" wire:click="{{ $dismiss }}" aria-label="Dismiss" class="shrink-0 rounded-lg p-0.5 opacity-60 hover:opacity-100 cursor-pointer">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
    @endif
</div>
