<div class="space-y-6">
    @include('livewire.members._notice')

    <!-- Member Management Domain Unified Navigation -->
    <div class="flex items-center gap-2 p-1.5 bg-slate-200/80 dark:bg-slate-700/80 rounded-2xl w-fit text-xs font-bold border border-slate-300/60 dark:border-slate-700/60 shadow-inner">
        <a
            href="{{ route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 bg-slate-900 dark:bg-slate-700 text-white shadow-md font-black"
        >
            <span>👥</span>
            <span>Members Roster</span>
        </a>

        <a
            href="{{ route('admin.officers.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-slate-900/60"
        >
            <span>👔</span>
            <span>Annual Officer Rosters &amp; History</span>
        </a>

        <a
            href="{{ route('admin.club_acc.candidates.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-slate-900/60"
        >
            <span>📋</span>
            <span>Candidates (Form P Vetting)</span>
        </a>

        <a
            href="{{ route('admin.club_acc.subscriptions.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white/60 dark:hover:bg-slate-900/60"
        >
            <span>💳</span>
            <span>Subscriptions &amp; Dues</span>
        </a>
    </div>

    <!-- Header & Action Toolbar -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-6 sm:p-8 shadow-sm space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-6">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-400">Lodge Governance &amp; Administration</span>
                    <span class="text-slate-300">•</span>
                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200/60 dark:border-blue-800/60">
                        Rule 153 Roster
                    </span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Lodge Member Directory</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Comprehensive roster of lodge members, masonic ranks, progressive offices, and accounting ledger integration.
                </p>
            </div>

            <div class="flex flex-wrap lg:flex-nowrap items-center justify-start lg:justify-end lg:shrink-0 gap-2.5">
                <button
                    type="button"
                    wire:click="exportCsv"
                    class="px-4 py-2.5 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl shadow-xs transition-all inline-flex items-center gap-1.5 cursor-pointer"
                >
                    <span>📥</span>
                    <span>Export CSV</span>
                </button>

                @if($canInvite)
                    <a
                        href="{{ route('admin.club_acc.members.import', ['clubSlug' => $club->slug]) }}"
                        class="px-4 py-2.5 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl shadow-xs transition-all inline-flex items-center gap-1.5"
                    >
                        <span>📤</span>
                        <span>Import CSV</span>
                    </a>
                @endif

                <a
                    href="{{ route('admin.club_acc.members.create', ['clubSlug' => $club->slug]) }}"
                    class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-600/20 transition-all inline-flex items-center gap-1.5 cursor-pointer"
                >
                    <span>➕</span>
                    <span>Add New Member</span>
                </a>
            </div>
        </div>

        <!-- 4-Stat Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl space-y-1">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Roster</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-black text-slate-900 dark:text-white">{{ $totalMembers }}</span>
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold">Brethren</span>
                </div>
            </div>

            <div class="p-4 bg-emerald-50/50 dark:bg-emerald-950/50 border border-emerald-200/80 dark:border-emerald-800/80 rounded-2xl space-y-1">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-800 dark:text-emerald-200">Active Subscribing</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-black text-emerald-950 dark:text-emerald-100">{{ $activeCount }}</span>
                    <span class="text-xs text-emerald-700 dark:text-emerald-300 font-semibold">Members</span>
                </div>
            </div>

            <div class="p-4 bg-blue-50/50 dark:bg-blue-950/50 border border-blue-200/80 dark:border-blue-800/80 rounded-2xl space-y-1">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-800 dark:text-blue-200">Lodge Officers</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-black text-blue-950 dark:text-blue-100">{{ $officerCount }}</span>
                    <span class="text-xs text-blue-700 dark:text-blue-300 font-semibold">In Office</span>
                </div>
            </div>

            <div class="p-4 bg-amber-50/50 dark:bg-amber-950/50 border border-amber-200/80 dark:border-amber-800/80 rounded-2xl space-y-1">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-800 dark:text-amber-200">Past Masters</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-black text-amber-950 dark:text-amber-100">{{ $pmCount }}</span>
                    <span class="text-xs text-amber-700 dark:text-amber-300 font-semibold">W.Bro / R.Bro</span>
                </div>
            </div>
        </div>

        <!-- Filters Toolbar -->
        <div class="flex flex-col md:flex-row items-center justify-between gap-4 pt-2">
            <!-- Search Input -->
            <div class="w-full md:w-80 relative">
                <input
                    type="search"
                    name="member_search_query"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search name, email, Hermes ID, rank..."
                    class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-medium text-slate-800 dark:text-slate-100 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 focus:outline-none transition-all"
                />
                <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
            </div>

            <!-- Dropdown Filters -->
            <div class="flex items-center gap-2 flex-wrap lg:flex-nowrap w-full md:w-auto justify-start md:justify-end text-xs">
                <!-- Status Filter -->
                <select
                    wire:model.live="statusFilter"
                    class="min-w-0 max-w-44 truncate px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                >
                    <option value="all">All Statuses</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>

                <!-- Office Filter -->
                <select
                    wire:model.live="officeFilter"
                    class="min-w-0 max-w-44 truncate px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                >
                    <option value="all">All Offices</option>
                    @foreach($offices as $of)
                        <option value="{{ $of->value }}">{{ $of->label() }}</option>
                    @endforeach
                </select>

                <!-- Rank Filter -->
                <select
                    wire:model.live="rankFilter"
                    class="min-w-0 max-w-44 truncate px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                >
                    <option value="all">All Masonic Ranks</option>
                    @foreach($ranks as $rankValue => $rankLabel)
                        <option value="{{ $rankValue }}">{{ $rankLabel }}</option>
                    @endforeach
                </select>

                <!-- Portal Account Filter -->
                <select
                    wire:model.live="accountFilter"
                    aria-label="Portal account"
                    class="min-w-0 max-w-44 truncate px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                >
                    <option value="all">All Accounts</option>
                    <option value="not_invited">Not invited</option>
                    <option value="invited">Invited</option>
                    <option value="has_account">Has account</option>
                </select>
            </div>
        </div>
    </div>

    @if($canInvite && count($selected) > 0)
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 rounded-2xl text-xs">
            <span class="font-bold text-blue-900 dark:text-blue-200">{{ count($selected) }} selected</span>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="inviteSelected" wire:loading.attr="disabled" wire:target="inviteSelected" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md transition cursor-pointer disabled:opacity-60">
                    Invite selected
                </button>
                <button type="button" wire:click="clearSelection" class="px-4 py-2 font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white cursor-pointer">
                    Clear
                </button>
            </div>
        </div>
    @endif

    <!-- Roster Table Card -->
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800/80 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        @if($canInvite)
                            <th class="py-3.5 pl-6 pr-0 w-8">
                                <input
                                    type="checkbox"
                                    aria-label="Select everyone on this page who can be invited"
                                    @disabled(empty($invitableIds))
                                    x-data
                                    x-effect="const ids = {{ \Illuminate\Support\Js::from($invitableIds) }}.map(String); const picked = $wire.selected.map(String).filter(id => ids.includes(id)); $el.checked = ids.length > 0 && picked.length === ids.length; $el.indeterminate = picked.length > 0 && picked.length < ids.length"
                                    x-on:change="$wire.$set('selected', $event.target.checked ? {{ \Illuminate\Support\Js::from($invitableIds) }} : [])"
                                    class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500"
                                />
                            </th>
                        @endif
                        <th class="py-3.5 px-6 cursor-pointer" wire:click="sortBy('full_name')">
                            Member Name
                            @if($sortField === 'full_name') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="py-3.5 px-4 cursor-pointer" wire:click="sortBy('current_office')">
                            Lodge Office
                            @if($sortField === 'current_office') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="py-3.5 px-4">Grand / Provincial Rank</th>
                        <th class="py-3.5 px-4 cursor-pointer" wire:click="sortBy('membership_status')">
                            Status
                            @if($sortField === 'membership_status') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="py-3.5 px-4">Portal Account</th>
                        <th class="py-3.5 px-4 text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($members as $m)
                        @php($account = $m->accountStatus($club))
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/70 transition-colors" wire:key="member-{{ $m->id }}">
                            @if($canInvite)
                                <td class="py-4 pl-6 pr-0 w-8">
                                    @if(in_array($m->id, $invitableIds, true))
                                        <input type="checkbox" value="{{ $m->id }}" wire:model.live="selected" aria-label="Select {{ $m->full_name }}" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500" />
                                    @endif
                                </td>
                            @endif
                            <!-- Member Name -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-slate-900 dark:bg-slate-700 text-white flex items-center justify-center font-black text-xs shrink-0 shadow-xs">
                                        {{ strtoupper(substr($m->first_name, 0, 1) . substr($m->last_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.club_acc.members.show', ['clubSlug' => $club->slug, 'memberId' => $m->id]) }}" class="font-extrabold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 block text-sm tracking-tight">
                                            {{ $m->full_name }}
                                        </a>
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium block">{{ $m->email ?: 'No email on record' }}</span>
                                    </div>
                                </div>
                            </td>

                            <!-- Office -->
                            <td class="py-4 px-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($m->active_offices as $office)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $office->badgeClass() }}">
                                            {{ $office->label() }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>

                            <!-- Grand / Provincial Ranks -->
                            <td class="py-4 px-4">
                                <div class="space-y-0.5 text-[11px]">
                                    @if($m->grand_rank)
                                        <span class="inline-block font-extrabold text-amber-900 dark:text-amber-200 bg-amber-50 dark:bg-amber-950/40 px-1.5 py-0.5 rounded border border-amber-200 dark:border-amber-800/60">
                                            GL: {{ $m->grand_rank }}
                                        </span>
                                    @endif
                                    @if($m->provincial_rank)
                                        <span class="inline-block font-bold text-blue-900 dark:text-blue-200 bg-blue-50 dark:bg-blue-950/40 px-1.5 py-0.5 rounded border border-blue-200 dark:border-blue-800/60">
                                            Prov: {{ $m->provincial_rank }}
                                        </span>
                                    @endif
                                    @if(!$m->grand_rank && !$m->provincial_rank)
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Membership Status -->
                            <td class="py-4 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $m->membership_status->badgeClass() }}">
                                    {{ $m->membership_status->label() }}
                                </span>
                            </td>

                            <!-- Portal Account -->
                            <td class="py-4 px-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $account->badgeClass() }}">
                                    {{ $account->label() }}
                                </span>
                                @if($account->isInvitePending() && $m->acct_invited_at)
                                    <span class="block mt-1 text-[10px] text-slate-400">Sent {{ \Illuminate\Support\Carbon::parse($m->acct_invited_at)->format('j M Y') }}</span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-4 px-4 text-right whitespace-nowrap">
                                @if($canInvite)
                                    @if($account->canInvite() && $m->email && $m->membership_status->isSubscribing())
                                        <button type="button" wire:click="inviteMember({{ $m->id }})" wire:loading.attr="disabled" wire:target="inviteMember({{ $m->id }})" class="mr-1.5 px-3 py-1.5 rounded-xl border border-blue-200 dark:border-blue-800/60 bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 font-bold text-[11px] hover:bg-blue-100 dark:hover:bg-blue-900/40 transition cursor-pointer disabled:opacity-60">
                                            Invite
                                        </button>
                                    @elseif($account->isInvitePending())
                                        <button type="button" wire:click="resendInvite({{ $m->id }})" wire:loading.attr="disabled" wire:target="resendInvite({{ $m->id }})" class="mr-1.5 px-3 py-1.5 rounded-xl border border-blue-200 dark:border-blue-800/60 bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 font-bold text-[11px] hover:bg-blue-100 dark:hover:bg-blue-900/40 transition cursor-pointer disabled:opacity-60">
                                            Resend
                                        </button>
                                        <button type="button" wire:click="revokeInvite({{ $m->id }})" wire:confirm="Withdraw the invitation for {{ $m->full_name }}? The link in their email will stop working." class="mr-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 font-bold text-[11px] hover:text-rose-600 dark:hover:text-rose-400 transition cursor-pointer">
                                            Revoke
                                        </button>
                                    @endif
                                @endif
                                <a
                                    href="{{ route('admin.club_acc.members.edit', ['clubSlug' => $club->slug, 'memberId' => $m->id]) }}"
                                    title="Edit {{ $m->full_name }}"
                                    aria-label="Edit {{ $m->full_name }}"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:border-blue-300 dark:hover:border-blue-700/60 transition-colors cursor-pointer"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.86 4.49a2.1 2.1 0 1 1 2.97 2.97L8.4 18.9l-3.9.93.93-3.9L16.86 4.49Z" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-400">
                                <div class="space-y-2">
                                    <span class="text-2xl block">👥</span>
                                    <p class="font-bold text-slate-700 dark:text-slate-200">No members found matching filters.</p>
                                    <p class="text-xs text-slate-400">Try adjusting search parameters or click "+ Add New Member".</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 flex items-center justify-between">
            {{ $members->links() }}
        </div>
    </div>
</div>
