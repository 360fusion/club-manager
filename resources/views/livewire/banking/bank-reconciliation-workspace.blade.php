<div class="space-y-6">
    <!-- Header -->
    <x-card>
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Bank Reconciliation Workspace</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Rule-based matching for member dues, supplier bills and the relief chest.</p>
            </div>

            <div class="flex items-center gap-2">
                <x-button variant="secondary" size="sm" href="{{ route('admin.club_acc.bank_imports.index', ['clubSlug' => $club->slug]) }}">Bank statement imports</x-button>
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

    <!-- Reconciliation Workspace Split View (2 Columns) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- LEFT COLUMN: Unmatched Statement Lines Stack (5 Columns wide on lg) -->
        <div class="lg:col-span-5 space-y-4">
            <x-card padding="sm" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Unmatched statement lines</h2>
                    <x-badge variant="warning">{{ $unmatchedCount }} pending</x-badge>
                </div>

                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search statement description"
                    aria-label="Search statement lines"
                    class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                />
            </x-card>

            <div class="space-y-2.5 max-h-[650px] overflow-y-auto pr-1">
                @forelse($unmatchedTransactions as $tx)
                    @php
                        $isSelected = $selectedTransactionId === $tx->id;
                    @endphp
                    <button
                        type="button"
                        wire:key="tx-{{ $tx->id }}"
                        wire:click="selectTransaction({{ $tx->id }})"
                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                        class="block w-full text-left p-4 rounded-xl border transition-colors cursor-pointer text-xs space-y-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 {{ $isSelected ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-400 dark:border-blue-700 ring-2 ring-blue-400/30' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700' }}"
                    >
                        <span class="flex items-start justify-between gap-2">
                            <span class="font-medium text-slate-500 dark:text-slate-400 text-xs whitespace-nowrap">
                                {{ $tx->transaction_date ? $tx->transaction_date->format('d M Y') : '—' }}
                            </span>
                            <span class="font-semibold text-sm whitespace-nowrap {{ $tx->amount > 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                                {{ $tx->amount > 0 ? '+' : '' }}{{ $cs }}{{ number_format(abs($tx->amount), 2) }}
                            </span>
                        </span>

                        <span class="block">
                            <span class="font-semibold text-slate-900 dark:text-white block line-clamp-2">{{ $tx->raw_description }}</span>
                            @if($tx->reference)
                                <span class="text-xs text-slate-500 dark:text-slate-400 font-mono block mt-0.5">Ref: {{ $tx->reference }}</span>
                            @endif
                        </span>
                    </button>
                @empty
                    <x-card padding="lg" class="text-center text-sm text-slate-500 dark:text-slate-400">
                        All bank statement lines are reconciled. No unmatched lines pending.
                    </x-card>
                @endforelse
            </div>

            @if($unmatchedTransactions->hasPages())
                <x-card padding="sm">
                    {{ $unmatchedTransactions->links() }}
                </x-card>
            @endif
        </div>

        <!-- RIGHT COLUMN: Reconciliation Inspector & Match Engine (7 Columns wide on lg) -->
        <div class="lg:col-span-7 space-y-4">
            @if($selectedTx)
                <!-- Selected Statement Line Card -->
                <x-card class="space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Statement line</span>
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $selectedTx->transaction_date ? $selectedTx->transaction_date->format('l, d F Y') : '' }}</span>
                    </div>

                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ $selectedTx->raw_description }}</h2>
                            @if($selectedTx->reference)
                                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">Ref: {{ $selectedTx->reference }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-bold {{ $selectedTx->amount > 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                                {{ $selectedTx->amount > 0 ? '+' : '' }}{{ $cs }}{{ number_format(abs($selectedTx->amount), 2) }}
                            </span>
                            <span class="text-xs text-slate-500 dark:text-slate-400 block font-medium">
                                {{ $selectedTx->amount > 0 ? 'Incoming credit (income)' : 'Outgoing debit (expense)' }}
                            </span>
                        </div>
                    </div>
                </x-card>

                <!-- Suggested Matches Engine Panel -->
                <x-card class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Suggested matches</h3>
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ count($suggestedMatches) }} candidate matches</span>
                    </div>

                    <div class="space-y-3">
                        @forelse($suggestedMatches as $match)
                            <div class="p-4 rounded-xl border space-y-3 {{ $match['confidence_level'] === 'high' ? 'bg-emerald-50/70 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-700/60' : 'bg-amber-50/70 dark:bg-amber-950/30 border-amber-300 dark:border-amber-700/60' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        @if($match['confidence_level'] === 'high')
                                            <x-badge variant="success">{{ $match['confidence_score'] }}% high confidence match</x-badge>
                                        @else
                                            <x-badge variant="warning">{{ $match['confidence_score'] }}% medium match</x-badge>
                                        @endif
                                    </div>
                                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $match['match_reason'] }}</span>
                                </div>

                                <div class="flex items-center justify-between gap-3 pt-1">
                                    <div>
                                        <h4 class="font-semibold text-slate-900 dark:text-white text-sm">{{ $match['target_title'] }}</h4>
                                        <span class="text-xs text-slate-600 dark:text-slate-300 font-medium">Target dues / amount: {{ $cs }}{{ number_format($match['target_amount'], 2) }}</span>
                                    </div>

                                    <x-button
                                        size="sm"
                                        class="whitespace-nowrap"
                                        wire:click="reconcileSuggested({{ $selectedTx->id }}, '{{ $match['match_type'] }}', {{ $match['target_id'] }})"
                                    >
                                        Reconcile and mark paid
                                    </x-button>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-slate-500 dark:text-slate-400 text-xs space-y-1">
                                <p class="font-medium">No automatic match found for this statement line.</p>
                                <p>Use manual allocation below to allocate it to a member, vendor bill or ledger code.</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Secondary Action Buttons -->
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3">
                        <x-button variant="secondary" size="sm" wire:click="openManualDrawer({{ $selectedTx->id }})">
                            Manual lookup and allocation
                        </x-button>

                        <x-button
                            variant="ghost"
                            size="sm"
                            wire:click="ignoreLine({{ $selectedTx->id }})"
                            wire:confirm="Mark this bank statement transaction line as ignored?"
                        >
                            Ignore line
                        </x-button>
                    </div>
                </x-card>
            @else
                <x-card padding="lg" class="text-center text-sm text-slate-500 dark:text-slate-400">
                    Select an unmatched statement line from the left panel to inspect and reconcile.
                </x-card>
            @endif
        </div>
    </div>

    <!-- Manual Lookup & Allocation Drawer Modal -->
    @if($selectedTx)
        @if ($showManualDrawer)
        <x-modal
            :show="$showManualDrawer"
            title="Manual ledger allocation"
            subtitle="Allocating: {{ $selectedTx->raw_description }} ({{ $cs }}{{ number_format(abs($selectedTx->amount), 2) }})"
            size="lg"
            close="$set('showManualDrawer', false)"
        >
            <div class="space-y-5">
                <!-- Allocation Type Selector -->
                <div class="flex items-center gap-2 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl text-xs font-medium text-slate-700 dark:text-slate-200" role="group" aria-label="Allocate to">
                    <button
                        type="button"
                        wire:click="$set('manualAllocationType', 'member_subscription')"
                        aria-pressed="{{ $manualAllocationType === 'member_subscription' ? 'true' : 'false' }}"
                        class="flex-1 py-1.5 px-3 rounded-xl transition-colors cursor-pointer {{ $manualAllocationType === 'member_subscription' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm' : 'hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        Member dues
                    </button>
                    <button
                        type="button"
                        wire:click="$set('manualAllocationType', 'supplier_bill')"
                        aria-pressed="{{ $manualAllocationType === 'supplier_bill' ? 'true' : 'false' }}"
                        class="flex-1 py-1.5 px-3 rounded-xl transition-colors cursor-pointer {{ $manualAllocationType === 'supplier_bill' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm' : 'hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        Vendor bill
                    </button>
                    <button
                        type="button"
                        wire:click="$set('manualAllocationType', 'invoice')"
                        aria-pressed="{{ $manualAllocationType === 'invoice' ? 'true' : 'false' }}"
                        class="flex-1 py-1.5 px-3 rounded-xl transition-colors cursor-pointer {{ $manualAllocationType === 'invoice' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm' : 'hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        Invoice
                    </button>
                    <button
                        type="button"
                        wire:click="$set('manualAllocationType', 'ledger_account')"
                        aria-pressed="{{ $manualAllocationType === 'ledger_account' ? 'true' : 'false' }}"
                        class="flex-1 py-1.5 px-3 rounded-xl transition-colors cursor-pointer {{ $manualAllocationType === 'ledger_account' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm' : 'hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        Nominal code
                    </button>
                </div>

                @if($manualAllocationType !== 'ledger_account')
                    <!-- Search Field -->
                    <x-input
                        type="search"
                        wire:model.live.debounce.300ms="manualSearch"
                        placeholder="Search member name or vendor bill"
                        aria-label="Search records to allocate to"
                    />

                    <!-- Candidates List -->
                    <div class="space-y-2 text-xs max-h-60 overflow-y-auto">
                        @forelse($manualCandidates as $cand)
                            <div class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center justify-between gap-3">
                                <div>
                                    @if($manualAllocationType === 'member_subscription')
                                        <span class="font-semibold text-slate-900 dark:text-white block">{{ $cand->member->formatted_rank_name }}</span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">Invoice: {{ $cand->invoice_reference }} (Dues: {{ $cs }}{{ number_format($cand->balance_due, 2) }})</span>
                                    @elseif($manualAllocationType === 'invoice')
                                        <span class="font-semibold text-slate-900 dark:text-white block">{{ $cand->user->name ?? 'Member' }} — {{ $cand->title }}</span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">Invoice: {{ $cand->invoice_number }} (Amount: {{ $cs }}{{ number_format($cand->amount, 2) }})</span>
                                    @else
                                        <span class="font-semibold text-slate-900 dark:text-white block">{{ $cand->vendor_name }} (Bill: {{ $cand->bill_number }})</span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">Bill amount: {{ $cs }}{{ number_format($cand->amount, 2) }}</span>
                                    @endif
                                </div>

                                <x-button
                                    size="sm"
                                    wire:click="executeManualAllocation({{ $cand->id }}, '{{ $manualAllocationType }}')"
                                >
                                    Reconcile
                                </x-button>
                            </div>
                        @empty
                            <div class="p-4 text-center text-slate-500 dark:text-slate-400">No matching records found.</div>
                        @endforelse
                    </div>
                @else
                    <!-- Nominal Ledger Allocation -->
                    <div class="space-y-3">
                        <x-select label="Nominal ledger account code" wire:model="manualNominalCode">
                            <option value="4000">4000 - General donations &amp; dues</option>
                            <option value="4100">4100 - Dining &amp; festive board fees</option>
                            <option value="7000">7000 - Rent &amp; temple premises</option>
                            <option value="7100">7100 - Catering &amp; hospitality dues</option>
                            <option value="8000">8000 - Charity relief chest contribution</option>
                        </x-select>

                        <x-button block wire:click="executeManualAllocation(0, 'ledger_account')">
                            Allocate to nominal ledger code
                        </x-button>
                    </div>
                @endif
            </div>

            <x-slot:footer>
                <x-button variant="secondary" wire:click="$set('showManualDrawer', false)">Cancel</x-button>
            </x-slot:footer>
        </x-modal>
        @endif
    @endif
</div>
