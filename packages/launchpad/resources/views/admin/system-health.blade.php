<x-filament-panels::page>
    <x-filament::section heading="Checks" description="The same checks as php artisan launchpad:doctor. Anything marked Fix stops a live site being ready.">
        @foreach ($this->checks() as $check)
            <div style="display:grid;grid-template-columns:5rem minmax(10rem,18rem) 1fr;gap:1rem;align-items:start;padding:.6rem 0;border-top:1px solid rgb(128 128 128 / .2)">
                <div><x-filament::badge :color="$check['ok'] ? 'success' : 'danger'">{{ $check['ok'] ? 'OK' : 'Fix' }}</x-filament::badge></div>
                <div style="font-weight:600">{{ $check['name'] }}</div>
                <div style="opacity:.75">{{ $check['detail'] }}</div>
            </div>
        @endforeach
        @unless ($remote)
            <div style="margin-top:1rem"><x-filament::button wire:click="checkStripe">Also check the Stripe account</x-filament::button></div>
        @endunless
    </x-filament::section>
    <x-filament::section heading="This server">
        @foreach ($this->environment() as $label => $value)
            <div style="display:flex;justify-content:space-between;gap:1rem;padding:.5rem 0;border-top:1px solid rgb(128 128 128 / .2)"><span style="opacity:.75">{{ $label }}</span><span>{{ $value }}</span></div>
        @endforeach
    </x-filament::section>
</x-filament-panels::page>
