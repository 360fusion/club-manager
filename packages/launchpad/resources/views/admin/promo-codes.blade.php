<x-filament-panels::page>
    <form wire:submit="mountAction('add')">
        {{ $this->form }}

        <div class="mt-4">
            {{ $this->addAction }}
        </div>
    </form>

    @if ($error)
        <p class="text-danger-600">{{ $error }}</p>
    @elseif ($codes === [])
        <p>No promo codes yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-start text-sm">
                <thead>
                    <tr><th class="p-2 text-start">Code</th><th class="p-2 text-start">Discount</th><th class="p-2 text-start">Used</th><th class="p-2 text-start">Stops</th><th class="p-2 text-start">State</th><th class="p-2"></th></tr>
                </thead>
                <tbody>
                @foreach ($codes as $code)
                    <tr wire:key="promo-{{ $code['id'] }}">
                        <td class="p-2"><strong>{{ $code['code'] }}</strong></td>
                        <td class="p-2">{{ $code['discount'] }}</td>
                        <td class="p-2">{{ $code['used'] }}</td>
                        <td class="p-2">{{ $code['expires'] }}</td>
                        <td class="p-2">{{ $code['active'] ? 'On' : 'Off' }}</td>
                        <td class="p-2">@if ($code['active'])<button type="button" class="text-danger-600 underline" wire:click="deactivate('{{ $code['id'] }}')" wire:confirm="Turn off {{ $code['code'] }}? Nobody can use it after this.">Turn off</button>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
