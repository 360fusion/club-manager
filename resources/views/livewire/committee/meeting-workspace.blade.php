<div class="space-y-8">
    <!-- Meeting Header Card -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b border-slate-100 pb-6">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-400">Lodge Committee</span>
                    <span class="text-slate-300">•</span>
                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-md border {{ $meeting->status->badgeClass() }}">
                        {{ $meeting->status->label() }}
                    </span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ $meeting->title }}</h1>
                <p class="text-xs text-slate-500 flex items-center gap-3">
                    <span>🗓️ {{ $meeting->meeting_date->format('l, jS F Y \a\t H:i') }}</span>
                    <span>📍 {{ $meeting->location ?: 'Lodge Committee Room' }}</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Status controls -->
                <div class="inline-flex rounded-xl bg-slate-100 p-1 text-xs font-bold">
                    @foreach(['scheduled' => 'Scheduled', 'in_progress' => 'In Progress', 'draft_saved' => 'Draft', 'finalized' => 'Finalize'] as $val => $lbl)
                        <button
                            type="button"
                            wire:click="setStatus('{{ $val }}')"
                            class="px-2.5 py-1 rounded-lg transition-all {{ $meeting->status->value === $val ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900' }}"
                        >
                            {{ $lbl }}
                        </button>
                    @endforeach
                </div>

                <a
                    href="{{ route('admin.committee.minutes', ['clubSlug' => $club->slug, 'meetingId' => $meeting->id]) }}"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all inline-flex items-center gap-1.5"
                >
                    <span>✍️</span>
                    <span>Live Minute Taker</span>
                </a>
            </div>
        </div>

        <!-- 3-Column Workspace Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Column 1: Roll-Call Attendance (UGLE Rule 153) -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-black text-slate-900 text-sm flex items-center gap-2">
                        <span>👥</span>
                        <span>Committee Roll-Call</span>
                    </h3>
                    <button
                        type="button"
                        wire:click="$set('showAttendeeModal', true)"
                        class="text-xs font-bold text-indigo-600 hover:text-indigo-800"
                    >
                        + Add Member
                    </button>
                </div>

                <div class="space-y-2">
                    @forelse($attendees as $att)
                        <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-2xl flex items-center justify-between text-xs">
                            <div>
                                <span class="font-bold text-slate-900 block">{{ $att->name }}</span>
                                <span class="text-[10px] text-slate-500 font-semibold">{{ $att->role_title ?: 'Committee Member' }}</span>
                            </div>

                            <div class="flex items-center gap-1">
                                @foreach(['present' => '✅', 'apology' => '✉️', 'remote_link' => '💻'] as $type => $icon)
                                    <button
                                        type="button"
                                        wire:click="updateAttendance({{ $att->id }}, '{{ $type }}')"
                                        title="{{ ucfirst($type) }}"
                                        class="p-1 rounded-md text-xs transition-all {{ $att->attendance_type->value === $type ? 'bg-white shadow-sm border border-slate-200 font-bold scale-110' : 'opacity-40 hover:opacity-100' }}"
                                    >
                                        {{ $icon }}
                                    </button>
                                @endforeach

                                <button
                                    type="button"
                                    wire:click="removeAttendee({{ $att->id }})"
                                    class="p-1 text-slate-300 hover:text-rose-500 text-xs ml-1"
                                    title="Remove from roll call"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center border-2 border-dashed border-slate-200 rounded-2xl text-xs text-slate-400">
                            No attendees added yet. Click "+ Add Member" to populate the committee roll-call.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Column 2: Agenda Items & Order of Business -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-black text-slate-900 text-sm flex items-center gap-2">
                        <span>📋</span>
                        <span>Agenda Items</span>
                    </h3>
                    <button
                        type="button"
                        wire:click="$set('showAgendaModal', true)"
                        class="text-xs font-bold text-indigo-600 hover:text-indigo-800"
                    >
                        + Add Item
                    </button>
                </div>

                <div class="space-y-2">
                    @forelse($agenda_items as $item)
                        <div class="p-3.5 bg-slate-50 border border-slate-200/80 rounded-2xl space-y-2 text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-slate-200 text-slate-700 font-black text-[10px] flex items-center justify-center shrink-0">
                                        {{ $item->order }}
                                    </span>
                                    <span class="font-bold text-slate-900">{{ $item->title }}</span>
                                </div>
                                <button
                                    type="button"
                                    wire:click="toggleAgendaApproval({{ $item->id }})"
                                    class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider {{ $item->is_approved ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-slate-200 text-slate-600' }}"
                                >
                                    {{ $item->is_approved ? 'Approved' : 'Pending' }}
                                </button>
                            </div>

                            @if($item->description)
                                <p class="text-[11px] text-slate-500 pl-7">{{ $item->description }}</p>
                            @endif

                            @if($item->recommendation_text)
                                <div class="pl-7 text-[10px] font-bold text-indigo-700 bg-indigo-50/50 p-1.5 rounded-lg border border-indigo-100">
                                    {{ $item->recommendation_text }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="p-6 text-center border-2 border-dashed border-slate-200 rounded-2xl text-xs text-slate-400">
                            No agenda items yet. Add business items or run candidate vetting below.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Column 3: Governance Queues (Candidate Vetting & Bill Audit) -->
            <div class="space-y-6">
                <!-- Candidate Vetting Queue (Rule 159) -->
                <div class="space-y-3">
                    <h3 class="font-black text-slate-900 text-sm flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <span>👤</span>
                            <span>Candidate Vetting (Rule 159)</span>
                        </span>
                        <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">
                            {{ count($candidates) }} Pending
                        </span>
                    </h3>

                    <div class="space-y-2">
                        @forelse($candidates as $cand)
                            <div class="p-3 bg-amber-50/50 border border-amber-200/80 rounded-2xl flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-bold text-slate-900 block">{{ $cand->name }}</span>
                                    <span class="text-[10px] text-slate-500 font-semibold">{{ $cand->email }}</span>
                                </div>

                                <button
                                    type="button"
                                    wire:click="$dispatch('openCandidateVetting', { candidateId: {{ $cand->id }}, meetingId: {{ $meeting->id }} })"
                                    class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold text-[10px] rounded-lg shadow-sm transition-all"
                                >
                                    Vet Candidate
                                </button>
                            </div>
                        @empty
                            <div class="p-4 bg-slate-50 border border-slate-100 rounded-2xl text-center text-xs text-slate-400">
                                No candidates awaiting vetting.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Unpaid Bill Audit Queue (Rule 158) -->
                <div class="space-y-3">
                    <h3 class="font-black text-slate-900 text-sm flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <span>🔍</span>
                            <span>Accounts Audit (Rule 158)</span>
                        </span>
                        <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800">
                            {{ count($unpaid_bills) }} Bills
                        </span>
                    </h3>

                    <div class="space-y-2">
                        @forelse($unpaid_bills as $bill)
                            <div class="p-3 bg-indigo-50/40 border border-indigo-200/70 rounded-2xl flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-bold text-slate-900 block">{{ $bill->vendor_name }}</span>
                                    <span class="text-[10px] font-bold text-slate-500 font-mono">£{{ number_format($bill->amount, 2) }} • {{ $bill->bill_number }}</span>
                                </div>

                                <button
                                    type="button"
                                    wire:click="$dispatch('openBillAudit', { billId: {{ $bill->id }}, meetingId: {{ $meeting->id }} })"
                                    class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[10px] rounded-lg shadow-sm transition-all"
                                >
                                    Audit Bill
                                </button>
                            </div>
                        @empty
                            <div class="p-4 bg-slate-50 border border-slate-100 rounded-2xl text-center text-xs text-slate-400">
                                No vendor bills pending committee audit.
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Modals -->
    @livewire(\App\Domains\ClubAccounting\Livewire\Committee\Modals\CandidateVettingModal::class)
    @livewire(\App\Domains\ClubAccounting\Livewire\Committee\Modals\BillAuditModal::class)

    <!-- Add Agenda Item Modal -->
    @if($showAgendaModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4">
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-black text-slate-900">Add Committee Agenda Item</h3>
                    <button type="button" wire:click="$set('showAgendaModal', false)" class="text-slate-400 hover:text-slate-700 font-bold">✕</button>
                </div>

                <form wire:submit="addAgendaItem" class="space-y-4">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700">Agenda Category / Type</label>
                        <select wire:model="agendaItemType" class="w-full px-3 py-2 border border-slate-200 rounded-xl font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="general">General Business</option>
                            <option value="candidate_vetting">Candidate Vetting (Rule 159)</option>
                            <option value="accounts_audit">Accounts & Bill Audit (Rule 158)</option>
                            <option value="hall_affairs">Hall Affairs & Tenancy</option>
                            <option value="motion">Notice of Motion (Rule 160)</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700">Item Title</label>
                        <input type="text" wire:model="agendaTitle" placeholder="e.g. Catering contract review for winter season" class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none" required />
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700">Description / Background Notes</label>
                        <textarea wire:model="agendaDescription" rows="3" placeholder="Brief notes for committee members..." class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none resize-none"></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showAgendaModal', false)" class="px-4 py-2 bg-slate-100 rounded-xl font-bold text-slate-700">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-sm">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Add Attendee Modal -->
    @if($showAttendeeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4">
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-sm font-black text-slate-900">Add Member to Roll-Call</h3>
                    <button type="button" wire:click="$set('showAttendeeModal', false)" class="text-slate-400 hover:text-slate-700 font-bold">✕</button>
                </div>

                <form wire:submit="addAttendee" class="space-y-4">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700">Select Member</label>
                        <select wire:model="selectedUserId" class="w-full px-3 py-2 border border-slate-200 rounded-xl font-medium focus:ring-2 focus:ring-indigo-500 focus:outline-none" required>
                            <option value="">-- Choose Member --</option>
                            @foreach($clubMembers as $member)
                                <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700">Role on Committee</label>
                        <input type="text" wire:model="attendeeRole" placeholder="e.g. Worshipful Master, Secretary, Member" class="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showAttendeeModal', false)" class="px-4 py-2 bg-slate-100 rounded-xl font-bold text-slate-700">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold shadow-sm">Add to Roll-Call</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
