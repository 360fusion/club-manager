<div class="space-y-6">
    <!-- Member Management Domain Unified Navigation -->
    <div class="flex items-center gap-2 p-1.5 bg-slate-200/80 rounded-2xl w-fit text-xs font-bold border border-slate-300/60 shadow-inner">
        <a
            href="{{ route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 bg-slate-900 text-white shadow-md font-black"
        >
            <span>👥</span>
            <span>Members Roster</span>
        </a>

        <a
            href="{{ route('admin.officers.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 hover:text-slate-900 hover:bg-white/60"
        >
            <span>👔</span>
            <span>Annual Officer Rosters &amp; History</span>
        </a>

        <a
            href="{{ route('admin.club_acc.candidates.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 hover:text-slate-900 hover:bg-white/60"
        >
            <span>📋</span>
            <span>Candidates (Form P Vetting)</span>
        </a>

        <a
            href="{{ route('admin.club_acc.subscriptions.index', ['clubSlug' => $club->slug]) }}"
            class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-slate-600 hover:text-slate-900 hover:bg-white/60"
        >
            <span>💳</span>
            <span>Subscriptions &amp; Dues</span>
        </a>
    </div>

    <!-- Header & Action Toolbar -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b border-slate-100 pb-6">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-400">Lodge Governance &amp; Administration</span>
                    <span class="text-slate-300">•</span>
                    <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                        Rule 153 Roster
                    </span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Lodge Member Directory</h1>
                <p class="text-xs text-slate-500">
                    Comprehensive roster of lodge members, masonic ranks, progressive offices, and accounting ledger integration.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <button
                    type="button"
                    wire:click="exportCsv"
                    class="px-4 py-2.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 font-bold text-xs rounded-xl shadow-xs transition-all inline-flex items-center gap-1.5 cursor-pointer"
                >
                    <span>📥</span>
                    <span>Export CSV (Secretarial Return)</span>
                </button>

                <button
                    type="button"
                    wire:click="openAddModal"
                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/20 transition-all inline-flex items-center gap-1.5 cursor-pointer"
                >
                    <span>➕</span>
                    <span>Add New Member</span>
                </button>
            </div>
        </div>

        <!-- 4-Stat Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-2xl space-y-1">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Roster</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-black text-slate-900">{{ $totalMembers }}</span>
                    <span class="text-xs text-slate-500 font-semibold">Brethren</span>
                </div>
            </div>

            <div class="p-4 bg-emerald-50/50 border border-emerald-200/80 rounded-2xl space-y-1">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-800">Active Subscribing</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-black text-emerald-950">{{ $activeCount }}</span>
                    <span class="text-xs text-emerald-700 font-semibold">Members</span>
                </div>
            </div>

            <div class="p-4 bg-indigo-50/50 border border-indigo-200/80 rounded-2xl space-y-1">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-800">Lodge Officers</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-black text-indigo-950">{{ $officerCount }}</span>
                    <span class="text-xs text-indigo-700 font-semibold">In Office</span>
                </div>
            </div>

            <div class="p-4 bg-amber-50/50 border border-amber-200/80 rounded-2xl space-y-1">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-800">Past Masters</span>
                <div class="flex items-baseline justify-between">
                    <span class="text-2xl font-black text-amber-950">{{ $pmCount }}</span>
                    <span class="text-xs text-amber-700 font-semibold">W.Bro / R.Bro</span>
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
                    class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition-all"
                />
                <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
            </div>

            <!-- Dropdown Filters -->
            <div class="flex items-center gap-2 flex-wrap w-full md:w-auto justify-start md:justify-end text-xs">
                <!-- Status Filter -->
                <select
                    wire:model.live="statusFilter"
                    class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                >
                    <option value="all">All Statuses</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>

                <!-- Office Filter -->
                <select
                    wire:model.live="officeFilter"
                    class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                >
                    <option value="all">All Offices</option>
                    @foreach($offices as $of)
                        <option value="{{ $of->value }}">{{ $of->label() }}</option>
                    @endforeach
                </select>

                <!-- Rank Filter -->
                <select
                    wire:model.live="rankFilter"
                    class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                >
                    <option value="all">All Masonic Ranks</option>
                    <option value="Bro">Bro (Master Mason / Fellowcraft / EA)</option>
                    <option value="WBro">WBro (Past Master / Master)</option>
                    <option value="VWBro">VWBro (Very Worshipful)</option>
                    <option value="RWBro">RWBro (Right Worshipful)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Roster Table Card -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
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
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($members as $m)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <!-- Member Name -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-slate-900 text-white flex items-center justify-center font-black text-xs shrink-0 shadow-xs">
                                        {{ strtoupper(substr($m->first_name, 0, 1) . substr($m->last_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.club_acc.members.show', ['clubSlug' => $club->slug, 'memberId' => $m->id]) }}" class="font-extrabold text-slate-900 hover:text-indigo-600 block text-sm tracking-tight">
                                            {{ $m->full_name }}
                                        </a>
                                        <span class="text-[11px] text-slate-500 font-medium block">{{ $m->email ?: 'No email on record' }}</span>
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
                                        <span class="inline-block font-extrabold text-amber-900 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                                            GL: {{ $m->grand_rank }}
                                        </span>
                                    @endif
                                    @if($m->provincial_rank)
                                        <span class="inline-block font-bold text-indigo-900 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-200">
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <div class="space-y-2">
                                    <span class="text-2xl block">👥</span>
                                    <p class="font-bold text-slate-700">No members found matching filters.</p>
                                    <p class="text-xs text-slate-400">Try adjusting search parameters or click "+ Add New Member".</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="p-4 border-t border-slate-100 bg-slate-50 flex items-center justify-between">
            {{ $members->links() }}
        </div>
    </div>

    <!-- Add / Edit Member Modal -->
    @if($showMemberModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 sm:p-6" x-data @keydown.escape.window="$wire.closeModal()">
            <div @click.outside="$wire.closeModal()" class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-6 relative text-slate-800">
                <!-- Header -->
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-base font-black text-slate-900">
                            {{ $editingMemberId ? 'Edit Member Record' : 'Add New Lodge Member' }}
                        </h3>
                        <p class="text-xs text-slate-500">
                            {{ $editingMemberId ? 'Update masonic ranks, office assignments, and contact details.' : 'Register a new brother onto the Lodge Rule 153 roster.' }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeModal" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer">
                        ✕
                    </button>
                </div>

                <!-- Form -->
                <form wire:submit="saveMember" class="space-y-4 text-xs">
                    <!-- Grid 1: Name & Title -->
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Title Prefix</label>
                            <input type="text" wire:model="title" placeholder="Bro / WBro / Dr" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">First Name *</label>
                            <input type="text" wire:model="first_name" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" required />
                            @error('first_name') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Middle Names</label>
                            <input type="text" wire:model="middle_names" placeholder="David Arthur" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Surname / Last Name *</label>
                            <input type="text" wire:model="last_name" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" required />
                            @error('last_name') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Preferred Name</label>
                            <input type="text" wire:model="preferred_name" placeholder="Dave" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                        </div>
                    </div>

                    <!-- Grid 2: Contact & Residential Address -->
                    <div class="space-y-3 p-4 bg-slate-50 border border-slate-200/80 rounded-2xl">
                        <span class="font-extrabold text-slate-900 block text-xs">Contact &amp; Residential Address</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Email Address</label>
                                <input type="email" wire:model="email" placeholder="brother@example.org" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                                @error('email') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Phone Number</label>
                                <input type="text" wire:model="phone" placeholder="07123 456789" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Address Line 1</label>
                                <input type="text" wire:model="address_line_1" placeholder="Building name, house number & street" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Address Line 2</label>
                                <input type="text" wire:model="address_line_2" placeholder="Apartment, suite, unit, etc." class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Town / City</label>
                                <input type="text" wire:model="city" placeholder="Oxford" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">County / Region</label>
                                <input type="text" wire:model="county" placeholder="Oxfordshire" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Postcode / ZIP</label>
                                <input type="text" wire:model="postcode" placeholder="OX1 2JD" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs uppercase focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Country</label>
                                <input type="text" wire:model="country" placeholder="United Kingdom" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                            </div>
                        </div>
                    </div>

                    <!-- Grid 3: Masonic Ranks & Metadata -->
                    <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-2xl space-y-3">
                        <span class="font-extrabold text-slate-900 block text-xs">Masonic Governance &amp; Ranks</span>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Masonic Rank *</label>
                                <select wire:model="masonic_rank" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                    <option value="Bro">Bro (Master Mason / EA / FC)</option>
                                    <option value="WBro">WBro (Worshipful Brother / PM)</option>
                                    <option value="VWBro">VWBro (Very Worshipful)</option>
                                    <option value="RWBro">RWBro (Right Worshipful)</option>
                                    <option value="MWBro">MWBro (Most Worshipful)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Grand Rank</label>
                                <input type="text" wire:model="grand_rank" placeholder="e.g. PAGDC / PJGD" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Provincial Rank</label>
                                <input type="text" wire:model="provincial_rank" placeholder="e.g. PPrGSuptWks" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Hermes / GL Member ID</label>
                                <input type="text" wire:model="grand_lodge_number" placeholder="1482092" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-mono focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Current Office *</label>
                                <select wire:model="current_office" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                    @foreach($offices as $of)
                                        <option value="{{ $of->value }}">{{ $of->label() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Membership Status *</label>
                                <select wire:model="membership_status" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                    @foreach($statuses as $st)
                                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Grid 4: Key Dates -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Date of Initiation</label>
                            <input type="date" wire:model="date_of_initiation" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Date of Passing</label>
                            <input type="date" wire:model="date_of_passing" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Date of Raising</label>
                            <input type="date" wire:model="date_of_raising" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Date Joined Lodge</label>
                            <input type="date" wire:model="date_of_joining" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none" />
                        </div>
                    </div>

                    <!-- Accounting Customer Link -->
                    @if($accountingContacts->isNotEmpty())
                        <div class="space-y-1">
                            <label class="block font-bold text-slate-700">Accounting Ledger Contact Link</label>
                            <select wire:model="customer_account_id" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <option value="">-- No Ledger Account Linked --</option>
                                @foreach($accountingContacts as $ac)
                                    <option value="{{ $ac->id }}">{{ $ac->full_name }} ({{ $ac->email ?: 'No email' }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <!-- Modal Actions -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="closeModal" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-md transition cursor-pointer">
                            {{ $editingMemberId ? 'Save Changes' : 'Create Member Record' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
