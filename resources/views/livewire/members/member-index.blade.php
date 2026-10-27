<div class="space-y-6">
    @include('livewire.members._notice')

    @include('livewire.members._tabs', ['active' => 'members'])

    <x-card>
        <div class="space-y-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Lodge Member Directory</h2>
                        <x-badge variant="info">Rule 153 roster</x-badge>
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        The lodge's members, masonic ranks, progressive offices and accounting ledger links.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 lg:shrink-0 lg:justify-end">
                    <x-button variant="secondary" size="sm" wire:click="exportCsv">Export CSV</x-button>

                    @if($canInvite)
                        <x-button variant="secondary" size="sm" href="{{ route('admin.club_acc.members.import', ['clubSlug' => $club->slug]) }}">Import CSV</x-button>
                    @endif

                    <x-button size="sm" href="{{ route('admin.club_acc.members.create', ['clubSlug' => $club->slug]) }}">Add a member</x-button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Total roster</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $totalMembers }} <span class="text-xs font-medium text-slate-500 dark:text-slate-400">brethren</span></p>
                </div>
                <div class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Active, subscribing</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $activeCount }} <span class="text-xs font-medium text-slate-500 dark:text-slate-400">members</span></p>
                </div>
                <div class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Lodge officers</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $officerCount }} <span class="text-xs font-medium text-slate-500 dark:text-slate-400">in office</span></p>
                </div>
                <div class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Past Masters</p>
                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $pmCount }} <span class="text-xs font-medium text-slate-500 dark:text-slate-400">W.Bro / R.Bro</span></p>
                </div>
            </div>

            <div class="grid grid-cols-1 items-end gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2 lg:grid-cols-5 dark:border-slate-800">
                <x-input
                    type="search"
                    label="Search"
                    name="member_search_query"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Name, email, Hermes ID, rank"
                />

                <x-select label="Status" wire:model.live="statusFilter">
                    <option value="all" @selected($statusFilter === 'all')>All statuses</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st->value }}" @selected($statusFilter === $st->value)>{{ $st->label() }}</option>
                    @endforeach
                </x-select>

                <x-select label="Office" wire:model.live="officeFilter">
                    <option value="all">All offices</option>
                    @foreach($offices as $of)
                        <option value="{{ $of->value }}">{{ $of->label() }}</option>
                    @endforeach
                </x-select>

                <x-select label="Masonic rank" wire:model.live="rankFilter">
                    <option value="all">All masonic ranks</option>
                    @foreach($ranks as $rankValue => $rankLabel)
                        <option value="{{ $rankValue }}">{{ $rankLabel }}</option>
                    @endforeach
                </x-select>

                <x-select label="Portal account" wire:model.live="accountFilter">
                    <option value="all">All accounts</option>
                    <option value="not_invited">Not invited</option>
                    <option value="invited">Invited</option>
                    <option value="has_account">Has account</option>
                </x-select>
            </div>
        </div>
    </x-card>

    @if($canInvite && count($selected) > 0)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm dark:border-blue-800/60 dark:bg-blue-950/40">
            <span class="font-medium text-blue-900 dark:text-blue-200">{{ count($selected) }} selected</span>
            <div class="flex items-center gap-2">
                <x-button size="sm" wire:click="inviteSelected" wire:loading.attr="disabled" wire:target="inviteSelected">Invite selected</x-button>
                <x-button variant="ghost" size="sm" wire:click="clearSelection">Clear</x-button>
            </div>
        </div>
    @endif

    <x-card padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-xs font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                        @if($canInvite)
                            <th class="w-8 py-3 pl-6 pr-0">
                                <input
                                    type="checkbox"
                                    aria-label="Select everyone on this page who can be invited"
                                    @disabled(empty($invitableIds))
                                    x-data
                                    x-effect="const ids = {{ \Illuminate\Support\Js::from($invitableIds) }}.map(String); const picked = $wire.selected.map(String).filter(id => ids.includes(id)); $el.checked = ids.length > 0 && picked.length === ids.length; $el.indeterminate = picked.length > 0 && picked.length < ids.length"
                                    x-on:change="$wire.$set('selected', $event.target.checked ? {{ \Illuminate\Support\Js::from($invitableIds) }} : [])"
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600"
                                />
                            </th>
                        @endif
                        <th class="px-6 py-3">
                            <button type="button" wire:click="sortBy('full_name')" class="cursor-pointer font-medium hover:text-slate-900 dark:hover:text-white">
                                Member name
                                @if($sortField === 'full_name') <span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('current_office')" class="cursor-pointer font-medium hover:text-slate-900 dark:hover:text-white">
                                Lodge office
                                @if($sortField === 'current_office') <span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </button>
                        </th>
                        <th class="px-4 py-3">Grand / provincial rank</th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('membership_status')" class="cursor-pointer font-medium hover:text-slate-900 dark:hover:text-white">
                                Status
                                @if($sortField === 'membership_status') <span aria-hidden="true">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </button>
                        </th>
                        <th class="px-4 py-3">Portal account</th>
                        <th class="px-4 py-3 text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($members as $m)
                        @php($account = $m->accountStatus($club))
                        <tr class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/70" wire:key="member-{{ $m->id }}">
                            @if($canInvite)
                                <td class="w-8 py-4 pl-6 pr-0">
                                    @if(in_array($m->id, $invitableIds, true))
                                        <input type="checkbox" value="{{ $m->id }}" wire:model.live="selected" aria-label="Select {{ $m->full_name }}" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600" />
                                    @endif
                                </td>
                            @endif
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-xs font-semibold text-white dark:bg-slate-700" aria-hidden="true">
                                        {{ strtoupper(substr($m->first_name, 0, 1) . substr($m->last_name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.club_acc.members.show', ['clubSlug' => $club->slug, 'memberId' => $m->id]) }}" class="block font-semibold text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400">
                                            {{ $m->full_name }}
                                        </a>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $m->email ?: 'No email on record' }}</span>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($m->active_offices as $office)
                                        <span class="inline-flex items-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $office->badgeClass() }}">{{ $office->label() }}</span>
                                    @endforeach
                                </div>
                            </td>

                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @if($m->grand_rank)
                                        <x-badge>GL: {{ $m->grand_rank }}</x-badge>
                                    @endif
                                    @if($m->provincial_rank)
                                        <x-badge>Prov: {{ $m->provincial_rank }}</x-badge>
                                    @endif
                                    @if(!$m->grand_rank && !$m->provincial_rank)
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-4 py-4">
                                <span class="inline-flex items-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $m->membership_status->badgeClass() }}">{{ $m->membership_status->label() }}</span>
                            </td>

                            <td class="px-4 py-4">
                                <span class="inline-flex items-center whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $account->badgeClass() }}">{{ $account->label() }}</span>
                                @if($account->isInvitePending() && $m->acct_invited_at)
                                    <span class="mt-1 block text-xs text-slate-400">Sent {{ \Illuminate\Support\Carbon::parse($m->acct_invited_at)->format('j M Y') }}</span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    @if($canInvite)
                                        @if($account->canInvite() && $m->email && $m->membership_status->isSubscribing())
                                            <x-button variant="secondary" size="sm" wire:click="inviteMember({{ $m->id }})" wire:loading.attr="disabled" wire:target="inviteMember({{ $m->id }})">Invite</x-button>
                                        @elseif($account->isInvitePending())
                                            <x-button variant="secondary" size="sm" wire:click="resendInvite({{ $m->id }})" wire:loading.attr="disabled" wire:target="resendInvite({{ $m->id }})">Resend</x-button>
                                            <x-button variant="ghost" size="sm" wire:click="revokeInvite({{ $m->id }})" wire:confirm="Withdraw the invitation for {{ $m->full_name }}? The link in their email will stop working.">Revoke</x-button>
                                        @endif
                                    @endif
                                    <x-button
                                        variant="secondary"
                                        size="sm"
                                        href="{{ route('admin.club_acc.members.edit', ['clubSlug' => $club->slug, 'memberId' => $m->id]) }}"
                                        title="Edit {{ $m->full_name }}"
                                        aria-label="Edit {{ $m->full_name }}"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.86 4.49a2.1 2.1 0 1 1 2.97 2.97L8.4 18.9l-3.9.93.93-3.9L16.86 4.49Z" />
                                        </svg>
                                    </x-button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center">
                                <p class="text-base font-semibold text-slate-900 dark:text-white">No members match these filters</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Try changing the search or filters, or add a member.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
            {{ $members->links() }}
        </div>
    </x-card>
</div>
