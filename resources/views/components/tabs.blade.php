{{-- The Blade twin of Components/Ui/Tabs.vue: keep the classes in step. Items are ['key' => ..., 'label' => ..., 'href' => ...]. --}}
@props(['items', 'active' => null, 'label' => 'Sections'])

<nav aria-label="{{ $label }}" {{ $attributes->class(['max-w-full overflow-x-auto']) }}>
    <div class="flex w-fit items-center gap-1 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 dark:border-slate-700/60 dark:bg-slate-800/80">
        @foreach ($items as $item)
            <a
                href="{{ $item['href'] }}"
                @if ($item['key'] === $active) aria-current="page" @endif
                @class([
                    'inline-flex items-center rounded-xl px-4 py-2 text-xs font-semibold whitespace-nowrap transition-colors',
                    'bg-slate-900 text-white shadow-sm dark:bg-slate-700' => $item['key'] === $active,
                    'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white' => $item['key'] !== $active,
                ])
            >{{ $item['label'] }}</a>
        @endforeach
    </div>
</nav>
