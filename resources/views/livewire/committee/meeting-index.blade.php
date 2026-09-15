<div class="space-y-6">
    <!-- Top Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Committee & Board Meetings</h2>
            <p class="text-xs text-slate-500 mt-1">Manage executive meetings, pre-meeting briefing packs, and live minutes under UGLE governance rules.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.meetings.index', ['clubSlug' => $club->slug]) }}" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 transition-all flex items-center gap-1.5">
                📅 Open Lodge Meetings
            </a>
            <button
                type="button"
                wire:click="openCreateModal"
                class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20 transition-all cursor-pointer inline-flex items-center gap-2 self-start sm:self-auto"
            >
                <span>➕</span>
                <span>Schedule Committee Meeting</span>
            </button>
        </div>
    </div>

    <!-- Rule Advisory Banner -->
    <div class="p-4 bg-amber-50/80 border border-amber-200/80 rounded-2xl flex items-start gap-3 text-xs text-amber-900">
        <span class="text-xl shrink-0">⚖️</span>
        <div>
            <span class="font-extrabold block">Constitutional Advisory (UGLE Rule 153 & 158)</span>
            <span class="text-amber-800/90 leading-relaxed block mt-0.5">
                The Lodge Committee is an advisory, investigating, and auditing body. Committee actions are recommendations requiring open lodge confirmation. All candidate vetting must be signed off here before appearance on the Summons (Rule 159).
            </span>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-1 overflow-x-auto w-full sm:w-auto">
            @foreach(['all' => 'All Meetings', 'scheduled' => 'Scheduled', 'in_progress' => 'In Progress', 'draft_saved' => 'Draft Saved', 'finalized' => 'Finalized'] as $val => $label)
                <button
                    type="button"
                    wire:click="$set('statusFilter', '{{ $val }}')"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap {{ $statusFilter === $val ? 'bg-slate-900 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}"
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
                class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all font-medium"
            />
        </div>
    </div>

    <!-- Meetings Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($meetings as $m)
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-all flex flex-col justify-between space-y-5 group">
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg border {{ $m->status->badgeClass() }}">
                            {{ $m->status->label() }}
                        </span>
                        <span class="text-xs font-bold text-slate-400 font-mono">
                            {{ $m->meeting_date->format('d M Y, H:i') }}
                        </span>
                    </div>

                    <div>
                        <h3 class="font-black text-slate-900 text-base group-hover:text-indigo-600 transition-colors">
                            {{ $m->title }}
                        </h3>
                        <p class="text-xs text-slate-500 flex items-center gap-1 mt-1">
                            <span>📍</span>
                            <span>{{ $m->location ?: 'Lodge Committee Room' }}</span>
                        </p>
                    </div>

                    <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 text-center text-xs">
                        <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-slate-400 block text-[10px] font-bold">Attendees</span>
                            <span class="font-black text-slate-800 text-sm">{{ $m->attendees->count() }}</span>
                        </div>
                        <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-slate-400 block text-[10px] font-bold">Agenda</span>
                            <span class="font-black text-slate-800 text-sm">{{ $m->agendaItems->count() }}</span>
                        </div>
                        <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                            <span class="text-slate-400 block text-[10px] font-bold">Tasks</span>
                            <span class="font-black text-slate-800 text-sm">{{ $m->tasks->count() }}</span>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center gap-2">
                    <a
                        href="{{ route('admin.committee.workspace', ['clubSlug' => $club->slug, 'meetingId' => $m->id]) }}"
                        class="flex-1 py-2 text-center bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all"
                    >
                        📁 Agenda Pack
                    </a>
                    <a
                        href="{{ route('admin.committee.minutes', ['clubSlug' => $club->slug, 'meetingId' => $m->id]) }}"
                        class="flex-1 py-2 text-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all"
                    >
                        ✍️ Live Minutes
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 bg-white rounded-3xl border-2 border-dashed border-slate-200 text-center space-y-3">
                <span class="text-4xl block">🏛️</span>
                <span class="text-sm font-bold text-slate-800 block">No committee meetings found</span>
                <p class="text-xs text-slate-500 max-w-sm mx-auto">Schedule a meeting to start organizing agenda items, vetting candidates, auditing accounts, and taking live minutes.</p>
                <button
                    type="button"
                    wire:click="openCreateModal"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer"
                >
                    ➕ Schedule First Committee Meeting
                </button>
            </div>
        @endforelse
    </div>

    <!-- Schedule Meeting Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4">
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                        <span>🏛️</span>
                        <span>Schedule Committee Meeting</span>
                    </h3>
                    <button type="button" wire:click="closeCreateModal" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
                </div>

                <form wire:submit="createMeeting" class="space-y-4 text-xs">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700">Meeting Title</label>
                        <input
                            type="text"
                            wire:model="newTitle"
                            placeholder="e.g. Regular Lodge Committee - October 2026"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            required
                        />
                        @error('newTitle') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700">Date & Time</label>
                        <input
                            type="datetime-local"
                            wire:model="newDate"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            required
                        />
                        @error('newDate') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700">Location / Room</label>
                        <input
                            type="text"
                            wire:model="newLocation"
                            placeholder="e.g. Committee Room 2, Masonic Hall"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                        />
                        @error('newLocation') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            wire:click="closeCreateModal"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-all"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-sm transition-all cursor-pointer"
                        >
                            Schedule Meeting
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
