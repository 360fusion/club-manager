<div class="space-y-8" x-data="{ complianceModal: null }">
    <!-- Meeting Header Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-8 shadow-sm space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-6">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-400">Lodge Committee</span>
                    <span class="text-slate-300">•</span>
                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-md border {{ $meeting->displayBadgeClass() }}">
                        {{ $meeting->displayStatusLabel() }}
                    </span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ $meeting->title }}</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-3">
                    <span>🗓️ {{ $meeting->meeting_date->format('l, jS F Y \a\t H:i') }}</span>
                    <span>📍 {{ $meeting->location ?: 'Lodge Committee Room' }}</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="openEditModal"
                    class="px-3.5 py-2 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl shadow-sm transition-all inline-flex items-center gap-1.5"
                    title="Edit Meeting Details (Title, Date, Location)"
                >
                    <span>✏️</span>
                    <span>Edit Details</span>
                </button>

                <button
                    type="button"
                    wire:click="$dispatch('open-agenda-pack-modal')"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all inline-flex items-center gap-1.5"
                >
                    <span>📄</span>
                    <span>Preview &amp; Send Pack</span>
                </button>

                <a
                    href="{{ route('admin.committee.minutes', ['clubSlug' => $club->slug, 'meetingId' => $meeting->id]) }}"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all inline-flex items-center gap-1.5"
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
                    <h3 class="font-black text-slate-900 dark:text-white text-sm flex items-center gap-2">
                        <span>👥</span>
                        <span>Committee Roll-Call</span>
                    </h3>
                    <button
                        type="button"
                        wire:click="openAttendeeModal"
                        class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200"
                    >
                        + Add Member
                    </button>
                </div>

                <div class="space-y-2">
                    @forelse($attendees as $att)
                        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl flex items-center justify-between text-xs">
                            <div class="space-y-0.5">
                                <span class="font-bold text-slate-900 dark:text-white block">{{ $att->name }}</span>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold">{{ $att->role_title ?: 'Committee Member' }}</span>
                                    @if($att->pack_sent_at)
                                        <span class="inline-flex items-center gap-0.5 text-[9px] font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/70 dark:border-emerald-800/70 px-1.5 py-0.5 rounded-full" title="Agenda pack emailed at {{ $att->pack_sent_at->format('d M Y H:i') }}">
                                            <span>✓ Pack Sent</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-0.5 text-[9px] font-semibold text-slate-400 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded-full" title="Agenda pack not yet dispatched">
                                            <span>Unsent</span>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-1">
                                @foreach(['present' => '✅', 'apology' => '✉️', 'remote_link' => '💻'] as $type => $icon)
                                    <button
                                        type="button"
                                        wire:click="updateAttendance({{ $att->id }}, '{{ $type }}')"
                                        title="{{ ucfirst($type) }}"
                                        class="p-1 rounded-md text-xs transition-all {{ $att->attendance_type->value === $type ? 'bg-white dark:bg-slate-900 shadow-sm border border-slate-200 dark:border-slate-800 font-bold scale-110' : 'opacity-40 hover:opacity-100' }}"
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
                        <div class="p-6 text-center border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl text-xs text-slate-400">
                            No attendees added yet. Click "+ Add Member" to populate the committee roll-call.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Column 2: Agenda Items & Order of Business -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-black text-slate-900 dark:text-white text-sm flex items-center gap-2">
                        <span>📋</span>
                        <span>Agenda Items</span>
                    </h3>
                    <button
                        type="button"
                        wire:click="$set('showAgendaModal', true)"
                        class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200"
                    >
                        + Add Item
                    </button>
                </div>

                <div class="space-y-2">
                    @forelse($agenda_items as $item)
                        @php
                            $itemTypeEnum = $item->item_type instanceof \App\Domains\ClubAccounting\Enums\CommitteeItemType
                                ? $item->item_type
                                : \App\Domains\ClubAccounting\Enums\CommitteeItemType::tryFrom($item->item_type);
                            $typeLabel = $itemTypeEnum ? $itemTypeEnum->label() : ($item->item_type ?: 'General Business');
                            $typeIcon = $itemTypeEnum ? $itemTypeEnum->icon() : '📋';
                            $typeValue = $itemTypeEnum ? $itemTypeEnum->value : 'general';
                        @endphp
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl space-y-2 text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <span class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-black text-[10px] flex items-center justify-center shrink-0 mt-0.5">
                                        {{ $item->order }}
                                    </span>
                                    <div class="min-w-0 space-y-1">
                                        <h4 class="font-bold text-slate-900 dark:text-white leading-snug">{{ $item->title }}</h4>
                                        <div>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ match($typeValue) {
                                                'general' => 'bg-slate-200/80 dark:bg-slate-700/80 text-slate-700 dark:text-slate-200 border border-slate-300/60 dark:border-slate-700/60',
                                                'candidate_vetting' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-200 dark:border-blue-800/60',
                                                'accounts_audit' => 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800/60',
                                                'hall_affairs' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-200 dark:border-blue-800/60',
                                                'motion' => 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800/60',
                                                default => 'bg-slate-200/80 dark:bg-slate-700/80 text-slate-700 dark:text-slate-200 border border-slate-300/60 dark:border-slate-700/60',
                                            } }}">
                                                <span>{{ $typeIcon }}</span>
                                                <span>{{ $typeLabel }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    wire:click="toggleAgendaApproval({{ $item->id }})"
                                    class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider shrink-0 {{ $item->is_approved ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800/60' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300' }}"
                                >
                                    {{ $item->is_approved ? 'Approved' : 'Pending' }}
                                </button>
                            </div>

                            @if($item->description)
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 pl-7">{{ $item->description }}</p>
                            @endif

                            @if($item->recommendation_text)
                                <div class="ml-7 text-[10px] font-bold text-blue-700 dark:text-blue-300 bg-blue-50/50 dark:bg-blue-950/50 p-1.5 rounded-lg border border-blue-100 dark:border-blue-900/40">
                                    {{ $item->recommendation_text }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="p-6 text-center border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl text-xs text-slate-400">
                            No agenda items yet. Add business items or run candidate vetting below.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Column 3: Governance Queues (Candidate Vetting & Bill Audit) -->
            <div class="space-y-6">
                <!-- Candidate Vetting Queue (Rule 159) -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-base">👤</span>
                            <h3 class="font-black text-slate-900 dark:text-white text-sm">Candidate Vetting</h3>
                            <span class="text-[10px] font-semibold bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 px-2 py-0.5 rounded-full">Rule 159</span>
                            <button
                                type="button"
                                @click="complianceModal = 'vetting'"
                                class="text-slate-400 hover:text-amber-600 dark:hover:text-amber-400 transition p-0.5 rounded-full hover:bg-amber-50 dark:hover:bg-amber-950/40 cursor-pointer"
                                title="Why is this here? Click for Rule 159 compliance explainer"
                                aria-label="Rule 159 compliance info"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>
                        </div>
                        <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200">
                            {{ count($candidates) }} Pending
                        </span>
                    </div>

                    <div class="space-y-2">
                        @forelse($candidates as $cand)
                            <div class="p-3 bg-amber-50/50 dark:bg-amber-950/50 border border-amber-200/80 dark:border-amber-800/80 rounded-2xl flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-bold text-slate-900 dark:text-white block">{{ $cand->name }}</span>
                                    <span class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold">{{ $cand->email }}</span>
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
                            <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 rounded-2xl text-center text-xs text-slate-400">
                                No candidates awaiting vetting.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Unpaid Bill Audit Queue (Rule 158) -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-base">🔍</span>
                            <h3 class="font-black text-slate-900 dark:text-white text-sm">Accounts Audit</h3>
                            <span class="text-[10px] font-semibold bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 px-2 py-0.5 rounded-full">Rule 158</span>
                            <button
                                type="button"
                                @click="complianceModal = 'audit'"
                                class="text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition p-0.5 rounded-full hover:bg-blue-50 dark:hover:bg-blue-950/40 cursor-pointer"
                                title="Why is this here? Click for Rule 158 compliance explainer"
                                aria-label="Rule 158 compliance info"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>
                        </div>
                        <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200">
                            {{ count($unpaid_bills) }} Bills
                        </span>
                    </div>

                    <div class="space-y-2">
                        @forelse($unpaid_bills as $bill)
                            <div class="p-3 bg-blue-50/40 dark:bg-blue-950/40 border border-blue-200/70 dark:border-blue-800/70 rounded-2xl flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-bold text-slate-900 dark:text-white block">{{ $bill->vendor_name }}</span>
                                    <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 font-mono">{{ $cs }}{{ number_format($bill->amount, 2) }} • {{ $bill->bill_number }}</span>
                                </div>

                                <button
                                    type="button"
                                    wire:click="$dispatch('openBillAudit', { billId: {{ $bill->id }}, meetingId: {{ $meeting->id }} })"
                                    class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white font-bold text-[10px] rounded-lg shadow-sm transition-all"
                                >
                                    Audit Bill
                                </button>
                            </div>
                        @empty
                            <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 rounded-2xl text-center text-xs text-slate-400">
                                No vendor bills pending committee audit.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Charitable Donation Proposals Queue -->
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-base">🎁</span>
                            <h3 class="font-black text-slate-900 dark:text-white text-sm">Charitable Donation Proposals</h3>
                            <button
                                type="button"
                                wire:click="openCharityModal"
                                class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200"
                            >
                                + Propose
                            </button>
                        </div>
                        <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200">
                            {{ count($charityGrants) }} Proposals
                        </span>
                    </div>

                    <div class="space-y-2">
                        @forelse($charityGrants as $grant)
                            <div class="p-3 bg-blue-50/40 dark:bg-blue-950/40 border border-blue-200/70 dark:border-blue-800/70 rounded-2xl space-y-2 text-xs">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="font-bold text-slate-900 dark:text-white block">{{ $grant->recipient_name }}</span>
                                        <span class="text-[11px] font-black text-blue-950 dark:text-blue-100">{{ $cs }}{{ number_format($grant->amount, 2) }}</span>
                                    </div>
                                    <span class="px-2 py-0.5 text-[9px] font-black rounded-full border {{ $grant->approval_status->badgeClasses() }}">
                                        {{ $grant->approval_status->label() }}
                                    </span>
                                </div>

                                <div class="text-[10px] text-slate-500 dark:text-slate-400 space-y-0.5">
                                    <div>Proposed by: <strong class="text-slate-800 dark:text-slate-100">{{ $grant->proposer?->formatted_rank_name ?: 'Not assigned' }}</strong></div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span>Seconded by: <strong class="text-slate-800 dark:text-slate-100">{{ $grant->seconder?->formatted_rank_name ?: 'Pending Seconder' }}</strong></span>
                                        @if(!$grant->seconder_member_id)
                                            <select
                                                wire:change="updateGrantSeconder({{ $grant->id }}, $event.target.value)"
                                                class="text-[9px] font-bold p-1 bg-white dark:bg-slate-900 border border-blue-200 dark:border-blue-800/60 rounded focus:outline-none"
                                            >
                                                <option value="">+ Assign Seconder</option>
                                                @foreach($clubMembers as $m)
                                                    @if($m->member_id && $m->member_id !== $grant->proposer_member_id)
                                                        <option value="{{ $m->member_id }}">{{ $m->name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-1 pt-1.5 border-t border-blue-100 dark:border-blue-900/40">
                                    @if($grant->approval_status->value === 'proposed')
                                        <button
                                            type="button"
                                            wire:click="updateGrantApprovalStatus({{ $grant->id }}, 'committee_approved')"
                                            class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white font-bold text-[10px] rounded-lg shadow-sm transition"
                                        >
                                            ✅ Committee Approve
                                        </button>
                                    @elseif($grant->approval_status->value === 'committee_approved')
                                        <button
                                            type="button"
                                            wire:click="updateGrantApprovalStatus({{ $grant->id }}, 'lodge_voted')"
                                            class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold text-[10px] rounded-lg shadow-sm transition"
                                        >
                                            🏛️ Lodge Sanction
                                        </button>
                                    @else
                                        <span class="text-[10px] text-emerald-700 dark:text-emerald-300 font-bold">Approved for Payment ✓</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 rounded-2xl text-center text-xs text-slate-400">
                                No charitable donation proposals logged for this meeting.
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>

        </div>
    </div>

    <!-- Propose Charity Grant Modal from Meeting Workspace -->
    @if($showCharityModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-5 border border-slate-100 dark:border-slate-800">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">🎁</span>
                        <h3 class="font-black text-slate-900 dark:text-white text-base">Propose Charitable Grant</h3>
                    </div>
                    <button type="button" wire:click="$set('showCharityModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg">✕</button>
                </div>

                <form wire:submit.prevent="saveCharityGrantFromMeeting" class="space-y-4 text-xs">
                    <div>
                        <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Recipient Charity / Cause *</label>
                        <input type="text" wire:model="grantRecipient" placeholder="e.g. Local Children's Hospice" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" required />
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Grant Purpose / Details *</label>
                        <textarea wire:model="grantPurpose" rows="2" placeholder="Reason for grant request..." class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" required></textarea>
                    </div>

                    <div>
                        <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Proposed Amount ({{ $cs }}) *</label>
                        <input type="number" step="0.01" wire:model="grantAmount" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none" required />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Proposer</label>
                            <select wire:model="grantProposerId" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option value="">Select Proposer...</option>
                                @foreach($clubMembers as $m)
                                    @if($m->member_id)
                                        <option value="{{ $m->member_id }}">{{ $m->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Seconder</label>
                            <select wire:model="grantSeconderId" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                                <option value="">Select Seconder...</option>
                                @foreach($clubMembers as $m)
                                    @if($m->member_id)
                                        <option value="{{ $m->member_id }}">{{ $m->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @error('grantSeconderId') <span class="text-rose-600 dark:text-rose-400 text-[10px] block mt-0.5">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                        <button type="button" wire:click="$set('showCharityModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-blue-700 hover:bg-blue-800 text-white font-black rounded-xl shadow-md transition">Save &amp; Log Proposal</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Modals -->
    @livewire(\App\Domains\ClubAccounting\Livewire\Committee\Modals\CandidateVettingModal::class)
    @livewire(\App\Domains\ClubAccounting\Livewire\Committee\Modals\BillAuditModal::class)

    <!-- Compliance Explainer Modal Overlay (UGLE Rule 158 & 159) -->
    <div
        x-show="complianceModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @keydown.escape.window="complianceModal = null"
    >
        <div
            @click.outside="complianceModal = null"
            class="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5 relative text-slate-800 dark:text-slate-100"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            <!-- Top Right Close Button -->
            <button
                type="button"
                @click="complianceModal = null"
                class="absolute top-4 right-4 p-1.5 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer"
                title="Close explainer"
            >
                ✕
            </button>

            <!-- Template A: Candidate Vetting (Rule 159) -->
            <template x-if="complianceModal === 'vetting'">
                <div class="space-y-4">
                    <!-- Header -->
                    <div class="flex items-start gap-3 pr-6">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 border border-amber-200/80 dark:border-amber-800/80 flex items-center justify-center text-lg shrink-0">
                            👤
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                                Candidate Vetting &amp; Approval (Rule 159)
                            </h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[10px] font-semibold bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800/60 px-2 py-0.5 rounded-full">
                                    Statutory Summons Gate
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Content Blocks -->
                    <div class="space-y-3 text-xs">
                        <!-- Constitutional Requirement -->
                        <div class="p-3.5 bg-amber-50/60 dark:bg-amber-950/60 border border-amber-200/70 dark:border-amber-800/70 rounded-xl space-y-1">
                            <span class="font-bold text-amber-900 dark:text-amber-200 block">Constitutional Requirement</span>
                            <p class="text-amber-950/90 dark:text-amber-100/90 leading-relaxed">
                                Under UGLE Rule 159, no candidate may be balloted for initiation or joining in open lodge without prior recommendation by the Lodge Committee and formal notice on the printed summons.
                            </p>
                        </div>

                        <!-- Platform Workflow -->
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1">
                            <span class="font-bold text-slate-900 dark:text-white block">Platform Workflow</span>
                            <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                                This gate pulls candidate records currently sitting at the <code class="px-1 py-0.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded text-slate-800 dark:text-slate-100 font-mono text-[11px]">lodge_committee</code> stage in the Candidate Pipeline. It provides immediate access to the candidate's Form P, statutory background declarations, and reports from the informal interviewers.
                            </p>
                        </div>

                        <!-- Downstream Impact -->
                        <div class="p-3.5 bg-emerald-50/60 dark:bg-emerald-950/60 border border-emerald-200/70 dark:border-emerald-800/70 rounded-xl space-y-1">
                            <span class="font-bold text-emerald-900 dark:text-emerald-200 block">Downstream Impact</span>
                            <p class="text-emerald-950/90 dark:text-emerald-100/90 leading-relaxed">
                                Marking a candidate as <em>Approved</em> clears them for publication on the upcoming summons and prompts the Secretary's Summons Builder to generate the statutory ballot notice.
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="pt-2 flex items-center justify-end border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="complianceModal = null"
                            class="px-4 py-2 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold text-xs rounded-xl shadow-xs transition"
                        >
                            Understood
                        </button>
                    </div>
                </div>
            </template>

            <!-- Template B: Accounts Audit (Rule 158) -->
            <template x-if="complianceModal === 'audit'">
                <div class="space-y-4">
                    <!-- Header -->
                    <div class="flex items-start gap-3 pr-6">
                        <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-600 border border-blue-200/80 dark:border-blue-800/80 flex items-center justify-center text-lg shrink-0">
                            🔍
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white tracking-tight">
                                Accounts &amp; Liability Pre-Audit (Rule 158)
                            </h3>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[10px] font-semibold bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border border-blue-200 dark:border-blue-800/60 px-2 py-0.5 rounded-full">
                                    Financial Governance Gate
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Content Blocks -->
                    <div class="space-y-3 text-xs">
                        <!-- Constitutional Requirement -->
                        <div class="p-3.5 bg-blue-50/60 dark:bg-blue-950/60 border border-blue-200/70 dark:border-blue-800/70 rounded-xl space-y-1">
                            <span class="font-bold text-blue-900 dark:text-blue-200 block">Constitutional Requirement</span>
                            <p class="text-blue-950/90 dark:text-blue-100/90 leading-relaxed">
                                Under UGLE Rule 158, the Lodge Committee is required to audit and examine all liabilities, bills, and demands incurred prior to them being presented in open lodge. The Master cannot submit unaudited accounts to the brethren for payment approval.
                            </p>
                        </div>

                        <!-- Platform Workflow -->
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1">
                            <span class="font-bold text-slate-900 dark:text-white block">Platform Workflow</span>
                            <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                                Integrates directly with the Accounts Payable ledger, pulling pending invoices (e.g. hall rental, dining catering, per-capita dues, regalia).
                            </p>
                        </div>

                        <!-- Downstream Impact -->
                        <div class="p-3.5 bg-emerald-50/60 dark:bg-emerald-950/60 border border-emerald-200/70 dark:border-emerald-800/70 rounded-xl space-y-1">
                            <span class="font-bold text-emerald-900 dark:text-emerald-200 block">Downstream Impact</span>
                            <p class="text-emerald-950/90 dark:text-emerald-100/90 leading-relaxed">
                                Audited bills are marked as <em>Committee Recommended</em>, allowing the Master to formally declare in open lodge that the accounts have been vetted and are approved for settlement.
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="pt-2 flex items-center justify-end border-t border-slate-100 dark:border-slate-800">
                        <button
                            type="button"
                            @click="complianceModal = null"
                            class="px-4 py-2 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold text-xs rounded-xl shadow-xs transition"
                        >
                            Understood
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Add Agenda Item Modal -->
    @if($showAgendaModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <h3 class="text-sm font-black text-slate-900 dark:text-white">Add Committee Agenda Item</h3>
                    <button type="button" wire:click="$set('showAgendaModal', false)" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold">✕</button>
                </div>

                <form wire:submit="addAgendaItem" class="space-y-4">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Agenda Category / Type</label>
                        <select wire:model="agendaItemType" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl font-medium focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="general">General Business</option>
                            <option value="candidate_vetting">Candidate Vetting (Rule 159)</option>
                            <option value="accounts_audit">Accounts & Bill Audit (Rule 158)</option>
                            <option value="hall_affairs">Hall Affairs & Tenancy</option>
                            <option value="motion">Notice of Motion (Rule 160)</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Item Title</label>
                        <input type="text" wire:model="agendaTitle" placeholder="e.g. Catering contract review for winter season" class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none" required />
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Description / Background Notes</label>
                        <textarea wire:model="agendaDescription" rows="3" placeholder="Brief notes for committee members..." class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none"></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2">
                        <button type="button" wire:click="$set('showAgendaModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 rounded-xl font-bold text-slate-700 dark:text-slate-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-sm">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Edit Committee Meeting Modal -->
    @if($showEditModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>✏️</span>
                            <span>Edit Committee Meeting</span>
                        </h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Update the meeting title, scheduled date &amp; time, and location.</p>
                    </div>
                    <button type="button" wire:click="closeEditModal" class="p-1 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold transition-all">✕</button>
                </div>

                <form wire:submit="updateMeeting" class="space-y-4">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Meeting Title / Name <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            wire:model="editTitle"
                            placeholder="e.g. Committee Meeting – October 2026"
                            class="w-full px-3.5 py-2.5 border border-slate-200 dark:border-slate-800 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none text-xs font-medium text-slate-800 dark:text-slate-100"
                            required
                        />
                        @error('editTitle') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Date &amp; Time <span class="text-rose-500">*</span></label>
                        <input
                            type="datetime-local"
                            wire:model="editDate"
                            class="w-full px-3.5 py-2.5 border border-slate-200 dark:border-slate-800 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none text-xs font-medium text-slate-800 dark:text-slate-100"
                            required
                        />
                        @error('editDate') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Meeting Location</label>
                        <input
                            type="text"
                            wire:model="editLocation"
                            placeholder="e.g. Lodge Committee Room or Masonic Hall"
                            class="w-full px-3.5 py-2.5 border border-slate-200 dark:border-slate-800 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none text-xs font-medium text-slate-800 dark:text-slate-100"
                        />
                        @error('editLocation') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Meeting Status</label>
                        <select
                            wire:model="editStatus"
                            class="w-full px-3.5 py-2.5 border border-slate-200 dark:border-slate-800 rounded-xl focus:ring-2 focus:ring-blue-500 focus:outline-none text-xs font-medium text-slate-800 dark:text-slate-100"
                        >
                            <option value="draft">Draft (invites not yet dispatched)</option>
                            <option value="scheduled">Scheduled (invites dispatched)</option>
                        </select>
                        @error('editStatus') <span class="text-rose-500 text-[11px]">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            wire:click="closeEditModal"
                            class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl font-bold text-slate-700 dark:text-slate-200 transition-all"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-sm transition-all flex items-center gap-1.5"
                        >
                            <span wire:loading.remove wire:target="updateMeeting">Save Changes</span>
                            <span wire:loading wire:target="updateMeeting">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Add Attendee Modal -->
    @if($showAttendeeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl border border-slate-200 dark:border-slate-800 text-xs overflow-hidden">
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50/50">
                    <div>
                        <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                            <span>👥</span>
                            <span>Add Members to Roll-Call</span>
                        </h3>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Select committee members or invite other lodge members with specific attendance roles.</p>
                    </div>
                    <button type="button" wire:click="$set('showAttendeeModal', false)" class="p-1 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 font-bold transition-all">✕</button>
                </div>

                <!-- Search & Filter Bar -->
                <div class="px-6 py-3 border-b border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-wrap items-center justify-between gap-2">
                    <div class="relative flex-1 min-w-[200px]">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">🔍</span>
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="attendeeSearch"
                            placeholder="Filter members by name, email or role..."
                            class="w-full pl-8 pr-3 py-1.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button
                            type="button"
                            wire:click="selectAllCommittee"
                            class="px-2.5 py-1.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-bold rounded-xl border border-blue-200/60 dark:border-blue-800/60 text-[11px] transition-all flex items-center gap-1"
                        >
                            <span>🏛️</span>
                            <span>Select All Committee</span>
                        </button>
                        @if(count($selectedMemberIds) > 0)
                            <button
                                type="button"
                                wire:click="deselectAll"
                                class="px-2.5 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 font-bold rounded-xl text-[11px] transition-all"
                            >
                                Clear Selection
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Scrollable Member List -->
                <div class="p-6 overflow-y-auto space-y-6 flex-1 max-h-[50vh]">
                    <!-- Group 1: Committee Members (Top Priority) -->
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-blue-700 dark:text-blue-300 border-b border-blue-100/70 dark:border-blue-900/70 pb-1">
                            <span class="flex items-center gap-1.5">
                                <span>🏛️</span>
                                <span>Committee Members ({{ $committeeMembers->count() }})</span>
                            </span>
                            <span class="text-[10px] font-medium text-slate-400 lowercase">priority attendance</span>
                        </div>

                        @forelse($committeeMembers as $member)
                            @php
                                $isAlreadyAttendee = ($member->user_id && in_array($member->user_id, $existingAttendeeUserIds))
                                    || in_array($member->name, $existingAttendeeNames ?? []);
                                $roleKey = $member->committee_role ?? 'member';
                                $defaultRoleText = match($roleKey) {
                                    'chair' => 'Committee Chair',
                                    'secretary' => 'Committee Secretary',
                                    'member' => 'Committee Member',
                                    default => 'Committee Member'
                                };
                            @endphp
                            <div class="p-2.5 rounded-2xl border transition-all flex items-center justify-between gap-3 {{ $isAlreadyAttendee ? 'bg-slate-50/70 dark:bg-slate-800/50/70 border-slate-200 dark:border-slate-800 opacity-60' : (in_array((string) $member->id, array_map('strval', $selectedMemberIds)) ? 'bg-blue-50/40 dark:bg-blue-950/40 border-blue-300 dark:border-blue-700/60 ring-1 ring-blue-200 dark:ring-blue-800/60' : 'bg-white dark:bg-slate-900 border-slate-200/80 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700') }}">
                                <label class="flex items-center gap-3 cursor-pointer flex-1 min-w-0 {{ $isAlreadyAttendee ? 'cursor-not-allowed' : '' }}">
                                    <input
                                        type="checkbox"
                                        value="{{ $member->id }}"
                                        wire:model.live="selectedMemberIds"
                                        {{ $isAlreadyAttendee ? 'disabled checked' : '' }}
                                        class="rounded border-slate-300 dark:border-slate-700 text-blue-600 dark:text-blue-400 focus:ring-blue-500 w-4 h-4"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-slate-900 dark:text-white truncate">{{ $member->name }}</span>
                                            @if($roleKey === 'chair')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700/60">👑 Chair</span>
                                            @elseif($roleKey === 'secretary')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-300 dark:border-blue-700/60">✍️ Secretary</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700/60">🏛️ Member</span>
                                            @endif

                                            @if($isAlreadyAttendee)
                                                <span class="text-[10px] font-bold text-slate-400 italic">✓ Already on roll-call</span>
                                            @endif
                                        </div>
                                        <span class="text-[10px] text-slate-400 block truncate">{{ $member->email }}</span>
                                    </div>
                                </label>

                                @if(!$isAlreadyAttendee)
                                    <div class="w-44 flex-shrink-0">
                                        <input
                                            type="text"
                                            wire:model="customRoles.{{ $member->id }}"
                                            placeholder="{{ $defaultRoleText }}"
                                            class="w-full px-2.5 py-1 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-lg text-[11px] text-slate-700 dark:text-slate-200 focus:bg-white dark:focus:bg-slate-900 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                            title="Override roll-call title"
                                        />
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl text-center text-[11px] text-slate-400">
                                No committee members found matching query.
                            </div>
                        @endforelse
                    </div>

                    <!-- Group 2: Non-Committee Members -->
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-800 pb-1">
                            <span class="flex items-center gap-1.5">
                                <span>👥</span>
                                <span>Other Lodge / Club Members ({{ $nonCommitteeMembers->count() }})</span>
                            </span>
                            <span class="text-[10px] font-medium text-slate-400 lowercase">optional guests & attendees</span>
                        </div>

                        @forelse($nonCommitteeMembers as $member)
                            @php
                                $isAlreadyAttendee = ($member->user_id && in_array($member->user_id, $existingAttendeeUserIds))
                                    || in_array($member->name, $existingAttendeeNames ?? []);
                            @endphp
                            <div class="p-2.5 rounded-2xl border transition-all flex items-center justify-between gap-3 {{ $isAlreadyAttendee ? 'bg-slate-50/70 dark:bg-slate-800/50/70 border-slate-200 dark:border-slate-800 opacity-60' : (in_array((string) $member->id, array_map('strval', $selectedMemberIds)) ? 'bg-blue-50/40 dark:bg-blue-950/40 border-blue-300 dark:border-blue-700/60 ring-1 ring-blue-200 dark:ring-blue-800/60' : 'bg-white dark:bg-slate-900 border-slate-200/80 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700') }}">
                                <label class="flex items-center gap-3 cursor-pointer flex-1 min-w-0 {{ $isAlreadyAttendee ? 'cursor-not-allowed' : '' }}">
                                    <input
                                        type="checkbox"
                                        value="{{ $member->id }}"
                                        wire:model.live="selectedMemberIds"
                                        {{ $isAlreadyAttendee ? 'disabled checked' : '' }}
                                        class="rounded border-slate-300 dark:border-slate-700 text-blue-600 dark:text-blue-400 focus:ring-blue-500 w-4 h-4"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-slate-800 dark:text-slate-100 truncate">{{ $member->name }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800">Non-Committee</span>

                                            @if($isAlreadyAttendee)
                                                <span class="text-[10px] font-bold text-slate-400 italic">✓ Already on roll-call</span>
                                            @endif
                                        </div>
                                        <span class="text-[10px] text-slate-400 block truncate">{{ $member->email }}</span>
                                    </div>
                                </label>

                                @if(!$isAlreadyAttendee)
                                    <div class="w-44 flex-shrink-0">
                                        <input
                                            type="text"
                                            wire:model="customRoles.{{ $member->id }}"
                                            placeholder="Guest / Attendee"
                                            class="w-full px-2.5 py-1 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-lg text-[11px] text-slate-700 dark:text-slate-200 focus:bg-white dark:focus:bg-slate-900 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                                            title="Roll-call role title"
                                        />
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl text-center text-[11px] text-slate-400">
                                No general members found matching query.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-3.5 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 flex items-center justify-between">
                    <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                        <span class="font-bold text-slate-800 dark:text-slate-100">{{ count($selectedMemberIds) }}</span> members selected
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="$set('showAttendeeModal', false)"
                            class="px-4 py-2 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 rounded-xl font-bold text-slate-700 dark:text-slate-200 transition-all shadow-sm"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            wire:click="addSelectedAttendees"
                            @if(count($selectedMemberIds) === 0) disabled @endif
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl font-bold shadow-sm transition-all flex items-center gap-1.5"
                        >
                            <span>Add to Roll-Call</span>
                            @if(count($selectedMemberIds) > 0)
                                <span class="px-1.5 py-0.5 bg-blue-500 rounded-full text-[10px] font-black">
                                    {{ count($selectedMemberIds) }}
                                </span>
                            @endif
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Agenda Pack Preview & Dispatch Modal -->
    @livewire(\App\Domains\ClubAccounting\Livewire\Committee\AgendaPackPreviewModal::class, ['clubSlug' => $club->slug, 'meetingId' => $meeting->id], key('agenda-pack-modal-' . $meeting->id))
</div>
