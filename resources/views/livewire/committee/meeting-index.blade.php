<div class="space-y-6">
    {{-- The same list as Components/MeetingsTabs.vue. --}}
    <x-tabs label="Meetings" active="committee" class="mb-6" :items="[
        ['key' => 'lodge', 'label' => 'Lodge meetings & summonses', 'href' => route('admin.meetings.index', ['clubSlug' => $clubSlug])],
        ['key' => 'committee', 'label' => 'Committee & board governance', 'href' => route('admin.committee.index', ['clubSlug' => $clubSlug])],
        ['key' => 'visitors', 'label' => 'Visitors', 'href' => route('admin.visitors.index', ['clubSlug' => $clubSlug])],
        ['key' => 'officers', 'label' => 'Annual officers roster & board', 'href' => route('admin.officers.index', ['clubSlug' => $clubSlug])],
    ]" />

    <x-card class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Committee and board meetings</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage executive meetings, pre-meeting briefing packs and live minutes under UGLE governance rules.</p>
        </div>

        <x-button wire:click="openCreateModal" class="self-start sm:self-auto">Schedule committee meeting</x-button>
    </x-card>

    <x-notice variant="warning" title="Constitutional advisory (UGLE Rules 153 and 158)">
        The Lodge Committee is an advisory, investigating and auditing body. Committee actions are recommendations that need confirming in open lodge. All candidate vetting must be signed off here before the candidate appears on the summons (Rule 159).
    </x-notice>

    <x-card padding="sm" class="flex flex-col items-center justify-between gap-4 sm:flex-row">
        <div role="group" aria-label="Filter meetings" class="flex w-full items-center gap-1 overflow-x-auto sm:w-auto">
            @foreach(['all' => 'All meetings', 'scheduled' => 'Scheduled', 'draft' => 'Draft', 'past' => 'Past'] as $val => $label)
                <button
                    type="button"
                    wire:click="$set('statusFilter', '{{ $val }}')"
                    aria-pressed="{{ $statusFilter === $val ? 'true' : 'false' }}"
                    @class([
                        'cursor-pointer whitespace-nowrap rounded-xl px-3.5 py-1.5 text-xs font-semibold transition-colors',
                        'bg-slate-900 text-white shadow-sm dark:bg-slate-700' => $statusFilter === $val,
                        'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' => $statusFilter !== $val,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="w-full sm:w-64">
            <x-input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search meetings by title..."
                aria-label="Search meetings by title"
            />
        </div>
    </x-card>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
        @forelse($meetings as $m)
            @php
                $statusLabel = $m->displayStatusLabel();
                $statusVariant = $m->isPast() ? 'neutral' : match ($m->status) {
                    \App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus::Scheduled => 'info',
                    \App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus::Finalized => 'success',
                    default => 'warning',
                };
            @endphp
            <x-card class="group flex flex-col justify-between space-y-5 transition-shadow hover:shadow-md">
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <x-badge :variant="$statusVariant">{{ $statusLabel }}</x-badge>
                        <span class="font-mono text-xs font-medium text-slate-500 dark:text-slate-400">
                            {{ $m->meeting_date->format('d M Y, H:i') }}
                        </span>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold text-slate-900 transition-colors group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                            {{ $m->title }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $m->location ?: 'Lodge committee room' }}
                        </p>
                    </div>

                    <dl class="grid grid-cols-3 gap-2 border-t border-slate-200 pt-2 text-center text-xs dark:border-slate-800">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-800 dark:bg-slate-800/50">
                            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Attendees</dt>
                            <dd class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $m->attendees->count() }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-800 dark:bg-slate-800/50">
                            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Agenda</dt>
                            <dd class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $m->agendaItems->count() }}</dd>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-800 dark:bg-slate-800/50">
                            <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Tasks</dt>
                            <dd class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $m->tasks->count() }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="flex items-center gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                    <x-button
                        variant="secondary"
                        size="sm"
                        wire:click="openEditModal({{ $m->id }})"
                        title="Edit meeting title, date and location"
                        aria-label="Edit {{ $m->title }}"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </x-button>
                    <x-button variant="secondary" size="sm" class="flex-1" href="{{ route('admin.committee.workspace', ['clubSlug' => $club->slug, 'meetingId' => $m->id]) }}">
                        Agenda pack
                    </x-button>
                    <x-button size="sm" class="flex-1" href="{{ route('admin.committee.minutes', ['clubSlug' => $club->slug, 'meetingId' => $m->id]) }}">
                        Live minutes
                    </x-button>
                </div>
            </x-card>
        @empty
            <div class="col-span-full space-y-3 rounded-2xl border-2 border-dashed border-slate-200 bg-white py-16 text-center dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">No committee meetings found</p>
                <p class="mx-auto max-w-sm text-xs text-slate-500 dark:text-slate-400">Schedule a meeting to start organising agenda items, vetting candidates, auditing accounts and taking live minutes.</p>
                <x-button wire:click="openCreateModal">Schedule the first committee meeting</x-button>
            </div>
        @endforelse
    </div>

    @if ($showCreateModal)
    <x-modal :show="$showCreateModal" title="Schedule committee meeting" close="closeCreateModal">
        <form wire:submit="createMeeting" id="meeting-index-create-form" class="space-y-4">
            @if(isset($upcomingRegularMeetings) && $upcomingRegularMeetings->isNotEmpty())
                <x-select
                    label="Link to a regular lodge meeting (optional)"
                    hint="Picking a regular lodge meeting moves the committee date to 9 days before it."
                    wire:model.live="linked_regular_meeting_id"
                >
                    <option value="">Standalone committee meeting</option>
                    @foreach($upcomingRegularMeetings as $regMeeting)
                        <option value="{{ $regMeeting->id }}">
                            {{ $regMeeting->title ?: 'Regular lodge meeting' }} ({{ $regMeeting->meeting_date?->format('d M Y') }})
                        </option>
                    @endforeach
                </x-select>
            @endif

            <x-input
                type="datetime-local"
                label="Date and time"
                hint="Sets the standard title."
                wire:model.live="newDate"
                required
            />

            <x-input
                label="Meeting title"
                hint="Filled in from the meeting date. You can change it if you like."
                wire:model.live="newTitle"
                placeholder="e.g. Committee Meeting – 15th September 2026"
                required
            />

            <x-input
                label="Location or venue"
                hint="Defaults to the lodge's usual venue."
                wire:model="newLocation"
                placeholder="e.g. Masonic Hall, Wellington Street..."
            />
        </form>

        <x-slot:footer>
            <x-button variant="secondary" wire:click="closeCreateModal">Cancel</x-button>
            <x-button type="submit" form="meeting-index-create-form">Schedule meeting</x-button>
        </x-slot:footer>
    </x-modal>
    @endif

    @if ($showEditModal)
    <x-modal :show="$showEditModal" title="Edit committee meeting" subtitle="Update the meeting title, date and time, and location." close="closeEditModal">
        <form wire:submit="updateMeeting" id="meeting-index-edit-form" class="space-y-4">
            <x-input
                label="Meeting title or name"
                wire:model="editTitle"
                placeholder="e.g. Regular Committee Meeting"
                required
            />

            <x-input
                type="datetime-local"
                label="Date and time"
                wire:model="editDate"
                required
            />

            <x-input
                label="Meeting location"
                wire:model="editLocation"
                placeholder="e.g. Masonic Hall, Wellington Street..."
            />

            <x-select label="Meeting status" wire:model="editStatus">
                <option value="draft" @selected($editStatus === 'draft')>Draft (invites not yet sent)</option>
                <option value="scheduled" @selected($editStatus === 'scheduled')>Scheduled (invites sent)</option>
            </x-select>
        </form>

        <x-slot:footer>
            <x-button variant="secondary" wire:click="closeEditModal">Cancel</x-button>
            <x-button type="submit" form="meeting-index-edit-form" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="updateMeeting">Save changes</span>
                <span wire:loading wire:target="updateMeeting">Saving...</span>
            </x-button>
        </x-slot:footer>
    </x-modal>
    @endif

    @livewire(\App\Domains\ClubAccounting\Livewire\Committee\CreateCommitteeMeetingModal::class, ['clubSlug' => $club->slug], key('create-comm-modal-' . $club->slug))
</div>
