<div class="space-y-6">
    <!-- Meetings & Governance Tab Bar -->
    <div class="flex items-center gap-2 p-1.5 bg-slate-200/80 dark:bg-slate-700/80 rounded-2xl w-fit text-xs font-bold border border-slate-300/60 dark:border-slate-700/60 mb-6 shadow-inner">
        <a
            href="{{ route('admin.meetings.index', ['clubSlug' => $clubSlug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-slate-900/60"
        >
            <span>📅</span>
            <span>Lodge Meetings & Summonses</span>
        </a>

        <a
            href="{{ route('admin.committee.index', ['clubSlug' => $clubSlug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer bg-slate-900 dark:bg-slate-700 text-white shadow-md font-black"
        >
            <span>🏛️</span>
            <span>Committee & Board Governance</span>
        </a>
    </div>

    <!-- Top Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Committee & Board Meetings</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Manage executive meetings, pre-meeting briefing packs, and live minutes under UGLE governance rules.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button
                type="button"
                wire:click="openCreateModal"
                class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-600/20 transition-all cursor-pointer inline-flex items-center gap-2 self-start sm:self-auto"
            >
                <span>➕</span>
                <span>Schedule Committee Meeting</span>
            </button>
        </div>
    </div>

    <!-- Rule Advisory Banner -->
    <div class="p-4 bg-amber-50/80 dark:bg-amber-950/80 border border-amber-200/80 dark:border-amber-800/80 rounded-2xl flex items-start gap-3 text-xs text-amber-900 dark:text-amber-200">
        <span class="text-xl shrink-0">⚖️</span>
        <div>
            <span class="font-extrabold block">Constitutional Advisory (UGLE Rule 153 & 158)</span>
            <span class="text-amber-800/90 dark:text-amber-200/90 leading-relaxed block mt-0.5">
                The Lodge Committee is an advisory, investigating, and auditing body. Committee actions are recommendations requiring open lodge confirmation. All candidate vetting must be signed off here before appearance on the Summons (Rule 159).
            </span>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-1 overflow-x-auto w-full sm:w-auto">
            @foreach(['all' => 'All Meetings', 'scheduled' => 'Scheduled', 'draft' => 'Draft', 'past' => 'Past'] as $val => $label)
                <button
                    type="button"
                    wire:click="$set('statusFilter', '{{ $val }}')"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap {{ $statusFilter === $val ? 'bg-slate-900 dark:bg-slate-700 text-white shadow-sm' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="w-full sm:w-64">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Search meetings by title..."
                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white dark:focus:bg-slate-900 transition-all font-medium"
            />
        </div>
    </div>

    <!-- Meetings Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($meetings as $m)
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between space-y-5 group">
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg border {{ $m->displayBadgeClass() }}">
                            {{ $m->displayStatusLabel() }}
                        </span>
                        <span class="text-xs font-bold text-slate-400 font-mono">
                            {{ $m->meeting_date->format('d M Y, H:i') }}
                        </span>
                    </div>

                    <div>
                        <h3 class="font-black text-slate-900 dark:text-white text-base group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                            {{ $m->title }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1 mt-1">
                            <span>📍</span>
                            <span>{{ $m->location ?: 'Lodge Committee Room' }}</span>
                        </p>
                    </div>

                    <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-center text-xs">
                        <div class="p-2 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <span class="text-slate-400 block text-[10px] font-bold">Attendees</span>
                            <span class="font-black text-slate-800 dark:text-slate-100 text-sm">{{ $m->attendees->count() }}</span>
                        </div>
                        <div class="p-2 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <span class="text-slate-400 block text-[10px] font-bold">Agenda</span>
                            <span class="font-black text-slate-800 dark:text-slate-100 text-sm">{{ $m->agendaItems->count() }}</span>
                        </div>
                        <div class="p-2 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800">
                            <span class="text-slate-400 block text-[10px] font-bold">Tasks</span>
                            <span class="font-black text-slate-800 dark:text-slate-100 text-sm">{{ $m->tasks->count() }}</span>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="openEditModal({{ $m->id }})"
                        class="p-2 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50 dark:hover:bg-blue-950/40 border border-slate-200/80 dark:border-slate-800/80 rounded-xl transition-all"
                        title="Edit Meeting Title, Date & Location"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                    <a
                        href="{{ route('admin.committee.workspace', ['clubSlug' => $club->slug, 'meetingId' => $m->id]) }}"
                        class="flex-1 py-2 text-center bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition-all"
                    >
                        📁 Agenda Pack
                    </a>
                    <a
                        href="{{ route('admin.committee.minutes', ['clubSlug' => $club->slug, 'meetingId' => $m->id]) }}"
                        class="flex-1 py-2 text-center bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all"
                    >
                        ✍️ Live Minutes
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 bg-white dark:bg-slate-900 rounded-3xl border-2 border-dashed border-slate-200 dark:border-slate-800 text-center space-y-3">
                <span class="text-4xl block">🏛️</span>
                <span class="text-sm font-bold text-slate-800 dark:text-slate-100 block">No committee meetings found</span>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">Schedule a meeting to start organizing agenda items, vetting candidates, auditing accounts, and taking live minutes.</p>
                <button
                    type="button"
                    wire:click="openCreateModal"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer"
                >
                    ➕ Schedule First Committee Meeting
                </button>
            </div>
        @endforelse
    </div>

    <!-- Schedule Meeting Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <span>🏛️</span>
                        <span>Schedule Committee Meeting</span>
                    </h3>
                    <button type="button" wire:click="closeCreateModal" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm">✕</button>
                </div>

                <form wire:submit="createMeeting" class="space-y-4 text-xs">
                    <!-- Helper: Link to Regular Lodge Meeting (Optional) -->
                    @if(isset($upcomingRegularMeetings) && $upcomingRegularMeetings->isNotEmpty())
                        <div class="p-3 bg-blue-50/50 dark:bg-blue-950/50 border border-blue-200/70 dark:border-blue-800/70 rounded-2xl space-y-1">
                            <label class="block font-bold text-blue-950 dark:text-blue-100 flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <span>🔗</span>
                                    <span>Link to Regular Lodge Meeting (Optional)</span>
                                </span>
                                <span class="text-[10px] font-semibold text-blue-600 dark:text-blue-400 bg-blue-100/70 dark:bg-blue-900/70 px-2 py-0.5 rounded-full">Auto-Suggests -9 Days</span>
                            </label>
                            <select
                                wire:model.live="linked_regular_meeting_id"
                                class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-blue-200 dark:border-blue-800/60 rounded-xl text-xs text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            >
                                <option value="">-- Standalone Committee Meeting --</option>
                                @foreach($upcomingRegularMeetings as $regMeeting)
                                    <option value="{{ $regMeeting->id }}">
                                        {{ $regMeeting->title ?: 'Regular Lodge Meeting' }} ({{ $regMeeting->meeting_date?->format('d M Y') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-slate-700 dark:text-slate-200">Date &amp; Time</label>
                            <span class="text-[10px] text-slate-400">Triggers standardized title</span>
                        </div>
                        <input
                            type="datetime-local"
                            wire:model.live="newDate"
                            class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none bg-slate-50 dark:bg-slate-800/50 focus:bg-white dark:focus:bg-slate-900 transition-all font-semibold"
                            required
                        />
                        @error('newDate') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-slate-700 dark:text-slate-200">Meeting Title</label>
                            <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">✓ Auto-Generated from Date</span>
                        </div>
                        <input
                            type="text"
                            wire:model.live="newTitle"
                            placeholder="e.g. Committee Meeting – 15th September 2026"
                            class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none font-bold text-slate-900 dark:text-white bg-white dark:bg-slate-900 transition-all"
                            required
                        />
                        <p class="text-[10px] text-slate-400">Standardized title generated from meeting date. Can be customized if desired.</p>
                        @error('newTitle') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-slate-700 dark:text-slate-200">Location / Venue</label>
                            <span class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold">Defaulted</span>
                        </div>
                        <input
                            type="text"
                            wire:model="newLocation"
                            placeholder="e.g. Masonic Hall, Wellington Street..."
                            class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                        @error('newLocation') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            wire:click="closeCreateModal"
                            class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs transition-all cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-sm transition-all cursor-pointer flex items-center gap-1.5"
                        >
                            <span>📅</span>
                            <span>Schedule Meeting</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Edit Committee Meeting Modal -->
    @if($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>✏️</span>
                            <span>Edit Committee Meeting</span>
                        </h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Update the meeting title, date &amp; time, and location.</p>
                    </div>
                    <button type="button" wire:click="closeEditModal" class="p-1 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold transition-all">✕</button>
                </div>

                <form wire:submit="updateMeeting" class="space-y-4">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Meeting Title / Name <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            wire:model="editTitle"
                            placeholder="e.g. Regular Committee Meeting"
                            class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            required
                        />
                        @error('editTitle') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Date &amp; Time <span class="text-rose-500">*</span></label>
                        <input
                            type="datetime-local"
                            wire:model="editDate"
                            class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            required
                        />
                        @error('editDate') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Meeting Location</label>
                        <input
                            type="text"
                            wire:model="editLocation"
                            placeholder="e.g. Masonic Hall, Wellington Street..."
                            class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                        @error('editLocation') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Meeting Status</label>
                        <select
                            wire:model="editStatus"
                            class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                            <option value="draft">Draft (invites not yet dispatched)</option>
                            <option value="scheduled">Scheduled (invites dispatched)</option>
                        </select>
                        @error('editStatus') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            wire:click="closeEditModal"
                            class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs transition-all cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-sm transition-all cursor-pointer flex items-center gap-1.5"
                        >
                            <span wire:loading.remove wire:target="updateMeeting">Save Changes</span>
                            <span wire:loading wire:target="updateMeeting">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @livewire(\App\Domains\ClubAccounting\Livewire\Committee\CreateCommitteeMeetingModal::class, ['clubSlug' => $club->slug], key('create-comm-modal-' . $club->slug))
</div>
