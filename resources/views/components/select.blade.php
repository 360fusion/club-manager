{{-- The Blade twin of Components/Ui/Select.vue (options go in the slot). The error shows by itself for the field named in wire:model; pass :error to override. --}}
@props(['label' => null, 'hint' => null, 'error' => null, 'required' => false])

@php
    $model = $attributes->whereStartsWith('wire:model')->first();
    $id = $attributes->get('id') ?? 'field-'.str_replace('.', '-', $model ?? \Illuminate\Support\Str::slug((string) $label));
    $error ??= $model ? $errors->first($model) : null;
@endphp

<div class="space-y-1.5">
    @if ($label)
        <label for="{{ $id }}" class="block text-xs font-medium text-slate-700 dark:text-slate-300">
            {{ $label }}
            @if ($required)<span class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <select
        id="{{ $id }}"
        @if ($required) required @endif
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->except('id')->class([
            'w-full rounded-xl border px-3 py-2 text-sm transition-colors',
            'bg-white text-slate-900 dark:bg-slate-950 dark:text-white',
            'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
            'disabled:opacity-50 disabled:cursor-not-allowed',
            $error ? 'border-rose-400 dark:border-rose-700' : 'border-slate-300 dark:border-slate-700',
        ]) }}
    >{{ $slot }}</select>

    @if ($error)
        <p id="{{ $id }}-error" class="text-xs text-rose-600 dark:text-rose-400">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
    @endif
</div>
