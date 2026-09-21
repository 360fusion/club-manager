<div class="space-y-6">
    <!-- Top Executive Meeting Header Bar -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-5 md:p-6 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="space-y-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('admin.committee.workspace', ['clubSlug' => $club->slug, 'meetingId' => $meeting->id]) }}" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 transition-colors flex items-center gap-1">
                    <span>←</span>
                    <span>Back to Agenda Pack</span>
                </a>
                <span class="text-slate-300">•</span>
                <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full border {{ $meeting->status->badgeClass() }}">
                    {{ $meeting->status->label() }}
                </span>
                @if($meeting->meeting_date)
                    <span class="text-slate-300">•</span>
                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        🗓️ {{ $meeting->meeting_date->format('D, jS M Y • H:i') }}
                    </span>
                @endif
            </div>
            <h1 class="text-xl font-black text-slate-900 dark:text-white truncate">{{ $meeting->title }} — Live Minutes</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">Executive live minutes ledger with automated @mention routing, task delegation, and notice-of-motion tagging.</p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
            <!-- Auto-save Status Indicator -->
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center gap-2 bg-slate-50 dark:bg-slate-800/50 px-3.5 py-2 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-inner">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Auto-saved at {{ $lastSavedAt }}</span>
            </div>

            <!-- Sync Entities Shortcut -->
            <button
                type="button"
                @click="$wire.commitDetectedItems($refs.notesEditor?.value)"
                class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-2xl border border-slate-200 dark:border-slate-800 transition-all flex items-center gap-1.5 cursor-pointer shadow-sm active:scale-95"
                title="Synchronize extracted tasks and motions from current notes"
            >
                <span>⚡</span>
                <span>Sync</span>
            </button>

            @if($meeting->status->value !== 'finalized')
                <button
                    type="button"
                    wire:click="finalizeMinutes"
                    wire:confirm="Finalize committee minutes? This will freeze the record and commit extracted tasks into the permanent lodge governance register."
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-2xl shadow-sm transition-all cursor-pointer flex items-center gap-1.5"
                >
                    <span>📜</span>
                    <span>Finalize Minutes</span>
                </button>
            @endif
        </div>
    </div>

    <!-- 3-Column Executive Meeting Workspace Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

        <!-- ================================================================= -->
        <!-- COLUMN 1 (Left Rail, 3 Cols): Meeting Agenda & Pacing             -->
        <!-- ================================================================= -->
        <div class="col-span-12 lg:col-span-3 space-y-4">
            <!-- Agenda Items Panel -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="font-black text-slate-900 dark:text-white text-xs uppercase tracking-wider flex items-center gap-1.5">
                            <span>📋</span>
                            <span>Agenda & Pacing</span>
                        </h3>
                        <span class="text-[10px] text-slate-400">{{ $agendaItems->count() }} order items</span>
                    </div>

                    <button
                        type="button"
                        wire:click="loadAgendaOutline"
                        wire:confirm="Load full agenda outline into notes? Existing text will be preserved."
                        class="px-2.5 py-1 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-bold text-[10px] rounded-xl border border-blue-200/80 dark:border-blue-800/80 transition-all flex items-center gap-1 cursor-pointer"
                        title="Inject full agenda outline and roll call into notes"
                    >
                        <span>⚡</span>
                        <span>Load Outline</span>
                    </button>
                </div>

                <div class="space-y-2.5 max-h-[580px] overflow-y-auto pr-1">
                    @forelse($agendaItems as $item)
                        @php
                            $itemTypeEnum = $item->item_type instanceof \App\Domains\ClubAccounting\Enums\CommitteeItemType
                                ? $item->item_type
                                : \App\Domains\ClubAccounting\Enums\CommitteeItemType::tryFrom($item->item_type);
                            $typeLabel = $itemTypeEnum ? $itemTypeEnum->label() : ($item->item_type ?: 'General Business');
                            $typeIcon = $itemTypeEnum ? $itemTypeEnum->icon() : '📋';
                            $typeValue = $itemTypeEnum ? $itemTypeEnum->value : 'general';
                        @endphp
                        <div class="p-2.5 rounded-2xl border transition-all text-xs space-y-2 {{ $item->is_approved ? 'bg-emerald-50/30 dark:bg-emerald-950/30 border-emerald-200/70 dark:border-emerald-800/70' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200/80 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700' }}">
                            <div class="flex items-start justify-between gap-1.5">
                                <div class="flex items-start gap-2 min-w-0">
                                    <span class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-black text-[10px] flex items-center justify-center shrink-0 mt-0.5">
                                        {{ $item->order }}
                                    </span>
                                    <div class="min-w-0 space-y-0.5">
                                        <h4 class="font-bold text-slate-900 dark:text-white leading-snug text-xs break-words">{{ $item->title }}</h4>
                                        <div>
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[9px] font-bold {{ match($typeValue) {
                                                'general' => 'bg-slate-200/80 dark:bg-slate-700/80 text-slate-700 dark:text-slate-200',
                                                'candidate_vetting' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-200 dark:border-blue-800/60',
                                                'accounts_audit' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800/60',
                                                'hall_affairs' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-200 dark:border-blue-800/60',
                                                'motion' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800/60',
                                                default => 'bg-slate-200/80 dark:bg-slate-700/80 text-slate-700 dark:text-slate-200',
                                            } }}">
                                                <span>{{ $typeIcon }}</span>
                                                <span class="truncate max-w-[130px]">{{ $typeLabel }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @if($item->description)
                                <p class="text-[10px] text-slate-500 dark:text-slate-400 pl-7 line-clamp-2">{{ $item->description }}</p>
                            @endif

                            <div class="flex items-center justify-between pt-1.5 border-t border-slate-200/50 dark:border-slate-800/50 text-[10px]">
                                <button
                                    type="button"
                                    wire:click="insertAgendaItem({{ $item->id }})"
                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 font-bold flex items-center gap-0.5 transition-colors cursor-pointer"
                                    title="Stamp section heading into live minutes"
                                >
                                    <span>✍️</span>
                                    <span>+ Note Section</span>
                                </button>

                                <button
                                    type="button"
                                    wire:click="toggleAgendaApproval({{ $item->id }})"
                                    class="px-2 py-0.5 rounded font-black uppercase text-[9px] transition-all cursor-pointer {{ $item->is_approved ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700/60' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-300' }}"
                                >
                                    {{ $item->is_approved ? '✓ Approved' : 'Pending' }}
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-xs text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800">
                            No agenda items recorded for this meeting.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Roll-Call Quick Reference -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-4 shadow-sm space-y-2.5 text-xs">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
                    <h4 class="font-black text-slate-900 dark:text-white text-[11px] uppercase tracking-wider flex items-center gap-1.5">
                        <span>👥</span>
                        <span>Roll-Call Summary</span>
                    </h4>
                    <span class="text-[10px] font-bold text-slate-400">{{ $attendees->count() }} total</span>
                </div>

                @php
                    $presentCount = $attendees->where('attendance_type.value', 'present')->count();
                    $apologyCount = $attendees->where('attendance_type.value', 'apology')->count();
                    $remoteCount = $attendees->where('attendance_type.value', 'remote_link')->count();
                @endphp

                <div class="grid grid-cols-3 gap-1.5 text-center">
                    <div class="p-2 bg-emerald-50/60 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/60 rounded-xl">
                        <span class="text-xs font-black text-emerald-700 dark:text-emerald-300 block">{{ $presentCount }}</span>
                        <span class="text-[9px] font-bold text-emerald-600 dark:text-emerald-400">Present</span>
                    </div>
                    <div class="p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl">
                        <span class="text-xs font-black text-slate-700 dark:text-slate-200 block">{{ $apologyCount }}</span>
                        <span class="text-[9px] font-bold text-slate-500 dark:text-slate-400">Apology</span>
                    </div>
                    <div class="p-2 bg-blue-50/60 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800/60 rounded-xl">
                        <span class="text-xs font-black text-blue-700 dark:text-blue-300 block">{{ $remoteCount }}</span>
                        <span class="text-[9px] font-bold text-blue-600 dark:text-blue-400">Remote</span>
                    </div>
                </div>

                <div class="pt-1">
                    <a href="{{ route('admin.committee.workspace', ['clubSlug' => $club->slug, 'meetingId' => $meeting->id]) }}" class="text-[10px] font-bold text-blue-600 dark:text-blue-400 hover:underline block text-center">
                        Manage Roll-Call in Pre-Meeting Pack →
                    </a>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- COLUMN 2 (Center, 6 Cols): Distraction-Free Rich Note Editor      -->
        <!-- ================================================================= -->
        <div class="col-span-12 lg:col-span-6 space-y-3 min-w-0">
            @if (session()->has('success'))
                <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-200 rounded-2xl text-xs font-bold flex items-center justify-between shadow-sm animate-fade-in">
                    <div class="flex items-center gap-2">
                        <span>✅</span>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button type="button" @click="$el.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-300 text-xs cursor-pointer">✕</button>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-200 rounded-2xl text-xs font-bold flex items-center justify-between shadow-sm animate-fade-in">
                    <div class="flex items-center gap-2">
                        <span>⚠️</span>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button type="button" @click="$el.parentElement.remove()" class="text-rose-500 hover:text-rose-700 dark:hover:text-rose-300 text-xs cursor-pointer">✕</button>
                </div>
            @endif

            <!-- Insertion Toolbar -->
            <div class="bg-white dark:bg-slate-900 p-3 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-slate-400 font-bold text-[10px] uppercase tracking-wider mr-1">Insert:</span>

                    <button
                        type="button"
                        wire:click="insertTemplate('task')"
                        class="px-2.5 py-1 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800/60 font-bold rounded-xl transition-all cursor-pointer text-[11px]"
                        title="Insert task checkbox with automated delegation"
                    >
                        ☑️ [ ] Task
                    </button>

                    <button
                        type="button"
                        wire:click="insertTemplate('motion')"
                        class="px-2.5 py-1 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-200 dark:border-blue-800/60 font-bold rounded-xl transition-all cursor-pointer text-[11px]"
                        title="Insert formal notice of motion tag"
                    >
                        📜 /motion
                    </button>

                    <button
                        type="button"
                        wire:click="insertTemplate('candidate')"
                        class="px-2.5 py-1 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-200 dark:border-blue-800/60 font-bold rounded-xl transition-all cursor-pointer text-[11px]"
                        title="Insert candidate vetting template"
                    >
                        👤 Candidate
                    </button>

                    <button
                        type="button"
                        wire:click="insertTemplate('audit')"
                        class="px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800/60 font-bold rounded-xl transition-all cursor-pointer text-[11px]"
                        title="Insert accounts audit template"
                    >
                        🔍 Audit
                    </button>

                    <button
                        type="button"
                        wire:click="insertTemplate('donation')"
                        class="px-2.5 py-1 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-800 dark:text-rose-200 border border-rose-200 dark:border-rose-800/60 font-bold rounded-xl transition-all cursor-pointer text-[11px]"
                        title="Insert charitable donation proposal template"
                    >
                        ❤️ Donation
                    </button>
                </div>

                <!-- Interactive Member @mention Autocomplete -->
                <div class="relative min-w-[150px]">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-slate-400 text-xs">@</span>
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="memberQuery"
                            placeholder="mention member..."
                            class="pl-6 pr-2.5 py-1 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 w-full"
                        />
                    </div>

                    @if(!empty($mentionSuggestions))
                        <div class="absolute right-0 top-full mt-1.5 w-60 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl z-30 overflow-hidden text-xs divide-y divide-slate-100 dark:divide-slate-800">
                            <div class="px-3 py-1.5 bg-slate-50 dark:bg-slate-800/50 text-[10px] font-black uppercase tracking-wider text-slate-400">
                                Select Member to @Mention
                            </div>
                            @foreach($mentionSuggestions as $sugg)
                                <button
                                    type="button"
                                    wire:click="insertMention('{{ $sugg['mention_tag'] }}')"
                                    class="w-full px-3 py-2 text-left hover:bg-blue-50 dark:hover:bg-blue-950/40 flex items-center justify-between transition-colors cursor-pointer"
                                >
                                    <div>
                                        <span class="font-bold text-slate-900 dark:text-white block">{{ $sugg['name'] }}</span>
                                        <span class="text-[10px] text-slate-400">{{ $sugg['role'] }}</span>
                                    </div>
                                    <span class="text-[10px] font-mono text-blue-600 dark:text-blue-400 font-bold">{{ $sugg['mention_tag'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Auto-saving Note Area -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden p-5 space-y-3">
                <div class="flex items-center justify-between text-xs text-slate-400 font-bold border-b border-slate-100 dark:border-slate-800 pb-2">
                    <span class="text-slate-700 dark:text-slate-200 font-black flex items-center gap-1.5">
                        <span>📝</span>
                        <span>Live Minute Ledger</span>
                    </span>
                    <span class="text-[11px] text-slate-400 font-normal">
                        {{ str_word_count($notesRaw) }} words • Supports Markdown, @mentions, [ ] tasks, /motion
                    </span>
                </div>

                <textarea
                    x-ref="notesEditor"
                    wire:model.live.debounce.1000ms="notesRaw"
                    rows="22"
                    placeholder="Type committee proceedings here... Use [ ] for action items, @Name for delegation, and /motion for notices of motion..."
                    class="w-full p-4 border border-slate-200 dark:border-slate-800 rounded-2xl text-xs font-mono leading-relaxed focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-slate-50/50 dark:bg-slate-800/50/50 resize-y min-h-[520px]"
                ></textarea>

                <div class="flex items-center justify-between pt-1 text-xs">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            wire:click="autoSave"
                            class="text-blue-600 dark:text-blue-400 font-bold hover:underline cursor-pointer"
                        >
                            Save Now
                        </button>
                        <span class="text-slate-300">•</span>
                        <span class="text-[11px] text-slate-400">
                            Auto-saving changes as you type
                        </span>
                    </div>

                    <button
                        type="button"
                        @click="$wire.commitDetectedItems($refs.notesEditor?.value)"
                        class="px-3.5 py-1.5 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold rounded-xl shadow-sm transition-all inline-flex items-center gap-1.5 cursor-pointer text-xs active:scale-95"
                    >
                        <span>⚡</span>
                        <span>Sync Extracted Tasks & Motions</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- COLUMN 3 (Right Rail, 3 Cols): Consolidated Live Extraction Tab   -->
        <!-- ================================================================= -->
        <div class="col-span-12 lg:col-span-3 space-y-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-4 shadow-sm space-y-3.5">
                <!-- Navigation Tabs -->
                <div class="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-2xl text-xs font-bold text-slate-600 dark:text-slate-300">
                    <button
                        type="button"
                        wire:click="$set('activeRightTab', 'live')"
                        class="flex-1 py-1.5 text-center rounded-xl transition-all cursor-pointer {{ $activeRightTab === 'live' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm font-black' : 'hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <span>⚡ Live</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeRightTab', 'tasks')"
                        class="flex-1 py-1.5 text-center rounded-xl transition-all cursor-pointer {{ $activeRightTab === 'tasks' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm font-black' : 'hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <span>☑️ Tasks ({{ $tasks->count() }})</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeRightTab', 'motions')"
                        class="flex-1 py-1.5 text-center rounded-xl transition-all cursor-pointer {{ $activeRightTab === 'motions' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm font-black' : 'hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <span>📜 Motions</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$set('activeRightTab', 'grants')"
                        class="flex-1 py-1.5 text-center rounded-xl transition-all cursor-pointer {{ $activeRightTab === 'grants' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm font-black' : 'hover:text-slate-900 dark:hover:text-white' }}"
                    >
                        <span>❤️ Grants ({{ $charityGrants->count() }})</span>
                    </button>
                </div>

                <!-- TAB 1: Real-Time Detected Live Feed -->
                @if($activeRightTab === 'live')
                    <div class="space-y-3.5 text-xs">
                        <div class="flex items-center justify-between text-[11px] font-bold text-slate-400">
                            <span>Detected in Notes</span>
                            <span>{{ count($parsedPreview['tasks']) }} tasks • {{ count($parsedPreview['motions']) }} motions</span>
                        </div>

                        <!-- Detected Mentions -->
                        @if(!empty($parsedPreview['mentions']))
                            <div class="space-y-1.5">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Mentioned Brethren</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($parsedPreview['mentions'] as $m)
                                        <span class="px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 font-bold text-[10px] border border-blue-200 dark:border-blue-800/60 flex items-center gap-1">
                                            <span>👤</span>
                                            <span>{{ $m['name'] }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Detected Tasks -->
                        @if(!empty($parsedPreview['tasks']))
                            <div class="space-y-1.5 pt-1">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Extracted Tasks ([ ])</span>
                                <div class="space-y-1.5">
                                    @foreach($parsedPreview['tasks'] as $t)
                                        <div class="p-2.5 bg-amber-50/70 dark:bg-amber-950/70 border border-amber-200 dark:border-amber-800/60 rounded-xl text-xs space-y-1">
                                            <span class="font-bold text-slate-900 dark:text-white block">{{ $t['title'] }}</span>
                                            <div class="flex items-center gap-2 text-[10px] text-amber-800 dark:text-amber-200 font-medium flex-wrap">
                                                @if($t['assigned_to_name'])
                                                    <span>👤 {{ $t['assigned_to_name'] }}</span>
                                                @endif
                                                @if($t['due_date'])
                                                    <span>🗓️ Due: {{ $t['due_date'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Detected Motions -->
                        @if(!empty($parsedPreview['motions']))
                            <div class="space-y-1.5 pt-1">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Formal Motions (/motion)</span>
                                <div class="space-y-1.5">
                                    @foreach($parsedPreview['motions'] as $mot)
                                        <div class="p-2.5 bg-blue-50/70 dark:bg-blue-950/70 border border-blue-200 dark:border-blue-800/60 rounded-xl text-xs">
                                            <span class="font-bold text-blue-950 dark:text-blue-100 block">📜 {{ $mot['motion_text'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(empty($parsedPreview['mentions']) && empty($parsedPreview['tasks']) && empty($parsedPreview['motions']))
                            <div class="p-6 text-center text-xs text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800">
                                Type notes in the editor. Mentions, tasks, and motions will parse live here.
                            </div>
                        @endif

                        <div class="pt-2">
                            <button
                                type="button"
                                @click="$wire.commitDetectedItems($refs.notesEditor?.value)"
                                class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-sm transition-all text-center flex items-center justify-center gap-1.5 cursor-pointer active:scale-95"
                            >
                                <span>⚡</span>
                                <span>Commit Detected Items</span>
                            </button>
                        </div>
                    </div>
                @endif

                <!-- TAB 2: Delegated Committee Tasks Register -->
                @if($activeRightTab === 'tasks')
                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between text-[11px] font-bold text-slate-400">
                            <span>Delegated Action Points</span>
                            <span class="font-bold text-slate-700 dark:text-slate-200">{{ $tasks->count() }} active</span>
                        </div>

                        <div class="space-y-2 max-h-[520px] overflow-y-auto pr-1">
                            @forelse($tasks as $task)
                                <div class="p-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl flex items-start justify-between gap-2 text-xs">
                                    <div class="space-y-1 flex-1 min-w-0">
                                        <div class="flex items-start gap-2">
                                            <button
                                                type="button"
                                                wire:click="toggleTaskStatus({{ $task->id }})"
                                                class="cursor-pointer text-sm shrink-0 mt-0.5"
                                            >
                                                {{ $task->status->value === 'completed' ? '✅' : '⬜' }}
                                            </button>
                                            <span class="font-bold leading-snug break-words {{ $task->status->value === 'completed' ? 'line-through text-slate-400' : 'text-slate-900 dark:text-white' }}">
                                                {{ $task->title }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2.5 text-[10px] text-slate-500 dark:text-slate-400 pl-6 flex-wrap">
                                            <span>👤 {{ $task->assignedTo ? $task->assignedTo->name : ($task->assigned_to_name ?: 'Unassigned') }}</span>
                                            @if($task->due_date)
                                                <span>🗓️ {{ $task->due_date->format('d M') }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    @if($task->assignedTo)
                                        <button
                                            type="button"
                                            wire:click="notifyAssignedMember({{ $task->id }})"
                                            class="px-2 py-1 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-bold text-[10px] rounded-lg border border-blue-200 dark:border-blue-800/60 transition-all cursor-pointer shrink-0"
                                            title="Send email alert to assigned brother"
                                        >
                                            📧
                                        </button>
                                    @endif
                                </div>
                            @empty
                                <div class="p-6 text-center text-xs text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800">
                                    No tasks committed yet. Insert [ ] in notes and click "Sync".
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif

                <!-- TAB 3: Notices of Motion (Rule 160) -->
                @if($activeRightTab === 'motions')
                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between text-[11px] font-bold text-slate-400">
                            <span>Notices of Motion</span>
                            <span class="font-bold text-slate-700 dark:text-slate-200">{{ $motions->count() }} total</span>
                        </div>

                        <div class="space-y-2 max-h-[520px] overflow-y-auto pr-1">
                            @forelse($motions as $motion)
                                <div class="p-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl space-y-2 text-xs">
                                    <div class="flex items-start justify-between gap-1.5">
                                        <span class="font-bold text-slate-900 dark:text-white leading-snug">{{ $motion->title }}</span>
                                        <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded border shrink-0 {{ $motion->status === 'published_on_summons' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border-emerald-200 dark:border-emerald-800/60' : 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 border-amber-200 dark:border-amber-800/60' }}">
                                            {{ str_replace('_', ' ', $motion->status) }}
                                        </span>
                                    </div>

                                    <p class="text-[10px] text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900 p-2 rounded-xl border border-slate-100 dark:border-slate-800 italic">
                                        "{{ $motion->motion_text }}"
                                    </p>

                                    <div class="flex items-center justify-between text-[10px] text-slate-500 dark:text-slate-400 pt-1">
                                        <span>Proposer: <strong>{{ $motion->proposer_name ?: ($motion->proposer?->name ?: 'Committee') }}</strong></span>
                                        @if($motion->status === 'draft_committee')
                                            <button
                                                type="button"
                                                wire:click="approveMotionForSummons({{ $motion->id }})"
                                                class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg shadow-sm text-[10px]"
                                            >
                                                Approve
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="p-6 text-center text-xs text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800">
                                    No formal notices of motion drafted yet. Use /motion in notes.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif

                <!-- TAB 4: Charitable Donation Proposals -->
                @if($activeRightTab === 'grants')
                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between text-[11px] font-bold text-slate-400">
                            <span>Charitable Donation Proposals</span>
                            <span class="font-bold text-slate-700 dark:text-slate-200">{{ $charityGrants->count() }} total</span>
                        </div>

                        <div class="space-y-2 max-h-[520px] overflow-y-auto pr-1">
                            @forelse($charityGrants as $grant)
                                <div class="p-3 bg-amber-50/70 dark:bg-amber-950/70 border border-amber-200/90 dark:border-amber-800/90 rounded-2xl space-y-2 text-xs">
                                    <div class="flex items-start justify-between gap-1.5">
                                        <div>
                                            <span class="font-black text-slate-900 dark:text-white block">{{ $cs }}{{ number_format($grant->amount, 2) }} — {{ $grant->recipient_name }}</span>
                                            <span class="text-[10px] text-amber-900 dark:text-amber-200 font-medium block mt-0.5">{{ $grant->purpose }}</span>
                                        </div>
                                        <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded border shrink-0 bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200 border-amber-300 dark:border-amber-700/60">
                                            {{ str_replace('_', ' ', $grant->approval_status->value ?? $grant->approval_status) }}
                                        </span>
                                    </div>

                                    <div class="space-y-0.5 text-[10px] text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900 p-2 rounded-xl border border-amber-100 dark:border-amber-900/40">
                                        @if($grant->proposer)
                                            <div>Proposed by: <strong>{{ $grant->proposer->formatted_rank_name }}</strong></div>
                                        @endif
                                        @if($grant->seconder)
                                            <div>Seconded by: <strong>{{ $grant->seconder->formatted_rank_name }}</strong></div>
                                        @endif
                                        @if($grant->relief_chest_number)
                                            <div class="text-slate-400">Relief Chest: {{ $grant->relief_chest_number }}</div>
                                        @endif
                                    </div>

                                    <div class="flex items-center justify-end gap-2 text-[10px] pt-1">
                                        @if(($grant->approval_status->value ?? $grant->approval_status) === 'proposed')
                                            <button
                                                type="button"
                                                wire:click="approveGrantForSummons({{ $grant->id }})"
                                                class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg shadow-sm text-[10px] flex items-center gap-1 cursor-pointer"
                                            >
                                                <span>✅</span>
                                                <span>Approve for Summons</span>
                                            </button>
                                        @elseif(($grant->approval_status->value ?? $grant->approval_status) === 'committee_approved')
                                            <button
                                                type="button"
                                                wire:click="lodgeVoteGrant({{ $grant->id }})"
                                                class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg shadow-sm text-[10px] flex items-center gap-1 cursor-pointer"
                                            >
                                                <span>🏛️</span>
                                                <span>Mark Lodge Voted</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="p-6 text-center text-xs text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800">
                                    No charitable donation proposals logged yet.
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>

    <!-- Real-time floating toast listener -->
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        x-on:notify.window="show = true; message = ($event.detail && ($event.detail[0]?.message || $event.detail.message)) || 'Action completed'; type = ($event.detail && ($event.detail[0]?.type || $event.detail.type)) || 'success'; setTimeout(() => show = false, 4000)"
        x-show="show"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-2xl text-xs font-bold text-white transition-all pointer-events-auto bg-slate-900 border border-slate-700"
        style="display: none;"
    >
        <span class="text-base" x-text="type === 'error' ? '⚠️' : '⚡'"></span>
        <span x-text="message"></span>
        <button type="button" @click="show = false" class="ml-2 text-slate-400 hover:text-white font-bold cursor-pointer">✕</button>
    </div>
</div>
