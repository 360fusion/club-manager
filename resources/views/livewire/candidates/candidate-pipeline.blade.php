<div class="space-y-6">
    @include('livewire.members._tabs', ['active' => 'candidates'])

    <!-- Header -->
    <x-card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Candidate Pipeline</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">From first enquiry to initiation, with every step and decision recorded</p>
            </div>
            <x-button wire:click="openCreateModal">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New enquiry
            </x-button>
        </div>
    </x-card>

    @if (session()->has('success'))
        <x-notice variant="success">{{ session('success') }}</x-notice>
    @endif
    @if (session()->has('error'))
        <x-notice variant="danger">{{ session('error') }}</x-notice>
    @endif

    <!-- Numbers -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach([
            'In the process' => $activeCount,
            'Committee and proposed' => $board['lodge_committee']['candidates']->count() + $board['proposed']['candidates']->count(),
            'Accepted, to initiate' => $board['accepted']['candidates']->count(),
            'Initiated' => $completed['initiated']->count(),
        ] as $statLabel => $statValue)
            <x-card padding="sm">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $statLabel }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ $statValue }}</p>
            </x-card>
        @endforeach
    </div>

    <!-- Search, filter and view -->
    <x-card padding="sm">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-3">
            <div class="grid w-full gap-3 sm:grid-cols-2 md:w-auto">
                <div class="sm:w-72">
                    <x-input type="search" label="Search" wire:model.live.debounce.300ms="search" placeholder="Name, email, occupation..." />
                </div>
                <x-select label="Stage" wire:model.live="stageFilter">
                    <option value="">All stages</option>
                    @foreach($stages as $stg)
                        <option value="{{ $stg->value }}" @selected($stageFilter === $stg->value)>{{ $stg->label() }}</option>
                    @endforeach
                </x-select>
            </div>
            <div class="flex items-center gap-1 shrink-0" role="group" aria-label="View">
                @foreach(['kanban' => 'Board', 'list' => 'List'] as $mode => $modeLabel)
                    <button type="button" wire:click="$set('viewMode', '{{ $mode }}')" aria-pressed="{{ $viewMode === $mode ? 'true' : 'false' }}" class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors cursor-pointer {{ $viewMode === $mode ? 'bg-slate-900 text-white dark:bg-slate-700' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">{{ $modeLabel }}</button>
                @endforeach
            </div>
        </div>
    </x-card>

    @php
        $reasonLabels = \App\Domains\ClubAccounting\Services\CandidateTransitionService::REJECT_REASONS + \App\Domains\ClubAccounting\Services\CandidateTransitionService::CLOSE_REASONS;

        // One line saying what is next for a candidate, and whether it needs attention.
        $nextAction = function ($c) {
            if ($c->isOnHold()) {
                return ['On hold'.($c->on_hold_until ? ' until '.$c->on_hold_until->format('j M') : ''), 'warning'];
            }
            return match ($c->stage->value) {
                'enquiry' => [$c->daysInStage() > 3 ? 'Call is overdue' : 'Arrange a call', $c->daysInStage() > 3 ? 'danger' : 'neutral'],
                'first_interview' => ['Log the call', 'neutral'],
                'interview_pending' => [($c->proposer_member_id && $c->seconder_member_id) ? 'Meeting, then committee' : 'Choose proposer and seconder', 'neutral'],
                'lodge_committee' => [$c->committee_recommended_at ? ($c->form_p_signed_at && $c->proposed_at ? 'Ready to mark proposed' : 'Needs Form P and proposal date') : 'Awaiting committee', 'neutral'],
                'proposed' => [$c->proposed_at ? 'Ballot after '.$c->proposed_at->format('j M') : 'Record the ballot', 'info'],
                'accepted' => [$c->initiate_by ? 'Initiate by '.$c->initiate_by->format('j M Y') : 'Arrange initiation', ($c->initiate_by && $c->initiate_by->lt(now()->addDays(60))) ? 'danger' : 'success'],
                default => ['', 'neutral'],
            };
        };
        // The stage enum's own colours include sky and violet, which the design system does not use.
        $stageBadge = ['enquiry' => 'neutral', 'first_interview' => 'info', 'interview_pending' => 'info', 'lodge_committee' => 'warning', 'proposed' => 'info', 'accepted' => 'success', 'initiated' => 'info', 'rejected' => 'danger', 'withdrawn' => 'neutral'];
        $menuItem = 'block w-full text-left px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer';
    @endphp

    @if($viewMode === 'kanban')
        <!-- Board: the working stages share the width and scroll sideways only if the screen is narrow -->
        <div class="overflow-x-auto pb-2 -mx-1 px-1">
            <div class="flex gap-3 min-w-max lg:min-w-0">
                @foreach($board as $column)
                    @php $stg = $column['stage']; $cards = $column['candidates']; @endphp
                    <section class="w-[240px] lg:w-auto lg:flex-1 lg:min-w-[180px] bg-slate-50 dark:bg-slate-800/40 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-2.5 flex flex-col min-h-[320px]" aria-label="{{ $stg->label() }}">
                        <header class="flex items-center justify-between pb-2 mb-2 border-b border-slate-200 dark:border-slate-800" title="{{ $stg->hint() }}">
                            <h3 class="font-semibold text-xs text-slate-800 dark:text-slate-100">{{ $stg->shortLabel() }}</h3>
                            <x-badge>{{ $cards->count() }}</x-badge>
                        </header>

                        <div class="space-y-2 flex-1">
                            @forelse($cards as $cand)
                                @php [$actionText, $actionTone] = $nextAction($cand); $next = $cand->stage->next(); @endphp
                                <article class="p-2.5 bg-white dark:bg-slate-900 border {{ $cand->isOnHold() ? 'border-amber-300 dark:border-amber-700/60' : 'border-slate-200 dark:border-slate-800' }} rounded-xl shadow-sm hover:shadow-md transition-all">
                                    <div class="flex items-start justify-between gap-1">
                                        <a href="{{ route('admin.club_acc.candidates.show', ['clubSlug' => $club->slug, 'candidateId' => $cand->id]) }}" class="font-semibold text-xs text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 leading-snug">{{ $cand->full_name }}</a>

                                        <div class="relative shrink-0" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                                            <button type="button" @click="open = !open" class="p-0.5 text-slate-500 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 rounded cursor-pointer" aria-haspopup="menu" :aria-expanded="open" aria-label="Actions for {{ $cand->full_name }}"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.75"/><circle cx="12" cy="12" r="1.75"/><circle cx="19" cy="12" r="1.75"/></svg></button>
                                            <div x-show="open" x-cloak role="menu" class="absolute right-0 z-30 mt-1 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900" @click="open = false">
                                                <a href="{{ route('admin.club_acc.candidates.show', ['clubSlug' => $club->slug, 'candidateId' => $cand->id]) }}" class="{{ $menuItem }}" role="menuitem">Open, edit and notes</a>
                                                @if($next && $next->value !== 'accepted' && $next->value !== 'initiated')
                                                    <button type="button" wire:click="advance({{ $cand->id }})" class="{{ $menuItem }}" role="menuitem">Move on to {{ $next->shortLabel() }}</button>
                                                @endif
                                                @if($cand->stage->value === 'proposed')
                                                    <button type="button" wire:click="openBallot({{ $cand->id }})" class="{{ $menuItem }}" role="menuitem">Record the ballot</button>
                                                @endif
                                                @if($cand->stage->value === 'accepted')
                                                    <button type="button" wire:click="openInitiationModal({{ $cand->id }})" class="{{ $menuItem }}" role="menuitem">Record initiation</button>
                                                @endif
                                                <button type="button" wire:click="openFormPModal({{ $cand->id }})" class="{{ $menuItem }}" role="menuitem">Proposer, Form P, proposal</button>
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

                                    @if($cand->occupation)<p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ $cand->occupation }}</p>@endif

                                    <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                        <x-badge :variant="$actionTone">{{ $actionText }}</x-badge>
                                        <x-badge :variant="$cand->daysInStage() > 14 ? 'danger' : 'neutral'" title="Time in this stage">{{ $cand->daysInStage() }}d</x-badge>
                                    </div>

                                    @if($cand->owner)
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Owner: {{ $cand->owner->name }}</p>
                                    @endif
                                </article>
                            @empty
                                <div class="p-3 text-center text-slate-500 dark:text-slate-400 text-xs border border-dashed border-slate-200 dark:border-slate-800 rounded-xl">Nobody here.</div>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @else
        <!-- List -->
        <x-card padding="none" class="overflow-hidden text-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-medium text-xs">
                            <th class="py-3 px-4">Candidate</th><th class="py-3 px-4">Stage</th><th class="py-3 px-4">Next</th><th class="py-3 px-4">Proposer / seconder</th><th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-800 dark:text-slate-100">
                        @forelse($allCandidates as $cand)
                            @php [$actionText, $actionTone] = $nextAction($cand); $next = $cand->stage->next(); @endphp
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                <td class="py-3 px-4">
                                    <a href="{{ route('admin.club_acc.candidates.show', ['clubSlug' => $club->slug, 'candidateId' => $cand->id]) }}" class="font-semibold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400">{{ $cand->full_name }}</a>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $cand->email }} {{ $cand->phone ? ' · '.$cand->phone : '' }}</div>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap"><x-badge :variant="$stageBadge[$cand->stage->value] ?? 'neutral'">{{ $cand->stage->label() }}</x-badge></td>
                                <td class="py-3 px-4">
                                    @if($cand->isActive())<x-badge :variant="$actionTone">{{ $actionText }}</x-badge> <span class="text-xs text-slate-500 dark:text-slate-400">{{ $cand->daysInStage() }}d</span>
                                    @elseif($cand->outcome_reason)<span class="text-xs text-slate-500 dark:text-slate-400">{{ $reasonLabels[$cand->outcome_reason] ?? $cand->outcome_reason }}</span>@endif
                                </td>
                                <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300">{{ $cand->proposer?->formatted_rank_name ?: '—' }} / {{ $cand->seconder?->formatted_rank_name ?: '—' }}</td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <x-button variant="secondary" size="sm" :href="route('admin.club_acc.candidates.show', ['clubSlug' => $club->slug, 'candidateId' => $cand->id])">Open</x-button>
                                    @if($cand->isActive() && $next && ! in_array($next->value, ['accepted', 'initiated'], true))
                                        <x-button size="sm" class="ml-1" wire:click="advance({{ $cand->id }})">Move on</x-button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-slate-500 dark:text-slate-400">No candidates found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    <!-- Finished: initiated, rejected and closed leave the board so it stays a size that fits -->
    @if($viewMode === 'kanban')
        <details class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
            <summary class="cursor-pointer select-none px-5 py-3.5 text-sm font-semibold text-slate-700 dark:text-slate-200 flex items-center justify-between">
                <span>Initiated, rejected and closed</span>
                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $completed['initiated']->count() }} initiated · {{ $completed['rejected']->count() }} rejected · {{ $completed['closed']->count() }} closed</span>
            </summary>
            <div class="grid md:grid-cols-3 gap-4 p-4 pt-1">
                @foreach(['initiated' => 'Initiated', 'rejected' => 'Rejected', 'closed' => 'Closed'] as $key => $title)
                    <div>
                        <h4 class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-2">{{ $title }}</h4>
                        <ul class="space-y-1.5">
                            @forelse($completed[$key] as $cand)
                                <li class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
                                    <a href="{{ route('admin.club_acc.candidates.show', ['clubSlug' => $club->slug, 'candidateId' => $cand->id]) }}" class="font-semibold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400">{{ $cand->full_name }}</a>
                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                        @if($key === 'initiated') Initiated {{ $cand->initiation_date?->format('j M Y') }}
                                        @else {{ $reasonLabels[$cand->outcome_reason] ?? 'No reason recorded' }} · {{ $cand->outcome_at?->format('j M Y') }}
                                        @endif
                                    </div>
                                </li>
                            @empty
                                <li class="text-xs text-slate-500 dark:text-slate-400">None.</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
            </div>
        </details>
    @endif

    <!-- New enquiry / quick edit -->
    @if ($showCandidateModal)
    <x-modal :show="$showCandidateModal" :title="$candidateId ? 'Edit candidate details' : 'New enquiry'" size="lg" close="$set('showCandidateModal', false)">
        <form id="candidate-form" wire:submit.prevent="saveCandidate" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <x-input label="First name" required wire:model="first_name" />
                <x-input label="Last name" required wire:model="last_name" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-input type="email" label="Email" wire:model="email" />
                <x-input label="Phone" wire:model="phone" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-input type="date" label="Date of birth" wire:model="date_of_birth" />
                <x-input label="Occupation" wire:model="occupation" />
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2"><x-input label="Address" wire:model="address" /></div>
                <x-input label="Postcode" wire:model="postcode" />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-select label="How did they hear of us?" wire:model="source">
                    <option value="">Not recorded</option>
                    @foreach(['website' => 'Website', 'member' => 'A member', 'event' => 'An event', 'social' => 'Social media', 'other' => 'Other'] as $sourceValue => $sourceLabel)
                        <option value="{{ $sourceValue }}" @selected($source === $sourceValue)>{{ $sourceLabel }}</option>
                    @endforeach
                </x-select>
                <x-input label="Who or which?" wire:model="source_note" placeholder="e.g. WBro John Doe" />
            </div>
            <x-textarea label="Notes" rows="3" wire:model="notes" placeholder="First impressions, what they asked about..." />
        </form>

        <x-slot:footer>
            <x-button variant="secondary" wire:click="$set('showCandidateModal', false)">Cancel</x-button>
            <x-button type="submit" form="candidate-form">Save</x-button>
        </x-slot:footer>
    </x-modal>
    @endif

    @include('livewire.candidates.partials.dialogs')
</div>
