<div class="space-y-6">
    <!-- Top Charity Section Sub-Navigation Bar -->
    @include('livewire.charity.navigation', ['clubSlug' => $clubSlug])

    <!-- Header Banner -->
    <div class="p-6 bg-slate-900 dark:bg-slate-700 rounded-3xl text-white shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-blue-500/20 text-blue-400 rounded-2xl border border-blue-500/30">🏆</span>
                <div>
                    <h1 class="text-2xl font-black tracking-tight">Provincial Festival &amp; Honor Milestones</h1>
                    <p class="text-xs text-slate-400 mt-1 font-medium">Relief Chest Ref: {{ $target->relief_chest_ref }} • Target Milestones &amp; Member Giving Qualifications</p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="$set('showTargetModal', true)"
                class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-2 cursor-pointer"
            >
                <span>⚙️</span>
                <span>Edit Festival Milestones</span>
            </button>
        </div>
    </div>

    <!-- Flash Notifications -->
    @if (session()->has('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-200 text-xs font-semibold rounded-2xl shadow-sm flex items-center gap-2">
            <span>✅</span>
            <span>{{ session('success') }}</span>
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
                    🏆 Current Honor Tier: {{ $currentHonorTier }}
                </span>
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
                <div class="p-3 rounded-xl border {{ $totalRaisedForFestival >= $target->bronze_tier ? 'bg-amber-100/70 dark:bg-amber-900/70 border-amber-300 dark:border-amber-700/60 text-amber-950 dark:text-amber-100' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-400' }}">
                    <span>🥉 Bronze Tier</span>
                    <span class="block font-black text-xs">{{ $cs }}{{ number_format($target->bronze_tier, 0) }}</span>
                </div>
                <div class="p-3 rounded-xl border {{ $totalRaisedForFestival >= $target->silver_tier ? 'bg-slate-200 dark:bg-slate-700 border-slate-400 text-slate-900 dark:text-white' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-400' }}">
                    <span>🥈 Silver Tier</span>
                    <span class="block font-black text-xs">{{ $cs }}{{ number_format($target->silver_tier, 0) }}</span>
                </div>
                <div class="p-3 rounded-xl border {{ $totalRaisedForFestival >= $target->gold_tier ? 'bg-yellow-100 dark:bg-yellow-900/40 border-yellow-300 dark:border-yellow-700/60 text-yellow-950 dark:text-yellow-100' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-400' }}">
                    <span>🥇 Gold Tier</span>
                    <span class="block font-black text-xs">{{ $cs }}{{ number_format($target->gold_tier, 0) }}</span>
                </div>
                <div class="p-3 rounded-xl border {{ $totalRaisedForFestival >= $target->platinum_tier ? 'bg-blue-100 dark:bg-blue-900/40 border-blue-300 dark:border-blue-700/60 text-blue-950 dark:text-blue-100' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-400' }}">
                    <span>💎 Platinum Tier</span>
                    <span class="block font-black text-xs">{{ $cs }}{{ number_format($target->platinum_tier, 0) }}</span>
                </div>
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

        <div class="border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-2xs">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-900 dark:bg-slate-700 text-white text-[10px] font-black uppercase tracking-wider">
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
                                        <span class="text-slate-400 text-[11px] italic">In Progress</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <button type="button" wire:click="openGivingModal({{ $m->id }})" class="px-3 py-1 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-extrabold text-[10px] rounded-lg shadow-sm transition">
                                    Edit Giving
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Festival Target Modal -->
    @if($showTargetModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-5 border border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">⚙️</span>
                        <h3 class="font-black text-slate-900 dark:text-white text-base">Edit Festival Target Milestones</h3>
                    </div>
                    <button type="button" wire:click="$set('showTargetModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg">✕</button>
                </div>

                <form wire:submit.prevent="saveFestivalTarget" class="space-y-4 text-xs">
                    <div>
                        <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Festival Name *</label>
                        <input type="text" wire:model="festival_name" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" required />
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">MCF Relief Chest Reference *</label>
                        <input type="text" wire:model="relief_chest_ref" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" required />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Bronze Tier Target ({{ $cs }})</label>
                            <input type="number" step="0.01" wire:model="bronze_tier" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" required />
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Silver Tier Target ({{ $cs }})</label>
                            <input type="number" step="0.01" wire:model="silver_tier" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" required />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Gold Tier Target ({{ $cs }})</label>
                            <input type="number" step="0.01" wire:model="gold_tier" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" required />
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Platinum Target ({{ $cs }})</label>
                            <input type="number" step="0.01" wire:model="platinum_tier" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" required />
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showTargetModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-blue-700 hover:bg-blue-800 text-white font-black rounded-xl shadow-md transition">Save Milestones</button>
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
