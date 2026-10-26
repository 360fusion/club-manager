{{-- The Blade twin of Components/Ui/Button.vue: keep the classes in step. Extra attributes (wire:click, wire:loading...) pass through. --}}
@props(['variant' => 'primary', 'size' => 'md', 'type' => 'button', 'href' => null, 'block' => false])

@php
    $variants = [
        'primary' => 'bg-blue-600 text-white hover:bg-blue-500 border border-transparent',
        'secondary' => 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 hover:border-slate-400 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700 dark:hover:bg-slate-800',
        'ghost' => 'bg-transparent text-slate-600 border border-transparent hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-500 border border-transparent',
    ];
    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs gap-1.5',
        'md' => 'px-4 py-2 text-sm gap-2',
        'lg' => 'px-5 py-2.5 text-sm gap-2',
    ];
    $classes = implode(' ', [
        'inline-flex items-center justify-center rounded-xl font-medium transition-colors cursor-pointer',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950',
        'disabled:opacity-50 disabled:cursor-not-allowed',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
        $block ? 'w-full' : '',
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
