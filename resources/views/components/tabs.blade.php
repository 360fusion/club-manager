{{-- The Blade twin of Components/Ui/Tabs.vue: keep the classes in step. Items are ['key' => ..., 'label' => ..., 'href' => ...]. --}}
@props(['items', 'active' => null, 'label' => 'Sections'])

<nav aria-label="{{ $label }}" {{ $attributes->class(['max-w-full sm:overflow-x-auto']) }}>
    <div class="flex flex-col divide-y divide-slate-300/70 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 sm:w-fit sm:flex-row sm:items-center sm:gap-1 sm:divide-y-0 dark:divide-slate-700/70 dark:border-slate-700/60 dark:bg-slate-800/80">
        @foreach ($items as $item)
            <a
                href="{{ $item['href'] }}"
                @if ($item['key'] === $active) aria-current="page" @endif
                @class([
                    'flex w-full items-center rounded-xl px-4 py-3 text-sm font-semibold transition-colors sm:inline-flex sm:w-auto sm:whitespace-nowrap sm:py-2 sm:text-xs',
                    'bg-slate-900 text-white shadow-sm dark:bg-slate-700' => $item['key'] === $active,
                    'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white' => $item['key'] !== $active,
                ])
            >{{ $item['label'] }}</a>
        @endforeach
    </div>
</nav>
