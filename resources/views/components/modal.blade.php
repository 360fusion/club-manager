{{-- The Blade twin of Components/Ui/Modal.vue for Livewire views: shown while :show is true; `close` names the
     component method that closes it (run by the close button, Escape and a click on the backdrop). Keyboard focus stays
     inside it while open (Alpine's x-trap, bundled with Livewire). --}}
@props(['show' => false, 'title', 'subtitle' => null, 'size' => 'md', 'close'])

@php
    $sizes = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-2xl', 'xl' => 'max-w-4xl'];
    $titleId = 'modal-'.\Illuminate\Support\Str::slug($title);
@endphp

@if ($show)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
        wire:mousedown.self="{{ $close }}"
        x-data
        x-on:keydown.escape.window="$wire.{{ $close }}()"
    >
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $titleId }}"
            tabindex="-1"
            x-trap.noscroll="true"
            {{ $attributes->class(['max-h-[calc(100vh-2rem)] w-full overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl outline-none dark:border-slate-800 dark:bg-slate-900', $sizes[$size] ?? $sizes['md']]) }}
        >
            <div class="mb-4 flex items-start justify-between gap-4 border-b border-slate-200 pb-4 dark:border-slate-800">
                <div class="min-w-0">
                    <h2 id="{{ $titleId }}" class="text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
                    @endif
                </div>
                <button type="button" wire:click="{{ $close }}" aria-label="Close" class="shrink-0 rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300 cursor-pointer">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
            </div>

            {{ $slot }}

            @isset($footer)
                <div class="mt-4 flex items-center justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
@endif
