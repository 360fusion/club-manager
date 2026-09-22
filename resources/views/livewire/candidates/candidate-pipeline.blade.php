<div class="space-y-6">
    <!-- Member Management Domain Unified Navigation -->
    <div class="flex items-center gap-2 p-1.5 bg-slate-200/80 dark:bg-slate-700/80 rounded-2xl w-fit text-xs font-bold border border-slate-300/60 dark:border-slate-700/60 shadow-inner">
        <a
            href="{{ route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-slate-900/60"
        >
            <span>👥</span>
            <span>Members Roster</span>
        </a>

        <a
            href="{{ route('admin.officers.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-slate-900/60"
        >
            <span>👔</span>
            <span>Annual Officer Rosters &amp; History</span>
        </a>

        <a
            href="{{ route('admin.club_acc.candidates.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 bg-slate-900 dark:bg-slate-700 text-white shadow-md font-black"
        >
            <span>📋</span>
            <span>Candidates (Form P Vetting)</span>
        </a>

        <a
            href="{{ route('admin.club_acc.subscriptions.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-slate-900/60"
        >
            <span>💳</span>
            <span>Subscriptions &amp; Dues</span>
        </a>
    </div>

    <!-- Header Banner -->
    <div class="p-6 bg-slate-900 dark:bg-slate-700 rounded-3xl text-white shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="p-2.5 bg-amber-500/20 text-amber-400 rounded-2xl border border-amber-500/30">📜</span>
            <div>
                <h1 class="text-2xl font-black tracking-tight">Candidate Pipeline</h1>
                <p class="text-xs text-slate-400 mt-1 font-medium">From first enquiry to initiation, with every step and decision recorded</p>
            </div>
        </div>

        <button type="button" wire:click="openCreateModal" class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-2 cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New enquiry
        </button>
    </div>

    @if (session()->has('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-200 text-xs font-semibold rounded-2xl shadow-sm flex items-center gap-2"><span>✅</span><span>{{ session('success') }}</span></div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-200 text-xs font-semibold rounded-2xl shadow-sm flex items-center gap-2"><span>⚠️</span><span>{{ session('error') }}</span></div>
    @endif

    <!-- Numbers -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="p-4 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">In the process</span>
            <div class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ $activeCount }}</div>
        </div>
        <div class="p-4 bg-amber-50/60 dark:bg-amber-950/60 border border-amber-200/80 dark:border-amber-800/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-200">Committee and proposed</span>
            <div class="text-2xl font-black text-amber-900 dark:text-amber-200 mt-1">{{ $board['lodge_committee']['candidates']->count() + $board['proposed']['candidates']->count() }}</div>
        </div>
        <div class="p-4 bg-emerald-50/60 dark:bg-emerald-950/60 border border-emerald-200/80 dark:border-emerald-800/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800 dark:text-emerald-200">Accepted, to initiate</span>
            <div class="text-2xl font-black text-emerald-900 dark:text-emerald-200 mt-1">{{ $board['accepted']['candidates']->count() }}</div>
        </div>
        <div class="p-4 bg-blue-50/60 dark:bg-blue-950/60 border border-blue-200/80 dark:border-blue-800/80 rounded-2xl shadow-sm">
            <span class="text-[11px] font-bold uppercase tracking-wider text-blue-800 dark:text-blue-200">Initiated</span>
            <div class="text-2xl font-black text-blue-900 dark:text-blue-200 mt-1">{{ $completed['initiated']->count() }}</div>
        </div>
    </div>

    <!-- Search, filter and view -->
    <div class="p-3 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl shadow-sm flex flex-col md:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-3 w-full md:w-auto flex-wrap">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name, email, occupation..." class="w-full sm:w-72 px-3.5 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500" />
            <select wire:model.live="stageFilter" class="px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-700 dark:text-slate-200 font-semibold focus:outline-none focus:ring-2 focus:ring-amber-500">
                <option value="">All stages</option>
                @foreach($stages as $stg)
                    <option value="{{ $stg->value }}">{{ $stg->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200 dark:border-slate-800 shrink-0">
            @foreach(['kanban' => 'Board', 'list' => 'List'] as $mode => $modeLabel)
                <button type="button" wire:click="$set('viewMode', '{{ $mode }}')" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer {{ $viewMode === $mode ? 'bg-slate-900 dark:bg-slate-700 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-white/60 dark:hover:bg-slate-900/60' }}">{{ $modeLabel }}</button>
            @endforeach
        </div>
    </div>

    @php
        $reasonLabels = \App\Domains\ClubAccounting\Services\CandidateTransitionService::REJECT_REASONS + \App\Domains\ClubAccounting\Services\CandidateTransitionService::CLOSE_REASONS;

        // One line saying what is next for a candidate, and whether it needs attention.
        $nextAction = function ($c) {
            if ($c->isOnHold()) {
                return ['On hold'.($c->on_hold_until ? ' until '.$c->on_hold_until->format('j M') : ''), 'amber'];
            }
            return match ($c->stage->value) {
                'enquiry' => [$c->daysInStage() > 3 ? 'Call is overdue' : 'Arrange a call', $c->daysInStage() > 3 ? 'rose' : 'slate'],
                'first_interview' => ['Log the call', 'slate'],
                'interview_pending' => [($c->proposer_member_id && $c->seconder_member_id) ? 'Meeting, then committee' : 'Choose proposer and seconder', 'slate'],
                'lodge_committee' => [$c->committee_recommended_at ? ($c->form_p_signed_at && $c->proposed_at ? 'Ready to mark proposed' : 'Needs Form P and proposal date') : 'Awaiting committee', 'slate'],
                'proposed' => [$c->proposed_at ? 'Ballot after '.$c->proposed_at->format('j M') : 'Record the ballot', 'violet'],
                'accepted' => [$c->initiate_by ? 'Initiate by '.$c->initiate_by->format('j M Y') : 'Arrange initiation', ($c->initiate_by && $c->initiate_by->lt(now()->addDays(60))) ? 'rose' : 'emerald'],
                default => ['', 'slate'],
            };
        };
        $chip = ['slate' => 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300', 'rose' => 'bg-rose-100 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300', 'amber' => 'bg-amber-100 dark:bg-amber-950/50 text-amber-800 dark:text-amber-300', 'violet' => 'bg-violet-100 dark:bg-violet-950/50 text-violet-700 dark:text-violet-300', 'emerald' => 'bg-emerald-100 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300'];
        $menuItem = 'block w-full text-left px-3 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer';
    @endphp

    @if($viewMode === 'kanban')
        <!-- Board: the working stages share the width and scroll sideways only if the screen is narrow -->
        <div class="overflow-x-auto pb-2 -mx-1 px-1">
            <div class="flex gap-3 min-w-max lg:min-w-0">
                @foreach($board as $column)
                    @php $stg = $column['stage']; $cards = $column['candidates']; @endphp
                    <section class="w-[240px] lg:w-auto lg:flex-1 lg:min-w-[180px] bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-2.5 flex flex-col min-h-[320px]" aria-label="{{ $stg->label() }}">
                        <header class="flex items-center justify-between pb-2 mb-2 border-b border-slate-200 dark:border-slate-800" title="{{ $stg->hint() }}">
                            <h3 class="font-black text-xs text-slate-800 dark:text-slate-100 tracking-tight">{{ $stg->shortLabel() }}</h3>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border {{ $stg->badgeClasses() }}">{{ $cards->count() }}</span>
                        </header>

                        <div class="space-y-2 flex-1">
                            @forelse($cards as $cand)
                                @php [$actionText, $actionTone] = $nextAction($cand); $next = $cand->stage->next(); @endphp
                                <article class="p-2.5 bg-white dark:bg-slate-900 border {{ $cand->isOnHold() ? 'border-amber-300 dark:border-amber-700/60' : 'border-slate-200 dark:border-slate-800' }} rounded-xl shadow-sm hover:shadow-md transition-all">
                                    <div class="flex items-start justify-between gap-1">
                                        <a href="{{ route('admin.club_acc.candidates.show', ['clubSlug' => $club->slug, 'candidateId' => $cand->id]) }}" class="font-bold text-xs text-slate-900 dark:text-white hover:text-amber-600 dark:hover:text-amber-400 leading-snug">{{ $cand->full_name }}</a>

                                        <div class="relative shrink-0" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                                            <button type="button" @click="open = !open" class="px-1.5 leading-none text-base text-slate-500 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 rounded cursor-pointer" aria-haspopup="menu" :aria-expanded="open" aria-label="Actions for {{ $cand->full_name }}">⋯</button>
                                            <div x-show="open" x-cloak role="menu" class="absolute right-0 z-30 mt-1 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900" @click="open = false">
                                                <a href="{{ route('admin.club_acc.candidates.show', ['clubSlug' => $club->slug, 'candidateId' => $cand->id]) }}" class="{{ $menuItem }}" role="menuitem">Open, edit and notes</a>
                                                @if($next && $next->value !== 'accepted' && $next->value !== 'initiated')
                                                    <button type="button" wire:click="advance({{ $cand->id }})" class="{{ $menuItem }}" role="menuitem">Move on to {{ $next->shortLabel() }}</button>
                                                @endif
                                                @if($cand->stage->value === 'proposed')
                                                    <button type="button" wire:click="openBallot({{ $cand->id }})" class="{{ $menuItem }}" role="menuitem">🗳️ Record the ballot</button>
                                                @endif
                                                @if($cand->stage->value === 'accepted')
                                                    <button type="button" wire:click="openInitiationModal({{ $cand->id }})" class="{{ $menuItem }}" role="menuitem">🏛️ Record initiation</button>
                                                @endif
                                                <button type="button" wire:click="openFormPModal({{ $cand->id }})" class="{{ $menuItem }}" role="menuitem">📜 Proposer, Form P, proposal</button>
                                                @if($cand->isOnHold())
                                                    <button type="button" wire:click="resume({{ $cand->id }})" class="{{ $menuItem }}" role="menuitem">Take off hold</button>
                                                @else
                                                    <button type="button" wire:click="openOutcome({{ $cand->id }}, 'hold')" class="{{ $menuItem }}" role="menuitem">Put on hold</button>
                                                @endif
                                                <button type="button" wire:click="openOutcome({{ $cand->id }}, 'close')" class="{{ $menuItem }}" role="menuitem">Close enquiry</button>
                                                <button type="button" wire:click="openOutcome({{ $cand->id }}, 'reject')" class="{{ $menuItem }} !text-rose-600 dark:!text-rose-400" role="menuitem">Reject</button>
                                            </div>
                                        </div>
                                    </div>

                                    @if($cand->occupation)<p class="text-[10px] text-slate-500 dark:text-slate-400 truncate">{{ $cand->occupation }}</p>@endif

                                    <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $chip[$actionTone] }}">{{ $actionText }}</span>
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $cand->daysInStage() > 14 ? $chip['rose'] : $chip['slate'] }}" title="Time in this stage">{{ $cand->daysInStage() }}d</span>
                                    </div>

                                    @if($cand->owner)
                                        <p class="mt-1 text-[10px] text-slate-400">Owner: {{ $cand->owner->name }}</p>
                                    @endif
                                </article>
                            @empty
                                <div class="p-3 text-center text-slate-400 text-[11px] italic border border-dashed border-slate-200 dark:border-slate-800 rounded-xl">Nobody here.</div>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @else
        <!-- List -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden text-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-extrabold uppercase tracking-wider text-[10px]">
                            <th class="py-3 px-4">Candidate</th><th class="py-3 px-4">Stage</th><th class="py-3 px-4">Next</th><th class="py-3 px-4">Proposer / seconder</th><th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-800 dark:text-slate-100">
                        @forelse($allCandidates as $cand)
                            @php [$actionText, $actionTone] = $nextAction($cand); $next = $cand->stage->next(); @endphp
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                <td class="py-3 px-4">
                                    <a href="{{ route('admin.club_acc.candidates.show', ['clubSlug' => $club->slug, 'candidateId' => $cand->id]) }}" class="font-bold text-slate-900 dark:text-white hover:text-amber-600">{{ $cand->full_name }}</a>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $cand->email }} {{ $cand->phone ? ' · '.$cand->phone : '' }}</div>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $cand->stage->badgeClasses() }}">{{ $cand->stage->label() }}</span></td>
                                <td class="py-3 px-4">
                                    @if($cand->isActive())<span class="px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $chip[$actionTone] }}">{{ $actionText }}</span> <span class="text-[10px] text-slate-400">{{ $cand->daysInStage() }}d</span>
                                    @elseif($cand->outcome_reason)<span class="text-[11px] text-slate-500">{{ $reasonLabels[$cand->outcome_reason] ?? $cand->outcome_reason }}</span>@endif
                                </td>
                                <td class="py-3 px-4 text-[11px] text-slate-600 dark:text-slate-300">{{ $cand->proposer?->formatted_rank_name ?: '—' }} / {{ $cand->seconder?->formatted_rank_name ?: '—' }}</td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.club_acc.candidates.show', ['clubSlug' => $club->slug, 'candidateId' => $cand->id]) }}" class="px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-[11px] font-bold hover:bg-slate-50 dark:hover:bg-slate-800">Open</a>
                                    @if($cand->isActive() && $next && ! in_array($next->value, ['accepted', 'initiated'], true))
                                        <button type="button" wire:click="advance({{ $cand->id }})" class="ml-1 px-2.5 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-[11px] font-bold cursor-pointer">Move on</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-slate-400">No candidates found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Finished: initiated, rejected and closed leave the board so it stays a size that fits -->
    @if($viewMode === 'kanban')
        <details class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl shadow-sm">
            <summary class="cursor-pointer select-none px-5 py-3.5 text-xs font-black text-slate-700 dark:text-slate-200 flex items-center justify-between">
                <span>Initiated, rejected and closed</span>
                <span class="text-[11px] font-semibold text-slate-400">{{ $completed['initiated']->count() }} initiated · {{ $completed['rejected']->count() }} rejected · {{ $completed['closed']->count() }} closed</span>
            </summary>
            <div class="grid md:grid-cols-3 gap-4 p-4 pt-1">
                @foreach(['initiated' => 'Initiated', 'rejected' => 'Rejected', 'closed' => 'Closed'] as $key => $title)
                    <div>
                        <h4 class="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">{{ $title }}</h4>
                        <ul class="space-y-1.5">
                            @forelse($completed[$key] as $cand)
                                <li class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
                                    <a href="{{ route('admin.club_acc.candidates.show', ['clubSlug' => $club->slug, 'candidateId' => $cand->id]) }}" class="font-bold text-slate-900 dark:text-white hover:text-amber-600">{{ $cand->full_name }}</a>
                                    <div class="text-[10px] text-slate-500 dark:text-slate-400">
                                        @if($key === 'initiated') Initiated {{ $cand->initiation_date?->format('j M Y') }}
                                        @else {{ $reasonLabels[$cand->outcome_reason] ?? 'No reason recorded' }} · {{ $cand->outcome_at?->format('j M Y') }}
                                        @endif
                                    </div>
                                </li>
                            @empty
                                <li class="text-[11px] italic text-slate-400">None.</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
            </div>
        </details>
    @endif

    <!-- New enquiry / quick edit -->
    @if($showCandidateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl max-w-lg w-full p-6 space-y-5 border border-slate-100 dark:border-slate-800 max-h-[92vh] overflow-y-auto">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="font-black text-slate-900 dark:text-white text-base">{{ $candidateId ? 'Edit candidate details' : 'New enquiry' }}</h3>
                    <button type="button" wire:click="$set('showCandidateModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg" aria-label="Close">✕</button>
                </div>

                <form wire:submit.prevent="saveCandidate" class="space-y-4 text-xs">
                    @php $f = 'w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none'; $l = 'font-bold text-slate-700 dark:text-slate-200 block mb-1'; @endphp
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="{{ $l }}">First name *</label><input type="text" wire:model="first_name" class="{{ $f }}" required />@error('first_name') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror</div>
                        <div><label class="{{ $l }}">Last name *</label><input type="text" wire:model="last_name" class="{{ $f }}" required />@error('last_name') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror</div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="{{ $l }}">Email</label><input type="email" wire:model="email" class="{{ $f }}" />@error('email') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror</div>
                        <div><label class="{{ $l }}">Phone</label><input type="text" wire:model="phone" class="{{ $f }}" />@error('phone') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror</div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="{{ $l }}">Date of birth</label><input type="date" wire:model="date_of_birth" class="{{ $f }}" /></div>
                        <div><label class="{{ $l }}">Occupation</label><input type="text" wire:model="occupation" class="{{ $f }}" /></div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2"><label class="{{ $l }}">Address</label><input type="text" wire:model="address" class="{{ $f }}" /></div>
                        <div><label class="{{ $l }}">Postcode</label><input type="text" wire:model="postcode" class="{{ $f }}" /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="{{ $l }}">How did they hear of us?</label>
                            <select wire:model="source" class="{{ $f }}">
                                <option value="">Not recorded</option>
                                <option value="website">Website</option><option value="member">A member</option><option value="event">An event</option><option value="social">Social media</option><option value="other">Other</option>
                            </select>
                        </div>
                        <div><label class="{{ $l }}">Who or which?</label><input type="text" wire:model="source_note" class="{{ $f }}" placeholder="e.g. WBro John Doe" /></div>
                    </div>
                    <div>
                        <label class="{{ $l }}">Notes</label>
                        <textarea wire:model="notes" rows="3" class="{{ $f }}" placeholder="First impressions, what they asked about..."></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showCandidateModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-xl shadow-md">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @include('livewire.candidates.partials.dialogs')
</div>
