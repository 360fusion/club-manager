@php
    $steps = ['upload' => 'Upload', 'match' => 'Match columns', 'review' => 'Review', 'done' => 'Done'];
    $stepKeys = array_keys($steps);
    $stepIndex = array_search($step, $stepKeys, true);
    $actionLabels = ['skip' => 'Skip', 'fill' => 'Update blank fields only', 'overwrite' => 'Overwrite with file values', 'create' => 'Import as new'];
    $statusStyles = [
        'new' => ['New', 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border-emerald-200 dark:border-emerald-800/60'],
        'duplicate' => ['Duplicate', 'bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200 border-amber-300 dark:border-amber-700/60'],
        'possible_duplicate' => ['Possible duplicate', 'bg-sky-100 dark:bg-sky-900/40 text-sky-800 dark:text-sky-200 border-sky-200 dark:border-sky-800/60'],
        'error' => ['Error', 'bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-200 border-rose-300 dark:border-rose-700/60'],
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
    $selectClass = 'px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:outline-none';
    $cardClass = 'bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm';
@endphp

<div class="space-y-6">
    @include('livewire.members._notice')

    <!-- Header & stepper -->
    <div class="{{ $cardClass }} p-6 sm:p-8 space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="space-y-1">
                <a href="{{ route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">&larr; Back to the member directory</a>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Import Members</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Add or update members from a spreadsheet. You will match the columns and settle any duplicates before anything is saved.</p>
            </div>
        </div>

        <ol class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs font-bold" aria-label="Import steps">
            @foreach($steps as $key => $label)
                @php $i = array_search($key, $stepKeys, true); @endphp
                <li class="flex items-center gap-2 px-3 py-2.5 rounded-xl border {{ $i === $stepIndex ? 'bg-slate-900 dark:bg-slate-700 text-white border-slate-900 dark:border-slate-700' : ($i < $stepIndex ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-200 border-emerald-200 dark:border-emerald-800/60' : 'bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-800') }}" @if($i === $stepIndex) aria-current="step" @endif>
                    <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-black {{ $i === $stepIndex ? 'bg-white/20' : 'bg-white/60 dark:bg-slate-900/40' }}">{{ $i < $stepIndex ? '✓' : $i + 1 }}</span>
                    <span>{{ $label }}</span>
                </li>
            @endforeach
        </ol>
    </div>

    {{-- ============ Step 1: upload ============ --}}
    @if($step === 'upload')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="{{ $cardClass }} p-6 sm:p-8 lg:col-span-2 space-y-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-base font-black text-slate-900 dark:text-white">1. Upload your spreadsheet</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">A CSV file, up to 5 MB and 5,000 rows. In Excel or Google Sheets use File, then Save as or Download, then CSV.</p>
                    </div>
                    <button type="button" wire:click="downloadTemplate" class="shrink-0 px-4 py-2.5 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl shadow-xs transition-all inline-flex items-center gap-1.5 cursor-pointer">
                        <span>📥</span>
                        <span>Download template</span>
                    </button>
                </div>

                <label
                    x-data="{ over: false }"
                    x-on:dragover.prevent="over = true"
                    x-on:dragleave.prevent="over = false"
                    x-on:drop.prevent="over = false; $refs.picker.files = $event.dataTransfer.files; $refs.picker.dispatchEvent(new Event('change', { bubbles: true }))"
                    :class="over ? 'border-blue-500 bg-blue-50/60 dark:bg-blue-950/30' : 'border-slate-300 dark:border-slate-700'"
                    class="flex flex-col items-center justify-center gap-2 px-6 py-12 border-2 border-dashed rounded-2xl text-center cursor-pointer transition-colors"
                >
                    <span class="text-3xl">📄</span>
                    <span class="text-sm font-bold text-slate-800 dark:text-slate-100">Drop a CSV file here, or click to choose one</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">The file is kept privately and deleted once the import is finished.</span>
                    <input x-ref="picker" type="file" wire:model="file" accept=".csv,.txt,text/csv" class="sr-only" />
                </label>

                <div wire:loading wire:target="file" class="p-3 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-xs font-bold text-blue-800 dark:text-blue-200">Reading your file…</div>

                @error('file')
                    <div class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-xs font-bold text-rose-800 dark:text-rose-200">{{ $message }}</div>
                @enderror
            </div>

            <div class="{{ $cardClass }} p-6 space-y-4 text-xs text-slate-600 dark:text-slate-300">
                <h2 class="text-base font-black text-slate-900 dark:text-white">How it works</h2>
                <ol class="space-y-3 list-decimal list-inside marker:font-black marker:text-slate-400">
                    <li><strong>Upload</strong> a file. The template has the same columns as the directory export, so an exported file can be edited and imported back.</li>
                    <li><strong>Match columns.</strong> Your headings are matched for you. Change any that are wrong, and save the matching for next time.</li>
                    <li><strong>Review.</strong> Rows already on the roster are flagged as duplicates, and you choose what happens to each.</li>
                    <li><strong>Import,</strong> then undo it from the history below if it was a mistake.</li>
                </ol>
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-1.5">
                    <p><strong>Needed:</strong> a first and last name (or one Name column).</p>
                    <p><strong>Dates:</strong> 31/12/2020, 2020-12-31 or 31 Dec 2020.</p>
                    <p><strong>Status:</strong> Active, Honorary, Resigned, Deceased and so on.</p>
                    <p><strong>Office:</strong> for example Secretary, WM, Senior Warden. Blank means Member.</p>
                    <p class="text-amber-700 dark:text-amber-300"><strong>Delete the example row</strong> in the template before you upload it.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- ============ Step 2: match columns ============ --}}
    @if($step === 'match' && $import)
        <div class="{{ $cardClass }} p-6 sm:p-8 space-y-5">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                <div>
                    <h2 class="text-base font-black text-slate-900 dark:text-white">2. Match your columns</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1"><strong>{{ $import->filename }}</strong> has {{ number_format($import->option('row_count', 0)) }} {{ \Illuminate\Support\Str::plural('row', $import->option('row_count', 0)) }} and {{ count($import->headers ?? []) }} columns. Choose what each column holds.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="autoMatch" class="{{ $selectClass }} cursor-pointer">Match automatically</button>
                    @if($savedMappings->isNotEmpty())
                        <select x-data x-on:change="if ($event.target.value) { $wire.loadSavedMapping(parseInt($event.target.value)); } $event.target.value = ''" aria-label="Load a saved matching" class="{{ $selectClass }}">
                            <option value="">Load a saved matching…</option>
                            @foreach($savedMappings as $saved)
                                <option value="{{ $saved->id }}">{{ $saved->name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>

            @if($import->option('already_imported_at'))
                <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs font-bold text-amber-900 dark:text-amber-200">
                    This exact file was already imported on {{ \Illuminate\Support\Carbon::parse($import->option('already_imported_at'))->format('j M Y') }}. Anything already on the roster will show as a duplicate.
                </div>
            @endif

            @if($mappingProblem)
                <div role="alert" class="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-xs font-bold text-rose-800 dark:text-rose-200">{{ $mappingProblem }}</div>
            @endif

            <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            <th class="py-3 px-4">Column in your file</th>
                            <th class="py-3 px-4">Examples</th>
                            <th class="py-3 px-4">Import it as</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($import->headers ?? [] as $index => $header)
                            @php $chosen = $mapping[$index] ?? ''; @endphp
                            <tr wire:key="map-{{ $index }}">
                                <td class="py-3 px-4 font-extrabold text-slate-900 dark:text-white">{{ $header }}</td>
                                <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                                    @forelse(($import->option('samples', [])[$index] ?? []) as $sample)
                                        <span class="inline-block max-w-[12rem] truncate align-bottom mr-1.5 px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800">{{ $sample }}</span>
                                    @empty
                                        <span class="text-slate-400">No values</span>
                                    @endforelse
                                </td>
                                <td class="py-3 px-4">
                                    <select wire:model.live="mapping.{{ $index }}" aria-label="What is {{ $header }}?" class="{{ $selectClass }} w-full max-w-xs {{ $chosen === '' ? 'text-slate-400 dark:text-slate-500' : '' }}">
                                        <option value="">Do not import</option>
                                        @foreach($targets as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                <div>
                    <label for="import-date-order" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Dates in your file are written</label>
                    <select id="import-date-order" wire:model="dateOrder" class="{{ $selectClass }} w-full">
                        @foreach($dateOrders as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="import-mapping-name" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Save this matching as (optional)</label>
                    <input id="import-mapping-name" type="text" wire:model="mappingName" maxlength="100" placeholder="e.g. Hermes export" class="{{ $selectClass }} w-full" />
                    @error('mappingName') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                </div>
            </div>

            @if($savedMappings->isNotEmpty())
                <div class="flex flex-wrap items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                    <span class="font-bold">Saved:</span>
                    @foreach($savedMappings as $saved)
                        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-800">
                            {{ $saved->name }}
                            <button type="button" wire:click="deleteSavedMapping({{ $saved->id }})" wire:confirm="Remove the saved matching '{{ $saved->name }}'?" aria-label="Remove {{ $saved->name }}" class="text-slate-400 hover:text-rose-600 cursor-pointer">✕</button>
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
                <button type="button" wire:click="startOver" wire:confirm="Discard this file and start again?" class="px-4 py-2.5 font-bold text-xs text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white cursor-pointer">Start over</button>
                <button type="button" wire:click="reviewRows" wire:loading.attr="disabled" wire:target="reviewRows" @disabled($mappingProblem) class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-xs rounded-xl shadow-md transition cursor-pointer">
                    <span wire:loading.remove wire:target="reviewRows">Continue to review</span>
                    <span wire:loading wire:target="reviewRows">Checking rows…</span>
                </button>
            </div>
        </div>
    @endif

    {{-- ============ Step 3: review ============ --}}
    @if($step === 'review' && $import)
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            @foreach([['all', 'All rows', $import->total_rows, 'bg-slate-50 dark:bg-slate-800/50 border-slate-200/80 dark:border-slate-800/80 text-slate-900 dark:text-white'], ['new', 'New', $import->new_count, 'bg-emerald-50/50 dark:bg-emerald-950/50 border-emerald-200/80 dark:border-emerald-800/80 text-emerald-950 dark:text-emerald-100'], ['duplicate', 'Duplicates', $import->duplicate_count, 'bg-amber-50/50 dark:bg-amber-950/50 border-amber-200/80 dark:border-amber-800/80 text-amber-950 dark:text-amber-100'], ['possible_duplicate', 'Possible duplicates', $import->possible_count, 'bg-sky-50/50 dark:bg-sky-950/50 border-sky-200/80 dark:border-sky-800/80 text-sky-950 dark:text-sky-100'], ['error', 'Errors', $import->error_count, 'bg-rose-50/50 dark:bg-rose-950/50 border-rose-200/80 dark:border-rose-800/80 text-rose-950 dark:text-rose-100']] as [$key, $label, $count, $tileClass])
                <button type="button" wire:click="$set('tab', '{{ $key }}')" class="text-left p-4 border rounded-2xl space-y-1 cursor-pointer transition {{ $tileClass }} {{ $tab === $key ? 'ring-2 ring-blue-500' : '' }}" @if($tab === $key) aria-pressed="true" @endif>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider opacity-70">{{ $label }}</span>
                    <span class="block text-2xl font-black">{{ number_format($count) }}</span>
                </button>
            @endforeach
        </div>

        <div class="{{ $cardClass }} overflow-hidden">
            <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 text-xs">
                <p class="text-slate-600 dark:text-slate-300 max-w-2xl">
                    Rows that match someone already on the roster are skipped unless you choose otherwise. A <strong>possible</strong> duplicate only shares a name, so check it before updating anyone.
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    @if(in_array($tab, ['duplicate', 'possible_duplicate'], true))
                        <span class="font-bold text-slate-500 dark:text-slate-400">Set every row in this list to</span>
                        <select x-data x-on:change="if ($event.target.value) { $wire.applyToTab($event.target.value); } $event.target.value = ''" aria-label="Set every row in this list" class="{{ $selectClass }}">
                            <option value="">Choose…</option>
                            @foreach($actionLabels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                    @if($hasFileDuplicates)
                        <label for="file-duplicates" class="font-bold text-slate-500 dark:text-slate-400">Repeated in the file:</label>
                        <select id="file-duplicates" wire:model.live="fileDuplicates" class="{{ $selectClass }}">
                            <option value="keep_first">Keep the first</option>
                            <option value="keep_last">Keep the last</option>
                            <option value="keep_both">Import both</option>
                        </select>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800/80 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            <th class="py-3.5 pl-6 pr-2 w-14">Row</th>
                            <th class="py-3.5 px-4">Member</th>
                            <th class="py-3.5 px-4">Result</th>
                            <th class="py-3.5 px-4">What to do</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($rows as $row)
                            @php
                                $data = $row->data ?? [];
                                [$statusLabel, $statusClass] = $statusStyles[$row->status];
                                $matched = $row->match_member_id ? ($matchedMembers[$row->match_member_id] ?? null) : null;
                                $allowed = match (true) {
                                    $row->status === 'error' => [],
                                    $row->status === 'new' => ['create', 'skip'],
                                    (bool) $row->match_member_id => ['skip', 'fill', 'overwrite', 'create'],
                                    default => ['skip', 'create'],
                                };
                            @endphp
                            <tr wire:key="row-{{ $row->id }}" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/70 align-top">
                                <td class="py-4 pl-6 pr-2 text-slate-400 font-bold">{{ $row->row_number }}</td>
                                <td class="py-4 px-4">
                                    <span class="block font-extrabold text-slate-900 dark:text-white text-sm">{{ trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: 'No name' }}</span>
                                    <span class="block text-[11px] text-slate-500 dark:text-slate-400">{{ $data['email'] ?? 'No email' }}</span>
                                </td>
                                <td class="py-4 px-4 space-y-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $statusClass }}">{{ $statusLabel }}</span>
                                    @if($row->status === 'error')
                                        @foreach($row->errors ?? [] as $error)
                                            <span class="block text-[11px] text-rose-700 dark:text-rose-300">{{ $error }}</span>
                                        @endforeach
                                    @elseif($row->match_type === 'file')
                                        <span class="block text-[11px] text-slate-500 dark:text-slate-400">Repeats row {{ $row->duplicate_of_row }} in this file</span>
                                    @elseif($row->match_type)
                                        <span class="block text-[11px] text-slate-500 dark:text-slate-400">{{ ($matchText[$row->match_type] ?? '').($matched ? ' '.$matched->full_name : '').($row->duplicate_of_row ? ', and repeats row '.$row->duplicate_of_row : '') }}</span>
                                    @endif
                                    @foreach($row->warnings ?? [] as $warning)
                                        <span class="block text-[11px] text-amber-700 dark:text-amber-300">{{ $warning }}</span>
                                    @endforeach
                                    @if($matched)
                                        <button type="button" wire:click="toggleRow({{ $row->id }})" class="text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer">{{ $expandedRow === $row->id ? 'Hide' : 'Compare' }} details</button>
                                    @endif
                                </td>
                                <td class="py-4 px-4">
                                    @if($allowed === [])
                                        <span class="text-slate-400">Not imported</span>
                                    @else
                                        <select wire:change="setRowAction({{ $row->id }}, $event.target.value)" aria-label="What to do with row {{ $row->row_number }}" class="{{ $selectClass }}">
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
                                    <td colspan="3" class="py-4 px-4">
                                        <table class="w-full max-w-3xl text-[11px]">
                                            <thead>
                                                <tr class="text-slate-400 uppercase tracking-wider text-left">
                                                    <th class="py-1 pr-4">Field</th>
                                                    <th class="py-1 pr-4">On the roster now</th>
                                                    <th class="py-1">In your file</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($data as $field => $incoming)
                                                    @php $existing = \App\Domains\ClubAccounting\Services\MemberImport\MemberImportFields::valueOf($expanded['member'], $field); @endphp
                                                    <tr class="{{ $existing !== $incoming ? 'font-bold text-amber-800 dark:text-amber-200' : 'text-slate-500 dark:text-slate-400' }}">
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
                                <td colspan="4" class="py-12 text-center text-slate-400">
                                    <p class="font-bold text-slate-700 dark:text-slate-200">Nothing in this list.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
                {{ $rows->links() }}
            </div>
        </div>

        <div class="{{ $cardClass }} p-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="space-y-2 text-xs">
                <p class="text-sm font-black text-slate-900 dark:text-white">Ready to import</p>
                <p class="text-slate-600 dark:text-slate-300">
                    <strong>{{ number_format($plan['create'] ?? 0) }}</strong> will be added,
                    <strong>{{ number_format($plan['update'] ?? 0) }}</strong> updated and
                    <strong>{{ number_format($plan['skip'] ?? 0) }}</strong> skipped @if(($plan['error'] ?? 0) > 0), and <strong class="text-rose-700 dark:text-rose-300">{{ number_format($plan['error']) }}</strong> have errors and will not be imported @endif.
                </p>
                <label class="flex items-start gap-2 text-slate-600 dark:text-slate-300">
                    <input type="checkbox" wire:model="invite" class="mt-0.5 rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500" />
                    <span>Email an invitation to set up an online account to each <strong>new</strong> member who has an email address.</span>
                </label>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($import->error_count > 0)
                    <button type="button" wire:click="downloadErrors" class="{{ $selectClass }} cursor-pointer">Download rows with errors</button>
                @endif
                <button type="button" wire:click="backToMatch" class="px-4 py-2.5 font-bold text-xs text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white cursor-pointer">Back to columns</button>
                <button type="button" wire:click="runImport" wire:loading.attr="disabled" wire:target="runImport" wire:confirm="Import these rows into the member roster?" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-bold text-xs rounded-xl shadow-md transition cursor-pointer">
                    <span wire:loading.remove wire:target="runImport">Import members</span>
                    <span wire:loading wire:target="runImport">Importing…</span>
                </button>
            </div>
        </div>
    @endif

    {{-- ============ Step 4: done ============ --}}
    @if($step === 'done' && $import)
        @php $result = $import->result ?? []; @endphp
        <div class="{{ $cardClass }} p-6 sm:p-8 space-y-5">
            <div>
                <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $import->status === 'undone' ? 'This import was undone' : 'Import complete' }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $import->filename }}</p>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-4 bg-emerald-50/50 dark:bg-emerald-950/50 border border-emerald-200/80 dark:border-emerald-800/80 rounded-2xl"><span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-800 dark:text-emerald-200">Added</span><span class="block text-2xl font-black text-emerald-950 dark:text-emerald-100">{{ $result['created'] ?? 0 }}</span></div>
                <div class="p-4 bg-blue-50/50 dark:bg-blue-950/50 border border-blue-200/80 dark:border-blue-800/80 rounded-2xl"><span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-800 dark:text-blue-200">Updated</span><span class="block text-2xl font-black text-blue-950 dark:text-blue-100">{{ $result['updated'] ?? 0 }}</span></div>
                <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl"><span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Skipped</span><span class="block text-2xl font-black text-slate-900 dark:text-white">{{ $result['skipped'] ?? 0 }}</span></div>
                <div class="p-4 bg-rose-50/50 dark:bg-rose-950/50 border border-rose-200/80 dark:border-rose-800/80 rounded-2xl"><span class="text-[10px] font-extrabold uppercase tracking-wider text-rose-800 dark:text-rose-200">Errors</span><span class="block text-2xl font-black text-rose-950 dark:text-rose-100">{{ $result['errors'] ?? 0 }}</span></div>
            </div>

            @if(($result['invited'] ?? 0) > 0 || ! empty($result['invite_skipped']))
                <p class="text-xs text-slate-600 dark:text-slate-300">
                    {{ $result['invited'] ?? 0 }} {{ \Illuminate\Support\Str::plural('invitation', $result['invited'] ?? 0) }} emailed.
                    @foreach($result['invite_skipped'] ?? [] as $reason => $count) {{ $count }} not invited ({{ strtolower($reason) }}). @endforeach
                </p>
            @endif

            <div class="flex flex-wrap items-center gap-2 pt-4 border-t border-slate-100 dark:border-slate-800">
                <a href="{{ route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md transition">View the directory</a>
                @if(($result['errors'] ?? 0) > 0)
                    <button type="button" wire:click="downloadErrors" class="{{ $selectClass }} cursor-pointer">Download rows with errors</button>
                @endif
                @if($import->status === 'imported')
                    <button type="button" wire:click="undoImport({{ $import->id }})" wire:confirm="Undo this import? Members it added are removed and fields it changed are put back." class="{{ $selectClass }} cursor-pointer">Undo this import</button>
                @endif
                <button type="button" wire:click="startOver" class="px-4 py-2.5 font-bold text-xs text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white cursor-pointer">Import another file</button>
            </div>
        </div>
    @endif

    {{-- ============ History ============ --}}
    @if(in_array($step, ['upload', 'done'], true) && $history->isNotEmpty())
        <div class="{{ $cardClass }} overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
                <h2 class="text-sm font-black text-slate-900 dark:text-white">Recent imports</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                            <th class="py-3 px-6">When</th>
                            <th class="py-3 px-4">File</th>
                            <th class="py-3 px-4">By</th>
                            <th class="py-3 px-4">Result</th>
                            <th class="py-3 px-4 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($history as $past)
                            @php $r = $past->result ?? []; @endphp
                            <tr wire:key="history-{{ $past->id }}">
                                <td class="py-3 px-6 text-slate-500 dark:text-slate-400 whitespace-nowrap">{{ $past->imported_at?->format('j M Y, H:i') }}</td>
                                <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">{{ $past->filename }}</td>
                                <td class="py-3 px-4 text-slate-500 dark:text-slate-400">{{ $past->user?->name ?? '—' }}</td>
                                <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                                    {{ $r['created'] ?? 0 }} added, {{ $r['updated'] ?? 0 }} updated, {{ $r['skipped'] ?? 0 }} skipped
                                    @if($past->status === 'undone')<span class="ml-1.5 px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-bold">Undone</span>@endif
                                </td>
                                <td class="py-3 px-4 text-right">
                                    @if($past->status === 'imported')
                                        <button type="button" wire:click="undoImport({{ $past->id }})" wire:confirm="Undo this import? Members it added are removed and fields it changed are put back." class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 font-bold text-[11px] text-slate-600 dark:text-slate-300 hover:text-rose-600 dark:hover:text-rose-400 transition cursor-pointer">Undo</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
