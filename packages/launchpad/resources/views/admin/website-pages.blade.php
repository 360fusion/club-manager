<x-filament-panels::page>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left"><th class="p-2">Page</th><th class="p-2">Text</th><th class="p-2">Status</th><th class="p-2">Missing</th><th class="p-2"></th></tr></thead>
            <tbody>
            @foreach ($this->rows() as $row)
                <tr class="border-t">
                    <td class="p-2"><a class="underline" href="{{ url('/'.$row['slug']) }}" target="_blank" rel="noopener">{{ $row['title'] }}</a> @unless ($row['on'])<span class="opacity-60">(off)</span>@endunless</td>
                    <td class="p-2">{{ $row['source'] }}</td>
                    <td class="p-2">
                        @if ($row['status'] === 'reviewed')
                            Reviewed @if ($row['review']) by {{ $row['review']['by'] ?? 'staff' }}, {{ \Illuminate\Support\Carbon::parse($row['review']['at'])->translatedFormat('j M Y') }}@endif
                        @elseif ($row['status'] === 'template')
                            <strong>Template</strong>
                        @else
                            n/a
                        @endif
                    </td>
                    <td class="p-2">{{ $row['missing'] }}</td>
                    <td class="p-2">
                        @if ($row['status'] === 'template')
                            <button type="button" class="underline" wire:click="markReviewed('{{ $row['slug'] }}')" wire:confirm="Mark this page as read and approved?">Mark reviewed</button>
                        @elseif ($row['status'] === 'reviewed')
                            <button type="button" class="underline" wire:click="markTemplate('{{ $row['slug'] }}')">Back to template</button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
