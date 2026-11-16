<x-filament-panels::page>
    @if ($this->languages())
        <label class="text-sm flex items-center gap-2">
            <span>{{ __('Language') }}</span>
            <select wire:model.live="locale" class="rounded border-gray-300 text-sm">
                @foreach ($this->languages() as $code => $name)
                    <option value="{{ $code }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
    @endif

    <div class="grid gap-6 lg:grid-cols-4">
        <ul class="text-sm space-y-1">
            @foreach ($this->rows() as $row)
                <li>
                    <button type="button" class="underline text-left {{ $row['key'] === $this->key ? 'font-bold' : '' }}" wire:click="open('{{ $row['key'] }}')">{{ $row['label'] }}</button>
                    <span class="opacity-60">{{ $row['edited'] ? 'edited' : 'default' }}</span>
                </li>
            @endforeach
        </ul>

        <div class="lg:col-span-3 space-y-4">
            <form wire:submit="save" class="space-y-4">
                {{ $this->form }}

                <div class="flex flex-wrap gap-2">
                    <x-filament::button type="submit">Save</x-filament::button>
                    <x-filament::button type="button" color="gray" wire:click="preview">Preview</x-filament::button>
                    <x-filament::button type="button" color="gray" wire:click="sendTest">Send me a test</x-filament::button>
                    <x-filament::button type="button" color="danger" outlined wire:click="resetToDefault" wire:confirm="Throw away the edited wording and use the default?">Reset to default</x-filament::button>
                </div>
            </form>

            @if ($previewHtml)
                <div>
                    <p class="text-sm"><strong>Subject:</strong> {{ $previewSubject }}</p>
                    <iframe class="w-full h-[40rem] border rounded bg-white" sandbox srcdoc="{{ $previewHtml }}" title="Preview"></iframe>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
