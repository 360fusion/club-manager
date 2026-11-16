<x-filament-panels::page>
    <form wire:submit="mountAction('save')">
        {{ $this->form }}

        <div class="mt-4">
            {{ $this->saveAction }}
        </div>
    </form>
</x-filament-panels::page>
