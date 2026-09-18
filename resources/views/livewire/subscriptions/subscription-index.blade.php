<div class="space-y-6">
    <!-- Member Management Domain Unified Navigation -->
    <div class="flex items-center gap-2 p-1.5 bg-slate-200/80 rounded-2xl w-fit text-xs font-bold border border-slate-300/60 shadow-inner">
        <a
            href="{{ route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 hover:text-slate-900 hover:bg-white/60"
        >
            <span>👥</span>
            <span>Members Roster</span>
        </a>

        <a
            href="{{ route('admin.officers.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 hover:text-slate-900 hover:bg-white/60"
        >
            <span>👔</span>
            <span>Annual Officer Rosters &amp; History</span>
        </a>

        <a
            href="{{ route('admin.club_acc.candidates.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 hover:text-slate-900 hover:bg-white/60"
        >
            <span>📋</span>
            <span>Candidates (Form P Vetting)</span>
        </a>

        <a
            href="{{ route('admin.club_acc.subscriptions.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 bg-slate-900 text-white shadow-md font-black"
        >
            <span>💳</span>
            <span>Subscriptions &amp; Dues</span>
        </a>
    </div>

    <!-- Header Banner -->
    <div class="p-6 bg-slate-900 rounded-3xl text-white shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-amber-500/20 text-amber-400 rounded-2xl border border-amber-500/30">💳</span>
                <div>
                    <h1 class="text-2xl font-black tracking-tight">Subscriptions</h1>
                    <p class="text-xs text-slate-400 mt-1 font-medium">UGLE Rule 181 Arrears Audit &amp; Accounts Receivable Ledger Integration</p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="openBillingModal"
                class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-2 cursor-pointer"
            >
                <span>⚡</span>
                <span>Run Annual Billing</span>
            </button>

            <button
                type="button"
                wire:click="runRule181ArrearsAudit"
                class="px-3.5 py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-2 cursor-pointer"
            >
                <span>⚠️</span>
                <span>Rule 181 Arrears Audit</span>
            </button>

            <button
                type="button"
                wire:click="openTierModal()"
                class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl border border-slate-700 transition-all flex items-center gap-2 cursor-pointer"
            >
                <span>⚙️</span>
                <span>Manage Fee Tiers</span>
            </button>
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

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 bg-white border border-slate-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Dues Billed ({{ $selectedYear }})</span>
            <div class="text-2xl font-black text-slate-900 mt-1">£{{ number_format($totalBilled, 2) }}</div>
            <span class="text-[10px] text-slate-500 mt-1 block">Total annual subscription invoicing</span>
        </div>
        <div class="p-5 bg-emerald-50/60 border border-emerald-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Dues Collected</span>
            <div class="text-2xl font-black text-emerald-950 mt-1">£{{ number_format($totalCollected, 2) }}</div>
            <span class="text-[10px] text-emerald-700 mt-1 block">Payments received &amp; reconciled</span>
        </div>
        <div class="p-5 bg-amber-50/60 border border-amber-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Outstanding Balance</span>
            <div class="text-2xl font-black text-amber-950 mt-1">£{{ number_format($totalOutstanding, 2) }}</div>
            <span class="text-[10px] text-amber-700 mt-1 block">Pending member payments</span>
        </div>
        <div class="p-5 bg-rose-50/60 border border-rose-200/80 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-rose-800">Rule 181 Arrears</span>
                <button type="button" wire:click="$set('showArrearsModal', true)" class="text-[10px] text-rose-700 underline font-bold">Review</button>
            </div>
            <div class="text-2xl font-black text-rose-950 mt-1">{{ $arrearsCount }}</div>
            <span class="text-[10px] text-rose-700 mt-1 block">Members past statutory grace period</span>
        </div>
    </div>

    <!-- Membership Fee Tiers & Pricing Grid -->
    <div class="bg-white rounded-3xl border border-slate-200/80 p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="font-black text-slate-900 text-sm flex items-center gap-2">
                    <span>💳</span>
                    <span>Membership Fee Tiers &amp; Pricing</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Active fee structures for annual subscription billing and member dues tiers.</p>
            </div>
            <button
                type="button"
                wire:click="openTierModal()"
                class="px-3.5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-sm transition-all inline-flex items-center gap-1.5 cursor-pointer"
            >
                <span>+</span>
                <span>Add Fee Tier</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @forelse($tiers as $t)
                <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-2xl flex flex-col justify-between space-y-3">
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="font-extrabold text-slate-900 text-sm">{{ $t->name }}</span>
                            <span class="font-black text-amber-950 text-base">£{{ number_format($t->annual_amount, 2) }}</span>
                        </div>
                        <p class="text-xs text-slate-500">{{ $t->description ?: 'Standard annual membership subscription rate.' }}</p>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 text-xs">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $t->is_active ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-200 text-slate-600' }}">
                            {{ $t->is_active ? 'Active Tier' : 'Inactive' }}
                        </span>
                        <button type="button" wire:click="openTierModal({{ $t->id }})" class="text-xs text-amber-700 hover:underline font-bold">
                            Edit Tier ✏️
                        </button>
                    </div>
                </div>
            @empty
                <div class="md:col-span-3 p-6 text-center text-slate-400 text-xs italic bg-slate-50 rounded-2xl">
                    No custom fee tiers configured. Default £160.00 rate will apply to annual billing runs.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="p-4 bg-white border border-slate-200/80 rounded-2xl shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="relative w-full md:w-80">
            <input
                type="search"
                name="sub_search_query"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false"
                wire:model.live.debounce.300ms="search"
                placeholder="Search member name, email..."
                class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500"
            />
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </div>

        <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <div class="flex items-center gap-1 text-xs">
                <span class="font-bold text-slate-500">Billing Year:</span>
                <select
                    wire:model.live="selectedYear"
                    class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500 cursor-pointer"
                >
                    @foreach([2024, 2025, 2026, 2027] as $yr)
                        <option value="{{ $yr }}">{{ $yr }} / {{ $yr + 1 }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-1 text-xs">
                <span class="font-bold text-slate-500">Status:</span>
                <select
                    wire:model.live="statusFilter"
                    class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500 cursor-pointer"
                >
                    <option value="">All Statuses</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Subscriptions Roster Table -->
    <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-black text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4">Member &amp; Rank</th>
                        <th class="py-3.5 px-4">Subscription Tier</th>
                        <th class="py-3.5 px-4">Invoice Ref</th>
                        <th class="py-3.5 px-4 text-right">Amount Due</th>
                        <th class="py-3.5 px-4 text-right">Amount Paid</th>
                        <th class="py-3.5 px-4 text-right">Balance</th>
                        <th class="py-3.5 px-4">Due Date</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                    @forelse ($subscriptions as $sub)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-4 font-bold text-slate-900">
                                <a href="{{ route('admin.club_acc.members.show', ['clubSlug' => $club->slug, 'memberId' => $sub->member->id]) }}" class="hover:underline hover:text-amber-600">
                                    {{ $sub->member->formatted_rank_name }}
                                </a>
                                @if($sub->member->current_office && $sub->member->current_office->value !== 'member')
                                    <span class="block text-[10px] text-amber-700 font-semibold">{{ $sub->member->current_office->label() }}</span>
                                @endif
                            </td>

                            <td class="py-3 px-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-slate-800">
                                        {{ $sub->tier ? $sub->tier->name : ($sub->member->annual_dues_override ? 'Custom Dues' : 'Standard Dues') }}
                                    </span>
                                </div>
                            </td>

                            <td class="py-3 px-4 font-mono text-[11px] text-slate-600">
                                {{ $sub->invoice_reference ?: '—' }}
                                @if($sub->member->customerAccount)
                                    <span class="inline-block px-1.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded text-[9px] font-bold" title="Linked to Accounting Contact">Linked ✓</span>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-right font-semibold text-slate-900">
                                £{{ number_format($sub->amount_due, 2) }}
                            </td>

                            <td class="py-3 px-4 text-right font-bold text-emerald-700">
                                £{{ number_format($sub->amount_paid, 2) }}
                            </td>

                            <td class="py-3 px-4 text-right font-black {{ $sub->balance_due > 0 ? 'text-amber-700' : 'text-slate-400' }}">
                                £{{ number_format($sub->balance_due, 2) }}
                            </td>

                            <td class="py-3 px-4 text-slate-600 font-medium">
                                {{ $sub->due_date ? $sub->due_date->format('d M Y') : '—' }}
                            </td>

                            <td class="py-3 px-4">
                                <span class="px-2.5 py-1 text-[10px] font-extrabold rounded-full border {{ $sub->status->badgeClasses() }}">
                                    {{ $sub->status->label() }}
                                </span>
                            </td>

                            <td class="py-3 px-4 text-right space-x-1">
                                @if($sub->status->isOutstanding())
                                    <button
                                        type="button"
                                        wire:click="openPaymentModal({{ $sub->id }})"
                                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] rounded-lg shadow-sm transition-all"
                                    >
                                        Record Payment
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="waiveDues({{ $sub->id }})"
                                        wire:confirm="Waive subscription dues for {{ $sub->member->full_name }}?"
                                        class="px-2 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold text-[10px] rounded-lg transition"
                                        title="Waive Dues"
                                    >
                                        Waive
                                    </button>
                                @else
                                    <span class="text-[10px] text-slate-400 italic">Settled</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-400 text-xs">
                                No subscription records found for {{ $selectedYear }}. Click <strong class="text-slate-700">"Run Annual Billing"</strong> to generate annual invoices.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($subscriptions->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $subscriptions->links() }}
            </div>
        @endif
    </div>

    <!-- Annual Billing Run Modal -->
    @if($showBillingModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-5 border border-slate-100">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">⚡</span>
                        <h3 class="font-black text-slate-900 text-base">Run Annual Subscription Billing</h3>
                    </div>
                    <button type="button" wire:click="$set('showBillingModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-xs text-amber-900 space-y-1">
                    <p class="font-bold">This batch action will generate annual dues invoices for all active lodge members who do not yet have an invoice for the selected billing year.</p>
                </div>

                <form wire:submit.prevent="runAnnualBilling" class="space-y-4 text-xs">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Billing Year *</label>
                        <select wire:model="billing_year" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            @foreach([2024, 2025, 2026, 2027] as $yr)
                                <option value="{{ $yr }}">{{ $yr }} / {{ $yr + 1 }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Due Date *</label>
                        <input type="date" wire:model="billing_due_date" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" required />
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showBillingModal', false)" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-xl shadow-md transition">Generate Annual Invoices</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Fee Tier Manager Modal -->
    @if($showTierModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 space-y-5 border border-slate-100 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">⚙️</span>
                        <h3 class="font-black text-slate-900 text-base">Subscription Fee Tiers</h3>
                    </div>
                    <button type="button" wire:click="$set('showTierModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <!-- Existing Tiers List -->
                <div class="space-y-2">
                    <span class="font-bold text-slate-800 text-xs block">Active Lodge Fee Structures:</span>
                    @forelse($tiers as $t)
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between text-xs">
                            <div>
                                <span class="font-black text-slate-900 block">{{ $t->name }}</span>
                                <span class="text-slate-500 font-medium text-[11px]">{{ $t->description ?: 'Standard fee structure' }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-black text-amber-900 text-sm">£{{ number_format($t->annual_amount, 2) }}</span>
                                <button type="button" wire:click="openTierModal({{ $t->id }})" class="text-blue-600 hover:underline font-bold text-[11px]">Edit</button>
                            </div>
                        </div>
                    @empty
                        <div class="p-3 bg-slate-50 text-slate-400 text-center text-xs rounded-xl">No custom tiers configured. Default £160.00 will apply.</div>
                    @endforelse
                </div>

                <form wire:submit.prevent="saveTier" class="space-y-4 text-xs pt-4 border-t border-slate-100">
                    <span class="font-black text-slate-900 block text-sm">{{ $tierId ? 'Edit Fee Tier' : 'Add New Fee Tier' }}</span>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Tier Name *</label>
                        <input type="text" wire:model="tier_name" placeholder="e.g. Full Member, Country Member" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" required />
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Annual Amount (£) *</label>
                        <input type="number" step="0.01" wire:model="tier_amount" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" required />
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Description</label>
                        <input type="text" wire:model="tier_description" placeholder="Optional details..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" />
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" wire:model="tier_active" id="tier_active_cb" class="rounded text-amber-600 focus:ring-amber-500" />
                        <label for="tier_active_cb" class="font-bold text-slate-700 cursor-pointer">Active Tier</label>
                    </div>

                    <div class="pt-3 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showTierModal', false)" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Close</button>
                        <button type="submit" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl shadow-md transition">Save Tier</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Payment Recording Modal -->
    @if($showPaymentModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-5 border border-slate-100">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">💳</span>
                        <h3 class="font-black text-slate-900 text-base">Record Dues Payment</h3>
                    </div>
                    <button type="button" wire:click="$set('showPaymentModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <form wire:submit.prevent="recordPayment" class="space-y-4 text-xs">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Payment Amount (£) *</label>
                        <input type="number" step="0.01" wire:model="payment_amount" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none" required />
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Payment Reference (BACS / Cheque / Cash)</label>
                        <input type="text" wire:model="payment_reference" placeholder="e.g. BACS-20260916" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none" />
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Notes</label>
                        <textarea wire:model="payment_notes" rows="2" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none" placeholder="Optional notes..."></textarea>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showPaymentModal', false)" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl shadow-md transition">Record Payment</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Confidential Rule 181 Arrears Review Drawer Modal -->
    @if($showArrearsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-6 space-y-5 border border-slate-100 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">⚠️</span>
                        <div>
                            <h3 class="font-black text-slate-900 text-base">UGLE Rule 181 Confidential Arrears Review</h3>
                            <p class="text-[10px] text-slate-500 font-medium">Confidential list for Almoner &amp; Secretary before statutory exclusion proceedings.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="$set('showArrearsModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl text-xs text-rose-900 space-y-1">
                    <p class="font-bold">UGLE Rule 181 Procedure:</p>
                    <p>Members overdue past the 90-day grace period must be reviewed by the Almoner to confirm if brotherly relief or hardship support is required before formal exclusion warnings are dispatched.</p>
                </div>

                <div class="space-y-3 text-xs">
                    @forelse($arrearsSubscriptions as $arrSub)
                        <div class="p-4 bg-rose-50/50 border border-rose-200/80 rounded-2xl flex items-center justify-between">
                            <div>
                                <span class="font-extrabold text-slate-900 text-sm block">{{ $arrSub->member->formatted_rank_name }}</span>
                                <span class="text-slate-500 text-[11px] block">Due: {{ $arrSub->due_date ? $arrSub->due_date->format('d M Y') : '—' }} (Overdue past 90 days)</span>
                                <span class="text-rose-800 font-semibold text-[10px]">Invoice Ref: {{ $arrSub->invoice_reference }}</span>
                            </div>
                            <div class="text-right space-y-1">
                                <span class="text-base font-black text-rose-950 block">£{{ number_format($arrSub->balance_due, 2) }}</span>
                                <button
                                    type="button"
                                    wire:click="openPaymentModal({{ $arrSub->id }})"
                                    class="px-2.5 py-1 bg-rose-700 hover:bg-rose-800 text-white font-bold text-[10px] rounded-lg shadow-sm"
                                >
                                    Settle Dues
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-2xl font-bold">
                            No members currently in Rule 181 statutory arrears!
                        </div>
                    @endforelse
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end">
                    <button type="button" wire:click="$set('showArrearsModal', false)" class="px-5 py-2 bg-slate-900 text-white font-black rounded-xl hover:bg-slate-800 transition">Close Review</button>
                </div>
            </div>
        </div>
    @endif
</div>
