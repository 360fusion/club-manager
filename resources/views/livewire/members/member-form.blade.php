<div class="max-w-5xl space-y-6">
    <div class="space-y-1">
        <a href="{{ $editing ? route('admin.club_acc.members.show', ['clubSlug' => $club->slug, 'memberId' => $memberId]) : route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">&larr; {{ $editing ? 'Back to the member' : 'Back to the member directory' }}</a>
        <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $editing ? 'Edit Member Record' : 'Add New Lodge Member' }}</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $editing ? 'Update masonic ranks, office assignments and contact details.' : 'Register a new brother onto the Lodge Rule 153 roster.' }}</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        <x-card>
            <h3 id="sec-name" class="mb-4 text-base font-semibold text-slate-900 dark:text-white">Name</h3>
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-input label="First name" required wire:model="first_name" />
                    <x-input label="Middle names" wire:model="middle_names" placeholder="David Arthur" />
                    <x-input label="Surname" required wire:model="last_name" />
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-input label="Preferred name" wire:model="preferred_name" placeholder="Dave" />
                </div>
            </div>
        </x-card>

        <x-card>
            <h3 id="sec-contact" class="mb-4 text-base font-semibold text-slate-900 dark:text-white">Contact and home address</h3>
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-input label="Email address" type="email" wire:model="email" placeholder="brother@example.org" />
                    <x-input label="Phone number" wire:model="phone" placeholder="07123 456789" />
                    <x-input label="Address line 1" wire:model="address_line_1" placeholder="Building name, house number and street" />
                    <x-input label="Address line 2" wire:model="address_line_2" placeholder="Flat, suite, unit, etc." />
                </div>
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <x-input label="Town or city" wire:model="city" placeholder="Oxford" />
                    <x-input label="County or region" wire:model="county" placeholder="Oxfordshire" />
                    <x-input label="Postcode" wire:model="postcode" placeholder="OX1 2JD" class="uppercase" />
                    <x-input label="Country" wire:model="country" placeholder="United Kingdom" />
                </div>
            </div>
        </x-card>

        <x-card>
            <h3 id="sec-masonic" class="mb-4 text-base font-semibold text-slate-900 dark:text-white">Masonic ranks and office</h3>
            <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-select label="Masonic rank" required wire:model="masonic_rank">
                        @foreach($ranks as $rankValue => $rankLabel)
                            <option value="{{ $rankValue }}" @selected($masonic_rank === $rankValue)>{{ $rankLabel }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Grand rank" wire:model="grand_rank">
                        <option value="">None</option>
                        @foreach($grandRanks as $rank)
                            <option value="{{ $rank['value'] }}" @selected($grand_rank === $rank['value'])>{{ $rank['label'] }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Provincial rank" wire:model="provincial_rank">
                        <option value="">None</option>
                        @foreach($provincialRanks as $rank)
                            <option value="{{ $rank['value'] }}" @selected($provincial_rank === $rank['value'])>{{ $rank['label'] }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-input label="Hermes / GL member ID" wire:model="grand_lodge_number" placeholder="1482092" class="font-mono" />
                    <x-select label="Current office" required wire:model="current_office">
                        @foreach($offices as $of)
                            <option value="{{ $of->value }}" @selected($current_office === $of->value)>{{ $of->label() }}</option>
                        @endforeach
                    </x-select>
                    <x-select label="Membership status" required wire:model="membership_status">
                        @foreach($statuses as $st)
                            <option value="{{ $st->value }}" @selected($membership_status === $st->value)>{{ $st->label() }}</option>
                        @endforeach
                    </x-select>
                </div>
            </div>
        </x-card>

        <x-card>
            <h3 id="sec-dates" class="mb-4 text-base font-semibold text-slate-900 dark:text-white">Key dates</h3>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-input label="Date of initiation" type="date" wire:model="date_of_initiation" />
                <x-input label="Date of passing" type="date" wire:model="date_of_passing" />
                <x-input label="Date of raising" type="date" wire:model="date_of_raising" />
                <x-input label="Date joined the lodge" type="date" wire:model="date_of_joining" />
            </div>
        </x-card>

        <x-card>
            <h3 id="sec-more" class="mb-4 text-base font-semibold text-slate-900 dark:text-white">Notes and accounting</h3>
            <div class="space-y-4">
                <x-textarea label="Notes" rows="3" wire:model="notes" />

                @if($accountingContacts->isNotEmpty())
                    <x-select label="Accounting ledger contact" wire:model="customer_account_id">
                        <option value="">No ledger account linked</option>
                        @foreach($accountingContacts as $ac)
                            <option value="{{ $ac->id }}" @selected($customer_account_id === $ac->id)>{{ $ac->full_name }} ({{ $ac->email ?: 'No email' }})</option>
                        @endforeach
                    </x-select>
                @endif

                @if(! $editing && $canInvite)
                    <label
                        x-data
                        :class="($wire.email ?? '').trim() === '' ? 'opacity-60' : ''"
                        class="flex items-start gap-2.5 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm dark:border-blue-800/60 dark:bg-blue-950/40"
                    >
                        <input type="checkbox" wire:model="sendInvitation" :disabled="($wire.email ?? '').trim() === ''" class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600" />
                        <span>
                            <span class="block font-medium text-slate-900 dark:text-white">Send an invitation to set up an online account</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Emails a private link so they can create their own login. Type an email address above to turn this on.</span>
                        </span>
                    </label>
                @endif
            </div>
        </x-card>

        <div class="sticky bottom-0 -mx-1 flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50/90 px-1 py-4 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
            <x-button variant="secondary" href="{{ $editing ? route('admin.club_acc.members.show', ['clubSlug' => $club->slug, 'memberId' => $memberId]) : route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}">Cancel</x-button>
            <x-button type="submit" wire:loading.attr="disabled" wire:target="save">
                {{ $editing ? 'Save changes' : 'Create member record' }}
            </x-button>
        </div>
    </form>
</div>
