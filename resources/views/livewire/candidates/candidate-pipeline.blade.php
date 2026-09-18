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
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 bg-slate-900 text-white shadow-md font-black"
        >
            <span>📋</span>
            <span>Candidates (Form P Vetting)</span>
        </a>

        <a
            href="{{ route('admin.club_acc.subscriptions.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 hover:text-slate-900 hover:bg-white/60"
        >
            <span>💳</span>
            <span>Subscriptions &amp; Dues</span>
        </a>
    </div>

    <!-- Header Banner -->
    <div class="p-6 bg-slate-900 rounded-3xl text-white shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-amber-500/20 text-amber-400 rounded-2xl border border-amber-500/30">📜</span>
                <div>
                    <h1 class="text-2xl font-black tracking-tight">Candidate Pipeline</h1>
                    <p class="text-xs text-slate-400 mt-1 font-medium">UGLE Rule 159 Candidate Progression, Form P Vetting &amp; Initiation Bridge</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="button"
                wire:click="openCreateModal"
                class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Candidate Enquiry
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

    <!-- Pipeline KPI Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="p-5 bg-white border border-slate-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Enquiries</span>
            <div class="text-2xl font-black text-slate-900 mt-1">{{ $allCandidates->count() }}</div>
            <span class="text-[10px] text-slate-500 mt-1 block">Candidates in system</span>
        </div>
        <div class="p-5 bg-amber-50/60 border border-amber-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Rule 159 Vetting</span>
            <div class="text-2xl font-black text-amber-900 mt-1">{{ $allCandidates->where('stage.value', 'lodge_committee')->count() }}</div>
            <span class="text-[10px] text-amber-700 mt-1 block">Awaiting Committee Clearance</span>
        </div>
        <div class="p-5 bg-emerald-50/60 border border-emerald-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Ballot Approved</span>
            <div class="text-2xl font-black text-emerald-900 mt-1">{{ $allCandidates->where('stage.value', 'ballot_approved')->count() }}</div>
            <span class="text-[10px] text-emerald-700 mt-1 block">Ready for Initiation Ballot</span>
        </div>
        <div class="p-5 bg-purple-50/60 border border-purple-200/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-purple-800">Initiated Brethren</span>
            <div class="text-2xl font-black text-purple-900 mt-1">{{ $allCandidates->where('stage.value', 'initiated')->count() }}</div>
            <span class="text-[10px] text-purple-700 mt-1 block">Converted to Active Member</span>
        </div>
    </div>

    <!-- Search & Filter Controls + View Mode Switcher -->
    <div class="p-4 bg-white border border-slate-200/80 rounded-2xl shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3 w-full md:w-auto flex-wrap">
            <div class="relative w-full sm:w-80">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search candidate name, email..."
                    class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                />
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <select
                wire:model.live="stageFilter"
                class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500"
            >
                <option value="">All Stages</option>
                @foreach($stages as $stg)
                    <option value="{{ $stg->value }}">{{ $stg->label() }}</option>
                @endforeach
            </select>
        </div>

        <!-- View Mode Switcher (2 Icons: Kanban & Table List) -->
        <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 shrink-0 self-end md:self-auto">
            <button
                type="button"
                wire:click="$set('viewMode', 'kanban')"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer {{ $viewMode === 'kanban' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' }}"
                title="Kanban Board View"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2m0 10V7m6 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                </svg>
                <span>Board</span>
            </button>
            <button
                type="button"
                wire:click="$set('viewMode', 'list')"
                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer {{ $viewMode === 'list' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' }}"
                title="Table List View"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                </svg>
                <span>List</span>
            </button>
        </div>
    </div>

    @if($viewMode === 'kanban')
        <!-- Kanban Pipeline Board -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 overflow-x-auto pb-4">
            @foreach($stages as $stg)
                @php
                    $colCandidates = $kanbanColumns[$stg->value]['candidates'];
                @endphp
                <div class="bg-slate-50 border border-slate-200/80 rounded-2xl p-3 flex flex-col min-h-[500px]">
                    <!-- Column Header -->
                    <div class="flex items-center justify-between pb-3 border-b border-slate-200 mb-3">
                        <h3 class="font-black text-xs text-slate-800 tracking-tight">{{ $stg->label() }}</h3>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $stg->badgeClasses() }}">
                            {{ $colCandidates->count() }}
                        </span>
                    </div>

                    <!-- Candidate Card Stack -->
                    <div class="space-y-3 flex-1">
                        @forelse($colCandidates as $cand)
                            <div class="p-3.5 bg-white border border-slate-200 rounded-xl shadow-sm hover:shadow-md transition-all space-y-2">
                                <div class="flex items-start justify-between gap-1">
                                    <div>
                                        <h4 class="font-bold text-xs text-slate-900">{{ $cand->full_name }}</h4>
                                        @if($cand->occupation)
                                            <p class="text-[10px] text-slate-500 font-medium">{{ $cand->occupation }}</p>
                                        @endif
                                    </div>
                                    <span class="px-2 py-0.5 text-[9px] font-bold rounded border {{ $cand->stage->badgeClasses() }}">
                                        {{ $cand->stage->value }}
                                    </span>
                                </div>

                                @if($cand->email || $cand->phone)
                                    <div class="text-[10px] text-slate-600 space-y-0.5">
                                        @if($cand->email)
                                            <div class="truncate">✉️ {{ $cand->email }}</div>
                                        @endif
                                        @if($cand->phone)
                                            <div class="truncate">📞 {{ $cand->phone }}</div>
                                        @endif
                                    </div>
                                @endif

                                @if($cand->proposer || $cand->seconder)
                                    <div class="pt-2 border-t border-slate-100 text-[10px] text-slate-500 space-y-0.5">
                                        @if($cand->proposer)
                                            <div class="truncate">P: <strong>{{ $cand->proposer->formatted_rank_name }}</strong></div>
                                        @endif
                                        @if($cand->seconder)
                                            <div class="truncate">S: <strong>{{ $cand->seconder->formatted_rank_name }}</strong></div>
                                        @endif
                                    </div>
                                @endif

                                <!-- Quick Stage Transition & Action Buttons -->
                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-1">
                                    <select
                                        wire:change="moveStage({{ $cand->id }}, $event.target.value)"
                                        class="px-1.5 py-1 bg-slate-50 border border-slate-200 rounded text-[10px] font-semibold text-slate-700 cursor-pointer focus:outline-none"
                                    >
                                        @foreach($stages as $stgOpt)
                                            <option value="{{ $stgOpt->value }}" {{ $cand->stage->value === $stgOpt->value ? 'selected' : '' }}>
                                                → {{ $stgOpt->label() }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <div class="flex items-center gap-1">
                                        <!-- Form P Vetting Modal Trigger -->
                                        <button
                                            type="button"
                                            wire:click="openFormPModal({{ $cand->id }})"
                                            class="p-1 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-md transition"
                                            title="Form P Statutory Vetting"
                                        >
                                            📜
                                        </button>

                                        <!-- Edit Candidate -->
                                        <button
                                            type="button"
                                            wire:click="editCandidate({{ $cand->id }})"
                                            class="p-1 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-md transition"
                                            title="Edit Candidate"
                                        >
                                            ✏️
                                        </button>

                                        <!-- Delete Candidate -->
                                        <button
                                            type="button"
                                            wire:click="deleteCandidate({{ $cand->id }})"
                                            wire:confirm="Are you sure you want to remove {{ $cand->full_name }} from the pipeline?"
                                            class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-md transition"
                                            title="Delete Candidate"
                                        >
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-4 text-center text-slate-400 text-[11px] italic bg-white/50 border border-dashed border-slate-200 rounded-xl">
                                No candidates in this stage.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- Table / List View -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden text-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-extrabold uppercase tracking-wider text-[10px]">
                            <th class="py-3.5 px-4">Candidate &amp; Contact Details</th>
                            <th class="py-3.5 px-4">Current Stage</th>
                            <th class="py-3.5 px-4">Form P Vetting (Proposer / Seconder)</th>
                            <th class="py-3.5 px-4">Clearance &amp; Status</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-800">
                        @forelse($allCandidates as $cand)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3.5 px-4">
                                    <div class="space-y-0.5">
                                        <div class="font-bold text-slate-900 text-xs">{{ $cand->full_name }}</div>
                                        @if($cand->occupation)
                                            <div class="text-[11px] text-slate-500 font-medium">{{ $cand->occupation }}</div>
                                        @endif
                                        <div class="text-[11px] text-slate-500 flex items-center gap-2 flex-wrap">
                                            @if($cand->email) <span>✉️ {{ $cand->email }}</span> @endif
                                            @if($cand->phone) <span>📞 {{ $cand->phone }}</span> @endif
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <select
                                            wire:change="moveStage({{ $cand->id }}, $event.target.value)"
                                            class="px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 cursor-pointer focus:outline-none focus:ring-2 focus:ring-amber-500"
                                        >
                                            @foreach($stages as $stgOpt)
                                                <option value="{{ $stgOpt->value }}" {{ $cand->stage->value === $stgOpt->value ? 'selected' : '' }}>
                                                    {{ $stgOpt->label() }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4">
                                    <div class="space-y-0.5 text-[11px]">
                                        <div><span class="text-slate-400">Proposer:</span> <strong class="text-slate-700">{{ $cand->proposer?->formatted_rank_name ?: 'Not assigned' }}</strong></div>
                                        <div><span class="text-slate-400">Seconder:</span> <strong class="text-slate-700">{{ $cand->seconder?->formatted_rank_name ?: 'Not assigned' }}</strong></div>
                                    </div>
                                </td>

                                <td class="py-3.5 px-4 whitespace-nowrap text-[11px]">
                                    @if($cand->rule_159_cleared)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Rule 159 Cleared
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            wire:click="openFormPModal({{ $cand->id }})"
                                            class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-lg text-xs font-bold transition flex items-center gap-1 cursor-pointer"
                                            title="Form P Statutory Vetting"
                                        >
                                            📜 Form P
                                        </button>
                                        @if($cand->stage->value === 'ballot_approved')
                                            <button
                                                type="button"
                                                wire:click="openInitiationModal({{ $cand->id }})"
                                                class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1 cursor-pointer"
                                                title="Initiate Candidate"
                                            >
                                                🏛️ Initiate
                                            </button>
                                        @endif
                                        <button
                                            type="button"
                                            wire:click="editCandidate({{ $cand->id }})"
                                            class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition cursor-pointer"
                                            title="Edit Candidate"
                                        >
                                            ✏️
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="deleteCandidate({{ $cand->id }})"
                                            wire:confirm="Are you sure you want to remove {{ $cand->full_name }} from the pipeline?"
                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition cursor-pointer"
                                            title="Delete Candidate"
                                        >
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400">
                                    <div class="space-y-2">
                                        <span class="text-2xl block">👥</span>
                                        <p class="font-bold text-slate-700">No candidates found in pipeline.</p>
                                        <p class="text-xs text-slate-400">Click "+ New Candidate Enquiry" to register a candidate.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Candidate Add / Edit Modal -->
    @if($showCandidateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 space-y-5 border border-slate-100">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="font-black text-slate-900 text-base">
                        {{ $candidateId ? 'Edit Candidate Details' : 'New Candidate Enquiry' }}
                    </h3>
                    <button type="button" wire:click="$set('showCandidateModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <form wire:submit.prevent="saveCandidate" class="space-y-4 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">First Name *</label>
                            <input type="text" wire:model="first_name" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" required />
                            @error('first_name') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Last Name *</label>
                            <input type="text" wire:model="last_name" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" required />
                            @error('last_name') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Email Address</label>
                            <input type="email" wire:model="email" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" />
                            @error('email') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Phone Number</label>
                            <input type="text" wire:model="phone" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" />
                            @error('phone') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Date of Birth</label>
                            <input type="date" wire:model="date_of_birth" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Occupation</label>
                            <input type="text" wire:model="occupation" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="font-bold text-slate-700 block mb-1">Address</label>
                            <input type="text" wire:model="address" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Postcode</label>
                            <input type="text" wire:model="postcode" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" />
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Stage</label>
                        <select wire:model="stage" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            @foreach($stages as $stgOpt)
                                <option value="{{ $stgOpt->value }}">{{ $stgOpt->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Notes &amp; Observations</label>
                        <textarea wire:model="notes" rows="3" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="Initial enquiry notes, informal meeting feedback..."></textarea>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showCandidateModal', false)" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-xl shadow-md transition">Save Candidate</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Form P Statutory Vetting Modal -->
    @if($showFormPModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-xl w-full p-6 space-y-5 border border-slate-100">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📜</span>
                        <h3 class="font-black text-slate-900 text-base">UGLE Form P Statutory Vetting Checklist</h3>
                    </div>
                    <button type="button" wire:click="$set('showFormPModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <form wire:submit.prevent="saveFormPVetting" class="space-y-4 text-xs">
                    <!-- Proposer & Seconder Selectors -->
                    <div class="grid grid-cols-2 gap-3 bg-amber-50/50 p-4 border border-amber-200/80 rounded-2xl">
                        <div>
                            <label class="font-bold text-slate-800 block mb-1">Proposer (Active Member) *</label>
                            <select wire:model="proposer_member_id" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none" required>
                                <option value="">Select Proposer...</option>
                                @foreach($activeMembers as $m)
                                    <option value="{{ $m->id }}">{{ $m->formatted_rank_name }} ({{ $m->email }})</option>
                                @endforeach
                            </select>
                            @error('proposer_member_id') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="font-bold text-slate-800 block mb-1">Seconder (Active Member) *</label>
                            <select wire:model="seconder_member_id" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-amber-500 focus:outline-none" required>
                                <option value="">Select Seconder...</option>
                                @foreach($activeMembers as $m)
                                    <option value="{{ $m->id }}">{{ $m->formatted_rank_name }} ({{ $m->email }})</option>
                                @endforeach
                            </select>
                            @error('seconder_member_id') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Form P Signing Date *</label>
                        <input type="date" wire:model="form_p_signed_at" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" required />
                        @error('form_p_signed_at') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <!-- Statutory Background Declarations -->
                    <div class="space-y-2 p-4 bg-slate-50 border border-slate-200 rounded-2xl">
                        <span class="font-black text-slate-800 block mb-2">UGLE Statutory Declarations:</span>
                        
                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="belief_in_supreme_being" class="mt-0.5 rounded text-amber-600 focus:ring-amber-500" />
                            <span class="text-slate-700">Candidate has declared belief in a Supreme Being (Fundamental Masonic Landmark).</span>
                        </label>
                        @error('belief_in_supreme_being') <span class="text-rose-600 text-[10px] block pl-6">{{ $message }}</span> @enderror

                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="no_criminal_record" class="mt-0.5 rounded text-amber-600 focus:ring-amber-500" />
                            <span class="text-slate-700">Candidate has declared no unspent criminal convictions or pending prosecutions.</span>
                        </label>
                        @error('no_criminal_record') <span class="text-rose-600 text-[10px] block pl-6">{{ $message }}</span> @enderror

                        <label class="flex items-start gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="no_bankruptcies" class="mt-0.5 rounded text-amber-600 focus:ring-amber-500" />
                            <span class="text-slate-700">Candidate has declared no un-discharged bankruptcies or insolvency orders.</span>
                        </label>
                        @error('no_bankruptcies') <span class="text-rose-600 text-[10px] block pl-6">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex items-center gap-2 p-3 bg-blue-50 border border-blue-200 rounded-xl">
                            <input type="checkbox" wire:model="rule_159_cleared" id="rule_159_cb" class="rounded text-blue-600 focus:ring-blue-500" />
                            <label for="rule_159_cb" class="font-bold text-blue-900 cursor-pointer">Rule 159 Committee Cleared</label>
                        </div>

                        <div>
                            <label class="font-bold text-slate-700 block mb-1">Hermes Clearance Date</label>
                            <input type="date" wire:model="hermes_clearance_date" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-amber-500 focus:outline-none" />
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showFormPModal', false)" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-xl shadow-md transition">Save Form P Vetting</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Initiation Confirmation Modal -->
    @if($showInitiationModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-5 border border-slate-100">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🏛️</span>
                        <h3 class="font-black text-slate-900 text-base">Confirm Candidate Initiation</h3>
                    </div>
                    <button type="button" wire:click="$set('showInitiationModal', false)" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <div class="p-4 bg-purple-50 border border-purple-200 rounded-2xl text-xs text-purple-900 space-y-2">
                    <p class="font-bold">This action will instantiate a full record in the Member Directory (<code class="bg-white px-1.5 py-0.5 rounded text-purple-950">club_acc_members</code>).</p>
                    <ul class="list-disc pl-4 space-y-1 text-purple-800">
                        <li>Membership status set to <strong>Active</strong>.</li>
                        <li>Initial Rank set to <strong>Bro</strong>.</li>
                        <li>Historical contact details & address copied automatically.</li>
                    </ul>
                </div>

                <form wire:submit.prevent="confirmInitiation" class="space-y-4 text-xs">
                    <div>
                        <label class="font-bold text-slate-700 block mb-1">Initiation Ceremony Date *</label>
                        <input type="date" wire:model="initiation_date" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500 focus:outline-none" required />
                        @error('initiation_date') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showInitiationModal', false)" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-purple-700 hover:bg-purple-800 text-white font-black rounded-xl shadow-md transition flex items-center gap-2">
                            <span>🏛️</span>
                            <span>Record Initiation &amp; Create Member</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
