<div class="space-y-6 max-w-5xl">
    <div class="space-y-1">
        <a href="{{ $editing ? route('admin.club_acc.members.show', ['clubSlug' => $club->slug, 'memberId' => $memberId]) : route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">&larr; {{ $editing ? 'Back to the member' : 'Back to the member directory' }}</a>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ $editing ? 'Edit Member Record' : 'Add New Lodge Member' }}</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $editing ? 'Update masonic ranks, office assignments, and contact details.' : 'Register a new brother onto the Lodge Rule 153 roster.' }}</p>
    </div>

    <form wire:submit="save" class="space-y-6 text-xs">
        <section class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 sm:p-8 space-y-4" aria-labelledby="sec-name">
            <h2 id="sec-name" class="text-sm font-black text-slate-900 dark:text-white">Name</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="f-first_name" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">First Name *</label>
                <input id="f-first_name" type="text" wire:model="first_name" placeholder="" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " required />
                @error('first_name') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="f-middle_names" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Middle Names</label>
                <input id="f-middle_names" type="text" wire:model="middle_names" placeholder="David Arthur" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " />
                @error('middle_names') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="f-last_name" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Surname / Last Name *</label>
                <input id="f-last_name" type="text" wire:model="last_name" placeholder="" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " required />
                @error('last_name') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
            </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="f-preferred_name" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Preferred Name</label>
                <input id="f-preferred_name" type="text" wire:model="preferred_name" placeholder="Dave" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " />
                @error('preferred_name') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
            </div>
            </div>
        </section>

        <section class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 sm:p-8 space-y-4" aria-labelledby="sec-contact">
            <h2 id="sec-contact" class="text-sm font-black text-slate-900 dark:text-white">Contact &amp; Residential Address</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="f-email" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Email Address</label>
                <input id="f-email" type="email" wire:model="email" placeholder="brother@example.org" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " />
                @error('email') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="f-phone" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Phone Number</label>
                <input id="f-phone" type="text" wire:model="phone" placeholder="07123 456789" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " />
                @error('phone') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="f-address_line_1" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Address Line 1</label>
                <input id="f-address_line_1" type="text" wire:model="address_line_1" placeholder="Building name, house number &amp; street" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " />
            </div>
            <div>
                <label for="f-address_line_2" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Address Line 2</label>
                <input id="f-address_line_2" type="text" wire:model="address_line_2" placeholder="Apartment, suite, unit, etc." class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " />
            </div>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="f-city" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Town / City</label>
                <input id="f-city" type="text" wire:model="city" placeholder="Oxford" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " />
            </div>
            <div>
                <label for="f-county" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">County / Region</label>
                <input id="f-county" type="text" wire:model="county" placeholder="Oxfordshire" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " />
            </div>
            <div>
                <label for="f-postcode" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Postcode / ZIP</label>
                <input id="f-postcode" type="text" wire:model="postcode" placeholder="OX1 2JD" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none uppercase" />
            </div>
            <div>
                <label for="f-country" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Country</label>
                <input id="f-country" type="text" wire:model="country" placeholder="United Kingdom" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " />
            </div>
            </div>
        </section>

        <section class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 sm:p-8 space-y-4" aria-labelledby="sec-masonic">
            <h2 id="sec-masonic" class="text-sm font-black text-slate-900 dark:text-white">Masonic Governance &amp; Ranks</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="f-masonic_rank" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Masonic Rank *</label>
                    <select id="f-masonic_rank" wire:model="masonic_rank" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        @foreach($ranks as $rankValue => $rankLabel)
                            <option value="{{ $rankValue }}">{{ $rankLabel }}</option>
                        @endforeach
                    </select>
                </div>
            <div>
                <label for="f-grand_rank" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Grand Rank</label>
                <select id="f-grand_rank" wire:model="grand_rank" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="">None</option>
                    @foreach($grandRanks as $rank)
                        <option value="{{ $rank['value'] }}">{{ $rank['label'] }}</option>
                    @endforeach
                </select>
                @error('grand_rank') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="f-provincial_rank" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Provincial Rank</label>
                <select id="f-provincial_rank" wire:model="provincial_rank" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    <option value="">None</option>
                    @foreach($provincialRanks as $rank)
                        <option value="{{ $rank['value'] }}">{{ $rank['label'] }}</option>
                    @endforeach
                </select>
                @error('provincial_rank') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
            </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="f-grand_lodge_number" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Hermes / GL Member ID</label>
                <input id="f-grand_lodge_number" type="text" wire:model="grand_lodge_number" placeholder="1482092" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none font-mono" />
            </div>
                <div>
                    <label for="f-current_office" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Current Office *</label>
                    <select id="f-current_office" wire:model="current_office" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        @foreach($offices as $of)
                            <option value="{{ $of->value }}">{{ $of->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="f-membership_status" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Membership Status *</label>
                    <select id="f-membership_status" wire:model="membership_status" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        @foreach($statuses as $st)
                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <section class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 sm:p-8 space-y-4" aria-labelledby="sec-dates">
            <h2 id="sec-dates" class="text-sm font-black text-slate-900 dark:text-white">Key Dates</h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="f-date_of_initiation" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Date of Initiation</label>
                <input id="f-date_of_initiation" type="date" wire:model="date_of_initiation" placeholder="" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " />
                @error('date_of_initiation') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="f-date_of_passing" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Date of Passing</label>
                <input id="f-date_of_passing" type="date" wire:model="date_of_passing" placeholder="" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " />
                @error('date_of_passing') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="f-date_of_raising" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Date of Raising</label>
                <input id="f-date_of_raising" type="date" wire:model="date_of_raising" placeholder="" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " />
                @error('date_of_raising') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
            </div>
            <div>
                <label for="f-date_of_joining" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Date Joined Lodge</label>
                <input id="f-date_of_joining" type="date" wire:model="date_of_joining" placeholder="" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none " />
                @error('date_of_joining') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
            </div>
            </div>
        </section>

        <section class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 sm:p-8 space-y-4" aria-labelledby="sec-more">
            <h2 id="sec-more" class="text-sm font-black text-slate-900 dark:text-white">Notes &amp; Accounting</h2>
            <div>
                <label for="f-notes" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Notes</label>
                <textarea id="f-notes" wire:model="notes" rows="3" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
            </div>
            @if($accountingContacts->isNotEmpty())
                <div>
                    <label for="f-customer_account_id" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Accounting Ledger Contact Link</label>
                    <select id="f-customer_account_id" wire:model="customer_account_id" class="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <option value="">-- No Ledger Account Linked --</option>
                        @foreach($accountingContacts as $ac)
                            <option value="{{ $ac->id }}">{{ $ac->full_name }} ({{ $ac->email ?: 'No email' }})</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if(! $editing && $canInvite)
                <label
                    x-data
                    :class="($wire.email ?? '').trim() === '' ? 'opacity-60' : ''"
                    class="flex items-start gap-2.5 p-3 rounded-xl bg-blue-50/60 dark:bg-blue-950/30 border border-blue-100 dark:border-blue-900/50"
                >
                    <input type="checkbox" wire:model="sendInvitation" :disabled="($wire.email ?? '').trim() === ''" class="mt-0.5 rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500" />
                    <span>
                        <span class="block font-bold text-slate-800 dark:text-slate-100">Send an invitation to set up an online account</span>
                        <span class="block text-slate-500 dark:text-slate-400">Emails a private link so they can create their own login. Type an email address above to turn this on.</span>
                    </span>
                </label>
            @endif
        </section>

        <div class="sticky bottom-0 -mx-1 px-1 py-4 bg-slate-50/90 dark:bg-slate-950/90 backdrop-blur border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2">
            <a href="{{ $editing ? route('admin.club_acc.members.show', ['clubSlug' => $club->slug, 'memberId' => $memberId]) : route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs transition">Cancel</a>
            <button type="submit" wire:loading.attr="disabled" wire:target="save" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-bold rounded-xl text-xs shadow-md transition cursor-pointer">
                {{ $editing ? 'Save Changes' : 'Create Member Record' }}
            </button>
        </div>
    </form>
</div>
