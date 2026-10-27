<div class="space-y-6">
    @php
        $meetingStatusVariant = match ($meeting->status) {
            \App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus::Scheduled => 'info',
            \App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus::Finalized => 'success',
            default => 'warning',
        };
        $fieldClasses = 'rounded-xl border border-slate-300 bg-white text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
        $tabClasses = fn (bool $active) => $active
            ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-700'
            : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white';
    @endphp

    <x-card class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.committee.workspace', ['clubSlug' => $club->slug, 'meetingId' => $meeting->id]) }}" class="text-xs font-medium text-blue-600 transition-colors hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">
                    <span aria-hidden="true">←</span> Back to agenda pack
                </a>
                <x-badge :variant="$meetingStatusVariant">{{ $meeting->status->label() }}</x-badge>
                @if($meeting->meeting_date)
                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">
                        {{ $meeting->meeting_date->format('D, jS M Y • H:i') }}
                    </span>
                @endif
            </div>
            <h1 class="truncate text-xl font-semibold text-slate-900 dark:text-white">{{ $meeting->title }}: live minutes</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">Live minutes with @mention routing, task delegation and notice of motion tagging.</p>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2.5">
            <span class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500" aria-hidden="true"></span>
                Auto-saved at {{ $lastSavedAt }}
            </span>

            <x-button
                variant="secondary"
                x-on:click="$wire.commitDetectedItems($refs.notesEditor?.value)"
                title="Sync the tasks and motions found in the current notes"
            >
                Sync
            </x-button>

            @if($meeting->status->value !== 'finalized')
                <x-button
                    wire:click="finalizeMinutes"
                    wire:confirm="Finalize committee minutes? This will freeze the record and commit extracted tasks into the permanent lodge governance register."
                >
                    Finalise minutes
                </x-button>
            @endif
        </div>
    </x-card>

    <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-12">

        {{-- Left: agenda and roll-call --}}
        <div class="col-span-12 space-y-4 lg:col-span-3">
            <x-card padding="sm" class="space-y-3">
                <div class="flex items-center justify-between gap-2 border-b border-slate-200 pb-2.5 dark:border-slate-800">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Agenda and pacing</h3>
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ $agendaItems->count() }} order items</span>
                    </div>

                    <x-button
                        variant="secondary"
                        size="sm"
                        wire:click="loadAgendaOutline"
                        wire:confirm="Load full agenda outline into notes? Existing text will be preserved."
                        title="Add the full agenda outline and roll-call to the notes"
                    >
                        Load outline
                    </x-button>
                </div>

                <div class="max-h-[580px] space-y-2.5 overflow-y-auto pr-1">
                    @forelse($agendaItems as $item)
                        @php
                            $itemTypeEnum = $item->item_type instanceof \App\Domains\ClubAccounting\Enums\CommitteeItemType
                                ? $item->item_type
                                : \App\Domains\ClubAccounting\Enums\CommitteeItemType::tryFrom($item->item_type);
                            $typeLabel = $itemTypeEnum ? $itemTypeEnum->label() : ($item->item_type ?: 'General business');
                            $typeValue = $itemTypeEnum ? $itemTypeEnum->value : 'general';
                        @endphp
                        <div @class([
                            'space-y-2 rounded-xl border p-2.5 text-xs transition-colors',
                            'border-emerald-200/70 bg-emerald-50/30 dark:border-emerald-800/70 dark:bg-emerald-950/30' => $item->is_approved,
                            'border-slate-200 bg-slate-50 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-800/50 dark:hover:border-slate-700' => ! $item->is_approved,
                        ])>
                            <div class="flex min-w-0 items-start gap-2">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                    {{ $item->order }}
                                </span>
                                <div class="min-w-0 space-y-1">
                                    <h4 class="break-words text-sm font-medium leading-snug text-slate-900 dark:text-white">{{ $item->title }}</h4>
                                    <x-badge :variant="match ($typeValue) {
                                        'candidate_vetting', 'hall_affairs' => 'info',
                                        'accounts_audit' => 'success',
                                        'motion' => 'warning',
                                        default => 'neutral',
                                    }">
                                        <span class="max-w-[130px] truncate">{{ $typeLabel }}</span>
                                    </x-badge>
                                </div>
                            </div>

                            @if($item->description)
                                <p class="line-clamp-2 pl-7 text-xs text-slate-500 dark:text-slate-400">{{ $item->description }}</p>
                            @endif

                            <div class="flex items-center justify-between border-t border-slate-200/50 pt-1.5 dark:border-slate-800/50">
                                <x-button
                                    variant="ghost"
                                    size="sm"
                                    wire:click="insertAgendaItem({{ $item->id }})"
                                    title="Add this item's heading to the live minutes"
                                >
                                    Add note section
                                </x-button>

                                <button
                                    type="button"
                                    wire:click="toggleAgendaApproval({{ $item->id }})"
                                    aria-pressed="{{ $item->is_approved ? 'true' : 'false' }}"
                                    aria-label="{{ $item->is_approved ? 'Approved' : 'Pending' }}: change approval of {{ $item->title }}"
                                    @class([
                                        'cursor-pointer rounded-full border px-2.5 py-0.5 text-xs font-medium transition-colors',
                                        'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800/50 dark:bg-emerald-950/50 dark:text-emerald-400' => $item->is_approved,
                                        'border-slate-200 bg-slate-100 text-slate-700 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700' => ! $item->is_approved,
                                    ])
                                >
                                    {{ $item->is_approved ? 'Approved' : 'Pending' }}
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-center text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                            No agenda items recorded for this meeting.
                        </p>
                    @endforelse
                </div>
            </x-card>

            <x-card padding="sm" class="space-y-2.5 text-xs">
                <div class="flex items-center justify-between border-b border-slate-200 pb-2 dark:border-slate-800">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Roll-call summary</h4>
                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $attendees->count() }} total</span>
                </div>

                @php
                    $presentCount = $attendees->where('attendance_type.value', 'present')->count();
                    $apologyCount = $attendees->where('attendance_type.value', 'apology')->count();
                    $remoteCount = $attendees->where('attendance_type.value', 'remote_link')->count();
                @endphp

                <dl class="grid grid-cols-3 gap-1.5 text-center">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-2 dark:border-emerald-800/60 dark:bg-emerald-950/60">
                        <dd class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ $presentCount }}</dd>
                        <dt class="text-xs font-medium text-emerald-600 dark:text-emerald-400">Present</dt>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-800 dark:bg-slate-800/50">
                        <dd class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $apologyCount }}</dd>
                        <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">Apology</dt>
                    </div>
                    <div class="rounded-xl border border-blue-200 bg-blue-50/60 p-2 dark:border-blue-800/60 dark:bg-blue-950/60">
                        <dd class="text-sm font-semibold text-blue-700 dark:text-blue-300">{{ $remoteCount }}</dd>
                        <dt class="text-xs font-medium text-blue-600 dark:text-blue-400">Remote</dt>
                    </div>
                </dl>

                <a href="{{ route('admin.committee.workspace', ['clubSlug' => $club->slug, 'meetingId' => $meeting->id]) }}" class="block pt-1 text-center text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">
                    Manage the roll-call in the pre-meeting pack <span aria-hidden="true">→</span>
                </a>
            </x-card>
        </div>

        {{-- Centre: the notes editor --}}
        <div class="col-span-12 min-w-0 space-y-3 lg:col-span-6">
            @if (session()->has('success'))
                <div class="relative">
                    <x-notice variant="success" class="pr-10">{{ session('success') }}</x-notice>
                    <button type="button" @click="$el.parentElement.remove()" aria-label="Dismiss" class="absolute right-3 top-3 cursor-pointer rounded-lg p-0.5 text-emerald-700 opacity-60 hover:opacity-100 dark:text-emerald-300">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="relative">
                    <x-notice variant="danger" class="pr-10">{{ session('error') }}</x-notice>
                    <button type="button" @click="$el.parentElement.remove()" aria-label="Dismiss" class="absolute right-3 top-3 cursor-pointer rounded-lg p-0.5 text-rose-700 opacity-60 hover:opacity-100 dark:text-rose-300">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                </div>
            @endif

            <x-card padding="sm" class="flex flex-wrap items-center justify-between gap-2">
                <div role="group" aria-label="Insert into notes" class="flex flex-wrap items-center gap-1.5">
                    <span class="mr-1 text-xs font-medium text-slate-500 dark:text-slate-400">Insert:</span>

                    <x-button variant="secondary" size="sm" wire:click="insertTemplate('task')" title="Insert a task checkbox to delegate">[ ] Task</x-button>
                    <x-button variant="secondary" size="sm" wire:click="insertTemplate('motion')" title="Insert a formal notice of motion tag">/motion</x-button>
                    <x-button variant="secondary" size="sm" wire:click="insertTemplate('candidate')" title="Insert the candidate vetting template">Candidate</x-button>
                    <x-button variant="secondary" size="sm" wire:click="insertTemplate('audit')" title="Insert the accounts audit template">Audit</x-button>
                    <x-button variant="secondary" size="sm" wire:click="insertTemplate('donation')" title="Insert the charitable donation proposal template">Donation</x-button>
                </div>

                <div class="relative min-w-[150px]">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-400" aria-hidden="true">@</span>
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="memberQuery"
                            placeholder="Mention a member..."
                            aria-label="Mention a member"
                            class="{{ $fieldClasses }} w-full py-1.5 pl-7 pr-3"
                        />
                    </div>

                    @if(!empty($mentionSuggestions))
                        <div class="absolute right-0 top-full z-30 mt-1.5 w-60 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white text-xs shadow-2xl dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                            <div class="bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                                Choose a member to mention
                            </div>
                            @foreach($mentionSuggestions as $sugg)
                                <button
                                    type="button"
                                    wire:click="insertMention('{{ $sugg['mention_tag'] }}')"
                                    class="flex w-full cursor-pointer items-center justify-between px-3 py-2 text-left transition-colors hover:bg-blue-50 dark:hover:bg-blue-950/40"
                                >
                                    <span>
                                        <span class="block font-medium text-slate-900 dark:text-white">{{ $sugg['name'] }}</span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ $sugg['role'] }}</span>
                                    </span>
                                    <span class="font-mono text-xs font-medium text-blue-600 dark:text-blue-400">{{ $sugg['mention_tag'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-card>

            <x-card padding="sm" class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-2 dark:border-slate-800">
                    <label for="live-minutes-notes" class="text-sm font-semibold text-slate-900 dark:text-white">Live minutes</label>
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        {{ str_word_count($notesRaw) }} words • Supports Markdown, @mentions, [ ] tasks, /motion
                    </span>
                </div>

                <textarea
                    id="live-minutes-notes"
                    x-ref="notesEditor"
                    wire:model.live.debounce.1000ms="notesRaw"
                    rows="22"
                    placeholder="Type committee proceedings here... Use [ ] for action items, @Name for delegation, and /motion for notices of motion..."
                    class="{{ $fieldClasses }} min-h-[520px] w-full resize-y p-4 font-mono leading-relaxed"
                ></textarea>

                <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                    <div class="flex items-center gap-3">
                        <x-button variant="ghost" size="sm" wire:click="autoSave">Save now</x-button>
                        <span class="text-xs text-slate-500 dark:text-slate-400">Changes save as you type</span>
                    </div>

                    <x-button variant="secondary" size="sm" x-on:click="$wire.commitDetectedItems($refs.notesEditor?.value)">
                        Sync extracted tasks and motions
                    </x-button>
                </div>
            </x-card>
        </div>

        {{-- Right: what the notes contain and the registers --}}
        <div class="col-span-12 space-y-4 lg:col-span-3">
            <x-card padding="sm" class="space-y-3.5">
                <div role="group" aria-label="Minutes panels" class="grid grid-cols-2 gap-1 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 dark:border-slate-700/60 dark:bg-slate-800/80">
                    @foreach([
                        'live' => 'Live',
                        'tasks' => 'Tasks ('.$tasks->count().')',
                        'motions' => 'Motions',
                        'grants' => 'Grants ('.$charityGrants->count().')',
                    ] as $tabKey => $tabLabel)
                        <button
                            type="button"
                            wire:click="$set('activeRightTab', '{{ $tabKey }}')"
                            aria-pressed="{{ $activeRightTab === $tabKey ? 'true' : 'false' }}"
                            class="cursor-pointer whitespace-nowrap rounded-xl px-2 py-1.5 text-center text-xs font-semibold transition-colors {{ $tabClasses($activeRightTab === $tabKey) }}"
                        >
                            {{ $tabLabel }}
                        </button>
                    @endforeach
                </div>

                @if($activeRightTab === 'live')
                    <div class="space-y-3.5 text-xs">
                        <div class="flex items-center justify-between font-medium text-slate-500 dark:text-slate-400">
                            <span>Found in the notes</span>
                            <span>{{ count($parsedPreview['tasks']) }} tasks • {{ count($parsedPreview['motions']) }} motions</span>
                        </div>

                        @if(!empty($parsedPreview['mentions']))
                            <div class="space-y-1.5">
                                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Mentioned brethren</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($parsedPreview['mentions'] as $m)
                                        <x-badge variant="info">{{ $m['name'] }}</x-badge>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(!empty($parsedPreview['tasks']))
                            <div class="space-y-1.5 pt-1">
                                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Extracted tasks ([ ])</span>
                                <div class="space-y-1.5">
                                    @foreach($parsedPreview['tasks'] as $t)
                                        <div class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800 dark:bg-slate-800/50">
                                            <span class="block font-medium text-slate-900 dark:text-white">{{ $t['title'] }}</span>
                                            <div class="flex flex-wrap items-center gap-2 text-slate-500 dark:text-slate-400">
                                                @if($t['assigned_to_name'])
                                                    <span>Assigned to {{ $t['assigned_to_name'] }}</span>
                                                @endif
                                                @if($t['due_date'])
                                                    <span>Due {{ $t['due_date'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(!empty($parsedPreview['motions']))
                            <div class="space-y-1.5 pt-1">
                                <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Formal motions (/motion)</span>
                                <div class="space-y-1.5">
                                    @foreach($parsedPreview['motions'] as $mot)
                                        <div class="rounded-xl border border-blue-200 bg-blue-50/70 p-2.5 font-medium text-blue-950 dark:border-blue-800/60 dark:bg-blue-950/70 dark:text-blue-100">
                                            {{ $mot['motion_text'] }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(empty($parsedPreview['mentions']) && empty($parsedPreview['tasks']) && empty($parsedPreview['motions']))
                            <p class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                                Type notes in the editor. Mentions, tasks and motions show here as you type.
                            </p>
                        @endif

                        <x-button block x-on:click="$wire.commitDetectedItems($refs.notesEditor?.value)">
                            Commit detected items
                        </x-button>
                    </div>
                @endif

                @if($activeRightTab === 'tasks')
                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between font-medium text-slate-500 dark:text-slate-400">
                            <span>Delegated action points</span>
                            <span class="text-slate-700 dark:text-slate-200">{{ $tasks->count() }} active</span>
                        </div>

                        <div class="max-h-[520px] space-y-2 overflow-y-auto pr-1">
                            @forelse($tasks as $task)
                                @php $isDone = $task->status->value === 'completed'; @endphp
                                <div class="flex items-start justify-between gap-2 rounded-xl border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800 dark:bg-slate-800/50">
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <div class="flex items-start gap-2">
                                            <button
                                                type="button"
                                                wire:click="toggleTaskStatus({{ $task->id }})"
                                                role="checkbox"
                                                aria-checked="{{ $isDone ? 'true' : 'false' }}"
                                                aria-label="Done: {{ $task->title }}"
                                                @class([
                                                    'mt-0.5 flex h-4 w-4 shrink-0 cursor-pointer items-center justify-center rounded border',
                                                    'border-blue-600 bg-blue-600 text-white' => $isDone,
                                                    'border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-900' => ! $isDone,
                                                ])
                                            >
                                                @if($isDone)
                                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                                @endif
                                            </button>
                                            <span @class([
                                                'break-words font-medium leading-snug',
                                                'text-slate-400 line-through' => $isDone,
                                                'text-slate-900 dark:text-white' => ! $isDone,
                                            ])>
                                                {{ $task->title }}
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2.5 pl-6 text-slate-500 dark:text-slate-400">
                                            <span>{{ $task->assignedTo ? $task->assignedTo->name : ($task->assigned_to_name ?: 'Unassigned') }}</span>
                                            @if($task->due_date)
                                                <span>Due {{ $task->due_date->format('d M') }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    @if($task->assignedTo)
                                        <x-button
                                            variant="secondary"
                                            size="sm"
                                            wire:click="notifyAssignedMember({{ $task->id }})"
                                            title="Email the assigned brother"
                                            aria-label="Email {{ $task->assignedTo->name }} about this task"
                                            class="shrink-0"
                                        >
                                            Email
                                        </x-button>
                                    @endif
                                </div>
                            @empty
                                <p class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                                    No tasks committed yet. Type [ ] in the notes and choose Sync.
                                </p>
                            @endforelse
                        </div>
                    </div>
                @endif

                @if($activeRightTab === 'motions')
                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between font-medium text-slate-500 dark:text-slate-400">
                            <span>Notices of motion</span>
                            <span class="text-slate-700 dark:text-slate-200">{{ $motions->count() }} total</span>
                        </div>

                        <div class="max-h-[520px] space-y-2 overflow-y-auto pr-1">
                            @forelse($motions as $motion)
                                <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-2.5 dark:border-slate-800 dark:bg-slate-800/50">
                                    <div class="flex items-start justify-between gap-1.5">
                                        <span class="font-medium leading-snug text-slate-900 dark:text-white">{{ $motion->title }}</span>
                                        <x-badge :variant="$motion->status === 'published_on_summons' ? 'success' : 'warning'" class="shrink-0">
                                            {{ ucfirst(str_replace('_', ' ', $motion->status)) }}
                                        </x-badge>
                                    </div>

                                    <p class="rounded-xl border border-slate-200 bg-white p-2 italic text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                                        "{{ $motion->motion_text }}"
                                    </p>

                                    <div class="flex items-center justify-between pt-1 text-slate-500 dark:text-slate-400">
                                        <span>Proposer: <strong class="font-medium">{{ $motion->proposer_name ?: ($motion->proposer?->name ?: 'Committee') }}</strong></span>
                                        @if($motion->status === 'draft_committee')
                                            <x-button size="sm" wire:click="approveMotionForSummons({{ $motion->id }})">Approve</x-button>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                                    No formal notices of motion drafted yet. Use /motion in the notes.
                                </p>
                            @endforelse
                        </div>
                    </div>
                @endif

                @if($activeRightTab === 'grants')
                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between font-medium text-slate-500 dark:text-slate-400">
                            <span>Charitable donation proposals</span>
                            <span class="text-slate-700 dark:text-slate-200">{{ $charityGrants->count() }} total</span>
                        </div>

                        <div class="max-h-[520px] space-y-2 overflow-y-auto pr-1">
                            @forelse($charityGrants as $grant)
                                @php $grantStatus = $grant->approval_status->value ?? $grant->approval_status; @endphp
                                <div class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50">
                                    <div class="flex items-start justify-between gap-1.5">
                                        <div>
                                            <span class="block font-semibold text-slate-900 dark:text-white">{{ $cs }}{{ number_format($grant->amount, 2) }}: {{ $grant->recipient_name }}</span>
                                            <span class="mt-0.5 block text-slate-600 dark:text-slate-300">{{ $grant->purpose }}</span>
                                        </div>
                                        <x-badge :variant="match ($grantStatus) {
                                            'proposed' => 'warning',
                                            'committee_approved' => 'info',
                                            default => 'success',
                                        }" class="shrink-0">
                                            {{ ucfirst(str_replace('_', ' ', $grantStatus)) }}
                                        </x-badge>
                                    </div>

                                    <div class="space-y-0.5 rounded-xl border border-slate-200 bg-white p-2 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                                        @if($grant->proposer)
                                            <div>Proposed by: <strong class="font-medium">{{ $grant->proposer->formatted_rank_name }}</strong></div>
                                        @endif
                                        @if($grant->seconder)
                                            <div>Seconded by: <strong class="font-medium">{{ $grant->seconder->formatted_rank_name }}</strong></div>
                                        @endif
                                        @if($grant->relief_chest_number)
                                            <div class="text-slate-500 dark:text-slate-400">Relief Chest: {{ $grant->relief_chest_number }}</div>
                                        @endif
                                    </div>

                                    <div class="flex items-center justify-end gap-2 pt-1">
                                        @if($grantStatus === 'proposed')
                                            <x-button size="sm" wire:click="approveGrantForSummons({{ $grant->id }})">Approve for summons</x-button>
                                        @elseif($grantStatus === 'committee_approved')
                                            <x-button variant="secondary" size="sm" wire:click="lodgeVoteGrant({{ $grant->id }})">Mark lodge voted</x-button>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                                    No charitable donation proposals logged yet.
                                </p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </x-card>
        </div>

    </div>

    {{-- Toast for the `notify` browser events the component dispatches. --}}
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
        role="status"
        class="pointer-events-auto fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm font-medium text-white shadow-2xl transition-all"
        style="display: none;"
    >
        <span x-show="type === 'error'" class="font-semibold text-rose-300">Error:</span>
        <span x-text="message"></span>
        <button type="button" @click="show = false" aria-label="Dismiss" class="ml-2 cursor-pointer rounded-lg p-0.5 text-slate-400 hover:text-white">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
    </div>
</div>
