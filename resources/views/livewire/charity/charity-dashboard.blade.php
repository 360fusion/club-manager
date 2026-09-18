<div class="space-y-6">
    <!-- Header Banner -->
    <div class="p-6 bg-slate-900 rounded-3xl text-white shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-purple-500/20 text-purple-400 rounded-2xl border border-purple-500/30">🤝</span>
                <div>
                    <h1 class="text-2xl font-black tracking-tight">Charity Steward &amp; Provincial Festival Hub</h1>
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
                class="px-3.5 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-2 cursor-pointer"
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
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold rounded-2xl shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✅</span>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Provincial Festival Target Progress Gauge Card -->
    <div class="p-6 bg-white border border-slate-200/80 rounded-3xl shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-black text-slate-900">{{ $target->festival_name }}</h2>
                    <span class="px-2.5 py-0.5 bg-purple-100 text-purple-900 text-[10px] font-black rounded-full">Chest Ref: {{ $target->relief_chest_ref }}</span>
                </div>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Provincial Honor Milestone Target: £{{ number_format($target->target_amount, 2) }}</p>
            </div>

            <div class="flex items-center gap-3">
                <span class="px-3 py-1 bg-amber-500 text-slate-950 font-black text-xs rounded-full shadow-sm">
                    🏆 Honor Level: {{ $currentHonorTier }}
                </span>
                <button type="button" wire:click="$set('showTargetModal', true)" class="text-xs text-slate-500 hover:text-slate-800 underline font-bold">
                    Edit Milestones
                </button>
            </div>
        </div>

        <!-- Progress Bar & Milestones -->
        <div class="space-y-2">
            <div class="flex items-center justify-between text-xs font-black">
                <span class="text-slate-700">Total Festival Raised: £{{ number_format($totalRaisedForFestival, 2) }}</span>
                <span class="text-purple-700 font-extrabold">{{ $targetPercentage }}% Complete</span>
            </div>

            <!-- Gauge Progress Track -->
            <div class="w-full bg-slate-100 h-5 rounded-full overflow-hidden p-1 border border-slate-200 relative">
                <div class="bg-gradient-to-r from-amber-500 via-purple-600 to-indigo-600 h-full rounded-full transition-all duration-500 shadow" style="width: {{ $targetPercentage }}%"></div>
            </div>

            <!-- Milestone Markers Bar -->
            <div class="grid grid-cols-4 gap-2 pt-2 text-[10px] font-bold text-center">
                <div class="p-2 rounded-xl border {{ $totalRaisedForFestival >= $target->bronze_tier ? 'bg-amber-100/70 border-amber-300 text-amber-950' : 'bg-slate-50 border-slate-200 text-slate-400' }}">
                    <span>🥉 Bronze</span>
                    <span class="block font-black text-[11px]">£{{ number_format($target->bronze_tier, 0) }}</span>
                </div>
                <div class="p-2 rounded-xl border {{ $totalRaisedForFestival >= $target->silver_tier ? 'bg-slate-200 border-slate-300 text-slate-900' : 'bg-slate-50 border-slate-200 text-slate-400' }}">
                    <span>🥈 Silver</span>
                    <span class="block font-black text-[11px]">£{{ number_format($target->silver_tier, 0) }}</span>
                </div>
                <div class="p-2 rounded-xl border {{ $totalRaisedForFestival >= $target->gold_tier ? 'bg-yellow-100 border-yellow-300 text-yellow-950' : 'bg-slate-50 border-slate-200 text-slate-400' }}">
                    <span>🥇 Gold</span>
                    <span class="block font-black text-[11px]">£{{ number_format($target->gold_tier, 0) }}</span>
                </div>
                <div class="p-2 rounded-xl border {{ $totalRaisedForFestival >= $target->platinum_tier ? 'bg-indigo-100 border-indigo-300 text-indigo-950' : 'bg-slate-50 border-slate-200 text-slate-400' }}">
                    <span>💎 Platinum</span>
                    <span class="block font-black text-[11px]">£{{ number_format($target->platinum_tier, 0) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Metric KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 bg-white border border-slate-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Raised to Date</span>
            <div class="text-2xl font-black text-slate-900 mt-1">£{{ number_format($totalRaisedForFestival, 2) }}</div>
            <span class="text-[10px] text-slate-500 mt-1 block">Collections &amp; Member Giving</span>
        </div>

        <div class="p-5 bg-amber-50/60 border border-amber-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Meeting Alms &amp; Raffles</span>
            <div class="text-2xl font-black text-amber-950 mt-1">£{{ number_format($totalCollectionsAmount, 2) }}</div>
            <span class="text-[10px] text-amber-700 mt-1 block">Dual-custody verified takings</span>
        </div>

        <div class="p-5 bg-purple-50/60 border border-purple-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-purple-800">Grants Disbursed</span>
            <div class="text-2xl font-black text-purple-950 mt-1">£{{ number_format($totalGrantsDisbursed, 2) }}</div>
            <span class="text-[10px] text-purple-700 mt-1 block">Approved relief grants paid</span>
        </div>

        <div class="p-5 bg-indigo-50/60 border border-indigo-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-800">Festival Jewel Holders</span>
            <div class="text-2xl font-black text-indigo-950 mt-1">{{ $memberGivingRecords->where('qualifies_for_jewel', true)->count() }}</div>
            <span class="text-[10px] text-indigo-700 mt-1 block">Brethren qualifying for Festival Jewel</span>
        </div>
    </div>

    <!-- Two-Column Layout: Meeting Collections vs Charity Grants -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Column 1: Recent Dual-Custody Meeting Collections -->
        <div class="bg-white border border-slate-200/80 rounded-3xl p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-lg">💰</span>
                    <h3 class="font-black text-slate-900 text-sm">Meeting Night Collections</h3>
                </div>
                <button type="button" wire:click="openCollectionModal" class="text-xs text-amber-600 hover:underline font-bold">+ Record Collection</button>
            </div>

            <div class="space-y-3">
                @forelse($collections as $col)
                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-0.5 text-[10px] font-extrabold rounded-full border {{ $col->collection_type->badgeClasses() }}">
                                {{ $col->collection_type->label() }}
                            </span>
                            <span class="font-black text-slate-900 text-sm">£{{ number_format($col->total_amount, 2) }}</span>
                        </div>

                        <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1 border-t border-slate-200/60">
                            <span>Cash: £{{ number_format($col->cash_amount, 2) }} | Cheque: £{{ number_format($col->cheque_amount, 2) }}</span>
                            <span>{{ $col->created_at ? $col->created_at->format('d M Y') : '' }}</span>
                        </div>

                        <div class="flex items-center gap-2 text-[10px] text-slate-600 font-semibold bg-white p-2 rounded-xl border border-slate-200/80">
                            <span>🔐 Counter: <strong>{{ $col->countedBy?->last_name ?: 'Charity Steward' }}</strong></span>
                            <span>| Witness: <strong>{{ $col->witnessedBy?->last_name ?: 'Assistant DC' }}</strong></span>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-slate-400 text-xs italic bg-slate-50 rounded-2xl">
                        No meeting collections recorded. Click "Record Collection" to log alms or raffle takings.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Column 2: Charity Grants & Alms Disbursements -->
        <div class="bg-white border border-slate-200/80 rounded-3xl p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🎁</span>
                    <h3 class="font-black text-slate-900 text-sm">Charity Grants &amp; Relief Voting</h3>
                </div>
                <button type="button" wire:click="openGrantModal()" class="text-xs text-purple-600 hover:underline font-bold">+ Propose Grant</button>
            </div>

            <div class="space-y-3">
                @forelse($grants as $grant)
                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2 text-xs">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h4 class="font-black text-slate-900 text-sm">{{ $grant->recipient_name }}</h4>
                                <p class="text-[11px] text-slate-600 mt-0.5 line-clamp-1">{{ $grant->purpose }}</p>
                            </div>
                            <span class="font-black text-purple-950 text-sm whitespace-nowrap">£{{ number_format($grant->amount, 2) }}</span>
                        </div>

                        <div class="flex items-center gap-2 text-[10px] text-slate-500 pt-1 flex-wrap">
                            <span>Proposed: <strong class="text-slate-800">{{ $grant->proposer?->formatted_rank_name ?: 'Not specified' }}</strong></span>
                            <span>•</span>
                            <span>Seconded: <strong class="text-slate-800">{{ $grant->seconder?->formatted_rank_name ?: 'Pending Seconder' }}</strong></span>
                            @if($grant->committeeMeeting)
                                <span>•</span>
                                <span class="bg-indigo-50 text-indigo-700 font-bold px-1.5 py-0.5 rounded border border-indigo-100">
                                    🏛️ {{ $grant->committeeMeeting->title }}
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-200/60">
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
                                    <span class="text-[10px] text-emerald-700 font-bold">Disbursed ✓</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-slate-400 text-xs italic bg-slate-50 rounded-2xl">
                        No charity grant disbursements recorded.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Member Festival Giving Roster & Jewel Qualification Table -->
    <div class="bg-white border border-slate-200/80 rounded-3xl p-6 shadow-sm space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="text-lg">🏅</span>
                <h3 class="font-black text-slate-900 text-sm">Member Festival Giving &amp; Jewel Qualifications</h3>
            </div>
            <span class="text-xs font-semibold text-slate-500">Confidential Stewardship Roster</span>
        </div>

        <div class="border border-slate-200 rounded-2xl overflow-hidden">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Member Name &amp; Rank</th>
                        <th class="py-3 px-4 text-right">Regular Giving / mo</th>
                        <th class="py-3 px-4 text-right">Total Donated to Date</th>
                        <th class="py-3 px-4">Jewel Qualification</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @foreach($activeMembers as $m)
                        @php
                            $giving = $memberGivingRecords[$m->id] ?? null;
                            $donated = $giving ? (float)$giving->total_donated_to_date : 0.0;
                            $hasJewel = $giving ? $giving->qualifies_for_jewel : false;
                            $hasBar = $giving ? $giving->qualifies_for_bar : false;
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-4 font-bold text-slate-900">
                                {{ $m->formatted_rank_name }}
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-slate-800">
                                {{ $giving && $giving->regular_giving_amount > 0 ? '£' . number_format($giving->regular_giving_amount, 2) : '—' }}
                            </td>
                            <td class="py-3 px-4 text-right font-black text-purple-950">
                                £{{ number_format($donated, 2) }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-1.5">
                                    @if($hasJewel)
                                        <span class="px-2 py-0.5 bg-amber-100 text-amber-950 border border-amber-300 rounded font-black text-[10px]">
                                            🏅 Festival Jewel
                                        </span>
                                    @endif
                                    @if($hasBar)
                                        <span class="px-2 py-0.5 bg-indigo-100 text-indigo-950 border border-indigo-300 rounded font-black text-[10px]">
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
                                    class="px-2.5 py-1 bg-slate-900 hover:bg-slate-800 text-white font-bold text-[10px] rounded-lg transition"
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

    <!-- Dual-Custody Collection Recorder Modal -->
    @if($showCollectionModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-5 border border-slate-100">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">💰</span>
                        <h3 class="font-black text-slate-900 text-base">Record Meeting Night Collection</h3>
                    </div>
                    <button type="button" wire:click="$set('showCollectionModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <form wire:submit.prevent="recordCollection" class="space-y-4 text-xs">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Collection Type *</label>
                        <select wire:model="collection_type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            @foreach($collectionTypes as $ct)
                                <option value="{{ $ct->value }}">{{ $ct->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Cash Amount (£) *</label>
                            <input type="number" step="0.01" wire:model="cash_amount" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" required />
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Cheque Amount (£) *</label>
                            <input type="number" step="0.01" wire:model="cheque_amount" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" required />
                        </div>
                    </div>

                    <!-- Dual-Custody Verification -->
                    <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-2xl space-y-3">
                        <span class="font-black text-amber-950 block text-[11px]">🔐 Dual-Custody Verification Required:</span>

                        <div>
                            <label class="font-bold text-slate-800 block mb-1">Counter (e.g. Charity Steward) *</label>
                            <select wire:model="counted_by_member_id" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none" required>
                                <option value="">Select Counter...</option>
                                @foreach($activeMembers as $m)
                                    <option value="{{ $m->id }}">{{ $m->formatted_rank_name }}</option>
                                @endforeach
                            </select>
                            @error('counted_by_member_id') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="font-bold text-slate-800 block mb-1">Witness (e.g. Assistant DC) *</label>
                            <select wire:model="witnessed_by_member_id" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none" required>
                                <option value="">Select Witness...</option>
                                @foreach($activeMembers as $m)
                                    <option value="{{ $m->id }}">{{ $m->formatted_rank_name }}</option>
                                @endforeach
                            </select>
                            @error('witnessed_by_member_id') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showCollectionModal', false)" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-xl shadow-md transition">Save Collection</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Charity Grant Proposal Modal -->
    @if($showGrantModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-5 border border-slate-100">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🎁</span>
                        <h3 class="font-black text-slate-900 text-base">Propose Charity Grant</h3>
                    </div>
                    <button type="button" wire:click="$set('showGrantModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <form wire:submit.prevent="saveGrant" class="space-y-4 text-xs">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Recipient Name *</label>
                        <input type="text" wire:model="recipient_name" placeholder="e.g. Local Children's Hospice" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500 focus:outline-none" required />
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Grant Purpose *</label>
                        <textarea wire:model="purpose" rows="2" placeholder="Details of charity cause..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500 focus:outline-none" required></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Amount (£) *</label>
                            <input type="number" step="0.01" wire:model="grant_amount" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500 focus:outline-none" required />
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Relief Chest No.</label>
                            <input type="text" wire:model="relief_chest_number" placeholder="Optional" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500 focus:outline-none" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Proposed By (Proposer)</label>
                            <select wire:model="proposer_member_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500 focus:outline-none">
                                <option value="">Select Proposer...</option>
                                @foreach($activeMembers as $m)
                                    <option value="{{ $m->id }}">{{ $m->formatted_rank_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Seconded By (Seconder)</label>
                            <select wire:model="seconder_member_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500 focus:outline-none">
                                <option value="">Select Seconder...</option>
                                @foreach($activeMembers as $m)
                                    <option value="{{ $m->id }}">{{ $m->formatted_rank_name }}</option>
                                @endforeach
                            </select>
                            @error('seconder_member_id') <span class="text-rose-600 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Logged Committee Meeting</label>
                        <select wire:model="committee_meeting_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500 focus:outline-none">
                            <option value="">Assign to Committee Meeting (Optional)...</option>
                            @foreach($committeeMeetings as $cm)
                                <option value="{{ $cm->id }}">{{ $cm->title }} ({{ $cm->meeting_date ? $cm->meeting_date->format('d M Y') : 'TBD' }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Approval Stage</label>
                        <select wire:model="approval_status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500 focus:outline-none">
                            @foreach($grantStatuses as $gs)
                                <option value="{{ $gs->value }}">{{ $gs->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showGrantModal', false)" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-purple-700 hover:bg-purple-800 text-white font-black rounded-xl shadow-md transition">Save Grant Proposal</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Member Festival Giving Modal -->
    @if($showGivingModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-5 border border-slate-100">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🏅</span>
                        <h3 class="font-black text-slate-900 text-base">Edit Member Festival Giving</h3>
                    </div>
                    <button type="button" wire:click="$set('showGivingModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <form wire:submit.prevent="saveMemberGiving" class="space-y-4 text-xs">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Monthly Regular Giving Commitment (£)</label>
                        <input type="number" step="0.01" wire:model="regular_giving_amount" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Total Donated to Date (£) *</label>
                        <input type="number" step="0.01" wire:model="total_donated_to_date" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none" required />
                    </div>

                    <div class="space-y-2 p-3 bg-indigo-50 border border-indigo-200 rounded-xl">
                        <label class="flex items-center gap-2 font-bold text-indigo-950 cursor-pointer">
                            <input type="checkbox" wire:model="qualifies_for_jewel" class="rounded text-indigo-600 focus:ring-indigo-500" />
                            <span>Qualifies for Festival Jewel (>= £250)</span>
                        </label>

                        <label class="flex items-center gap-2 font-bold text-indigo-950 cursor-pointer">
                            <input type="checkbox" wire:model="qualifies_for_bar" class="rounded text-indigo-600 focus:ring-indigo-500" />
                            <span>Qualifies for Honor Bar (>= £500)</span>
                        </label>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showGivingModal', false)" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-900 hover:bg-indigo-800 text-white font-black rounded-xl shadow-md transition">Save Member Giving</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
