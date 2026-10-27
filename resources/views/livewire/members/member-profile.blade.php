<div class="space-y-6">
    @include('livewire.members._notice')

    <x-card>
        <div class="space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 flex-1 items-start gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-xl font-semibold text-white dark:bg-slate-700" aria-hidden="true">
                        {{ strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)) }}
                    </div>
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $member->membership_status->badgeClass() }}">{{ $member->membership_status->label() }}</span>
                            @foreach($member->active_offices as $office)
                                <span class="inline-flex items-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $office->badgeClass() }}">{{ $office->label() }}</span>
                            @endforeach
                        </div>
                        <h2 class="truncate text-xl font-bold text-slate-900 dark:text-white" title="{{ $member->formatted_rank_name }}">
                            {{ $member->formatted_rank_name }}
                        </h2>
                        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                            <span>Hermes/GL ID: <strong>{{ $member->grand_lodge_number ?: 'Unspecified' }}</strong></span>
                            <span>Joined: <strong>{{ $member->date_of_joining ? $member->date_of_joining->format('jS F Y') : 'N/A' }}</strong></span>
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 flex-col gap-2 sm:items-end">
                    <div class="flex items-center justify-end gap-2">
                        <x-button variant="secondary" size="sm" href="{{ route('admin.club_acc.members.index', ['clubSlug' => $club->slug]) }}">&larr; Back to directory</x-button>
                        <x-button size="sm" wire:click="toggleEdit">{{ $isEditing ? 'Cancel edit' : 'Edit profile' }}</x-button>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <x-button
                            variant="secondary"
                            size="sm"
                            wire:click="archiveMember"
                            wire:confirm="Mark {{ $member->formatted_rank_name }} as Resigned / Archived?"
                            title="Archive or resign the member"
                        >Archive member</x-button>
                        <x-button
                            variant="danger"
                            size="sm"
                            wire:click="deleteMember"
                            wire:confirm="Are you sure you want to remove {{ $member->formatted_rank_name }} from the roster? This action cannot be undone."
                            title="Delete the member from the roster"
                        >Delete member</x-button>
                    </div>
                </div>
            </div>

            <div class="max-w-full overflow-x-auto" role="group" aria-label="Profile sections">
                <div class="flex w-fit items-center gap-1 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 dark:border-slate-700/60 dark:bg-slate-800/80">
                    @foreach(['details' => 'Core details and masonic ranks', 'finances' => 'Subscription Balance Snapshot'] as $tabKey => $tabLabel)
                        <button
                            type="button"
                            wire:click="$set('activeTab', '{{ $tabKey }}')"
                            aria-pressed="{{ $activeTab === $tabKey ? 'true' : 'false' }}"
                            @class([
                                'inline-flex cursor-pointer items-center whitespace-nowrap rounded-xl px-4 py-2 text-xs font-semibold transition-colors',
                                'bg-slate-900 text-white shadow-sm dark:bg-slate-700' => $activeTab === $tabKey,
                                'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white' => $activeTab !== $tabKey,
                            ])
                        >{{ $tabLabel }}</button>
                    @endforeach
                </div>
            </div>
        </div>
    </x-card>

    @if($isEditing)
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Update member information</h3>
                    <span class="text-xs text-slate-400">* Required fields</span>
                </div>
            </x-slot:header>

            <form wire:submit="updateProfile" class="space-y-6">
                <div class="space-y-3">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Name</h4>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4">
                        <x-input label="First name" required wire:model="first_name" />
                        <x-input label="Middle names" wire:model="middle_names" placeholder="e.g. David" />
                        <x-input label="Surname" required wire:model="last_name" />
                        <x-input label="Preferred name" wire:model="preferred_name" placeholder="e.g. Dave" />
                    </div>
                </div>

                <div class="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Contact and home address</h4>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input label="Email address" type="email" wire:model="email" placeholder="brother@example.org" />
                        <x-input label="Telephone or mobile" wire:model="phone" placeholder="07123 456789" />
                        <x-input label="Address line 1" wire:model="address_line_1" placeholder="Building name, house number and street" />
                        <x-input label="Address line 2" wire:model="address_line_2" placeholder="Flat, suite, unit, etc. (optional)" />
                    </div>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <x-input label="Town or city" wire:model="city" placeholder="e.g. Oxford" />
                        <x-input label="County or region" wire:model="county" placeholder="e.g. Oxfordshire" />
                        <x-input label="Postcode" wire:model="postcode" placeholder="e.g. OX1 2JD" class="uppercase" />
                        <x-input label="Country" wire:model="country" placeholder="e.g. United Kingdom" />
                    </div>
                </div>

                <div class="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Masonic ranks and member status</h4>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
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
                        <x-select label="Membership status" wire:model="membership_status">
                            @foreach($statuses as $st)
                                <option value="{{ $st->value }}" @selected($membership_status === $st->value)>{{ $st->label() }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <div class="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Lodge office and registration</h4>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-input label="Hermes / GL member ID" wire:model="grand_lodge_number" placeholder="e.g. 7819203" class="font-mono" />
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="block text-xs font-medium text-slate-700 dark:text-slate-300">Current office</span>
                                <span class="text-xs text-slate-400">Managed in the officer rosters</span>
                            </div>
                            <div class="flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-800 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-100">
                                <span class="truncate">{{ $member->current_office?->label() ?: 'Member / Brethren' }}</span>
                                <x-button size="sm" class="shrink-0" href="{{ route('admin.officers.index', ['clubSlug' => $club->slug]) }}">Manage roster</x-button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Masonic progress and key dates</h4>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <x-input label="Initiated" type="date" wire:model="date_of_initiation" />
                        <x-input label="Passed" type="date" wire:model="date_of_passing" />
                        <x-input label="Raised" type="date" wire:model="date_of_raising" />
                        <x-input label="Joined the lodge" type="date" wire:model="date_of_joining" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <x-button variant="secondary" wire:click="toggleEdit">Cancel</x-button>
                    <x-button type="submit">Save profile</x-button>
                </div>
            </form>
        </x-card>
    @endif

    @if(!$isEditing && $activeTab === 'details')
        <div class="grid grid-cols-1 gap-6 text-sm lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-card>
                    <x-slot:header>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Personal and contact details</h3>
                    </x-slot:header>

                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Email address</dt>
                            <dd class="mt-0.5 font-medium text-slate-900 dark:text-white">{{ $member->email ?: 'Not provided' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Telephone or mobile</dt>
                            <dd class="mt-0.5 font-medium text-slate-900 dark:text-white">{{ $member->phone ?: 'Not provided' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-slate-500 dark:text-slate-400">Home address</dt>
                            <dd>
                                @if($member->formatted_address)
                                    <span class="mt-0.5 block font-medium text-slate-900 dark:text-white">{{ $member->formatted_address }}</span>
                                    <div class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 text-xs sm:grid-cols-3 dark:border-slate-800">
                                        @foreach(['address_line_1' => 'Address line 1', 'address_line_2' => 'Address line 2', 'city' => 'Town or city', 'county' => 'County or region', 'postcode' => 'Postcode', 'country' => 'Country'] as $field => $fieldLabel)
                                            @if($member->{$field})
                                                <div>
                                                    <span class="block text-slate-400">{{ $fieldLabel }}</span>
                                                    <span class="font-medium text-slate-800 dark:text-slate-100 {{ $field === 'postcode' ? 'uppercase' : '' }}">{{ $member->{$field} }}</span>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <span class="mt-0.5 block italic text-slate-400">No address registered</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </x-card>

                <x-card>
                    <x-slot:header>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Masonic progress and key dates</h3>
                    </x-slot:header>

                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        @foreach(['Initiated' => $member->date_of_initiation, 'Passed' => $member->date_of_passing, 'Raised' => $member->date_of_raising, 'Joined the lodge' => $member->date_of_joining] as $dateLabel => $date)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50">
                                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $dateLabel }}</dt>
                                <dd class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $date ? $date->format('d M Y') : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card>
                    <x-slot:header>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Masonic ranks</h3>
                    </x-slot:header>

                    <dl class="space-y-2">
                        @foreach(['Craft rank' => $member->masonic_rank, 'Grand rank' => $member->grand_rank ?: 'None', 'Provincial rank' => $member->provincial_rank ?: 'None'] as $rankLabel => $rankValue)
                            <div class="flex items-center justify-between rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800/50">
                                <dt class="text-slate-500 dark:text-slate-400">{{ $rankLabel }}</dt>
                                <dd class="font-semibold text-slate-900 dark:text-white">{{ $rankValue }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-card>

                <x-card>
                    <x-slot:header>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Offices held</h3>
                    </x-slot:header>

                    <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                        <div>
                            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Current office</span>
                            <span class="mt-0.5 block text-base font-semibold text-slate-900 dark:text-white">{{ $member->current_office->label() }}</span>
                        </div>
                        <span class="inline-flex items-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $member->current_office->badgeClass() }}">Active office</span>
                    </div>
                </x-card>

                <x-card>
                    <x-slot:header>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Portal Account</h3>
                    </x-slot:header>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <span class="inline-flex items-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $account->badgeClass() }}">{{ $account->label() }}</span>
                            @if($member->user)
                                <span class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $member->user->email }}</span>
                            @endif
                        </div>

                        <div class="space-y-0.5 text-xs text-slate-500 dark:text-slate-400">
                            @if($account === \App\Domains\ClubAccounting\Enums\MemberAccountStatus::NotInvited)
                                <p>{{ $member->email ? 'Not yet invited to set up an online account.' : 'Add an email address to invite this member.' }}</p>
                            @elseif($account->isInvitePending() && $accountPivot?->invited_at)
                                <p>Invited {{ \Illuminate\Support\Carbon::parse($accountPivot->invited_at)->format('j M Y') }}@if($accountInviter) by {{ $accountInviter->name }}@endif.</p>
                                @if($accountExpiresAt)
                                    <p>{{ $account === \App\Domains\ClubAccounting\Enums\MemberAccountStatus::InviteExpired ? 'The link expired on' : 'The link works until' }} {{ $accountExpiresAt->format('j M Y') }}.</p>
                                @endif
                                @if($accountPivot->invitation_reminded_at)
                                    <p>Reminder sent {{ \Illuminate\Support\Carbon::parse($accountPivot->invitation_reminded_at)->format('j M Y') }}.</p>
                                @endif
                            @elseif($account === \App\Domains\ClubAccounting\Enums\MemberAccountStatus::AwaitingApproval)
                                <p>This person asked to join and is waiting for approval on the Users page.</p>
                            @elseif($account === \App\Domains\ClubAccounting\Enums\MemberAccountStatus::HasAccount)
                                <p>@if($accountPivot?->invitation_accepted_at)Accepted {{ \Illuminate\Support\Carbon::parse($accountPivot->invitation_accepted_at)->format('j M Y') }}.@else Signed up with a verified email.@endif</p>
                            @elseif($account === \App\Domains\ClubAccounting\Enums\MemberAccountStatus::Deactivated)
                                <p>Portal access has been switched off. Inviting again will restore it.</p>
                            @endif
                        </div>

                        @if($canInvite)
                            <div class="flex flex-wrap items-center gap-2 pt-1">
                                @if($account->canInvite() && $member->email && $member->membership_status->isSubscribing())
                                    <x-button size="sm" wire:click="inviteToPortal" wire:loading.attr="disabled">Invite to portal</x-button>
                                @elseif($account->isInvitePending())
                                    <x-button size="sm" wire:click="resendPortalInvite" wire:loading.attr="disabled">Resend invitation</x-button>
                                    <x-button variant="secondary" size="sm" wire:click="revokePortalInvite" wire:confirm="Withdraw this invitation? The link in their email will stop working.">Revoke</x-button>
                                @endif
                                @if($member->user_id)
                                    <a href="{{ route('admin.users.show', ['clubSlug' => $club->slug, 'userId' => $member->user_id]) }}" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">Manage on Users page</a>
                                @endif
                            </div>
                        @endif
                    </div>
                </x-card>

                <x-card>
                    <x-slot:header>
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Accounting ledger</h3>
                    </x-slot:header>

                    @if($member->customerAccount)
                        <div class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-3.5 dark:border-slate-800 dark:bg-slate-800/50">
                            <span class="block text-xs font-medium text-slate-500 dark:text-slate-400">Linked ledger contact</span>
                            <span class="block font-semibold text-slate-900 dark:text-white">{{ $member->customerAccount->full_name }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $member->customerAccount->email }}</span>
                        </div>
                    @else
                        <p class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-center text-xs text-slate-400 dark:border-slate-800 dark:bg-slate-800/50">No accounting customer ledger account linked.</p>
                    @endif
                </x-card>
            </div>
        </div>
    @endif

    @if(!$isEditing && $activeTab === 'finances')
        <x-card>
            <x-slot:header>
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Annual subscription and dues ledger</h3>
                    <a href="{{ route('admin.club_acc.subscriptions.index', ['clubSlug' => $club->slug]) }}" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Manage all dues &rarr;</a>
                </div>
            </x-slot:header>

            <div class="space-y-6 text-sm">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Assigned fee tier</span>
                        <span class="block text-base font-semibold text-slate-900 dark:text-white">
                            {{ $member->subscriptionTier ? $member->subscriptionTier->name : 'Standard lodge dues' }}
                        </span>
                        @if($member->subscriptionTier)
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $cs }}{{ number_format($member->subscriptionTier->annual_amount, 2) }} / year</span>
                        @endif
                    </div>

                    <div class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Annual dues override</span>
                        <span class="block text-base font-semibold text-slate-900 dark:text-white">
                            {{ $member->annual_dues_override ? $cs . number_format($member->annual_dues_override, 2) : 'None (standard rate)' }}
                        </span>
                    </div>

                    @php
                        $latestSub = $member->subscriptions->sortByDesc('billing_year')->first();
                        $isClear = !$latestSub || !$latestSub->status->isOutstanding();
                    @endphp
                    <div class="space-y-1 rounded-xl border p-4 {{ $isClear ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-100' : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-100' }}">
                        <span class="block text-xs font-medium">Subscription status</span>
                        <span class="block text-base font-semibold">
                            {{ $latestSub ? $latestSub->status->label() : 'No dues recorded' }}
                        </span>
                    </div>
                </div>

                <div class="space-y-3">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Subscription invoices and payment history</h4>
                    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                        <table class="w-full border-collapse text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 bg-slate-50 text-xs font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                                    <th class="px-3 py-2.5">Billing year</th>
                                    <th class="px-3 py-2.5">Invoice ref</th>
                                    <th class="px-3 py-2.5 text-right">Amount due</th>
                                    <th class="px-3 py-2.5 text-right">Amount paid</th>
                                    <th class="px-3 py-2.5">Due date</th>
                                    <th class="px-3 py-2.5">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse($member->subscriptions->sortByDesc('billing_year') as $sub)
                                    <tr wire:key="sub-{{ $sub->id }}">
                                        <td class="px-3 py-2.5 font-medium text-slate-900 dark:text-white">{{ $sub->billing_year }} / {{ $sub->billing_year + 1 }}</td>
                                        <td class="px-3 py-2.5 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $sub->invoice_reference ?: '—' }}</td>
                                        <td class="px-3 py-2.5 text-right">{{ $cs }}{{ number_format($sub->amount_due, 2) }}</td>
                                        <td class="px-3 py-2.5 text-right font-medium text-slate-900 dark:text-white">{{ $cs }}{{ number_format($sub->amount_paid, 2) }}</td>
                                        <td class="px-3 py-2.5 text-slate-600 dark:text-slate-300">{{ $sub->due_date ? $sub->due_date->format('d M Y') : '—' }}</td>
                                        <td class="px-3 py-2.5">
                                            <span class="inline-flex items-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $sub->status->badgeClasses() }}">{{ $sub->status->label() }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-4 text-center italic text-slate-400">No subscription invoices recorded for this member.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </x-card>
    @endif
</div>
