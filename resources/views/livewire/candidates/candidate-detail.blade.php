@php
    $field = 'w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none text-xs';
    $lbl = 'font-bold text-slate-700 dark:text-slate-200 block mb-1 text-xs';
    $tabButton = 'px-3.5 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer';
@endphp

<div class="space-y-6" x-data="{ tab: 'details' }">
    <a href="{{ route('admin.club_acc.candidates.index', ['clubSlug' => $club->slug]) }}" class="inline-flex items-center gap-1 text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-amber-600 dark:hover:text-amber-400">&larr; Back to the pipeline</a>

    @if (session()->has('success'))
        <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-200 text-xs font-semibold rounded-2xl flex items-center gap-2"><span>✅</span><span>{{ session('success') }}</span></div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-200 text-xs font-semibold rounded-2xl flex items-center gap-2"><span>⚠️</span><span>{{ session('error') }}</span></div>
    @endif

    <!-- Header: who, where they are, and the actions -->
    <div class="p-6 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-3xl shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl font-black text-slate-900 dark:text-white">{{ $candidate->full_name }}</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $candidate->stage->badgeClasses() }}">{{ $candidate->stage->label() }}</span>
                    @if($candidate->isOnHold())<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border-amber-300">On hold</span>@endif
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $candidate->stage->hint() }}</p>
                @if($candidate->owner)<p class="text-[11px] text-slate-400 mt-0.5">Owner: {{ $candidate->owner->name }}</p>@endif
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($candidate->isActive())
                    @php $next = $candidate->stage->next(); @endphp
                    @if($next && $next->value === 'accepted')
                        <button type="button" wire:click="openBallot({{ $candidate->id }})" class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-black rounded-xl shadow-sm cursor-pointer">🗳️ Record ballot</button>
                    @elseif($next && $next->value === 'initiated')
                        <button type="button" wire:click="openInitiationModal({{ $candidate->id }})" class="px-4 py-2 bg-blue-700 hover:bg-blue-800 text-white text-xs font-black rounded-xl shadow-sm cursor-pointer">🏛️ Record initiation</button>
                    @elseif($next)
                        <button type="button" wire:click="advance({{ $candidate->id }})" class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-black rounded-xl shadow-sm cursor-pointer">Move on to {{ $next->shortLabel() }} &rarr;</button>
                    @endif
                    @if($candidate->isOnHold())
                        <button type="button" wire:click="resume({{ $candidate->id }})" class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl cursor-pointer">Take off hold</button>
                    @else
                        <button type="button" wire:click="openOutcome({{ $candidate->id }}, 'hold')" class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl cursor-pointer">Hold</button>
                    @endif
                    <button type="button" wire:click="openOutcome({{ $candidate->id }}, 'close')" class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl cursor-pointer">Close</button>
                    <button type="button" wire:click="openOutcome({{ $candidate->id }}, 'reject')" class="px-3.5 py-2 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 text-xs font-bold rounded-xl cursor-pointer">Reject</button>
                @elseif($canReopen && $candidate->stage->isClosed())
                    <button type="button" wire:click="openOutcome({{ $candidate->id }}, 'reopen')" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-black rounded-xl shadow-sm cursor-pointer">Reopen</button>
                @endif
            </div>
        </div>

        @if($candidate->isActive() && count($checks))
            <div class="flex flex-wrap gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                @foreach($checks as $label => $done)
                    <span class="px-2.5 py-1 rounded-lg text-[11px] font-semibold {{ $done ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400' }}">{{ $done ? '✓' : '○' }} {{ $label }}</span>
                @endforeach
            </div>
        @endif

        @if(! $candidate->isActive() && $candidate->outcome_note)
            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 text-xs">
                <span class="font-bold text-slate-700 dark:text-slate-200">{{ $candidate->stage === \App\Domains\ClubAccounting\Enums\CandidateStage::Rejected ? 'Rejected' : 'Closed' }}:</span>
                <span class="text-slate-500 dark:text-slate-400">{{ \App\Domains\ClubAccounting\Services\CandidateTransitionService::REJECT_REASONS[$candidate->outcome_reason] ?? \App\Domains\ClubAccounting\Services\CandidateTransitionService::CLOSE_REASONS[$candidate->outcome_reason] ?? $candidate->outcome_reason }} — {{ $candidate->outcome_note }}</span>
            </div>
        @endif
    </div>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl p-1.5">
        @foreach(['details' => 'Details', 'timeline' => 'Timeline & notes', 'formp' => 'Form P & ballot'] as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-slate-900 dark:bg-slate-700 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'" class="{{ $tabButton }}">{{ $label }}</button>
        @endforeach
    </div>

    <!-- Details -->
    <div x-show="tab === 'details'" x-cloak class="p-6 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl shadow-sm">
        <form wire:submit.prevent="saveDetails" class="space-y-4 text-xs">
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $lbl }}">First name *</label><input type="text" wire:model="first_name" class="{{ $field }}" required />@error('first_name') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror</div>
                <div><label class="{{ $lbl }}">Last name *</label><input type="text" wire:model="last_name" class="{{ $field }}" required />@error('last_name') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror</div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $lbl }}">Email</label><input type="email" wire:model="email" class="{{ $field }}" />@error('email') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror</div>
                <div><label class="{{ $lbl }}">Phone</label><input type="text" wire:model="phone" class="{{ $field }}" /></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $lbl }}">Date of birth</label><input type="date" wire:model="date_of_birth" class="{{ $field }}" />@error('date_of_birth') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror</div>
                <div><label class="{{ $lbl }}">Occupation</label><input type="text" wire:model="occupation" class="{{ $field }}" /></div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2"><label class="{{ $lbl }}">Address</label><input type="text" wire:model="address" class="{{ $field }}" /></div>
                <div><label class="{{ $lbl }}">Postcode</label><input type="text" wire:model="postcode" class="{{ $field }}" /></div>
            </div>

            <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200 dark:border-slate-800">
                <div>
                    <label class="{{ $lbl }}">Candidate type</label>
                    <select wire:model="candidate_type" class="{{ $field }}"><option value="new_candidate">New candidate (initiation)</option><option value="joining_member">Joining member</option></select>
                </div>
                <div><label class="{{ $lbl }}">Owner</label>
                    <select wire:model="owner_user_id" class="{{ $field }}">
                        <option value="">Not assigned</option>
                        @foreach($owners as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach
                    </select>
                </div>
                @if($candidate_type === 'joining_member')
                    <div><label class="{{ $lbl }}">Grand Lodge number</label><input type="text" wire:model="grand_lodge_number" class="{{ $field }}" /></div>
                    <div><label class="{{ $lbl }}">Mother lodge</label><input type="text" wire:model="mother_lodge_info" class="{{ $field }}" placeholder="Name and number" /></div>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="{{ $lbl }}">How did they hear of us?</label>
                    <select wire:model="source" class="{{ $field }}">
                        <option value="">Not recorded</option>
                        <option value="website">Website</option><option value="member">A member</option><option value="event">An event</option><option value="social">Social media</option><option value="other">Other</option>
                    </select>
                </div>
                <div><label class="{{ $lbl }}">Who or which?</label><input type="text" wire:model="source_note" class="{{ $field }}" /></div>
            </div>

            <div><label class="{{ $lbl }}">General notes</label><textarea wire:model="notes" rows="3" class="{{ $field }}"></textarea></div>

            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-xl shadow-md">Save details</button>
                @if($candidate->stage->value !== 'initiated')
                    <button type="button" wire:click="deleteCandidate" wire:confirm="Delete {{ $candidate->full_name }} from the pipeline? This cannot be undone." class="text-rose-600 dark:text-rose-400 font-bold hover:underline">Delete candidate</button>
                @endif
            </div>
        </form>
    </div>

    <!-- Timeline & notes -->
    <div x-show="tab === 'timeline'" x-cloak class="grid md:grid-cols-3 gap-6">
        <div class="md:col-span-1 p-5 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl shadow-sm h-fit space-y-3">
            <h3 class="font-black text-slate-900 dark:text-white text-sm">Add to the record</h3>
            <form wire:submit.prevent="addNote" class="space-y-3 text-xs">
                <div class="flex gap-2">
                    @foreach(['note' => 'Note', 'call' => 'Call', 'meeting' => 'Meeting'] as $val => $noteLabel)
                        <label class="flex-1 text-center px-2 py-1.5 rounded-lg border cursor-pointer {{ $note_type === $val ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/40' : 'border-slate-200 dark:border-slate-800' }}">
                            <input type="radio" wire:model.live="note_type" value="{{ $val }}" class="sr-only" />{{ $noteLabel }}
                        </label>
                    @endforeach
                </div>
                <div><label class="{{ $lbl }}">Date</label><input type="date" wire:model="note_date" class="{{ $field }}" /></div>
                <div><label class="{{ $lbl }}">Summary *</label><input type="text" wire:model="note_summary" class="{{ $field }}" placeholder="e.g. Phoned, keen to proceed" />@error('note_summary') <span class="text-rose-600 text-[10px]">{{ $message }}</span> @enderror</div>
                <div><label class="{{ $lbl }}">Details</label><textarea wire:model="note_body" rows="3" class="{{ $field }}"></textarea></div>
                <button type="submit" class="w-full px-4 py-2 bg-slate-900 dark:bg-slate-700 text-white font-bold rounded-xl">Add</button>
            </form>
        </div>

        <div class="md:col-span-2 p-5 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl shadow-sm">
            <h3 class="font-black text-slate-900 dark:text-white text-sm mb-3">History</h3>
            <ol class="space-y-3">
                @forelse($candidate->events as $event)
                    <li class="p-3 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-slate-900 dark:text-white">{{ $event->summary }}</span>
                            @if(in_array($event->type, \App\Domains\ClubAccounting\Models\CandidateEvent::NOTE_TYPES, true))
                                <button type="button" wire:click="deleteNote({{ $event->id }})" wire:confirm="Remove this note?" class="text-slate-400 hover:text-rose-600 cursor-pointer" aria-label="Remove note">✕</button>
                            @endif
                        </div>
                        @if($event->body)<p class="mt-1 text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $event->body }}</p>@endif
                        <p class="mt-1 text-[10px] text-slate-400">{{ $event->occurred_at->format('j M Y, H:i') }} @if($event->author) · {{ $event->author->name }} @endif</p>
                    </li>
                @empty
                    <li class="text-slate-400 italic text-xs">Nothing recorded yet.</li>
                @endforelse
            </ol>
        </div>
    </div>

    <!-- Form P & ballot -->
    <div x-show="tab === 'formp'" x-cloak class="p-6 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl shadow-sm space-y-4 text-xs">
        <div class="flex items-center justify-between">
            <h3 class="font-black text-slate-900 dark:text-white text-sm">Proposer, seconder, Form P and the ballot</h3>
            <button type="button" wire:click="openFormPModal({{ $candidate->id }})" class="px-3.5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold rounded-xl cursor-pointer">Edit</button>
        </div>
        <div class="grid sm:grid-cols-2 gap-3">
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40"><span class="text-slate-400">Proposer</span><div class="font-bold text-slate-900 dark:text-white">{{ $candidate->proposer?->formatted_rank_name ?: 'Not chosen' }}</div></div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40"><span class="text-slate-400">Seconder</span><div class="font-bold text-slate-900 dark:text-white">{{ $candidate->seconder?->formatted_rank_name ?: 'Not chosen' }}</div></div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40"><span class="text-slate-400">Form P signed</span><div class="font-bold text-slate-900 dark:text-white">{{ $candidate->form_p_signed_at?->format('j M Y') ?: '—' }}</div></div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40"><span class="text-slate-400">Proposed in open lodge</span><div class="font-bold text-slate-900 dark:text-white">{{ $candidate->proposed_at?->format('j M Y') ?: '—' }}</div></div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40"><span class="text-slate-400">Committee recommendation</span><div class="font-bold text-slate-900 dark:text-white">{{ $candidate->committee_recommended_at ? 'Recommended '.$candidate->committee_recommended_at->format('j M Y') : 'Not yet' }}</div></div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40"><span class="text-slate-400">Ballot</span><div class="font-bold text-slate-900 dark:text-white">{{ $candidate->ballot_at ? $candidate->ballot_at->format('j M Y').' — '.($candidate->ballot_result === 'elected' ? 'Elected' : 'Not elected') : 'Not held' }}</div></div>
            @if($candidate->initiate_by)
                <div class="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 sm:col-span-2"><span class="text-emerald-700 dark:text-emerald-300">Must be initiated by</span><div class="font-bold text-emerald-900 dark:text-emerald-200">{{ $candidate->initiate_by->format('j M Y') }}</div></div>
            @endif
        </div>
    </div>

    @include('livewire.candidates.partials.dialogs')
</div>
