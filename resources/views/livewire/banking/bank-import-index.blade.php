<div class="space-y-6">
    <!-- Header Banner -->
    <div class="p-6 bg-slate-900 rounded-3xl text-white shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-emerald-500/20 text-emerald-400 rounded-2xl border border-emerald-500/30">🏦</span>
                <div>
                    <h1 class="text-2xl font-black tracking-tight">Bank Statement Import Engine</h1>
                    <p class="text-xs text-slate-400 mt-1 font-medium">UK Bank Statement CSV &amp; OFX Ingestion Layer &amp; SHA-256 Deduplication</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <label class="px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-2 cursor-pointer">
                <span>📤</span>
                <span>Upload Statement (CSV/OFX)</span>
                <input type="file" wire:model="statementFile" accept=".csv,.txt,.ofx,.qfx" class="hidden" />
            </label>
        </div>
    </div>

    <!-- Flash Notifications -->
    @if (session()->has('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold rounded-2xl shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✅</span>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold rounded-2xl shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>⚠️</span>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Upload Indicator Loading Bar -->
    <div wire:loading wire:target="statementFile" class="p-4 bg-indigo-50 border border-indigo-200 text-indigo-900 text-xs font-bold rounded-2xl shadow-sm flex items-center gap-3">
        <svg class="w-5 h-5 text-indigo-600 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>Parsing bank statement file... Validating format and checking duplicate lines.</span>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 bg-white border border-slate-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Staged Lines</span>
            <div class="text-2xl font-black text-slate-900 mt-1">{{ $totalStagedCount }}</div>
            <span class="text-[10px] text-slate-500 mt-1 block">Transactions awaiting reconciliation</span>
        </div>

        <div class="p-5 bg-amber-50/60 border border-amber-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Unmatched Transactions</span>
            <div class="text-2xl font-black text-amber-950 mt-1">{{ $unmatchedCount }}</div>
            <span class="text-[10px] text-amber-700 mt-1 block">Pending ledger match</span>
        </div>

        <div class="p-5 bg-emerald-50/60 border border-emerald-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Reconciled Lines</span>
            <div class="text-2xl font-black text-emerald-950 mt-1">{{ $matchedCount }}</div>
            <span class="text-[10px] text-emerald-700 mt-1 block">Matched to accounts</span>
        </div>

        <div class="p-5 bg-indigo-50/60 border border-indigo-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-800">Net Staged Amount</span>
            <div class="text-2xl font-black text-indigo-950 mt-1">£{{ number_format($totalNetAmount, 2) }}</div>
            <span class="text-[10px] text-indigo-700 mt-1 block">Net balance of staged transactions</span>
        </div>
    </div>

    <!-- Drag & Drop Upload Zone -->
    <div class="p-6 bg-white border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-3xl text-center space-y-2 transition-all">
        <div class="text-3xl text-emerald-600">📁</div>
        <h3 class="font-black text-slate-800 text-sm">Drag &amp; Drop UK Bank Statement CSV or OFX File</h3>
        <p class="text-xs text-slate-500 max-w-md mx-auto">Supports Barclays, HSBC, Lloyds, NatWest, Santander, Revolut, Starling &amp; standard OFX exports.</p>
        <div class="pt-2">
            <label class="inline-block px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow cursor-pointer transition">
                Browse Files...
                <input type="file" wire:model="statementFile" accept=".csv,.txt,.ofx,.qfx" class="hidden" />
            </label>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="p-4 bg-white border border-slate-200/80 rounded-2xl shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="relative w-full md:w-80">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search transaction description, ref..."
                class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto">
            <select
                wire:model.live="statusFilter"
                class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
                <option value="">All Statuses</option>
                @foreach($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Staged Transactions Table -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-black text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4">Date</th>
                        <th class="py-3.5 px-4">Raw Description &amp; Reference</th>
                        <th class="py-3.5 px-4 text-right">Amount (£)</th>
                        <th class="py-3.5 px-4 text-right">Balance After</th>
                        <th class="py-3.5 px-4">Batch Import File</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                    @forelse ($transactions as $tx)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-4 font-bold text-slate-900 whitespace-nowrap">
                                {{ $tx->transaction_date ? $tx->transaction_date->format('d M Y') : '—' }}
                            </td>

                            <td class="py-3 px-4">
                                <span class="font-bold text-slate-900 block">{{ $tx->raw_description }}</span>
                                @if($tx->reference)
                                    <span class="text-[10px] text-slate-500 font-mono">Ref: {{ $tx->reference }}</span>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-right font-black whitespace-nowrap {{ $tx->amount > 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $tx->amount > 0 ? '+' : '' }}£{{ number_format(abs($tx->amount), 2) }}
                            </td>

                            <td class="py-3 px-4 text-right font-medium text-slate-600 whitespace-nowrap">
                                {{ $tx->balance_after !== null ? '£' . number_format($tx->balance_after, 2) : '—' }}
                            </td>

                            <td class="py-3 px-4 text-slate-600 font-medium truncate max-w-[150px]" title="{{ $tx->import?->filename }}">
                                📄 {{ $tx->import?->filename ?: 'Manual' }}
                            </td>

                            <td class="py-3 px-4">
                                <span class="px-2.5 py-1 text-[10px] font-extrabold rounded-full border {{ $tx->status->badgeClasses() }}">
                                    {{ $tx->status->label() }}
                                </span>
                            </td>

                            <td class="py-3 px-4 text-right">
                                <button
                                    type="button"
                                    wire:click="deleteTransaction({{ $tx->id }})"
                                    wire:confirm="Remove this staged bank transaction?"
                                    class="p-1 text-slate-400 hover:text-rose-600 rounded-md transition"
                                    title="Delete Transaction"
                                >
                                    🗑️
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                                No staged bank transactions found. Upload a bank CSV or OFX file to import statement lines.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>

    <!-- Parsed Statement Preview Modal -->
    @if($showPreviewModal && $previewBundle)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full p-6 space-y-5 border border-slate-100 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📄</span>
                        <div>
                            <h3 class="font-black text-slate-900 text-base">Statement Parsing Preview</h3>
                            <p class="text-[10px] text-slate-500 font-medium">File: <strong class="text-slate-800">{{ $previewBundle['filename'] }}</strong></p>
                        </div>
                    </div>
                    <button type="button" wire:click="cancelPreview" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <!-- Summary Bar -->
                <div class="grid grid-cols-3 gap-3 p-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs">
                    <div>
                        <span class="text-slate-400 font-bold block text-[10px] uppercase">Total Lines Parsed</span>
                        <span class="text-lg font-black text-slate-900">{{ $previewBundle['total_lines'] }}</span>
                    </div>

                    <div>
                        <span class="text-emerald-700 font-bold block text-[10px] uppercase">New Lines to Import</span>
                        <span class="text-lg font-black text-emerald-950">{{ $previewBundle['new_lines_count'] }}</span>
                    </div>

                    <div>
                        <span class="text-amber-700 font-bold block text-[10px] uppercase">Duplicates Skipped</span>
                        <span class="text-lg font-black text-amber-950">{{ $previewBundle['duplicate_lines_count'] }}</span>
                    </div>
                </div>

                @if($previewBundle['duplicate_lines_count'] > 0)
                    <div class="p-3.5 bg-amber-50 border border-amber-200 text-amber-900 text-xs rounded-xl font-medium">
                        ⚠️ <strong>{{ $previewBundle['duplicate_lines_count'] }}</strong> duplicate line(s) detected via SHA-256 hash. Duplicate lines are highlighted in amber and will be automatically skipped during import.
                    </div>
                @endif

                <!-- Preview Table -->
                <div class="border border-slate-200 rounded-2xl overflow-hidden max-h-80 overflow-y-auto text-xs">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-wider sticky top-0 bg-slate-100">
                                <th class="py-2.5 px-3">Date</th>
                                <th class="py-2.5 px-3">Description</th>
                                <th class="py-2.5 px-3 text-right">Amount (£)</th>
                                <th class="py-2.5 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            @foreach($previewBundle['lines'] as $line)
                                <tr class="{{ $line['is_duplicate'] ? 'bg-amber-50/70 text-amber-900 font-medium' : 'hover:bg-slate-50' }}">
                                    <td class="py-2.5 px-3 whitespace-nowrap">{{ $line['transaction_date']->format('d M Y') }}</td>
                                    <td class="py-2.5 px-3 font-semibold">{{ $line['raw_description'] }}</td>
                                    <td class="py-2.5 px-3 text-right font-black whitespace-nowrap {{ $line['amount'] > 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                        {{ $line['amount'] > 0 ? '+' : '' }}£{{ number_format(abs($line['amount']), 2) }}
                                    </td>
                                    <td class="py-2.5 px-3 whitespace-nowrap">
                                        @if($line['is_duplicate'])
                                            <span class="px-2 py-0.5 bg-amber-200 text-amber-900 rounded font-bold text-[10px]">Duplicate (Skipped)</span>
                                        @else
                                            <span class="px-2 py-0.5 bg-emerald-100 text-emerald-900 rounded font-bold text-[10px]">New Line</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
                    <button type="button" wire:click="cancelPreview" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Cancel</button>
                    <button type="button" wire:click="confirmImport" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl shadow-md transition flex items-center gap-2">
                        <span>✅</span>
                        <span>Import {{ $previewBundle['new_lines_count'] }} Staged Lines</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
