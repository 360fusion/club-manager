<div class="space-y-8" x-data="{ complianceModal: null }">
    <!-- Meeting Header Card -->
    <x-card padding="lg" class="space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-6">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Lodge Committee</span>
                    <x-badge :variant="$meeting->isPast() ? 'neutral' : (['draft' => 'warning', 'draft_saved' => 'warning', 'scheduled' => 'info', 'in_progress' => 'warning', 'finalized' => 'success'][$meeting->status->value] ?? 'neutral')">
                        {{ $meeting->displayStatusLabel() }}
                    </x-badge>
                </div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ $meeting->title }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-x-3">
                    <span>{{ $meeting->meeting_date->format('l, jS F Y \a\t H:i') }}</span>
                    <span>{{ $meeting->location ?: 'Lodge Committee Room' }}</span>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-button
                    variant="secondary"
                    size="sm"
                    wire:click="openEditModal"
                    title="Edit the meeting title, date and location"
                >
                    Edit details
                </x-button>

                <x-button variant="secondary" size="sm" wire:click="$dispatch('open-agenda-pack-modal')">
                    Preview and send pack
                </x-button>

                <x-button size="sm" href="{{ route('admin.committee.minutes', ['clubSlug' => $club->slug, 'meetingId' => $meeting->id]) }}">
                    Live minute taker
                </x-button>
            </div>
        </div>

        <!-- 3-Column Workspace Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Column 1: Roll-Call Attendance (UGLE Rule 153) -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Committee roll-call</h3>
                    <x-button variant="ghost" size="sm" wire:click="openAttendeeModal">Add member</x-button>
                </div>

                <div class="space-y-2">
                    @forelse($attendees as $att)
                        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl flex items-center justify-between gap-2 text-xs">
                            <div class="space-y-0.5 min-w-0">
                                <span class="font-semibold text-slate-900 dark:text-white block">{{ $att->name }}</span>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $att->role_title ?: 'Committee Member' }}</span>
                                    @if($att->pack_sent_at)
                                        <x-badge variant="success" title="Agenda pack emailed at {{ $att->pack_sent_at->format('d M Y H:i') }}">Pack sent</x-badge>
                                    @else
                                        <x-badge title="Agenda pack not yet sent">Unsent</x-badge>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-1 shrink-0" role="group" aria-label="Attendance for {{ $att->name }}">
                                @foreach(['present' => 'Present', 'apology' => 'Apology', 'remote_link' => 'Remote'] as $type => $icon)
                                    <button
                                        type="button"
                                        wire:click="updateAttendance({{ $att->id }}, '{{ $type }}')"
                                        aria-pressed="{{ $att->attendance_type->value === $type ? 'true' : 'false' }}"
                                        class="px-2 py-1 rounded-lg text-xs font-medium transition-colors cursor-pointer {{ $att->attendance_type->value === $type ? 'bg-white dark:bg-slate-900 shadow-sm border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white' : 'border border-transparent text-slate-500 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-900 hover:text-slate-900 dark:hover:text-white' }}"
                                    >
                                        {{ $icon }}
                                    </button>
                                @endforeach

                                <button
                                    type="button"
                                    wire:click="removeAttendee({{ $att->id }})"
                                    class="ml-1 rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-rose-600 dark:hover:bg-slate-800 dark:hover:text-rose-400 cursor-pointer"
                                    title="Remove from roll call"
                                    aria-label="Remove {{ $att->name }} from roll call"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="p-6 text-center border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-500 dark:text-slate-400">
                            No attendees added yet. Select "Add member" to fill the committee roll-call.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Column 2: Agenda Items & Order of Business -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Agenda items</h3>
                    <x-button variant="ghost" size="sm" wire:click="$set('showAgendaModal', true)">Add item</x-button>
                </div>

                <div class="space-y-2">
                    @forelse($agenda_items as $item)
                        @php
                            $itemTypeEnum = $item->item_type instanceof \App\Domains\ClubAccounting\Enums\CommitteeItemType
                                ? $item->item_type
                                : \App\Domains\ClubAccounting\Enums\CommitteeItemType::tryFrom($item->item_type);
                            $typeLabel = $itemTypeEnum ? $itemTypeEnum->label() : ($item->item_type ?: 'General Business');
                            $typeValue = $itemTypeEnum ? $itemTypeEnum->value : 'general';
                        @endphp
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl space-y-2 text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <span class="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs flex items-center justify-center shrink-0 mt-0.5">
                                        {{ $item->order }}
                                    </span>
                                    <div class="min-w-0 space-y-1">
                                        <h4 class="font-semibold text-slate-900 dark:text-white leading-snug">{{ $item->title }}</h4>
                                        <div>
                                            <x-badge :variant="$typeValue === 'general' ? 'neutral' : 'info'">{{ $typeLabel }}</x-badge>
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    wire:click="toggleAgendaApproval({{ $item->id }})"
                                    aria-pressed="{{ $item->is_approved ? 'true' : 'false' }}"
                                    title="Toggle approval"
                                    class="px-2.5 py-0.5 rounded-full border text-xs font-medium shrink-0 cursor-pointer {{ $item->is_approved ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/50' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700' }}"
                                >
                                    {{ $item->is_approved ? 'Approved' : 'Pending' }}
                                </button>
                            </div>

                            @if($item->description)
                                <p class="text-xs text-slate-500 dark:text-slate-400 pl-7">{{ $item->description }}</p>
                            @endif

                            @if($item->recommendation_text)
                                <div class="ml-7 text-xs font-medium text-blue-700 dark:text-blue-300 bg-blue-50/50 dark:bg-blue-950/50 p-1.5 rounded-lg border border-blue-100 dark:border-blue-900/40">
                                    {{ $item->recommendation_text }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="p-6 text-center border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-500 dark:text-slate-400">
                            No agenda items yet. Add business items or run candidate vetting below.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Column 3: Governance Queues (Candidate Vetting & Bill Audit) -->
            <div class="space-y-6">
                <!-- Candidate Vetting Queue (Rule 159) -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Candidate Vetting</h3>
                            <x-badge>Rule 159</x-badge>
                            <button
                                type="button"
                                @click="complianceModal = 'vetting'"
                                class="text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors p-0.5 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                                title="Why is this here? Rule 159 explained"
                                aria-label="Rule 159 compliance info"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>
                        </div>
                        <x-badge variant="warning">{{ count($candidates) }} pending</x-badge>
                    </div>

                    <div class="space-y-2">
                        @forelse($candidates as $cand)
                            <div class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl flex items-center justify-between gap-2 text-xs">
                                <div class="min-w-0">
                                    <span class="font-semibold text-slate-900 dark:text-white block">{{ $cand->name }}</span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $cand->email }}</span>
                                </div>

                                <x-button
                                    size="sm"
                                    wire:click="$dispatch('openCandidateVetting', { candidateId: {{ $cand->id }}, meetingId: {{ $meeting->id }} })"
                                >
                                    Vet candidate
                                </x-button>
                            </div>
                        @empty
                            <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 rounded-xl text-center text-xs text-slate-500 dark:text-slate-400">
                                No candidates awaiting vetting.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Unpaid Bill Audit Queue (Rule 158) -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Accounts Audit</h3>
                            <x-badge>Rule 158</x-badge>
                            <button
                                type="button"
                                @click="complianceModal = 'audit'"
                                class="text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors p-0.5 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                                title="Why is this here? Rule 158 explained"
                                aria-label="Rule 158 compliance info"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </button>
                        </div>
                        <x-badge>{{ count($unpaid_bills) }} bills</x-badge>
                    </div>

                    <div class="space-y-2">
                        @forelse($unpaid_bills as $bill)
                            <div class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl flex items-center justify-between gap-2 text-xs">
                                <div class="min-w-0">
                                    <span class="font-semibold text-slate-900 dark:text-white block">{{ $bill->vendor_name }}</span>
                                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400 font-mono">{{ $cs }}{{ number_format($bill->amount, 2) }} • {{ $bill->bill_number }}</span>
                                </div>

                                <x-button
                                    size="sm"
                                    wire:click="$dispatch('openBillAudit', { billId: {{ $bill->id }}, meetingId: {{ $meeting->id }} })"
                                >
                                    Audit bill
                                </x-button>
                            </div>
                        @empty
                            <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 rounded-xl text-center text-xs text-slate-500 dark:text-slate-400">
                                No vendor bills pending committee audit.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Charitable Donation Proposals Queue -->
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Charitable donation proposals</h3>
                            <x-button variant="ghost" size="sm" wire:click="openCharityModal">Propose</x-button>
                        </div>
                        <x-badge>{{ count($charityGrants) }} proposals</x-badge>
                    </div>

                    <div class="space-y-2">
                        @forelse($charityGrants as $grant)
                            <div class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl space-y-2 text-xs">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="font-semibold text-slate-900 dark:text-white block">{{ $grant->recipient_name }}</span>
                                        <span class="text-xs font-semibold text-slate-900 dark:text-white">{{ $cs }}{{ number_format($grant->amount, 2) }}</span>
                                    </div>
                                    <x-badge :variant="['committee_approved' => 'info', 'lodge_voted' => 'warning', 'disbursed' => 'success'][$grant->approval_status->value] ?? 'neutral'">
                                        {{ $grant->approval_status->label() }}
                                    </x-badge>
                                </div>

                                <div class="text-xs text-slate-500 dark:text-slate-400 space-y-0.5">
                                    <div>Proposed by: <strong class="font-medium text-slate-800 dark:text-slate-100">{{ $grant->proposer?->formatted_rank_name ?: 'Not assigned' }}</strong></div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span>Seconded by: <strong class="font-medium text-slate-800 dark:text-slate-100">{{ $grant->seconder?->formatted_rank_name ?: 'Pending seconder' }}</strong></span>
                                        @if(!$grant->seconder_member_id)
                                            <select
                                                wire:change="updateGrantSeconder({{ $grant->id }}, $event.target.value)"
                                                aria-label="Assign a seconder for {{ $grant->recipient_name }}"
                                                class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                            >
                                                <option value="">Assign seconder</option>
                                                @foreach($clubMembers as $m)
                                                    @if($m->member_id && $m->member_id !== $grant->proposer_member_id)
                                                        <option value="{{ $m->member_id }}">{{ $m->name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-1 pt-1.5 border-t border-slate-200 dark:border-slate-800">
                                    @if($grant->approval_status->value === 'proposed')
                                        <x-button
                                            size="sm"
                                            wire:click="updateGrantApprovalStatus({{ $grant->id }}, 'committee_approved')"
                                        >
                                            Committee approve
                                        </x-button>
                                    @elseif($grant->approval_status->value === 'committee_approved')
                                        <x-button
                                            size="sm"
                                            wire:click="updateGrantApprovalStatus({{ $grant->id }}, 'lodge_voted')"
                                        >
                                            Lodge sanction
                                        </x-button>
                                    @else
                                        <x-badge variant="success">Approved for payment</x-badge>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800 rounded-xl text-center text-xs text-slate-500 dark:text-slate-400">
                                No charitable donation proposals logged for this meeting.
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>

        </div>
    </x-card>

    <!-- Propose Charity Grant Modal from Meeting Workspace -->
    @if ($showCharityModal)
    <x-modal :show="$showCharityModal" title="Propose a charitable grant" close="$set('showCharityModal', false)">
        <form id="charity-grant-form" wire:submit.prevent="saveCharityGrantFromMeeting" class="space-y-4">
            <x-input label="Recipient charity or cause" required wire:model="grantRecipient" placeholder="e.g. Local children's hospice" />

            <x-textarea label="Grant purpose or details" required rows="2" wire:model="grantPurpose" placeholder="Reason for the grant request" />

            <x-input :label="'Proposed amount ('.$cs.')'" required type="number" step="0.01" wire:model="grantAmount" />

            <div class="grid grid-cols-2 gap-3">
                <x-select label="Proposer" wire:model="grantProposerId">
                    <option value="">Select proposer</option>
                    @foreach($clubMembers as $m)
                        @if($m->member_id)
                            <option value="{{ $m->member_id }}">{{ $m->name }}</option>
                        @endif
                    @endforeach
                </x-select>

                <x-select label="Seconder" wire:model="grantSeconderId">
                    <option value="">Select seconder</option>
                    @foreach($clubMembers as $m)
                        @if($m->member_id)
                            <option value="{{ $m->member_id }}">{{ $m->name }}</option>
                        @endif
                    @endforeach
                </x-select>
            </div>
        </form>

        <x-slot:footer>
            <x-button variant="secondary" wire:click="$set('showCharityModal', false)">Cancel</x-button>
            <x-button type="submit" form="charity-grant-form">Save and log proposal</x-button>
        </x-slot:footer>
    </x-modal>
    @endif

    <!-- Modals -->
    @livewire(\App\Domains\ClubAccounting\Livewire\Committee\Modals\CandidateVettingModal::class)
    @livewire(\App\Domains\ClubAccounting\Livewire\Committee\Modals\BillAuditModal::class)

    <!-- Compliance Explainer Modal Overlay (UGLE Rule 158 & 159) -->
    <div
        x-show="complianceModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4"
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
            role="dialog"
            aria-modal="true"
            :aria-labelledby="'compliance-title-' + complianceModal"
            x-trap.noscroll="complianceModal"
            class="bg-white dark:bg-slate-900 rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5 relative text-slate-800 dark:text-slate-100"
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
                class="absolute top-4 right-4 rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300 cursor-pointer"
                aria-label="Close explainer"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
            </button>

            <!-- Template A: Candidate Vetting (Rule 159) -->
            <template x-if="complianceModal === 'vetting'">
                <div class="space-y-4">
                    <!-- Header -->
                    <div class="pr-8">
                        <h3 id="compliance-title-vetting" class="text-base font-semibold text-slate-900 dark:text-white">
                            Candidate vetting and approval (Rule 159)
                        </h3>
                        <div class="flex items-center gap-2 mt-1">
                            <x-badge variant="info">Statutory Summons Gate</x-badge>
                        </div>
                    </div>

                    <!-- Content Blocks -->
                    <div class="space-y-3 text-xs">
                        <!-- Constitutional Requirement -->
                        <div class="p-3.5 bg-blue-50/60 dark:bg-blue-950/40 border border-blue-200/70 dark:border-blue-800/60 rounded-xl space-y-1">
                            <span class="font-semibold text-blue-900 dark:text-blue-200 block">Constitutional Requirement</span>
                            <p class="text-blue-950/90 dark:text-blue-100/90 leading-relaxed">
                                Under UGLE Rule 159, no candidate may be balloted for initiation or joining in open lodge without prior recommendation by the Lodge Committee and formal notice on the printed summons.
                            </p>
                        </div>

                        <!-- Platform Workflow -->
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1">
                            <span class="font-semibold text-slate-900 dark:text-white block">Platform Workflow</span>
                            <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                                This gate pulls candidate records currently sitting at the <code class="px-1 py-0.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded text-slate-800 dark:text-slate-100 font-mono text-xs">lodge_committee</code> stage in the Candidate Pipeline. It provides immediate access to the candidate's Form P, statutory background declarations, and reports from the informal interviewers.
                            </p>
                        </div>

                        <!-- Downstream Impact -->
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1">
                            <span class="font-semibold text-slate-900 dark:text-white block">Downstream Impact</span>
                            <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                                Marking a candidate as <em>Approved</em> clears them for publication on the upcoming summons and prompts the Secretary's Summons Builder to generate the statutory ballot notice.
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="pt-4 flex items-center justify-end border-t border-slate-100 dark:border-slate-800">
                        <x-button variant="secondary" x-on:click="complianceModal = null">Understood</x-button>
                    </div>
                </div>
            </template>

            <!-- Template B: Accounts Audit (Rule 158) -->
            <template x-if="complianceModal === 'audit'">
                <div class="space-y-4">
                    <!-- Header -->
                    <div class="pr-8">
                        <h3 id="compliance-title-audit" class="text-base font-semibold text-slate-900 dark:text-white">
                            Accounts and liability pre-audit (Rule 158)
                        </h3>
                        <div class="flex items-center gap-2 mt-1">
                            <x-badge variant="info">Financial Governance Gate</x-badge>
                        </div>
                    </div>

                    <!-- Content Blocks -->
                    <div class="space-y-3 text-xs">
                        <!-- Constitutional Requirement -->
                        <div class="p-3.5 bg-blue-50/60 dark:bg-blue-950/40 border border-blue-200/70 dark:border-blue-800/60 rounded-xl space-y-1">
                            <span class="font-semibold text-blue-900 dark:text-blue-200 block">Constitutional Requirement</span>
                            <p class="text-blue-950/90 dark:text-blue-100/90 leading-relaxed">
                                Under UGLE Rule 158, the Lodge Committee is required to audit and examine all liabilities, bills, and demands incurred prior to them being presented in open lodge. The Master cannot submit unaudited accounts to the brethren for payment approval.
                            </p>
                        </div>

                        <!-- Platform Workflow -->
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1">
                            <span class="font-semibold text-slate-900 dark:text-white block">Platform Workflow</span>
                            <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                                Integrates directly with the Accounts Payable ledger, pulling pending invoices (e.g. hall rental, dining catering, per-capita dues, regalia).
                            </p>
                        </div>

                        <!-- Downstream Impact -->
                        <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1">
                            <span class="font-semibold text-slate-900 dark:text-white block">Downstream Impact</span>
                            <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                                Audited bills are marked as <em>Committee Recommended</em>, allowing the Master to formally declare in open lodge that the accounts have been vetted and are approved for settlement.
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="pt-4 flex items-center justify-end border-t border-slate-100 dark:border-slate-800">
                        <x-button variant="secondary" x-on:click="complianceModal = null">Understood</x-button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Add Agenda Item Modal -->
    @if ($showAgendaModal)
    <x-modal :show="$showAgendaModal" title="Add a committee agenda item" close="$set('showAgendaModal', false)">
        <form id="agenda-item-form" wire:submit="addAgendaItem" class="space-y-4">
            <x-select label="Agenda category" wire:model="agendaItemType">
                <option value="general">General business</option>
                <option value="candidate_vetting">Candidate vetting (Rule 159)</option>
                <option value="accounts_audit">Accounts and bill audit (Rule 158)</option>
                <option value="hall_affairs">Hall affairs and tenancy</option>
                <option value="motion">Notice of motion (Rule 160)</option>
            </x-select>

            <x-input label="Item title" required wire:model="agendaTitle" placeholder="e.g. Catering contract review for winter season" />

            <x-textarea label="Description or background notes" rows="3" wire:model="agendaDescription" placeholder="Brief notes for committee members" />
        </form>

        <x-slot:footer>
            <x-button variant="secondary" wire:click="$set('showAgendaModal', false)">Cancel</x-button>
            <x-button type="submit" form="agenda-item-form">Add item</x-button>
        </x-slot:footer>
    </x-modal>
    @endif

    <!-- Edit Committee Meeting Modal -->
    @if ($showEditModal)
    <x-modal :show="$showEditModal" title="Edit committee meeting" subtitle="Update the meeting title, date and time, and location." size="lg" close="closeEditModal">
        <form id="edit-meeting-form" wire:submit="updateMeeting" class="space-y-4">
            <x-input label="Meeting title" required wire:model="editTitle" placeholder="e.g. Committee Meeting – October 2026" />

            <x-input label="Date and time" required type="datetime-local" wire:model="editDate" />

            <x-input label="Meeting location" wire:model="editLocation" placeholder="e.g. Lodge Committee Room or Masonic Hall" />

            <x-select label="Meeting status" wire:model="editStatus">
                <option value="draft" @selected($editStatus === 'draft')>Draft (invites not yet sent)</option>
                <option value="scheduled" @selected($editStatus === 'scheduled')>Scheduled (invites sent)</option>
            </x-select>
        </form>

        <x-slot:footer>
            <x-button variant="secondary" wire:click="closeEditModal">Cancel</x-button>
            <x-button type="submit" form="edit-meeting-form" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="updateMeeting">Save changes</span>
                <span wire:loading wire:target="updateMeeting">Saving...</span>
            </x-button>
        </x-slot:footer>
    </x-modal>
    @endif

    <!-- Add Attendee Modal -->
    @if ($showAttendeeModal)
    <x-modal :show="$showAttendeeModal" title="Add members to the roll-call" subtitle="Select committee members or invite other lodge members with specific attendance roles." size="lg" close="$set('showAttendeeModal', false)">
        <div class="space-y-4 text-xs">
            <!-- Search & Filter Bar -->
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex-1 min-w-[200px]">
                    <input
                        type="search"
                        wire:model.live.debounce.250ms="attendeeSearch"
                        placeholder="Filter members by name, email or role"
                        aria-label="Filter members"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                    />
                </div>
                <div class="flex items-center gap-1.5">
                    <x-button variant="secondary" size="sm" wire:click="selectAllCommittee">Select all committee</x-button>
                    @if(count($selectedMemberIds) > 0)
                        <x-button variant="ghost" size="sm" wire:click="deselectAll">Clear selection</x-button>
                    @endif
                </div>
            </div>

            <!-- Scrollable Member List -->
            <div class="overflow-y-auto space-y-6 max-h-[50vh] pr-1">
                <!-- Group 1: Committee Members (Top Priority) -->
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between text-xs font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-1">
                        <span>Committee members ({{ $committeeMembers->count() }})</span>
                        <span class="text-xs font-normal text-slate-500 dark:text-slate-400">Priority attendance</span>
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
                        <div class="p-2.5 rounded-xl border transition-colors flex items-center justify-between gap-3 {{ $isAlreadyAttendee ? 'bg-slate-50/70 dark:bg-slate-800/70 border-slate-200 dark:border-slate-800 opacity-60' : (in_array((string) $member->id, array_map('strval', $selectedMemberIds)) ? 'bg-blue-50/40 dark:bg-blue-950/40 border-blue-300 dark:border-blue-700/60 ring-1 ring-blue-200 dark:ring-blue-800/60' : 'bg-white dark:bg-slate-900 border-slate-200/80 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700') }}">
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
                                        <span class="font-semibold text-slate-900 dark:text-white truncate">{{ $member->name }}</span>
                                        @if($roleKey === 'chair')
                                            <x-badge variant="info">Chair</x-badge>
                                        @elseif($roleKey === 'secretary')
                                            <x-badge variant="info">Secretary</x-badge>
                                        @else
                                            <x-badge>Member</x-badge>
                                        @endif

                                        @if($isAlreadyAttendee)
                                            <span class="text-xs text-slate-500 dark:text-slate-400">Already on roll-call</span>
                                        @endif
                                    </div>
                                    <span class="text-xs text-slate-500 dark:text-slate-400 block truncate">{{ $member->email }}</span>
                                </div>
                            </label>

                            @if(!$isAlreadyAttendee)
                                <div class="w-44 flex-shrink-0">
                                    <input
                                        type="text"
                                        wire:model="customRoles.{{ $member->id }}"
                                        placeholder="{{ $defaultRoleText }}"
                                        aria-label="Roll-call title for {{ $member->name }}"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                        title="Override roll-call title"
                                    />
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl text-center text-xs text-slate-500 dark:text-slate-400">
                            No committee members match your search.
                        </div>
                    @endforelse
                </div>

                <!-- Group 2: Non-Committee Members -->
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between text-xs font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-1">
                        <span>Other lodge members ({{ $nonCommitteeMembers->count() }})</span>
                        <span class="text-xs font-normal text-slate-500 dark:text-slate-400">Optional guests and attendees</span>
                    </div>

                    @forelse($nonCommitteeMembers as $member)
                        @php
                            $isAlreadyAttendee = ($member->user_id && in_array($member->user_id, $existingAttendeeUserIds))
                                || in_array($member->name, $existingAttendeeNames ?? []);
                        @endphp
                        <div class="p-2.5 rounded-xl border transition-colors flex items-center justify-between gap-3 {{ $isAlreadyAttendee ? 'bg-slate-50/70 dark:bg-slate-800/70 border-slate-200 dark:border-slate-800 opacity-60' : (in_array((string) $member->id, array_map('strval', $selectedMemberIds)) ? 'bg-blue-50/40 dark:bg-blue-950/40 border-blue-300 dark:border-blue-700/60 ring-1 ring-blue-200 dark:ring-blue-800/60' : 'bg-white dark:bg-slate-900 border-slate-200/80 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700') }}">
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
                                        <span class="font-semibold text-slate-800 dark:text-slate-100 truncate">{{ $member->name }}</span>
                                        <x-badge>Non-committee</x-badge>

                                        @if($isAlreadyAttendee)
                                            <span class="text-xs text-slate-500 dark:text-slate-400">Already on roll-call</span>
                                        @endif
                                    </div>
                                    <span class="text-xs text-slate-500 dark:text-slate-400 block truncate">{{ $member->email }}</span>
                                </div>
                            </label>

                            @if(!$isAlreadyAttendee)
                                <div class="w-44 flex-shrink-0">
                                    <input
                                        type="text"
                                        wire:model="customRoles.{{ $member->id }}"
                                        placeholder="Guest / Attendee"
                                        aria-label="Roll-call title for {{ $member->name }}"
                                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                        title="Roll-call role title"
                                    />
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl text-center text-xs text-slate-500 dark:text-slate-400">
                            No other members match your search.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <x-slot:footer>
            <p class="mr-auto text-xs text-slate-500 dark:text-slate-400">
                <span class="font-semibold text-slate-900 dark:text-white">{{ count($selectedMemberIds) }}</span> members selected
            </p>
            <x-button variant="secondary" wire:click="$set('showAttendeeModal', false)">Cancel</x-button>
            <x-button wire:click="addSelectedAttendees" :disabled="count($selectedMemberIds) === 0">
                Add to roll-call
                @if(count($selectedMemberIds) > 0)
                    <span class="px-1.5 py-0.5 bg-blue-500 rounded-full text-xs font-semibold">
                        {{ count($selectedMemberIds) }}
                    </span>
                @endif
            </x-button>
        </x-slot:footer>
    </x-modal>
    @endif

    <!-- Agenda Pack Preview & Dispatch Modal -->
    @livewire(\App\Domains\ClubAccounting\Livewire\Committee\AgendaPackPreviewModal::class, ['clubSlug' => $club->slug, 'meetingId' => $meeting->id], key('agenda-pack-modal-' . $meeting->id))
</div>
