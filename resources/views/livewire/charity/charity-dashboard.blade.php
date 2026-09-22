<div class="space-y-6">
    <!-- Top Charity Section Sub-Navigation Bar -->
    @include('livewire.charity.navigation', ['clubSlug' => $clubSlug])

    <!-- Header Banner -->
    <div class="p-6 bg-slate-900 dark:bg-slate-700 rounded-3xl text-white shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-blue-500/20 text-blue-400 rounded-2xl border border-blue-500/30">🤝</span>
                <div>
                    <h1 class="text-2xl font-black tracking-tight">Charity Dashboard</h1>
                    <p class="text-xs text-slate-400 mt-1 font-medium">Dual-Custody Meeting Collections, Grant Voting &amp; Relief Chest Integration</p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="openCollectionModal"
                class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-2 cursor-pointer"
            >
                <span>📥</span>
                <span>Record Collection</span>
            </button>

            <button
                type="button"
                wire:click="openGrantModal()"
                class="px-3.5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-2 cursor-pointer"
            >
                <span>🎁</span>
                <span>Propose Grant</span>
            </button>

            <button
                type="button"
                wire:click="exportReliefChestCsv"
                class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl border border-slate-700 transition-all flex items-center gap-2 cursor-pointer"
            >
                <span>📄</span>
                <span>MCF Relief Chest CSV</span>
            </button>

            <button
                type="button"
                wire:click="exportBacsSchedule"
                class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl border border-slate-700 transition-all flex items-center gap-2 cursor-pointer"
            >
                <span>🏦</span>
                <span>BACS Schedule</span>
            </button>
        </div>
    </div>

    <!-- Flash Notifications -->
    @if (session()->has('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-200 text-xs font-semibold rounded-2xl shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✅</span>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Provincial Festival Target Progress Gauge Card -->
    <div class="p-6 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-black text-slate-900 dark:text-white">{{ $target->festival_name }}</h2>
                    <span class="px-2.5 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-900 dark:text-blue-200 text-[10px] font-black rounded-full">Chest Ref: {{ $target->relief_chest_ref }}</span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">Provincial Honor Milestone Target: {{ $cs }}{{ number_format($target->target_amount, 2) }}</p>
            </div>

            <div class="flex items-center gap-3">
                <span class="px-3 py-1 bg-amber-500 text-slate-950 font-black text-xs rounded-full shadow-sm">
                    🏆 Honor Level: {{ $currentHonorTier }}
                </span>
                <button type="button" wire:click="$set('showTargetModal', true)" class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100 underline font-bold">
                    Edit Milestones
                </button>
            </div>
        </div>

        <!-- Progress Bar & Milestones -->
        <div class="space-y-2">
            <div class="flex items-center justify-between text-xs font-black">
                <span class="text-slate-700 dark:text-slate-200">Total Festival Raised: {{ $cs }}{{ number_format($totalRaisedForFestival, 2) }}</span>
                <span class="text-blue-700 dark:text-blue-300 font-extrabold">{{ $targetPercentage }}% Complete</span>
            </div>

            <!-- Gauge Progress Track -->
            <div class="w-full bg-slate-100 dark:bg-slate-800 h-5 rounded-full overflow-hidden p-1 border border-slate-200 dark:border-slate-800 relative">
                <div class="bg-gradient-to-r from-amber-500 via-blue-600 to-blue-600 h-full rounded-full transition-all duration-500 shadow" style="width: {{ $targetPercentage }}%"></div>
            </div>

            <!-- Milestone Markers Bar -->
            <div class="grid grid-cols-4 gap-2 pt-2 text-[10px] font-bold text-center">
                <div class="p-2 rounded-xl border {{ $totalRaisedForFestival >= $target->bronze_tier ? 'bg-amber-100/70 dark:bg-amber-900/70 border-amber-300 dark:border-amber-700/60 text-amber-950 dark:text-amber-100' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-400' }}">
                    <span>🥉 Bronze</span>
                    <span class="block font-black text-[11px]">{{ $cs }}{{ number_format($target->bronze_tier, 0) }}</span>
                </div>
                <div class="p-2 rounded-xl border {{ $totalRaisedForFestival >= $target->silver_tier ? 'bg-slate-200 dark:bg-slate-700 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-400' }}">
                    <span>🥈 Silver</span>
                    <span class="block font-black text-[11px]">{{ $cs }}{{ number_format($target->silver_tier, 0) }}</span>
                </div>
                <div class="p-2 rounded-xl border {{ $totalRaisedForFestival >= $target->gold_tier ? 'bg-yellow-100 dark:bg-yellow-900/40 border-yellow-300 dark:border-yellow-700/60 text-yellow-950 dark:text-yellow-100' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-400' }}">
                    <span>🥇 Gold</span>
                    <span class="block font-black text-[11px]">{{ $cs }}{{ number_format($target->gold_tier, 0) }}</span>
                </div>
                <div class="p-2 rounded-xl border {{ $totalRaisedForFestival >= $target->platinum_tier ? 'bg-blue-100 dark:bg-blue-900/40 border-blue-300 dark:border-blue-700/60 text-blue-950 dark:text-blue-100' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-400' }}">
                    <span>💎 Platinum</span>
                    <span class="block font-black text-[11px]">{{ $cs }}{{ number_format($target->platinum_tier, 0) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Metric KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Raised to Date</span>
            <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ $cs }}{{ number_format($totalRaisedForFestival, 2) }}</div>
            <span class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 block">Collections &amp; Member Giving</span>
        </div>

        <div class="p-5 bg-amber-50/60 dark:bg-amber-950/60 border border-amber-200/80 dark:border-amber-800/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-200">Meeting Alms &amp; Raffles</span>
            <div class="text-2xl font-black text-amber-950 dark:text-amber-100 mt-1">{{ $cs }}{{ number_format($totalCollectionsAmount, 2) }}</div>
            <span class="text-[10px] text-amber-700 dark:text-amber-300 mt-1 block">Dual-custody verified takings</span>
        </div>

        <div class="p-5 bg-blue-50/60 dark:bg-blue-950/60 border border-blue-200/80 dark:border-blue-800/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-blue-800 dark:text-blue-200">Grants Disbursed</span>
            <div class="text-2xl font-black text-blue-950 dark:text-blue-100 mt-1">{{ $cs }}{{ number_format($totalGrantsDisbursed, 2) }}</div>
            <span class="text-[10px] text-blue-700 dark:text-blue-300 mt-1 block">Approved relief grants paid</span>
        </div>

        <div class="p-5 bg-blue-50/60 dark:bg-blue-950/60 border border-blue-200/80 dark:border-blue-800/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-blue-800 dark:text-blue-200">Festival Jewel Holders</span>
            <div class="text-2xl font-black text-blue-950 dark:text-blue-100 mt-1">{{ $memberGivingRecords->where('qualifies_for_jewel', true)->count() }}</div>
            <span class="text-[10px] text-blue-700 dark:text-blue-300 mt-1 block">Brethren qualifying for Festival Jewel</span>
        </div>
    </div>

    <!-- Gift Aid & Relief Chest Automated Reconciliation Summary Banner -->
    <div class="p-5 bg-gradient-to-r from-amber-500/10 via-blue-500/10 to-blue-500/10 border border-amber-300/80 dark:border-amber-700/80 rounded-3xl shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="p-3 bg-amber-500/20 text-amber-800 rounded-2xl text-xl">🏛️</span>
            <div>
                <h3 class="font-black text-slate-900 dark:text-white text-sm">Automated Gift Aid &amp; Relief Chest Reconciliation Position</h3>
                <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5">
                    Pending Gift Aid 25% Tax Reclaim: <strong class="text-amber-900 dark:text-amber-200 font-black">{{ $giftAidSummary['formatted_pending_gift_aid'] }}</strong>
                    ({{ $giftAidSummary['pending_claim_count'] }} collection batches pending)
                    • Net Relief Chest Balance: <strong class="text-blue-900 dark:text-blue-200 font-black">{{ $giftAidSummary['formatted_net_relief_chest_balance'] }}</strong>
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('admin.accounting.index', ['clubSlug' => $club->slug, 'tab' => 'reconciliation']) }}"
                class="px-4 py-2 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-extrabold text-xs rounded-xl shadow-md transition-all flex items-center gap-1.5 cursor-pointer"
            >
                <span>⚡ Open Reconciliation Workspace</span>
            </a>
        </div>
    </div>

    <!-- Two-Column Layout: Meeting Collections vs Charity Grants -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Column 1: Recent Dual-Custody Meeting Collections -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-lg">💰</span>
                    <h3 class="font-black text-slate-900 dark:text-white text-sm">Meeting Night Collections</h3>
                </div>
                <button type="button" wire:click="openCollectionModal" class="text-xs text-amber-600 dark:text-amber-400 hover:underline font-bold">+ Record Collection</button>
            </div>

            <div class="space-y-3">
                @forelse($collections as $col)
                    <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-0.5 text-[10px] font-extrabold rounded-full border {{ $col->collection_type->badgeClasses() }}">
                                {{ $col->collection_type->label() }}
                            </span>
                            <span class="font-black text-slate-900 dark:text-white text-sm">{{ $cs }}{{ number_format($col->total_amount, 2) }}</span>
                        </div>

                        <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 pt-1 border-t border-slate-200/60 dark:border-slate-800/60">
                            <span>Cash: {{ $cs }}{{ number_format($col->cash_amount, 2) }} | Cheque: {{ $cs }}{{ number_format($col->cheque_amount, 2) }}</span>
                            <span>{{ $col->created_at ? $col->created_at->format('d M Y') : '' }}</span>
                        </div>

                        <div class="flex items-center gap-2 text-[10px] text-slate-600 dark:text-slate-300 font-semibold bg-white dark:bg-slate-900 p-2 rounded-xl border border-slate-200/80 dark:border-slate-800/80">
                            <span>🔐 Counter: <strong>{{ $col->countedBy?->last_name ?: 'Charity Steward' }}</strong></span>
                            <span>| Witness: <strong>{{ $col->witnessedBy?->last_name ?: 'Assistant DC' }}</strong></span>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-slate-400 text-xs italic bg-slate-50 dark:bg-slate-800/50 rounded-2xl">
                        No meeting collections recorded. Click "Record Collection" to log alms or raffle takings.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Column 2: Charity Grants & Alms Disbursements -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🎁</span>
                    <h3 class="font-black text-slate-900 dark:text-white text-sm">Charity Grants &amp; Relief Voting</h3>
                </div>
                <button type="button" wire:click="openGrantModal()" class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-bold">+ Propose Grant</button>
            </div>

            <div class="space-y-3">
                @forelse($grants as $grant)
                    <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-2 text-xs">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h4 class="font-black text-slate-900 dark:text-white text-sm">{{ $grant->recipient_name }}</h4>
                                <p class="text-[11px] text-slate-600 dark:text-slate-300 mt-0.5 line-clamp-1">{{ $grant->purpose }}</p>
                            </div>
                            <span class="font-black text-blue-950 dark:text-blue-100 text-sm whitespace-nowrap">{{ $cs }}{{ number_format($grant->amount, 2) }}</span>
                        </div>

                        <div class="flex items-center gap-2 text-[10px] text-slate-500 dark:text-slate-400 pt-1 flex-wrap">
                            <span>Proposed: <strong class="text-slate-800 dark:text-slate-100">{{ $grant->proposer?->formatted_rank_name ?: 'Not specified' }}</strong></span>
                            <span>•</span>
                            <span>Seconded: <strong class="text-slate-800 dark:text-slate-100">{{ $grant->seconder?->formatted_rank_name ?: 'Pending Seconder' }}</strong></span>
                            @if($grant->committeeMeeting)
                                <span>•</span>
                                <span class="bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 font-bold px-1.5 py-0.5 rounded border border-blue-100 dark:border-blue-900/40">
                                    🏛️ {{ $grant->committeeMeeting->title }}
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 dark:border-slate-800/60">
                            <span class="px-2.5 py-0.5 text-[10px] font-extrabold rounded-full border {{ $grant->approval_status->badgeClasses() }}">
                                {{ $grant->approval_status->label() }}
                            </span>

                            <!-- Status Advancement Buttons -->
                            <div class="flex items-center gap-1">
                                @if($grant->approval_status->value === 'proposed')
                                    <button type="button" wire:click="updateGrantStatus({{ $grant->id }}, 'committee_approved')" class="px-2 py-1 bg-blue-600 text-white font-bold text-[10px] rounded">Approve Committee</button>
                                @elseif($grant->approval_status->value === 'committee_approved')
                                    <button type="button" wire:click="updateGrantStatus({{ $grant->id }}, 'lodge_voted')" class="px-2 py-1 bg-amber-600 text-white font-bold text-[10px] rounded">Lodge Voted</button>
                                @elseif($grant->approval_status->value === 'lodge_voted')
                                    <button type="button" wire:click="updateGrantStatus({{ $grant->id }}, 'disbursed')" class="px-2 py-1 bg-emerald-600 text-white font-bold text-[10px] rounded">Disburse Payment</button>
                                @else
                                    <span class="text-[10px] text-emerald-700 dark:text-emerald-300 font-bold">Disbursed ✓</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-slate-400 text-xs italic bg-slate-50 dark:bg-slate-800/50 rounded-2xl">
                        No charity grant disbursements recorded.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Member Festival Giving Roster & Jewel Qualification Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2">
                <span class="text-lg">🏅</span>
                <h3 class="font-black text-slate-900 dark:text-white text-sm">Member Festival Giving &amp; Jewel Qualifications</h3>
            </div>
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Confidential Stewardship Roster</span>
        </div>

        <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-4">Member Name &amp; Rank</th>
                        <th class="py-3 px-4 text-right">Regular Giving / mo</th>
                        <th class="py-3 px-4 text-right">Total Donated to Date</th>
                        <th class="py-3 px-4">Jewel Qualification</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200">
                    @foreach($activeMembers as $m)
                        @php
                            $giving = $memberGivingRecords[$m->id] ?? null;
                            $donated = $giving ? (float)$giving->total_donated_to_date : 0.0;
                            $hasJewel = $giving ? $giving->qualifies_for_jewel : false;
                            $hasBar = $giving ? $giving->qualifies_for_bar : false;
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                                {{ $m->formatted_rank_name }}
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-slate-800 dark:text-slate-100">
                                {{ $giving && $giving->regular_giving_amount > 0 ? $cs . number_format($giving->regular_giving_amount, 2) : '—' }}
                            </td>
                            <td class="py-3 px-4 text-right font-black text-blue-950 dark:text-blue-100">
                                {{ $cs }}{{ number_format($donated, 2) }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-1.5">
                                    @if($hasJewel)
                                        <span class="px-2 py-0.5 bg-amber-100 dark:bg-amber-900/40 text-amber-950 dark:text-amber-100 border border-amber-300 dark:border-amber-700/60 rounded font-black text-[10px]">
                                            🏅 Festival Jewel
                                        </span>
                                    @endif
                                    @if($hasBar)
                                        <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-950 dark:text-blue-100 border border-blue-300 dark:border-blue-700/60 rounded font-black text-[10px]">
                                            🎗️ Honor Bar
                                        </span>
                                    @endif
                                    @if(!$hasJewel && !$hasBar)
                                        <span class="text-slate-400 text-[10px] italic">Standard Giving</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <button
                                    type="button"
                                    wire:click="openGivingModal({{ $m->id }})"
                                    class="px-2.5 py-1 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold text-[10px] rounded-lg transition"
                                >
                                    Edit Giving
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Reconciled Gift Aid & Donations Detailed Audit Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl p-6 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2">
                <span class="text-xl">📜</span>
                <div>
                    <h3 class="font-black text-slate-900 dark:text-white text-base">Reconciled Gift Aid &amp; Donations Detailed Audit</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Breakdown of meeting collections, donors, 25% tax reclaims, and bank credit reconciliation status.</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a
                    href="{{ route('admin.accounting.giftaid.export_schedule', $club->slug) }}"
                    target="_blank"
                    class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 font-extrabold text-xs rounded-xl border border-slate-300 dark:border-slate-700 transition flex items-center gap-1.5"
                >
                    <span>📥 Export HMRC CSV</span>
                </a>
                <a
                    href="{{ route('admin.accounting.index', ['clubSlug' => $club->slug, 'tab' => 'reconciliation']) }}"
                    class="px-4 py-2 bg-blue-700 hover:bg-blue-800 text-white font-extrabold text-xs rounded-xl shadow-md transition flex items-center gap-1.5"
                >
                    <span>⚡ Accounting Reconciliation</span>
                </a>
            </div>
        </div>

        <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-2xs">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-900 dark:bg-slate-700 text-white text-[10px] font-extrabold uppercase tracking-wider">
                        <th class="py-3 px-4">Collection Date</th>
                        <th class="py-3 px-4">Donor / Person</th>
                        <th class="py-3 px-4">Collection Type</th>
                        <th class="py-3 px-4 text-right">Donation Total</th>
                        <th class="py-3 px-4 text-right">25% Gift Aid</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4">Matched Bank Deposit</th>
                        <th class="py-3 px-4">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-800 dark:text-slate-100 font-medium">
                    @forelse($reconciledDonations['collections'] ?? [] as $col)
                        <tr class="hover:bg-blue-50/40 dark:hover:bg-blue-950/40 transition-colors">
                            <td class="py-3 px-4 font-mono text-[11px] text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                {{ $col['created_at'] }}
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                👤 {{ $col['donor_name'] }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="px-2.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-[10px]">
                                    {{ $col['collection_type'] }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white whitespace-nowrap">
                                {{ $col['formatted_total'] }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-extrabold text-blue-700 dark:text-blue-300 whitespace-nowrap">
                                {{ $col['formatted_gift_aid'] }}
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider inline-block {{ $col['gift_aid_status'] === 'reconciled' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700/60' : ($col['gift_aid_status'] === 'claimed' ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-300 dark:border-blue-700/60' : 'bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700/60') }}">
                                    @if($col['gift_aid_status'] === 'reconciled')
                                        ✓ Reconciled
                                    @elseif($col['gift_aid_status'] === 'claimed')
                                        ⚡ Claimed
                                    @else
                                        ⏳ Pending
                                    @endif
                                </span>
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300 max-w-xs">
                                @if(!empty($col['bank_transaction']))
                                    <div class="p-2 bg-emerald-50/70 dark:bg-emerald-950/70 border border-emerald-200 dark:border-emerald-800/60 rounded-lg text-[11px]">
                                        <div class="font-extrabold text-emerald-900 dark:text-emerald-200">Credit: {{ $col['bank_transaction']['formatted_amount'] }} ({{ $col['bank_transaction']['transaction_date'] }})</div>
                                        <div class="text-[10px] text-emerald-800 dark:text-emerald-200 truncate" title="{{ $col['bank_transaction']['raw_description'] }}">
                                            {{ $col['bank_transaction']['raw_description'] }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-slate-400 italic text-[11px]">Unlinked bank credit</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-slate-500 dark:text-slate-400 text-[11px] max-w-xs truncate" title="{{ $col['notes'] }}">
                                {{ $col['notes'] ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-400 italic">No charity collection records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Dual-Custody Collection Recorder Modal -->
    @if($showCollectionModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-5 border border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">💰</span>
                        <h3 class="font-black text-slate-900 dark:text-white text-base">Record Meeting Night Collection</h3>
                    </div>
                    <button type="button" wire:click="$set('showCollectionModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg">✕</button>
                </div>

                <form wire:submit.prevent="recordCollection" class="space-y-4 text-xs">
                    <div>
                        <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Collection Type *</label>
                        <select wire:model="collection_type" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            @foreach($collectionTypes as $ct)
                                <option value="{{ $ct->value }}">{{ $ct->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Cash Amount ({{ $cs }}) *</label>
                            <input type="number" step="0.01" wire:model="cash_amount" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none" required />
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Cheque Amount ({{ $cs }}) *</label>
                            <input type="number" step="0.01" wire:model="cheque_amount" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none" required />
                        </div>
                    </div>

                    <!-- Dual-Custody Verification -->
                    <div class="p-3.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-2xl space-y-3">
                        <span class="font-black text-amber-950 dark:text-amber-100 block text-[11px]">🔐 Dual-Custody Verification Required:</span>

                        <div>
                            <label class="font-bold text-slate-800 dark:text-slate-100 block mb-1">Counter (e.g. Charity Steward) *</label>
                            <select wire:model="counted_by_member_id" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none" required>
                                <option value="">Select Counter...</option>
                                @foreach($activeMembers as $m)
                                    <option value="{{ $m->id }}">{{ $m->formatted_rank_name }}</option>
                                @endforeach
                            </select>
                            @error('counted_by_member_id') <span class="text-rose-600 dark:text-rose-400 text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="font-bold text-slate-800 dark:text-slate-100 block mb-1">Witness (e.g. Assistant DC) *</label>
                            <select wire:model="witnessed_by_member_id" class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none" required>
                                <option value="">Select Witness...</option>
                                @foreach($activeMembers as $m)
                                    <option value="{{ $m->id }}">{{ $m->formatted_rank_name }}</option>
                                @endforeach
                            </select>
                            @error('witnessed_by_member_id') <span class="text-rose-600 dark:text-rose-400 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showCollectionModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-xl shadow-md transition">Save Collection</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Charity Grant Proposal Modal -->
    @if($showGrantModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-5 border border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🎁</span>
                        <h3 class="font-black text-slate-900 dark:text-white text-base">Propose Charity Grant</h3>
                    </div>
                    <button type="button" wire:click="$set('showGrantModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg">✕</button>
                </div>

                <form wire:submit.prevent="saveGrant" class="space-y-4 text-xs">
                    <div>
                        <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Recipient Name *</label>
                        <input type="text" wire:model="recipient_name" placeholder="e.g. Local Children's Hospice" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" required />
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Grant Purpose *</label>
                        <textarea wire:model="purpose" rows="2" placeholder="Details of charity cause..." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" required></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Amount ({{ $cs }}) *</label>
                            <input type="number" step="0.01" wire:model="grant_amount" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" required />
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Relief Chest No.</label>
                            <input type="text" wire:model="relief_chest_number" placeholder="Optional" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Proposed By (Proposer)</label>
                            <select wire:model="proposer_member_id" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option value="">Select Proposer...</option>
                                @foreach($activeMembers as $m)
                                    <option value="{{ $m->id }}">{{ $m->formatted_rank_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Seconded By (Seconder)</label>
                            <select wire:model="seconder_member_id" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option value="">Select Seconder...</option>
                                @foreach($activeMembers as $m)
                                    <option value="{{ $m->id }}">{{ $m->formatted_rank_name }}</option>
                                @endforeach
                            </select>
                            @error('seconder_member_id') <span class="text-rose-600 dark:text-rose-400 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Logged Committee Meeting</label>
                        <select wire:model="committee_meeting_id" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="">Assign to Committee Meeting (Optional)...</option>
                            @foreach($committeeMeetings as $cm)
                                <option value="{{ $cm->id }}">{{ $cm->title }} ({{ $cm->meeting_date ? $cm->meeting_date->format('d M Y') : 'TBD' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Approval Stage</label>
                        <select wire:model="approval_status" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            @foreach($grantStatuses as $gs)
                                <option value="{{ $gs->value }}">{{ $gs->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showGrantModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-blue-700 hover:bg-blue-800 text-white font-black rounded-xl shadow-md transition">Save Grant Proposal</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Member Festival Giving Modal -->
    @if($showGivingModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-5 border border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🏅</span>
                        <h3 class="font-black text-slate-900 dark:text-white text-base">Edit Member Festival Giving</h3>
                    </div>
                    <button type="button" wire:click="$set('showGivingModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg">✕</button>
                </div>

                <form wire:submit.prevent="saveMemberGiving" class="space-y-4 text-xs">
                    <div>
                        <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Monthly Regular Giving Commitment ({{ $cs }})</label>
                        <input type="number" step="0.01" wire:model="regular_giving_amount" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" />
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Total Donated to Date ({{ $cs }}) *</label>
                        <input type="number" step="0.01" wire:model="total_donated_to_date" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" required />
                    </div>

                    <div class="space-y-2 p-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 rounded-xl">
                        <label class="flex items-center gap-2 font-bold text-blue-950 dark:text-blue-100 cursor-pointer">
                            <input type="checkbox" wire:model="qualifies_for_jewel" class="rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
                            <span>Qualifies for Festival Jewel (>= {{ $cs }}250)</span>
                        </label>

                        <label class="flex items-center gap-2 font-bold text-blue-950 dark:text-blue-100 cursor-pointer">
                            <input type="checkbox" wire:model="qualifies_for_bar" class="rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
                            <span>Qualifies for Honor Bar (>= {{ $cs }}500)</span>
                        </label>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showGivingModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-blue-900 hover:bg-blue-800 text-white font-black rounded-xl shadow-md transition">Save Member Giving</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
