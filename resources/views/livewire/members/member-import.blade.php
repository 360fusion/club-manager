@php
    $steps = ['upload' => 'Upload', 'match' => 'Match columns', 'review' => 'Review', 'done' => 'Done'];
    $stepKeys = array_keys($steps);
    $stepIndex = array_search($step, $stepKeys, true);
    $actionLabels = ['skip' => 'Skip', 'fill' => 'Update blank fields only', 'overwrite' => 'Overwrite with file values', 'create' => 'Import as new'];
    $statusStyles = [
        'new' => ['New', 'success'],
        'duplicate' => ['Duplicate', 'warning'],
        'possible_duplicate' => ['Possible duplicate', 'info'],
        'error' => ['Error', 'danger'],
    ];
    $matchText = [
        'email' => 'Same email as',
        'grand_lodge_number' => 'Same Hermes ID as',
        'name' => 'Same name as',
        'name_ambiguous' => 'Same name as several members',
        'email_ambiguous' => 'This email belongs to several members',
        'id_ambiguous' => 'This Hermes ID belongs to several members',
        'conflict' => 'Email and Hermes ID belong to different members',
    ];
    $fieldClass = 'rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
@endphp

<div class="space-y-6">
    @include('livewire.members._notice')

    <x-card>
        <div class="space-y-6">
            <div class="space-y-1">
                <a href="{{ route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">&larr; Back to the member directory</a>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Import members</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Add or update members from a spreadsheet. You will match the columns and settle any duplicates before anything is saved.</p>
            </div>

            <ol class="grid grid-cols-2 gap-2 text-sm font-medium md:grid-cols-4" aria-label="Import steps">
                @foreach($steps as $key => $label)
                    @php $i = array_search($key, $stepKeys, true); @endphp
                    <li class="flex items-center gap-2 rounded-xl border px-3 py-2.5 {{ $i === $stepIndex ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-700 dark:bg-slate-700' : ($i < $stepIndex ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-200' : 'border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400') }}" @if($i === $stepIndex) aria-current="step" @endif>
                        <span class="flex h-5 w-5 items-center justify-center rounded-full text-xs font-semibold {{ $i === $stepIndex ? 'bg-white/20' : 'bg-white/60 dark:bg-slate-900/40' }}" aria-hidden="true">{{ $i < $stepIndex ? '✓' : $i + 1 }}</span>
                        <span>{{ $label }}@if($i < $stepIndex)<span class="sr-only"> (done)</span>@endif</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </x-card>

    {{-- ============ Step 1: upload ============ --}}
    @if($step === 'upload')
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card class="space-y-5 lg:col-span-2">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">1. Upload your spreadsheet</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A CSV file, up to 5 MB and 5,000 rows. In Excel or Google Sheets use File, then Save as or Download, then CSV.</p>
                    </div>
                    <x-button variant="secondary" size="sm" class="shrink-0" wire:click="downloadTemplate">Download template</x-button>
                </div>

                <label
                    x-data="{ over: false }"
                    x-on:dragover.prevent="over = true"
                    x-on:dragleave.prevent="over = false"
                    x-on:drop.prevent="over = false; $refs.picker.files = $event.dataTransfer.files; $refs.picker.dispatchEvent(new Event('change', { bubbles: true }))"
                    :class="over ? 'border-blue-500 bg-blue-50/60 dark:bg-blue-950/30' : 'border-slate-300 dark:border-slate-700'"
                    class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed px-6 py-12 text-center transition-colors"
                >
                    <svg class="h-8 w-8 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.63a3.38 3.38 0 0 0-3.38-3.37h-1.5A1.13 1.13 0 0 1 13.5 7.13v-1.5A3.38 3.38 0 0 0 10.13 2.25H8.25m2.25 0H5.63c-.62 0-1.13.5-1.13 1.13v17.25c0 .62.5 1.12 1.13 1.12h12.75c.62 0 1.12-.5 1.12-1.12V11.25a9 9 0 0 0-9-9Z" /></svg>
                    <span class="text-sm font-semibold text-slate-900 dark:text-white">Drop a CSV file here, or click to choose one</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">The file is kept privately and deleted once the import is finished.</span>
                    <input x-ref="picker" type="file" wire:model="file" accept=".csv,.txt,text/csv" class="sr-only" />
                </label>

                <div wire:loading wire:target="file">
                    <x-notice>Reading your file…</x-notice>
                </div>

                @error('file')
                    <x-notice variant="danger" role="alert">{{ $message }}</x-notice>
                @enderror
            </x-card>

            <x-card class="space-y-4 text-sm text-slate-600 dark:text-slate-300">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">How it works</h3>
                <ol class="list-inside list-decimal space-y-3 marker:font-semibold marker:text-slate-400">
                    <li><strong>Upload</strong> a file. The template has the same columns as the directory export, so an exported file can be edited and imported back.</li>
                    <li><strong>Match columns.</strong> Your headings are matched for you. Change any that are wrong, and save the matching for next time.</li>
                    <li><strong>Review.</strong> Rows already on the roster are flagged as duplicates, and you choose what happens to each.</li>
                    <li><strong>Import,</strong> then undo it from the history below if it was a mistake.</li>
                </ol>
                <div class="space-y-1.5 border-t border-slate-100 pt-3 dark:border-slate-800">
                    <p><strong>Needed:</strong> a first and last name (or one Name column).</p>
                    <p><strong>Dates:</strong> 31/12/2020, 2020-12-31 or 31 Dec 2020.</p>
                    <p><strong>Status:</strong> Active, Honorary, Resigned, Deceased and so on.</p>
                    <p><strong>Office:</strong> for example Secretary, WM, Senior Warden. Blank means Member.</p>
                    <p class="text-amber-700 dark:text-amber-300"><strong>Delete the example row</strong> in the template before you upload it.</p>
                </div>
            </x-card>
        </div>
    @endif

    {{-- ============ Step 2: match columns ============ --}}
    @if($step === 'match' && $import)
        <x-card class="space-y-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">2. Match your columns</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><strong>{{ $import->filename }}</strong> has {{ number_format($import->option('row_count', 0)) }} {{ \Illuminate\Support\Str::plural('row', $import->option('row_count', 0)) }} and {{ count($import->headers ?? []) }} columns. Choose what each column holds.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <x-button variant="secondary" size="sm" wire:click="autoMatch">Match automatically</x-button>
                    @if($savedMappings->isNotEmpty())
                        <select x-data x-on:change="if ($event.target.value) { $wire.loadSavedMapping(parseInt($event.target.value)); } $event.target.value = ''" aria-label="Load a saved matching" class="{{ $fieldClass }}">
                            <option value="">Load a saved matching…</option>
                            @foreach($savedMappings as $saved)
                                <option value="{{ $saved->id }}">{{ $saved->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>

            @if($import->option('already_imported_at'))
                <x-notice variant="warning">
                    This exact file was already imported on {{ \Illuminate\Support\Carbon::parse($import->option('already_imported_at'))->format('j M Y') }}. Anything already on the roster will show as a duplicate.
                </x-notice>
            @endif

            @if($mappingProblem)
                <x-notice variant="danger" role="alert">{{ $mappingProblem }}</x-notice>
            @endif

            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs font-medium text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <th class="px-4 py-3">Column in your file</th>
                            <th class="px-4 py-3">Examples</th>
                            <th class="px-4 py-3">Import it as</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($import->headers ?? [] as $index => $header)
                            @php $chosen = $mapping[$index] ?? ''; @endphp
                            <tr wire:key="map-{{ $index }}">
                                <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $header }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
                                    @forelse(($import->option('samples', [])[$index] ?? []) as $sample)
                                        <span class="mr-1.5 inline-block max-w-[12rem] truncate rounded bg-slate-100 px-1.5 py-0.5 align-bottom dark:bg-slate-800">{{ $sample }}</span>
                                    @empty
                                        <span class="text-slate-400">No values</span>
                                    @endforelse
                                </td>
                                <td class="px-4 py-3">
                                    <select wire:model.live="mapping.{{ $index }}" aria-label="What is {{ $header }}?" class="{{ $fieldClass }} w-full max-w-xs {{ $chosen === '' ? 'text-slate-400 dark:text-slate-500' : '' }}">
                                        <option value="">Do not import</option>
                                        @foreach($targets as $key => $label)
                                            <option value="{{ $key }}" @selected($chosen === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 gap-4 pt-1 md:grid-cols-2">
                <x-select id="import-date-order" label="Dates in your file are written" wire:model="dateOrder">
                    @foreach($dateOrders as $key => $label)
                        <option value="{{ $key }}" @selected($dateOrder === $key)>{{ $label }}</option>
                    @endforeach
                </x-select>
                <x-input id="import-mapping-name" label="Save this matching as (optional)" wire:model="mappingName" maxlength="100" placeholder="e.g. Hermes export" />
            </div>

            @if($savedMappings->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-medium">Saved:</span>
                    @foreach($savedMappings as $saved)
                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2 py-1 dark:bg-slate-800">
                            {{ $saved->name }}
                            <button type="button" wire:click="deleteSavedMapping({{ $saved->id }})" wire:confirm="Remove the saved matching '{{ $saved->name }}'?" aria-label="Remove {{ $saved->name }}" class="cursor-pointer text-slate-400 hover:text-rose-600">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                            </button>
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center justify-between border-t border-slate-100 pt-4 dark:border-slate-800">
                <x-button variant="ghost" wire:click="startOver" wire:confirm="Discard this file and start again?">Start over</x-button>
                <x-button wire:click="reviewRows" wire:loading.attr="disabled" wire:target="reviewRows" :disabled="(bool) $mappingProblem">
                    <span wire:loading.remove wire:target="reviewRows">Continue to review</span>
                    <span wire:loading wire:target="reviewRows">Checking rows…</span>
                </x-button>
            </div>
        </x-card>
    @endif

    {{-- ============ Step 3: review ============ --}}
    @if($step === 'review' && $import)
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            @foreach([['all', 'All rows', $import->total_rows, 'border-slate-200 bg-slate-50 text-slate-900 dark:border-slate-800 dark:bg-slate-800/50 dark:text-white'], ['new', 'New', $import->new_count, 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-100'], ['duplicate', 'Duplicates', $import->duplicate_count, 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-100'], ['possible_duplicate', 'Possible duplicates', $import->possible_count, 'border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-800/60 dark:bg-blue-950/40 dark:text-blue-100'], ['error', 'Errors', $import->error_count, 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-100']] as [$key, $label, $count, $tileClass])
                <button type="button" wire:click="$set('tab', '{{ $key }}')" class="cursor-pointer space-y-1 rounded-xl border p-4 text-left transition {{ $tileClass }} {{ $tab === $key ? 'ring-2 ring-blue-500' : '' }}" aria-pressed="{{ $tab === $key ? 'true' : 'false' }}">
                    <span class="text-xs font-medium opacity-80">{{ $label }}</span>
                    <span class="block text-2xl font-bold">{{ number_format($count) }}</span>
                </button>
            @endforeach
        </div>

        <x-card padding="none" class="overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-5 text-sm lg:flex-row lg:items-center lg:justify-between dark:border-slate-800">
                <p class="max-w-2xl text-slate-600 dark:text-slate-300">
                    Rows that match someone already on the roster are skipped unless you choose otherwise. A <strong>possible</strong> duplicate only shares a name, so check it before updating anyone.
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    @if(in_array($tab, ['duplicate', 'possible_duplicate'], true))
                        <label for="apply-to-tab" class="font-medium text-slate-500 dark:text-slate-400">Set every row in this list to</label>
                        <select id="apply-to-tab" x-data x-on:change="if ($event.target.value) { $wire.applyToTab($event.target.value); } $event.target.value = ''" class="{{ $fieldClass }}">
                            <option value="">Choose…</option>
                            @foreach($actionLabels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                    @if($hasFileDuplicates)
                        <label for="file-duplicates" class="font-medium text-slate-500 dark:text-slate-400">Repeated in the file:</label>
                        <select id="file-duplicates" wire:model.live="fileDuplicates" class="{{ $fieldClass }}">
                            <option value="keep_first" @selected($fileDuplicates === 'keep_first')>Keep the first</option>
                            <option value="keep_last" @selected($fileDuplicates === 'keep_last')>Keep the last</option>
                            <option value="keep_both" @selected($fileDuplicates === 'keep_both')>Import both</option>
                        </select>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-xs font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                            <th class="w-14 py-3 pl-6 pr-2">Row</th>
                            <th class="px-4 py-3">Member</th>
                            <th class="px-4 py-3">Result</th>
                            <th class="px-4 py-3">What to do</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($rows as $row)
                            @php
                                $data = $row->data ?? [];
                                [$statusLabel, $statusVariant] = $statusStyles[$row->status];
                                $matched = $row->match_member_id ? ($matchedMembers[$row->match_member_id] ?? null) : null;
                                $allowed = match (true) {
                                    $row->status === 'error' => [],
                                    $row->status === 'new' => ['create', 'skip'],
                                    (bool) $row->match_member_id => ['skip', 'fill', 'overwrite', 'create'],
                                    default => ['skip', 'create'],
                                };
                            @endphp
                            <tr wire:key="row-{{ $row->id }}" class="align-top hover:bg-slate-50/70 dark:hover:bg-slate-800/70">
                                <td class="py-4 pl-6 pr-2 font-medium text-slate-400">{{ $row->row_number }}</td>
                                <td class="px-4 py-4">
                                    <span class="block font-semibold text-slate-900 dark:text-white">{{ trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: 'No name' }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $data['email'] ?? 'No email' }}</span>
                                </td>
                                <td class="space-y-1 px-4 py-4">
                                    <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
                                    @if($row->status === 'error')
                                        @foreach($row->errors ?? [] as $error)
                                            <span class="block text-xs text-rose-700 dark:text-rose-300">{{ $error }}</span>
                                        @endforeach
                                    @elseif($row->match_type === 'file')
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">Repeats row {{ $row->duplicate_of_row }} in this file</span>
                                    @elseif($row->match_type)
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ ($matchText[$row->match_type] ?? '').($matched ? ' '.$matched->full_name : '').($row->duplicate_of_row ? ', and repeats row '.$row->duplicate_of_row : '') }}</span>
                                    @endif
                                    @foreach($row->warnings ?? [] as $warning)
                                        <span class="block text-xs text-amber-700 dark:text-amber-300">{{ $warning }}</span>
                                    @endforeach
                                    @if($matched)
                                        <button type="button" wire:click="toggleRow({{ $row->id }})" class="cursor-pointer text-xs font-medium text-blue-600 hover:underline dark:text-blue-400" aria-expanded="{{ $expandedRow === $row->id ? 'true' : 'false' }}">{{ $expandedRow === $row->id ? 'Hide' : 'Compare' }} details</button>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    @if($allowed === [])
                                        <span class="text-slate-400">Not imported</span>
                                    @else
                                        <select wire:change="setRowAction({{ $row->id }}, $event.target.value)" aria-label="What to do with row {{ $row->row_number }}" class="{{ $fieldClass }}">
                                            @foreach($allowed as $option)
                                                <option value="{{ $option }}" @selected($row->action === $option)>{{ $row->status === 'new' && $option === 'create' ? 'Add to roster' : $actionLabels[$option] }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </td>
                            </tr>

                            @if($expanded && $expanded['row']->id === $row->id && $expanded['member'])
                                <tr wire:key="detail-{{ $row->id }}" class="bg-slate-50/70 dark:bg-slate-800/40">
                                    <td></td>
                                    <td colspan="3" class="px-4 py-4">
                                        <table class="w-full max-w-3xl text-xs">
                                            <thead>
                                                <tr class="text-left font-medium text-slate-400">
                                                    <th class="py-1 pr-4">Field</th>
                                                    <th class="py-1 pr-4">On the roster now</th>
                                                    <th class="py-1">In your file</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($data as $field => $incoming)
                                                    @php $existing = \App\Domains\ClubAccounting\Services\MemberImport\MemberImportFields::valueOf($expanded['member'], $field); @endphp
                                                    <tr class="{{ $existing !== $incoming ? 'font-semibold text-amber-800 dark:text-amber-200' : 'text-slate-500 dark:text-slate-400' }}">
                                                        <td class="py-1 pr-4">{{ \App\Domains\ClubAccounting\Services\MemberImport\MemberImportFields::label($field) }}</td>
                                                        <td class="py-1 pr-4">{{ filled($existing) ? $existing : '—' }}</td>
                                                        <td class="py-1">{{ $incoming }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="4" class="py-12 text-center">
                                    <p class="text-base font-semibold text-slate-900 dark:text-white">Nothing in this list</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                {{ $rows->links() }}
            </div>
        </x-card>

        <x-card>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2 text-sm">
                    <p class="text-base font-semibold text-slate-900 dark:text-white">Ready to import</p>
                    <p class="text-slate-600 dark:text-slate-300">
                        <strong>{{ number_format($plan['create'] ?? 0) }}</strong> will be added,
                        <strong>{{ number_format($plan['update'] ?? 0) }}</strong> updated and
                        <strong>{{ number_format($plan['skip'] ?? 0) }}</strong> skipped @if(($plan['error'] ?? 0) > 0), and <strong class="text-rose-700 dark:text-rose-300">{{ number_format($plan['error']) }}</strong> have errors and will not be imported @endif.
                    </p>
                    <label class="flex items-start gap-2 text-slate-600 dark:text-slate-300">
                        <input type="checkbox" wire:model="invite" class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600" />
                        <span>Email an invitation to set up an online account to each <strong>new</strong> member who has an email address.</span>
                    </label>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($import->error_count > 0)
                        <x-button variant="secondary" wire:click="downloadErrors">Download rows with errors</x-button>
                    @endif
                    <x-button variant="ghost" wire:click="backToMatch">Back to columns</x-button>
                    <x-button wire:click="runImport" wire:loading.attr="disabled" wire:target="runImport" wire:confirm="Import these rows into the member roster?">
                        <span wire:loading.remove wire:target="runImport">Import members</span>
                        <span wire:loading wire:target="runImport">Importing…</span>
                    </x-button>
                </div>
            </div>
        </x-card>
    @endif

    {{-- ============ Step 4: done ============ --}}
    @if($step === 'done' && $import)
        @php $result = $import->result ?? []; @endphp
        <x-card class="space-y-5">
            <div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ $import->status === 'undone' ? 'This import was undone' : 'Import complete' }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $import->filename }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach([['Added', $result['created'] ?? 0], ['Updated', $result['updated'] ?? 0], ['Skipped', $result['skipped'] ?? 0], ['Errors', $result['errors'] ?? 0]] as [$label, $count])
                    <div class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $count }}</p>
                    </div>
                @endforeach
            </div>

            @if(($result['invited'] ?? 0) > 0 || ! empty($result['invite_skipped']))
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    {{ $result['invited'] ?? 0 }} {{ \Illuminate\Support\Str::plural('invitation', $result['invited'] ?? 0) }} emailed.
                    @foreach($result['invite_skipped'] ?? [] as $reason => $count) {{ $count }} not invited ({{ strtolower($reason) }}). @endforeach
                </p>
            @endif

            <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                <x-button href="{{ route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}">View the directory</x-button>
                @if(($result['errors'] ?? 0) > 0)
                    <x-button variant="secondary" wire:click="downloadErrors">Download rows with errors</x-button>
                @endif
                @if($import->status === 'imported')
                    <x-button variant="secondary" wire:click="undoImport({{ $import->id }})" wire:confirm="Undo this import? Members it added are removed and fields it changed are put back.">Undo this import</x-button>
                @endif
                <x-button variant="ghost" wire:click="startOver">Import another file</x-button>
            </div>
        </x-card>
    @endif

    {{-- ============ History ============ --}}
    @if(in_array($step, ['upload', 'done'], true) && $history->isNotEmpty())
        <x-card padding="none" class="overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Recent imports</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-xs font-medium text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                            <th class="px-6 py-3">When</th>
                            <th class="px-4 py-3">File</th>
                            <th class="px-4 py-3">By</th>
                            <th class="px-4 py-3">Result</th>
                            <th class="px-4 py-3 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($history as $past)
                            @php $r = $past->result ?? []; @endphp
                            <tr wire:key="history-{{ $past->id }}">
                                <td class="whitespace-nowrap px-6 py-3 text-slate-500 dark:text-slate-400">{{ $past->imported_at?->format('j M Y, H:i') }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $past->filename }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $past->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                    {{ $r['created'] ?? 0 }} added, {{ $r['updated'] ?? 0 }} updated, {{ $r['skipped'] ?? 0 }} skipped
                                    @if($past->status === 'undone')<x-badge class="ml-1.5">Undone</x-badge>@endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if($past->status === 'imported')
                                        <x-button variant="secondary" size="sm" wire:click="undoImport({{ $past->id }})" wire:confirm="Undo this import? Members it added are removed and fields it changed are put back.">Undo</x-button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>
