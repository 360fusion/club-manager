<div class="space-y-6">
    <!-- Meeting Header Bar -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.committee.workspace', ['clubSlug' => $club->slug, 'meetingId' => $meeting->id]) }}" class="text-xs font-bold text-indigo-600 hover:underline">
                    ← Back to Agenda Pack
                </a>
                <span class="text-slate-300">•</span>
                <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md border {{ $meeting->status->badgeClass() }}">
                    {{ $meeting->status->label() }}
                </span>
            </div>
            <h1 class="text-xl font-black text-slate-900">{{ $meeting->title }} — Live Minutes</h1>
            <p class="text-xs text-slate-500">Fast auto-saving minutes editor with automatic task delegation and notice-of-motion tagging.</p>
        </div>

        <div class="flex items-center gap-3">
            <span class="text-xs font-bold text-slate-400 flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Auto-saved at {{ $lastSavedAt }}</span>
            </span>

            @if($meeting->status->value !== 'finalized')
                <button
                    type="button"
                    wire:click="finalizeMinutes"
                    wire:confirm="Finalize committee minutes? This will confirm the record and commit extracted tasks into the permanent lodge governance register."
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer inline-flex items-center gap-1.5"
                >
                    <span>📜</span>
                    <span>Finalize Minutes</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Main Live Editor Layout (2 Columns) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Left 7 Cols: Editor & Insertion Toolbar -->
        <div class="lg:col-span-7 space-y-4">
            <!-- Toolbar for Quick Tags -->
            <div class="bg-white p-3 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center gap-2 text-xs">
                <span class="text-slate-400 font-bold text-[11px] mr-1">Insert:</span>

                <button
                    type="button"
                    wire:click="insertTemplate('task')"
                    class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-bold rounded-lg transition-all"
                    title="Insert task checkbox with due date"
                >
                    ☑️ [ ] Task
                </button>

                <button
                    type="button"
                    wire:click="insertTemplate('motion')"
                    class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-800 border border-indigo-200 font-bold rounded-lg transition-all"
                    title="Insert formal notice of motion tag"
                >
                    📜 /motion Tag
                </button>

                <button
                    type="button"
                    wire:click="insertTemplate('candidate')"
                    class="px-2.5 py-1 bg-sky-50 hover:bg-sky-100 text-sky-800 border border-sky-200 font-bold rounded-lg transition-all"
                    title="Insert candidate vetting sign-off notes"
                >
                    👤 Candidate Vetting
                </button>

                <button
                    type="button"
                    wire:click="insertTemplate('audit')"
                    class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold rounded-lg transition-all"
                    title="Insert Rule 158 accounts audit notes"
                >
                    🔍 Accounts Audit
                </button>

                <!-- Quick Member Search for @mention -->
                <div class="relative ml-auto">
                    <input
                        type="text"
                        wire:model.live.debounce.250ms="memberQuery"
                        placeholder="@mention member..."
                        class="px-2.5 py-1 text-xs rounded-lg border border-slate-200 bg-slate-50 focus:outline-none focus:ring-1 focus:ring-indigo-500 w-36"
                    />

                    @if(!empty($mentionSuggestions))
                        <div class="absolute right-0 top-full mt-1 w-56 bg-white border border-slate-200 rounded-xl shadow-xl z-20 overflow-hidden text-xs divide-y divide-slate-100">
                            @foreach($mentionSuggestions as $sugg)
                                <button
                                    type="button"
                                    wire:click="insertMention('{{ $sugg['mention_tag'] }}')"
                                    class="w-full px-3 py-2 text-left hover:bg-indigo-50 flex flex-col transition-colors cursor-pointer"
                                >
                                    <span class="font-bold text-slate-900">{{ $sugg['name'] }}</span>
                                    <span class="text-[10px] text-slate-400">{{ $sugg['role'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Auto-saving Note Area -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden p-6 space-y-3">
                <div class="flex items-center justify-between text-xs text-slate-400 font-bold">
                    <span>Live Minute Ledger</span>
                    <span>Supports Markdown, @mentions, [ ] tasks, /motion</span>
                </div>

                <textarea
                    wire:model.live.debounce.1000ms="notesRaw"
                    rows="20"
                    placeholder="Type committee proceedings here... Use [ ] for delegated tasks and /motion for formal notices of motion..."
                    class="w-full p-4 border border-slate-200 rounded-2xl text-xs font-mono leading-relaxed focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-slate-50/50 resize-y"
                ></textarea>

                <div class="flex items-center justify-between pt-2 text-xs">
                    <button
                        type="button"
                        wire:click="autoSave"
                        class="text-indigo-600 font-bold hover:underline"
                    >
                        Save Now
                    </button>
                    <button
                        type="button"
                        wire:click="syncEntities"
                        class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl shadow-sm transition-all inline-flex items-center gap-1.5"
                    >
                        <span>⚡</span>
                        <span>Sync Extracted Tasks & Motions</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Right 5 Cols: Real-time Parser Feed & Actions -->
        <div class="lg:col-span-5 space-y-6">

            <!-- Live Parser Feed -->
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider flex items-center gap-1.5">
                        <span>🔍</span>
                        <span>Detected in Notes (Real-Time)</span>
                    </h3>
                    <span class="text-[10px] font-bold text-slate-400">
                        {{ count($parsedPreview['tasks']) }} Tasks • {{ count($parsedPreview['motions']) }} Motions
                    </span>
                </div>

                <!-- Detected Mentions -->
                @if(!empty($parsedPreview['mentions']))
                    <div class="space-y-1.5">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Mentioned Brethren</span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($parsedPreview['mentions'] as $m)
                                <span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 font-bold text-[10px] border border-indigo-200 flex items-center gap-1">
                                    <span>👤</span>
                                    <span>{{ $m['name'] }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Detected Tasks -->
                @if(!empty($parsedPreview['tasks']))
                    <div class="space-y-1.5 pt-2">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Extracted Action Points ([ ])</span>
                        <div class="space-y-1.5">
                            @foreach($parsedPreview['tasks'] as $t)
                                <div class="p-2.5 bg-amber-50/60 border border-amber-200 rounded-xl text-xs space-y-1">
                                    <span class="font-bold text-slate-900 block">{{ $t['title'] }}</span>
                                    <div class="flex items-center gap-3 text-[10px] text-amber-800 font-medium">
                                        @if($t['assigned_to_name'])
                                            <span>👤 Assigned: <strong>{{ $t['assigned_to_name'] }}</strong></span>
                                        @endif
                                        @if($t['due_date'])
                                            <span>🗓️ Due: <strong>{{ $t['due_date'] }}</strong></span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Detected Motions -->
                @if(!empty($parsedPreview['motions']))
                    <div class="space-y-1.5 pt-2">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Formal Motions (/motion)</span>
                        <div class="space-y-1.5">
                            @foreach($parsedPreview['motions'] as $mot)
                                <div class="p-2.5 bg-indigo-50/60 border border-indigo-200 rounded-xl text-xs">
                                    <span class="font-bold text-indigo-950 block">📜 {{ $mot['motion_text'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(empty($parsedPreview['mentions']) && empty($parsedPreview['tasks']) && empty($parsedPreview['motions']))
                    <div class="p-4 text-center text-xs text-slate-400 bg-slate-50 rounded-2xl border border-slate-100">
                        Type notes in the left editor. The parser will automatically surface @mentions, [ ] tasks, and /motion directives here.
                    </div>
                @endif
            </div>

            <!-- Committee Tasks Register -->
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider flex items-center gap-1.5">
                        <span>☑️</span>
                        <span>Delegated Committee Tasks</span>
                    </h3>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                        {{ $tasks->count() }} Tasks
                    </span>
                </div>

                <div class="space-y-2">
                    @forelse($tasks as $task)
                        <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-2xl flex items-start justify-between gap-2 text-xs">
                            <div class="space-y-1 flex-1">
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        wire:click="toggleTaskStatus({{ $task->id }})"
                                        class="cursor-pointer text-sm"
                                    >
                                        {{ $task->status->value === 'completed' ? '✅' : '⬜' }}
                                    </button>
                                    <span class="font-bold {{ $task->status->value === 'completed' ? 'line-through text-slate-400' : 'text-slate-900' }}">
                                        {{ $task->title }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3 text-[10px] text-slate-500 pl-6">
                                    <span>👤 {{ $task->assignedTo ? $task->assignedTo->name : ($task->assigned_to_name ?: 'Unassigned') }}</span>
                                    <span>🗓️ {{ $task->due_date ? $task->due_date->format('d M Y') : 'No date' }}</span>
                                </div>
                            </div>

                            @if($task->assignedTo)
                                <button
                                    type="button"
                                    wire:click="notifyAssignedMember({{ $task->id }})"
                                    class="px-2 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-[10px] rounded-lg border border-indigo-200 transition-all cursor-pointer shrink-0"
                                    title="Send notification email & alert to member"
                                >
                                    📧 Notify
                                </button>
                            @endif
                        </div>
                    @empty
                        <div class="p-4 text-center text-xs text-slate-400 bg-slate-50 rounded-2xl border border-slate-100">
                            No tasks registered yet. Type [ ] tasks in the minutes and click "Sync Extracted Tasks".
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Committee Notices of Motion -->
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider flex items-center gap-1.5">
                        <span>📜</span>
                        <span>Notices of Motion (Rule 160)</span>
                    </h3>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">
                        {{ $motions->count() }} Motions
                    </span>
                </div>

                <div class="space-y-2">
                    @forelse($motions as $motion)
                        <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-2xl space-y-2 text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-bold text-slate-900">{{ $motion->title }}</span>
                                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded border {{ $motion->status === 'published_on_summons' ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-amber-100 text-amber-800 border-amber-200' }}">
                                    {{ str_replace('_', ' ', $motion->status) }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-600 bg-white p-2 rounded-xl border border-slate-100 italic">
                                "{{ $motion->motion_text }}"
                            </p>
                            <div class="flex items-center justify-between text-[10px] text-slate-500 pt-1">
                                <span>Proposer: <strong>{{ $motion->proposer_name ?: ($motion->proposer?->name ?: 'Committee') }}</strong></span>
                                @if($motion->status === 'draft_committee')
                                    <button
                                        type="button"
                                        wire:click="approveMotionForSummons({{ $motion->id }})"
                                        class="px-2 py-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm"
                                    >
                                        Approve for Summons
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-xs text-slate-400 bg-slate-50 rounded-2xl border border-slate-100">
                            No notices of motion drafted yet. Use /motion in minutes to draft formal motions.
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

    </div>
</div>
