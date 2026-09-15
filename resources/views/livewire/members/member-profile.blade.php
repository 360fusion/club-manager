<div class="space-y-8">
    <!-- Header Card -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b border-slate-100 pb-6">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-2xl bg-slate-900 text-white flex items-center justify-center font-black text-xl shrink-0 shadow-md">
                    {{ strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)) }}
                </div>
                <div class="space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $member->membership_status->badgeClass() }}">
                            {{ $member->membership_status->label() }}
                        </span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $member->current_office->badgeClass() }}">
                            {{ $member->current_office->label() }}
                        </span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ $member->formatted_rank_name }}</h1>
                    <p class="text-xs text-slate-500 flex items-center gap-3">
                        <span>Hermes/GL ID: <strong>{{ $member->grand_lodge_number ?: 'Unspecified' }}</strong></span>
                        <span>•</span>
                        <span>Joined: <strong>{{ $member->date_of_joining ? $member->date_of_joining->format('jS F Y') : 'N/A' }}</strong></span>
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all inline-flex items-center gap-1.5">
                    ← Back to Directory
                </a>
                <button
                    type="button"
                    wire:click="toggleEdit"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all inline-flex items-center gap-1.5 cursor-pointer"
                >
                    <span>{{ $isEditing ? 'Cancel Edit' : '✏️ Edit Profile' }}</span>
                </button>
            </div>
        </div>

        <!-- Tabbed Navigation Bar -->
        <div class="flex items-center gap-1 border-b border-slate-200/80 overflow-x-auto pb-1 text-xs">
            @foreach(['details' => '📋 Core Details & Masonic Ranks', 'offices' => '🏛️ Offices Held', 'finances' => '💳 Subscription Balance Snapshot', 'charity' => '🤝 Charity Record'] as $tabKey => $tabLabel)
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
        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-5 text-xs">
            <h3 class="text-base font-black text-slate-900 pb-3 border-b border-slate-100">Update Member Information</h3>

            <form wire:submit="updateProfile" class="space-y-4 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Title</label>
                        <input type="text" wire:model="title" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">First Name *</label>
                        <input type="text" wire:model="first_name" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" required />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-slate-700 mb-1">Last Name *</label>
                        <input type="text" wire:model="last_name" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" required />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Masonic Rank *</label>
                        <select wire:model="masonic_rank" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs">
                            <option value="Bro">Bro</option>
                            <option value="WBro">WBro</option>
                            <option value="VWBro">VWBro</option>
                            <option value="RWBro">RWBro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Grand Rank</label>
                        <input type="text" wire:model="grand_rank" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Provincial Rank</label>
                        <input type="text" wire:model="provincial_rank" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Hermes / GL Member ID</label>
                        <input type="text" wire:model="grand_lodge_number" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-mono" />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Current Office</label>
                        <select wire:model="current_office" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs">
                            @foreach($offices as $of)
                                <option value="{{ $of->value }}">{{ $of->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Membership Status</label>
                        <select wire:model="membership_status" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs">
                            @foreach($statuses as $st)
                                <option value="{{ $st->value }}">{{ $st->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-2">
                    <button type="button" wire:click="toggleEdit" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 text-white font-bold rounded-xl text-xs">Save Profile</button>
                </div>
            </form>
        </div>
    @endif

    <!-- Tab 1: Core Details -->
    @if($activeTab === 'details')
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
                            <span class="font-bold text-slate-900 block mt-0.5">
                                {{ implode(', ', array_filter([$member->address_line_1, $member->address_line_2, $member->city, $member->postcode])) ?: 'No address registered' }}
                            </span>
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
                </div>

                <!-- Accounting Ledger Bridge Card -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-3">
                    <h3 class="text-sm font-black text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                        <span>💼</span>
                        <span>Liberu Accounting Ledger</span>
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

    <!-- Tab 2: Offices Held -->
    @if($activeTab === 'offices')
        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4 text-xs">
            <h3 class="text-sm font-black text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                <span>🏛️</span>
                <span>Current &amp; Historical Lodge Offices</span>
            </h3>

            <div class="p-4 bg-indigo-50/50 border border-indigo-200/80 rounded-2xl flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-800">Current Office Assignment</span>
                    <span class="font-black text-indigo-950 block text-base mt-0.5">{{ $member->current_office->label() }}</span>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-extrabold border {{ $member->current_office->badgeClass() }}">
                    Active Office
                </span>
            </div>
        </div>
    @endif

    <!-- Tab 3: Finances & Subscriptions -->
    @if($activeTab === 'finances')
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

    <!-- Tab 4: Charity Record -->
    @if($activeTab === 'charity')
        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-4 text-xs">
            <h3 class="text-sm font-black text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2">
                <span>🤝</span>
                <span>Charity Contributions &amp; Relief Record</span>
            </h3>

            <div class="p-6 text-center border-2 border-dashed border-slate-200 rounded-2xl text-slate-400">
                <span>Charity donation &amp; gift aid records integrated with Liberu Accounting ledger.</span>
            </div>
        </div>
    @endif
</div>
