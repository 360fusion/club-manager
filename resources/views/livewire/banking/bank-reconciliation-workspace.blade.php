<div class="space-y-6">
    <!-- Header Banner -->
    <div class="p-6 bg-slate-900 rounded-3xl text-white shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-emerald-500/20 text-emerald-400 rounded-2xl border border-emerald-500/30">⚡</span>
                <div>
                    <h1 class="text-2xl font-black tracking-tight">Bank Reconciliation Workspace</h1>
                    <p class="text-xs text-slate-400 mt-1 font-medium">Rule-Based Automatic Matcher for Member Dues, Supplier Bills &amp; Relief Chest</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a
                href="{{ route('admin.club_acc.bank_imports.index', ['clubSlug' => $club->slug]) }}"
                class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl border border-slate-700 transition-all flex items-center gap-2"
            >
                <span>📂</span>
                <span>Bank Statement Imports</span>
            </a>
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

    <!-- Reconciliation Workspace Split View (2 Columns) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- LEFT COLUMN: Unmatched Statement Lines Stack (5 Columns wide on lg) -->
        <div class="lg:col-span-5 space-y-4">
            <div class="p-4 bg-white border border-slate-200/80 rounded-2xl shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <span>📥</span>
                        <span>Unmatched Statement Lines</span>
                    </h3>
                    <span class="px-2.5 py-0.5 text-[10px] font-black rounded-full bg-amber-100 text-amber-900">
                        {{ $unmatchedCount }} Pending
                    </span>
                </div>

                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search statement description..."
                        class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                    />
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            <div class="space-y-2.5 max-h-[650px] overflow-y-auto pr-1">
                @forelse($unmatchedTransactions as $tx)
                    @php
                        $isSelected = $selectedTransactionId === $tx->id;
                    @endphp
                    <div
                        wire:click="selectTransaction({{ $tx->id }})"
                        class="p-4 rounded-2xl border transition-all cursor-pointer text-xs space-y-2 {{ $isSelected ? 'bg-amber-50/80 border-amber-400 ring-2 ring-amber-400/30 shadow-md' : 'bg-white border-slate-200 hover:border-slate-300 hover:shadow-sm' }}"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-bold text-slate-500 text-[11px] whitespace-nowrap">
                                {{ $tx->transaction_date ? $tx->transaction_date->format('d M Y') : '—' }}
                            </span>
                            <span class="font-black text-sm whitespace-nowrap {{ $tx->amount > 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $tx->amount > 0 ? '+' : '' }}£{{ number_format(abs($tx->amount), 2) }}
                            </span>
                        </div>

                        <div>
                            <span class="font-extrabold text-slate-900 block line-clamp-2">{{ $tx->raw_description }}</span>
                            @if($tx->reference)
                                <span class="text-[10px] text-slate-500 font-mono block mt-0.5">Ref: {{ $tx->reference }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center bg-white border border-slate-200 rounded-2xl text-slate-400 text-xs italic">
                        All bank statement lines reconciled! No unmatched lines pending.
                    </div>
                @endforelse
            </div>

            @if($unmatchedTransactions->hasPages())
                <div class="p-3 bg-white border border-slate-200 rounded-2xl">
                    {{ $unmatchedTransactions->links() }}
                </div>
            @endif
        </div>

        <!-- RIGHT COLUMN: Reconciliation Inspector & Match Engine (7 Columns wide on lg) -->
        <div class="lg:col-span-7 space-y-4">
            @if($selectedTx)
                <!-- Selected Statement Line Card -->
                <div class="p-6 bg-slate-900 text-white rounded-3xl shadow-lg space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <span class="text-[10px] font-black uppercase tracking-wider text-amber-400">Statement Line Inspector</span>
                        <span class="text-xs font-bold text-slate-400">{{ $selectedTx->transaction_date ? $selectedTx->transaction_date->format('l, d F Y') : '' }}</span>
                    </div>

                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-black text-white">{{ $selectedTx->raw_description }}</h2>
                            @if($selectedTx->reference)
                                <p class="text-xs text-slate-400 font-mono mt-0.5">Ref: {{ $selectedTx->reference }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-black {{ $selectedTx->amount > 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                                {{ $selectedTx->amount > 0 ? '+' : '' }}£{{ number_format(abs($selectedTx->amount), 2) }}
                            </span>
                            <span class="text-[10px] text-slate-400 block font-semibold uppercase">
                                {{ $selectedTx->amount > 0 ? 'Incoming Credit (Income)' : 'Outgoing Debit (Expense)' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Suggested Matches Engine Panel -->
                <div class="bg-white border border-slate-200/80 rounded-3xl p-6 shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🎯</span>
                            <h3 class="font-black text-slate-900 text-sm">Automated Rule-Based Match Suggestions</h3>
                        </div>
                        <span class="text-xs font-bold text-slate-500">{{ count($suggestedMatches) }} Candidate Matches</span>
                    </div>

                    <div class="space-y-3">
                        @forelse($suggestedMatches as $match)
                            <div class="p-4 rounded-2xl border transition-all space-y-3 {{ $match['confidence_level'] === 'high' ? 'bg-emerald-50/70 border-emerald-300' : 'bg-amber-50/70 border-amber-300' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        @if($match['confidence_level'] === 'high')
                                            <span class="px-2.5 py-0.5 bg-emerald-700 text-white rounded-full text-[10px] font-black">
                                                {{ $match['confidence_score'] }}% High Confidence Match
                                            </span>
                                        @else
                                            <span class="px-2.5 py-0.5 bg-amber-600 text-white rounded-full text-[10px] font-black">
                                                {{ $match['confidence_score'] }}% Medium Match
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-[11px] font-semibold text-slate-500">{{ $match['match_reason'] }}</span>
                                </div>

                                <div class="flex items-center justify-between gap-3 pt-1">
                                    <div>
                                        <h4 class="font-black text-slate-900 text-sm">{{ $match['target_title'] }}</h4>
                                        <span class="text-xs text-slate-600 font-medium">Target Dues / Amount: £{{ number_format($match['target_amount'], 2) }}</span>
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="reconcileSuggested({{ $selectedTx->id }}, '{{ $match['match_type'] }}', {{ $match['target_id'] }})"
                                        class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs rounded-xl shadow-md transition flex items-center gap-1.5 whitespace-nowrap cursor-pointer"
                                    >
                                        <span>✅</span>
                                        <span>Reconcile &amp; Mark Paid</span>
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="p-6 text-center bg-slate-50 border border-slate-200 rounded-2xl text-slate-500 text-xs space-y-1">
                                <p class="font-bold">No automatic rule-based match found for this statement line.</p>
                                <p class="text-slate-400">Use the manual lookup drawer below to allocate to a member, vendor bill, or ledger code.</p>
                            </div>
                        @endforelse
                    </div>

                    <!-- Secondary Action Buttons -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
                        <button
                            type="button"
                            wire:click="openManualDrawer({{ $selectedTx->id }})"
                            class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow transition flex items-center gap-2 cursor-pointer"
                        >
                            <span>🔍</span>
                            <span>Manual Lookup &amp; Allocation Drawer</span>
                        </button>

                        <button
                            type="button"
                            wire:click="ignoreLine({{ $selectedTx->id }})"
                            wire:confirm="Mark this bank statement transaction line as ignored?"
                            class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold text-xs rounded-xl transition cursor-pointer"
                        >
                            Ignore Line
                        </button>
                    </div>
                </div>
            @else
                <div class="p-12 bg-white border border-slate-200 rounded-3xl text-center text-slate-400 text-xs italic">
                    Select an unmatched statement line from the left panel to inspect and reconcile.
                </div>
            @endif
        </div>
    </div>

    <!-- Manual Lookup & Allocation Drawer Modal -->
    @if($showManualDrawer && $selectedTx)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-xl w-full p-6 space-y-5 border border-slate-100 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🔍</span>
                        <div>
                            <h3 class="font-black text-slate-900 text-base">Manual Ledger Allocation Drawer</h3>
                            <p class="text-[10px] text-slate-500 font-medium">Allocating: <strong class="text-slate-800">{{ $selectedTx->raw_description }}</strong> (£{{ number_format(abs($selectedTx->amount), 2) }})</p>
                        </div>
                    </div>
                    <button type="button" wire:click="$set('showManualDrawer', false)" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <!-- Allocation Type Selector -->
                <div class="flex items-center gap-2 p-1 bg-slate-100 rounded-xl text-xs font-bold text-slate-700">
                    <button
                        type="button"
                        wire:click="$set('manualAllocationType', 'member_subscription')"
                        class="flex-1 py-1.5 px-3 rounded-lg transition {{ $manualAllocationType === 'member_subscription' ? 'bg-white text-slate-950 shadow-sm' : 'hover:text-slate-900' }}"
                    >
                        Member Dues
                    </button>
                    <button
                        type="button"
                        wire:click="$set('manualAllocationType', 'supplier_bill')"
                        class="flex-1 py-1.5 px-3 rounded-lg transition {{ $manualAllocationType === 'supplier_bill' ? 'bg-white text-slate-950 shadow-sm' : 'hover:text-slate-900' }}"
                    >
                        Vendor Bill
                    </button>
                    <button
                        type="button"
                        wire:click="$set('manualAllocationType', 'ledger_account')"
                        class="flex-1 py-1.5 px-3 rounded-lg transition {{ $manualAllocationType === 'ledger_account' ? 'bg-white text-slate-950 shadow-sm' : 'hover:text-slate-900' }}"
                    >
                        Nominal Code
                    </button>
                </div>

                @if($manualAllocationType !== 'ledger_account')
                    <!-- Search Field -->
                    <div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="manualSearch"
                            placeholder="Search member name or vendor bill..."
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none"
                        />
                    </div>

                    <!-- Candidates List -->
                    <div class="space-y-2 text-xs max-h-60 overflow-y-auto">
                        @forelse($manualCandidates as $cand)
                            <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                                <div>
                                    @if($manualAllocationType === 'member_subscription')
                                        <span class="font-black text-slate-900 block">{{ $cand->member->formatted_rank_name }}</span>
                                        <span class="text-[10px] text-slate-500">Invoice: {{ $cand->invoice_reference }} (Dues: £{{ number_format($cand->balance_due, 2) }})</span>
                                    @else
                                        <span class="font-black text-slate-900 block">{{ $cand->vendor_name }} (Bill: {{ $cand->bill_number }})</span>
                                        <span class="text-[10px] text-slate-500">Bill Amount: £{{ number_format($cand->amount, 2) }}</span>
                                    @endif
                                </div>

                                <button
                                    type="button"
                                    wire:click="executeManualAllocation({{ $cand->id }}, '{{ $manualAllocationType }}')"
                                    class="px-3 py-1 bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-[11px] rounded-lg shadow transition"
                                >
                                    Reconcile
                                </button>
                            </div>
                        @empty
                            <div class="p-4 text-center text-slate-400 italic">No matching records found.</div>
                        @endforelse
                    </div>
                @else
                    <!-- Nominal Ledger Allocation -->
                    <div class="space-y-3 text-xs">
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Nominal Ledger Account Code</label>
                            <select wire:model="manualNominalCode" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none font-bold">
                                <option value="4000">4000 - General Donations &amp; Dues</option>
                                <option value="4100">4100 - Dining &amp; Festive Board Fees</option>
                                <option value="7000">7000 - Rent &amp; Temple Premises</option>
                                <option value="7100">7100 - Catering &amp; Hospitality Dues</option>
                                <option value="8000">8000 - Charity Relief Chest Contribution</option>
                            </select>
                        </div>

                        <div class="pt-2">
                            <button
                                type="button"
                                wire:click="executeManualAllocation(0, 'ledger_account')"
                                class="w-full py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs rounded-xl shadow transition"
                            >
                                Allocate to Nominal Ledger Code
                            </button>
                        </div>
                    </div>
                @endif

                <div class="pt-3 border-t border-slate-100 flex justify-end">
                    <button type="button" wire:click="$set('showManualDrawer', false)" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Cancel</button>
                </div>
            </div>
        </div>
    @endif
</div>
