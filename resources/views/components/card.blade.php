{{-- The Blade twin of Components/Ui/Card.vue. --}}
@props(['padding' => 'md'])

@php
    $paddings = ['none' => '', 'sm' => 'p-4', 'md' => 'p-6', 'lg' => 'p-6 sm:p-8'];
@endphp

<div {{ $attributes->class(['bg-white border border-slate-200 rounded-2xl shadow-sm dark:bg-slate-900 dark:border-slate-800', $paddings[$padding] ?? $paddings['md']]) }}>
    @isset($header)
        <div class="mb-4 pb-4 border-b border-slate-200 dark:border-slate-800">{{ $header }}</div>
    @endisset

    {{ $slot }}

    @isset($footer)
        <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-800">{{ $footer }}</div>
    @endisset
</div>
