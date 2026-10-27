<div class="space-y-6">
    <!-- Header -->
    <x-card>
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Bank statement import</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Import UK bank statement CSV and OFX files; duplicate lines are detected and skipped.</p>
            </div>

            <div class="flex items-center gap-2">
                <label class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-transparent bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-500 focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 dark:focus-within:ring-offset-slate-950">
                    Upload statement (CSV/OFX)
                    <input type="file" wire:model="statementFile" accept=".csv,.txt,.ofx,.qfx" class="sr-only" />
                </label>
            </div>
        </div>
    </x-card>

    <!-- Flash Notifications -->
    @if (session()->has('success'))
        <x-notice variant="success">{{ session('success') }}</x-notice>
    @endif
    @if (session()->has('error'))
        <x-notice variant="danger">{{ session('error') }}</x-notice>
    @endif

    <!-- Upload Indicator Loading Bar -->
    <div wire:loading wire:target="statementFile" class="w-full">
        <x-notice variant="info">
            <span class="inline-flex items-center gap-2">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Reading the bank statement file: checking the format and looking for duplicate lines.
            </span>
        </x-notice>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-card padding="sm">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Total staged lines</span>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $totalStagedCount }}</div>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block">Transactions awaiting reconciliation</span>
        </x-card>

        <x-card padding="sm">
            <span class="text-xs font-medium text-amber-700 dark:text-amber-300">Unmatched transactions</span>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $unmatchedCount }}</div>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block">Pending ledger match</span>
        </x-card>

        <x-card padding="sm">
            <span class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Reconciled lines</span>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $matchedCount }}</div>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block">Matched to accounts</span>
        </x-card>

        <x-card padding="sm">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Net staged amount</span>
            <div class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ $cs }}{{ number_format($totalNetAmount, 2) }}</div>
            <span class="text-xs text-slate-500 dark:text-slate-400 mt-1 block">Net balance of staged transactions</span>
        </x-card>
    </div>

    <!-- Upload Zone -->
    <div class="p-6 bg-white dark:bg-slate-900 border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-blue-500 rounded-2xl text-center space-y-2 transition-colors">
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Upload a UK bank statement (CSV or OFX file)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">Supports Barclays, HSBC, Lloyds, NatWest, Santander, Revolut, Starling and standard OFX exports.</p>
        <div class="pt-2">
            <label class="inline-flex cursor-pointer items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-within:ring-offset-slate-950">
                Browse files
                <input type="file" wire:model="statementFile" accept=".csv,.txt,.ofx,.qfx" class="sr-only" />
            </label>
        </div>
    </div>

    <!-- Search & Filters -->
    <x-card padding="sm" class="flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="w-full md:w-80">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search description or reference"
                aria-label="Search transactions"
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto">
            <select
                wire:model.live="statusFilter"
                aria-label="Filter by status"
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            >
                <option value="">All statuses</option>
                @foreach($statuses as $st)
                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                @endforeach
            </select>
        </div>
    </x-card>

    <!-- Staged Transactions Table -->
    <x-card padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-xs font-medium text-slate-500 dark:text-slate-400">
                        <th class="py-3 px-4">Date</th>
                        <th class="py-3 px-4">Description and reference</th>
                        <th class="py-3 px-4 text-right">Amount ({{ $cs }})</th>
                        <th class="py-3 px-4 text-right">Balance after</th>
                        <th class="py-3 px-4">Import file</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs text-slate-700 dark:text-slate-200">
                    @forelse ($transactions as $tx)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
                            <td class="py-3 px-4 font-medium text-slate-900 dark:text-white whitespace-nowrap">
                                {{ $tx->transaction_date ? $tx->transaction_date->format('d M Y') : '—' }}
                            </td>

                            <td class="py-3 px-4">
                                <span class="font-medium text-slate-900 dark:text-white block">{{ $tx->raw_description }}</span>
                                @if($tx->reference)
                                    <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">Ref: {{ $tx->reference }}</span>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-right font-semibold whitespace-nowrap {{ $tx->amount > 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                                {{ $tx->amount > 0 ? '+' : '' }}{{ $cs }}{{ number_format(abs($tx->amount), 2) }}
                            </td>

                            <td class="py-3 px-4 text-right font-medium text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                {{ $tx->balance_after !== null ? $cs . number_format($tx->balance_after, 2) : '—' }}
                            </td>

                            <td class="py-3 px-4 text-slate-600 dark:text-slate-300 font-medium truncate max-w-[150px]" title="{{ $tx->import?->filename }}">
                                {{ $tx->import?->filename ?: 'Manual' }}
                            </td>

                            <td class="py-3 px-4">
                                <x-badge :variant="['unmatched' => 'warning', 'matched' => 'success'][$tx->status->value] ?? 'neutral'">{{ $tx->status->label() }}</x-badge>
                            </td>

                            <td class="py-3 px-4 text-right">
                                <x-button
                                    variant="ghost"
                                    size="sm"
                                    wire:click="deleteTransaction({{ $tx->id }})"
                                    wire:confirm="Remove this staged bank transaction?"
                                >
                                    Delete
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500 dark:text-slate-400 text-xs">
                                No staged bank transactions found. Upload a bank CSV or OFX file to import statement lines.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
            <div class="p-4 border-t border-slate-100 dark:border-slate-800">
                {{ $transactions->links() }}
            </div>
        @endif
    </x-card>

    <!-- Parsed Statement Preview Modal -->
    @if($previewBundle)
        @if ($showPreviewModal)
        <x-modal :show="$showPreviewModal" title="Statement preview" subtitle="File: {{ $previewBundle['filename'] }}" size="xl" close="cancelPreview">
            <div class="space-y-5">
                <!-- Summary Bar -->
                <div class="grid grid-cols-3 gap-3 p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs">
                    <div>
                        <span class="text-slate-500 dark:text-slate-400 font-medium block text-xs">Total lines read</span>
                        <span class="text-lg font-bold text-slate-900 dark:text-white">{{ $previewBundle['total_lines'] }}</span>
                    </div>

                    <div>
                        <span class="text-emerald-700 dark:text-emerald-300 font-medium block text-xs">New lines to import</span>
                        <span class="text-lg font-bold text-slate-900 dark:text-white">{{ $previewBundle['new_lines_count'] }}</span>
                    </div>

                    <div>
                        <span class="text-amber-700 dark:text-amber-300 font-medium block text-xs">Duplicates skipped</span>
                        <span class="text-lg font-bold text-slate-900 dark:text-white">{{ $previewBundle['duplicate_lines_count'] }}</span>
                    </div>
                </div>

                @if($previewBundle['duplicate_lines_count'] > 0)
                    <x-notice variant="warning">
                        <strong>{{ $previewBundle['duplicate_lines_count'] }}</strong> duplicate line(s) found. Duplicate lines are highlighted in amber and will be skipped during import.
                    </x-notice>
                @endif

                <!-- Preview Table -->
                <div class="border border-slate-200 dark:border-slate-800 rounded-xl overflow-hidden max-h-80 overflow-y-auto text-xs">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-800 text-xs font-medium text-slate-500 dark:text-slate-400 sticky top-0">
                                <th class="py-2.5 px-3">Date</th>
                                <th class="py-2.5 px-3">Description</th>
                                <th class="py-2.5 px-3 text-right">Amount ({{ $cs }})</th>
                                <th class="py-2.5 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                            @foreach($previewBundle['lines'] as $line)
                                <tr class="{{ $line['is_duplicate'] ? 'bg-amber-50/70 dark:bg-amber-950/70 text-amber-900 dark:text-amber-200 font-medium' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50' }}">
                                    <td class="py-2.5 px-3 whitespace-nowrap">{{ $line['transaction_date']->format('d M Y') }}</td>
                                    <td class="py-2.5 px-3 font-medium">{{ $line['raw_description'] }}</td>
                                    <td class="py-2.5 px-3 text-right font-semibold whitespace-nowrap {{ $line['amount'] > 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                                        {{ $line['amount'] > 0 ? '+' : '' }}{{ $cs }}{{ number_format(abs($line['amount']), 2) }}
                                    </td>
                                    <td class="py-2.5 px-3 whitespace-nowrap">
                                        @if($line['is_duplicate'])
                                            <x-badge variant="warning">Duplicate (skipped)</x-badge>
                                        @else
                                            <x-badge variant="success">New line</x-badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <x-slot:footer>
                <x-button variant="secondary" wire:click="cancelPreview">Cancel</x-button>
                <x-button wire:click="confirmImport">Import {{ $previewBundle['new_lines_count'] }} staged lines</x-button>
            </x-slot:footer>
        </x-modal>
        @endif
    @endif
</div>
