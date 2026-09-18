<div class="space-y-8">
    <!-- Header Card -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b border-slate-100 pb-6">
            <div class="flex items-start gap-4 min-w-0 flex-1">
                <div class="w-14 h-14 rounded-2xl bg-slate-900 text-white flex items-center justify-center font-black text-xl shrink-0 shadow-md">
                    {{ strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)) }}
                </div>
                <div class="space-y-1 min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $member->membership_status->badgeClass() }}">
                            {{ $member->membership_status->label() }}
                        </span>
                        @foreach($member->active_offices as $office)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $office->badgeClass() }}">
                                {{ $office->label() }}
                            </span>
                        @endforeach
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight whitespace-nowrap overflow-hidden text-ellipsis" title="{{ $member->formatted_rank_name }}">
                        {{ $member->formatted_rank_name }}
                    </h1>
                    <p class="text-xs text-slate-500 flex items-center gap-3">
                        <span>Hermes/GL ID: <strong>{{ $member->grand_lodge_number ?: 'Unspecified' }}</strong></span>
                        <span>•</span>
                        <span>Joined: <strong>{{ $member->date_of_joining ? $member->date_of_joining->format('jS F Y') : 'N/A' }}</strong></span>
                    </p>
                </div>
            </div>

            <div class="flex flex-col sm:items-end gap-2 shrink-0">
                <div class="flex items-center gap-2 justify-end">
                    <a href="{{ route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all inline-flex items-center gap-1.5 shrink-0 whitespace-nowrap">
                        ← Back to Directory
                    </a>
                    <button
                        type="button"
                        wire:click="toggleEdit"
                        class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all inline-flex items-center gap-1.5 cursor-pointer shrink-0 whitespace-nowrap"
                    >
                        <span>{{ $isEditing ? 'Cancel Edit' : '✏️ Edit Profile' }}</span>
                    </button>
                </div>
                <div class="flex items-center gap-2 justify-end">
                    <button
                        type="button"
                        wire:click="archiveMember"
                        wire:confirm="Mark {{ $member->formatted_rank_name }} as Resigned / Archived?"
                        class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200/80 font-bold text-xs rounded-xl transition-all inline-flex items-center gap-1.5 cursor-pointer shrink-0 whitespace-nowrap"
                        title="Archive / Resign Member"
                    >
                        📁 Archive Member
                    </button>
                    <button
                        type="button"
                        wire:click="deleteMember"
                        wire:confirm="Are you sure you want to remove {{ $member->formatted_rank_name }} from the roster? This action cannot be undone."
                        class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/80 font-bold text-xs rounded-xl transition-all inline-flex items-center gap-1.5 cursor-pointer shrink-0 whitespace-nowrap"
                        title="Delete Member from Roster"
                    >
                        🗑️ Delete Member
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabbed Navigation Bar -->
        <div class="flex items-center gap-1 border-b border-slate-200/80 overflow-x-auto pb-1 text-xs">
            @foreach(['details' => '📋 Core Details & Masonic Ranks', 'finances' => '💳 Subscription Balance Snapshot'] as $tabKey => $tabLabel)
                <button
                    type="button"
                    wire:click="$set('activeTab', '{{ $tabKey }}')"
                    class="px-4 py-2.5 rounded-xl font-bold transition-all whitespace-nowrap cursor-pointer {{ $activeTab === $tabKey ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}"
                >
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Edit Profile Form (Inline Override) -->
    @if($isEditing)
        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6 text-xs">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-base font-black text-slate-900">Update Member Information</h3>
                <span class="text-[11px] text-slate-400 font-semibold">* Required fields</span>
            </div>

            <form wire:submit="updateProfile" class="space-y-6 text-xs">
                <!-- Names & Core Identifiers -->
                <div class="space-y-3">
                    <h4 class="text-[11px] font-black uppercase tracking-wider text-slate-400">Personal &amp; Preferred Name Details</h4>
                    <div class="flex flex-col md:flex-row gap-3 min-w-0">
                        <div class="w-full md:w-36 shrink-0 min-w-0">
                            <label class="block font-bold text-slate-700 mb-1 truncate">Title</label>
                            <select wire:model="title" class="w-full px-2.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white cursor-pointer">
                                <option value="Bro">Bro (Brother)</option>
                                <option value="WBro">WBro (Worshipful Brother)</option>
                                <option value="VWBro">VWBro (Very Worshipful Brother)</option>
                                <option value="RWBro">RWBro (Right Worshipful Brother)</option>
                                <option value="MWBro">MWBro (Most Worshipful Brother)</option>
                            </select>
                        </div>
                        <div class="w-full md:flex-1 min-w-0">
                            <label class="block font-bold text-slate-700 mb-1 truncate">First Name *</label>
                            <input type="text" wire:model="first_name" class="w-full px-2.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" required />
                        </div>
                        <div class="w-full md:flex-1 min-w-0">
                            <label class="block font-bold text-slate-700 mb-1 truncate">Middle Names</label>
                            <input type="text" wire:model="middle_names" class="w-full px-2.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" placeholder="e.g. David" />
                        </div>
                        <div class="w-full md:flex-1 min-w-0">
                            <label class="block font-bold text-slate-700 mb-1 truncate">Surname / Last Name *</label>
                            <input type="text" wire:model="last_name" class="w-full px-2.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" required />
                        </div>
                        <div class="w-full md:flex-1 min-w-0">
                            <label class="block font-bold text-slate-700 mb-1 truncate">Preferred Name</label>
                            <input type="text" wire:model="preferred_name" class="w-full px-2.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" placeholder="e.g. Dave" />
                        </div>
                    </div>
                </div>

                <!-- Contact & Residential Address Details -->
                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <h4 class="text-[11px] font-black uppercase tracking-wider text-slate-400">Contact &amp; Residential Address</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Email Address</label>
                            <input type="email" wire:model="email" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" placeholder="brother@example.org" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Telephone / Mobile</label>
                            <input type="text" wire:model="phone" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" placeholder="07123 456789" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Address Line 1</label>
                            <input type="text" wire:model="address_line_1" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" placeholder="Building name, house number & street" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Address Line 2</label>
                            <input type="text" wire:model="address_line_2" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" placeholder="Apartment, suite, unit, etc. (optional)" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-1">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Town / City</label>
                            <input type="text" wire:model="city" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" placeholder="e.g. Oxford" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">County / Region</label>
                            <input type="text" wire:model="county" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" placeholder="e.g. Oxfordshire" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Postcode / ZIP</label>
                            <input type="text" wire:model="postcode" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white uppercase" placeholder="e.g. OX1 2JD" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Country</label>
                            <input type="text" wire:model="country" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" placeholder="e.g. United Kingdom" />
                        </div>
                    </div>
                </div>

                <!-- Masonic Ranks & Status Grid -->
                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <h4 class="text-[11px] font-black uppercase tracking-wider text-slate-400">Masonic Ranks &amp; Member Status</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Masonic Rank *</label>
                            <select wire:model="masonic_rank" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold cursor-pointer focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                                <option value="Bro">Bro (Brother)</option>
                                <option value="WBro">WBro (Worshipful Brother)</option>
                                <option value="VWBro">VWBro (Very Worshipful Brother)</option>
                                <option value="RWBro">RWBro (Right Worshipful Brother)</option>
                                <option value="MWBro">MWBro (Most Worshipful Brother)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Grand Rank</label>
                            <input type="text" wire:model="grand_rank" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" placeholder="e.g. PJGD" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Provincial Rank</label>
                            <input type="text" wire:model="provincial_rank" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" placeholder="e.g. PPrGReg" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Membership Status</label>
                            <select wire:model="membership_status" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold cursor-pointer focus:ring-2 focus:ring-indigo-500 focus:bg-white">
                                @foreach($statuses as $st)
                                    <option value="{{ $st->value }}">{{ $st->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Lodge Administration & ID Grid -->
                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <h4 class="text-[11px] font-black uppercase tracking-wider text-slate-400">Lodge Office &amp; Registration</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Hermes / GL Member ID</label>
                            <input type="text" wire:model="grand_lodge_number" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" placeholder="e.g. 7819203" />
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block font-bold text-slate-700">Current Office</label>
                                <span class="text-[10px] text-slate-400 font-semibold">Managed via Rosters</span>
                            </div>
                            <div class="px-3.5 py-2 bg-slate-100 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 flex items-center justify-between gap-2 h-[42px]">
                                <span class="truncate flex items-center gap-1.5">
                                    <span>🏛️</span>
                                    <span>{{ $member->current_office?->label() ?: 'Member / Brethren' }}</span>
                                </span>
                                <a href="{{ route('admin.officers.index', ['clubSlug' => $club->slug]) }}" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-black rounded-lg transition-all flex items-center gap-1 shrink-0 shadow-xs">
                                    <span>👔</span>
                                    <span>Manage Roster</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Masonic Progress & Key Dates Grid -->
                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <h4 class="text-[11px] font-black uppercase tracking-wider text-slate-400">Masonic Progress &amp; Key Dates</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Initiated Date</label>
                            <input type="date" wire:model="date_of_initiation" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Passed Date</label>
                            <input type="date" wire:model="date_of_passing" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Raised Date</label>
                            <input type="date" wire:model="date_of_raising" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Joined Lodge Date</label>
                            <input type="date" wire:model="date_of_joining" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:bg-white" />
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                    <button type="button" wire:click="toggleEdit" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-all cursor-pointer">Cancel</button>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-md transition-all cursor-pointer">Save Profile</button>
                </div>
            </form>
        </div>
    @endif

    <!-- Tab 1: Core Details -->
    @if(!$isEditing && $activeTab === 'details')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 text-xs">
            <div class="lg:col-span-2 space-y-6">
                <!-- Personal & Contact Card -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4">
                    <h3 class="text-sm font-black text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                        <span>👤</span>
                        <span>Personal &amp; Contact Details</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <span class="text-slate-400 font-medium block text-[11px]">Email Address</span>
                            <span class="font-bold text-slate-900 block mt-0.5">{{ $member->email ?: 'Not provided' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-medium block text-[11px]">Telephone / Mobile</span>
                            <span class="font-bold text-slate-900 block mt-0.5">{{ $member->phone ?: 'Not provided' }}</span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-slate-400 font-medium block text-[11px]">Residential Address</span>
                            @if($member->formatted_address)
                                <span class="font-bold text-slate-900 block mt-0.5">
                                    {{ $member->formatted_address }}
                                </span>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-3 pt-3 border-t border-slate-100 text-[11px]">
                                    @if($member->address_line_1)
                                        <div>
                                            <span class="text-slate-400 block text-[10px]">Address Line 1</span>
                                            <span class="font-semibold text-slate-800">{{ $member->address_line_1 }}</span>
                                        </div>
                                    @endif
                                    @if($member->address_line_2)
                                        <div>
                                            <span class="text-slate-400 block text-[10px]">Address Line 2</span>
                                            <span class="font-semibold text-slate-800">{{ $member->address_line_2 }}</span>
                                        </div>
                                    @endif
                                    @if($member->city)
                                        <div>
                                            <span class="text-slate-400 block text-[10px]">Town / City</span>
                                            <span class="font-semibold text-slate-800">{{ $member->city }}</span>
                                        </div>
                                    @endif
                                    @if($member->county)
                                        <div>
                                            <span class="text-slate-400 block text-[10px]">County / Region</span>
                                            <span class="font-semibold text-slate-800">{{ $member->county }}</span>
                                        </div>
                                    @endif
                                    @if($member->postcode)
                                        <div>
                                            <span class="text-slate-400 block text-[10px]">Postcode / ZIP</span>
                                            <span class="font-semibold text-slate-800 uppercase">{{ $member->postcode }}</span>
                                        </div>
                                    @endif
                                    @if($member->country)
                                        <div>
                                            <span class="text-slate-400 block text-[10px]">Country</span>
                                            <span class="font-semibold text-slate-800">{{ $member->country }}</span>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <span class="font-bold text-slate-400 block mt-0.5 italic">No address registered</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Masonic Career & Key Dates Card -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4">
                    <h3 class="text-sm font-black text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                        <span>🏛️</span>
                        <span>Masonic Progress &amp; Key Dates</span>
                    </h3>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-2xl">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Initiated</span>
                            <span class="font-black text-slate-900 block mt-1">{{ $member->date_of_initiation ? $member->date_of_initiation->format('d M Y') : '—' }}</span>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-2xl">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Passed</span>
                            <span class="font-black text-slate-900 block mt-1">{{ $member->date_of_passing ? $member->date_of_passing->format('d M Y') : '—' }}</span>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-2xl">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Raised</span>
                            <span class="font-black text-slate-900 block mt-1">{{ $member->date_of_raising ? $member->date_of_raising->format('d M Y') : '—' }}</span>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-200/80 rounded-2xl">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Joined Lodge</span>
                            <span class="font-black text-slate-900 block mt-1">{{ $member->date_of_joining ? $member->date_of_joining->format('d M Y') : '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Masonic Ranks & Ledger Bridge -->
            <div class="space-y-6">
                <!-- Ranks Card -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <h3 class="text-sm font-black text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                        <span>🎖️</span>
                        <span>Masonic Ranks</span>
                    </h3>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between p-2.5 bg-slate-50 rounded-xl">
                            <span class="text-slate-500 font-medium">Primary Craft Rank</span>
                            <span class="font-black text-slate-900">{{ $member->masonic_rank }}</span>
                        </div>

                        <div class="flex items-center justify-between p-2.5 bg-amber-50/60 border border-amber-200/60 rounded-xl">
                            <span class="text-amber-900 font-medium">Grand Rank</span>
                            <span class="font-black text-amber-950">{{ $member->grand_rank ?: 'None' }}</span>
                        </div>

                        <div class="flex items-center justify-between p-2.5 bg-indigo-50/60 border border-indigo-200/60 rounded-xl">
                            <span class="text-indigo-900 font-medium">Provincial Rank</span>
                            <span class="font-black text-indigo-950">{{ $member->provincial_rank ?: 'None' }}</span>
                        </div>
                    </div>
                <!-- Offices Held Card -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
                    <h3 class="text-sm font-black text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                        <span>🏛️</span>
                        <span>Offices Held</span>
                    </h3>

                    <div class="p-4 bg-indigo-50/50 border border-indigo-200/80 rounded-2xl flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-800">Current Office</span>
                            <span class="font-black text-indigo-950 block text-base mt-0.5">{{ $member->current_office->label() }}</span>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-extrabold border {{ $member->current_office->badgeClass() }}">
                            Active Office
                        </span>
                    </div>
                </div>

                <!-- Accounting Ledger Bridge Card -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-3">
                    <h3 class="text-sm font-black text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                        <span>💼</span>
                        <span>Accounting Ledger</span>
                    </h3>

                    @if($member->customerAccount)
                        <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-2xl space-y-1">
                            <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block">Linked Ledger Contact</span>
                            <span class="font-extrabold text-emerald-950 block text-sm">{{ $member->customerAccount->full_name }}</span>
                            <span class="text-[11px] text-emerald-700 block">{{ $member->customerAccount->email }}</span>
                        </div>
                    @else
                        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl text-center space-y-2">
                            <span class="text-slate-400 block text-xs">No accounting customer ledger account linked.</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Tab 2: Finances & Subscriptions -->
    @if(!$isEditing && $activeTab === 'finances')
        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6 text-xs">
            <h3 class="text-sm font-black text-slate-900 border-b border-slate-100 pb-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span>💳</span>
                    <span>Annual Subscription &amp; Dues Ledger</span>
                </div>
                <a href="{{ route('admin.club_acc.subscriptions.index', ['clubSlug' => $club->slug]) }}" class="text-amber-600 hover:underline font-bold text-xs">Manage All Dues →</a>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Assigned Fee Tier</span>
                    <span class="text-base font-black text-slate-900 block">
                        {{ $member->subscriptionTier ? $member->subscriptionTier->name : 'Standard Lodge Dues' }}
                    </span>
                    @if($member->subscriptionTier)
                        <span class="text-[10px] text-slate-500 block">£{{ number_format($member->subscriptionTier->annual_amount, 2) }} / year</span>
                    @endif
                </div>

                <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Annual Dues Override</span>
                    <span class="text-base font-black text-slate-900 block">
                        {{ $member->annual_dues_override ? '£' . number_format($member->annual_dues_override, 2) : 'None (Standard Rate)' }}
                    </span>
                </div>

                @php
                    $latestSub = $member->subscriptions->sortByDesc('billing_year')->first();
                    $isClear = !$latestSub || !$latestSub->status->isOutstanding();
                @endphp
                <div class="p-4 {{ $isClear ? 'bg-emerald-50 border-emerald-200 text-emerald-950' : 'bg-amber-50 border-amber-200 text-amber-950' }} border rounded-2xl space-y-1">
                    <span class="text-[10px] font-bold uppercase tracking-wider block">Subscription Status</span>
                    <span class="text-base font-black block">
                        {{ $latestSub ? $latestSub->status->label() : 'No Dues Recorded' }}
                    </span>
                </div>
            </div>

            <!-- Member Subscriptions History Table -->
            <div class="space-y-3 pt-2">
                <h4 class="font-black text-slate-800 text-xs">Subscription Invoices &amp; Payment History:</h4>
                <div class="border border-slate-200 rounded-2xl overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-wider">
                                <th class="py-2.5 px-3">Billing Year</th>
                                <th class="py-2.5 px-3">Invoice Ref</th>
                                <th class="py-2.5 px-3 text-right">Amount Due</th>
                                <th class="py-2.5 px-3 text-right">Amount Paid</th>
                                <th class="py-2.5 px-3">Due Date</th>
                                <th class="py-2.5 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            @forelse($member->subscriptions->sortByDesc('billing_year') as $sub)
                                <tr>
                                    <td class="py-2.5 px-3 font-bold text-slate-900">{{ $sub->billing_year }} / {{ $sub->billing_year + 1 }}</td>
                                    <td class="py-2.5 px-3 font-mono text-[11px] text-slate-600">{{ $sub->invoice_reference ?: '—' }}</td>
                                    <td class="py-2.5 px-3 text-right font-semibold">£{{ number_format($sub->amount_due, 2) }}</td>
                                    <td class="py-2.5 px-3 text-right font-bold text-emerald-700">£{{ number_format($sub->amount_paid, 2) }}</td>
                                    <td class="py-2.5 px-3 text-slate-600">{{ $sub->due_date ? $sub->due_date->format('d M Y') : '—' }}</td>
                                    <td class="py-2.5 px-3">
                                        <span class="px-2 py-0.5 text-[9px] font-extrabold rounded-full border {{ $sub->status->badgeClasses() }}">
                                            {{ $sub->status->label() }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4 text-center text-slate-400 italic">No subscription invoices recorded for this member.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
