@php
    // The stage enum's own colours include sky and violet, which the design system does not use.
    $stageBadge = ['enquiry' => 'neutral', 'first_interview' => 'info', 'interview_pending' => 'info', 'lodge_committee' => 'warning', 'proposed' => 'info', 'accepted' => 'success', 'initiated' => 'info', 'rejected' => 'danger', 'withdrawn' => 'neutral'];
@endphp

<div class="space-y-6" x-data="{ tab: 'details' }">
    <a href="{{ route('admin.club_acc.candidates.index', ['clubSlug' => $club->slug]) }}" class="inline-flex items-center gap-1 text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400">&larr; Back to the pipeline</a>

    @if (session()->has('success'))
        <x-notice variant="success">{{ session('success') }}</x-notice>
    @endif
    @if (session()->has('error'))
        <x-notice variant="danger">{{ session('error') }}</x-notice>
    @endif

    <!-- Header: who, where they are, and the actions -->
    <x-card class="space-y-4">
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ $candidate->full_name }}</h1>
                    <x-badge :variant="$stageBadge[$candidate->stage->value] ?? 'neutral'">{{ $candidate->stage->label() }}</x-badge>
                    @if($candidate->isOnHold())<x-badge variant="warning">On hold</x-badge>@endif
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $candidate->stage->hint() }}</p>
                @if($candidate->owner)<p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Owner: {{ $candidate->owner->name }}</p>@endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($candidate->isActive())
                    @php $next = $candidate->stage->next(); @endphp
                    @if($next && $next->value === 'accepted')
                        <x-button size="sm" wire:click="openBallot({{ $candidate->id }})">Record ballot</x-button>
                    @elseif($next && $next->value === 'initiated')
                        <x-button size="sm" wire:click="openInitiationModal({{ $candidate->id }})">Record initiation</x-button>
                    @elseif($next)
                        <x-button size="sm" wire:click="advance({{ $candidate->id }})">Move on to {{ $next->shortLabel() }} &rarr;</x-button>
                    @endif
                    @if($candidate->isOnHold())
                        <x-button variant="secondary" size="sm" wire:click="resume({{ $candidate->id }})">Take off hold</x-button>
                    @else
                        <x-button variant="secondary" size="sm" wire:click="openOutcome({{ $candidate->id }}, 'hold')">Hold</x-button>
                    @endif
                    <x-button variant="secondary" size="sm" wire:click="openOutcome({{ $candidate->id }}, 'close')">Close</x-button>
                    <x-button variant="danger" size="sm" wire:click="openOutcome({{ $candidate->id }}, 'reject')">Reject</x-button>
                @elseif($canReopen && $candidate->stage->isClosed())
                    <x-button size="sm" wire:click="openOutcome({{ $candidate->id }}, 'reopen')">Reopen</x-button>
                @endif
            </div>
        </div>

        @if($candidate->isActive() && count($checks))
            <ul class="flex flex-wrap gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                @foreach($checks as $label => $done)
                    <li>
                        <x-badge :variant="$done ? 'success' : 'neutral'">
                            @if($done)<svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>@endif
                            {{ $label }}<span class="sr-only">{{ $done ? ': done' : ': to do' }}</span>
                        </x-badge>
                    </li>
                @endforeach
            </ul>
        @endif

        @if(! $candidate->isActive() && $candidate->outcome_note)
            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 text-sm">
                <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $candidate->stage === \App\Domains\ClubAccounting\Enums\CandidateStage::Rejected ? 'Rejected' : 'Closed' }}:</span>
                <span class="text-slate-500 dark:text-slate-400">{{ \App\Domains\ClubAccounting\Services\CandidateTransitionService::REJECT_REASONS[$candidate->outcome_reason] ?? \App\Domains\ClubAccounting\Services\CandidateTransitionService::CLOSE_REASONS[$candidate->outcome_reason] ?? $candidate->outcome_reason }} — {{ $candidate->outcome_note }}</span>
            </div>
        @endif
    </x-card>

    <!-- Tabs -->
    <div class="flex flex-wrap items-center gap-1" role="group" aria-label="Sections">
        @foreach(['details' => 'Details', 'timeline' => 'Timeline & notes', 'formp' => 'Form P & ballot'] as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'" :aria-pressed="tab === '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-slate-900 text-white dark:bg-slate-700' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'" class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors cursor-pointer">{{ $label }}</button>
        @endforeach
    </div>

    <!-- Details -->
    <x-card x-show="tab === 'details'" x-cloak>
        <form wire:submit.prevent="saveDetails" class="space-y-4">
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

            <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200 dark:border-slate-800">
                <x-select label="Candidate type" wire:model="candidate_type">
                    <option value="new_candidate" @selected($candidate_type === 'new_candidate')>New candidate (initiation)</option>
                    <option value="joining_member" @selected($candidate_type === 'joining_member')>Joining member</option>
                </x-select>
                <x-select label="Owner" wire:model="owner_user_id">
                    <option value="">Not assigned</option>
                    @foreach($owners as $person)<option value="{{ $person->id }}" @selected($owner_user_id === $person->id)>{{ $person->name }}</option>@endforeach
                </x-select>
                @if($candidate_type === 'joining_member')
                    <x-input label="Grand Lodge number" wire:model="grand_lodge_number" />
                    <x-input label="Mother lodge" wire:model="mother_lodge_info" placeholder="Name and number" />
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3">
                <x-select label="How did they hear of us?" wire:model="source">
                    <option value="">Not recorded</option>
                    @foreach(['website' => 'Website', 'member' => 'A member', 'event' => 'An event', 'social' => 'Social media', 'other' => 'Other'] as $sourceValue => $sourceLabel)
                        <option value="{{ $sourceValue }}" @selected($source === $sourceValue)>{{ $sourceLabel }}</option>
                    @endforeach
                </x-select>
                <x-input label="Who or which?" wire:model="source_note" />
            </div>

            <x-textarea label="General notes" rows="3" wire:model="notes" />

            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <x-button type="submit">Save details</x-button>
                @if($candidate->stage->value !== 'initiated')
                    <x-button variant="danger" size="sm" wire:click="deleteCandidate" wire:confirm="Delete {{ $candidate->full_name }} from the pipeline? This cannot be undone.">Delete candidate</x-button>
                @endif
            </div>
        </form>
    </x-card>

    <!-- Timeline & notes -->
    <div x-show="tab === 'timeline'" x-cloak class="grid md:grid-cols-3 gap-6">
        <x-card padding="sm" class="md:col-span-1 h-fit space-y-3">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Add to the record</h2>
            <form wire:submit.prevent="addNote" class="space-y-3">
                <fieldset>
                    <legend class="sr-only">Type of entry</legend>
                    <div class="flex gap-2">
                        @foreach(['note' => 'Note', 'call' => 'Call', 'meeting' => 'Meeting'] as $val => $noteLabel)
                            <label class="flex-1 text-center px-2 py-1.5 rounded-lg border text-xs font-medium cursor-pointer has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-blue-500 {{ $note_type === $val ? 'border-blue-400 bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-200' : 'border-slate-200 text-slate-700 dark:border-slate-800 dark:text-slate-200' }}">
                                <input type="radio" wire:model.live="note_type" value="{{ $val }}" class="sr-only" />{{ $noteLabel }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <x-input type="date" label="Date" wire:model="note_date" />
                <x-input label="Summary" required wire:model="note_summary" placeholder="e.g. Phoned, keen to proceed" />
                <x-textarea label="Details" rows="3" wire:model="note_body" />
                <x-button type="submit" block>Add</x-button>
            </form>
        </x-card>

        <x-card padding="sm" class="md:col-span-2">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">History</h2>
            <ol class="space-y-3">
                @forelse($candidate->events as $event)
                    <li wire:key="event-{{ $event->id }}" class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $event->summary }}</span>
                            @if(in_array($event->type, \App\Domains\ClubAccounting\Models\CandidateEvent::NOTE_TYPES, true))
                                <button type="button" wire:click="deleteNote({{ $event->id }})" wire:confirm="Remove this note?" class="rounded p-0.5 text-slate-400 hover:text-rose-600 cursor-pointer" aria-label="Remove note">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                                </button>
                            @endif
                        </div>
                        @if($event->body)<p class="mt-1 text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $event->body }}</p>@endif
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $event->occurred_at->format('j M Y, H:i') }} @if($event->author) · {{ $event->author->name }} @endif</p>
                    </li>
                @empty
                    <li class="text-slate-500 dark:text-slate-400 text-xs">Nothing recorded yet.</li>
                @endforelse
            </ol>
        </x-card>
    </div>

    <!-- Form P & ballot -->
    <x-card x-show="tab === 'formp'" x-cloak class="space-y-4 text-xs">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Proposer, seconder, Form P and the ballot</h2>
            <x-button variant="secondary" size="sm" wire:click="openFormPModal({{ $candidate->id }})">Edit</x-button>
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40"><span class="text-slate-500 dark:text-slate-400">Proposer</span><div class="font-semibold text-slate-900 dark:text-white">{{ $candidate->proposer?->formatted_rank_name ?: 'Not chosen' }}</div></div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40"><span class="text-slate-500 dark:text-slate-400">Seconder</span><div class="font-semibold text-slate-900 dark:text-white">{{ $candidate->seconder?->formatted_rank_name ?: 'Not chosen' }}</div></div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40"><span class="text-slate-500 dark:text-slate-400">Form P signed</span><div class="font-semibold text-slate-900 dark:text-white">{{ $candidate->form_p_signed_at?->format('j M Y') ?: '—' }}</div></div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40"><span class="text-slate-500 dark:text-slate-400">Proposed in open lodge</span><div class="font-semibold text-slate-900 dark:text-white">{{ $candidate->proposed_at?->format('j M Y') ?: '—' }}</div></div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40"><span class="text-slate-500 dark:text-slate-400">Committee recommendation</span><div class="font-semibold text-slate-900 dark:text-white">{{ $candidate->committee_recommended_at ? 'Recommended '.$candidate->committee_recommended_at->format('j M Y') : 'Not yet' }}</div></div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40"><span class="text-slate-500 dark:text-slate-400">Ballot</span><div class="font-semibold text-slate-900 dark:text-white">{{ $candidate->ballot_at ? $candidate->ballot_at->format('j M Y').' — '.($candidate->ballot_result === 'elected' ? 'Elected' : 'Not elected') : 'Not held' }}</div></div>
            @if($candidate->initiate_by)
                <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 sm:col-span-2"><span class="text-emerald-700 dark:text-emerald-300">Must be initiated by</span><div class="font-semibold text-emerald-900 dark:text-emerald-200">{{ $candidate->initiate_by->format('j M Y') }}</div></div>
            @endif
        </div>
    </x-card>

    @include('livewire.candidates.partials.dialogs')
</div>
