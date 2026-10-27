@php
    // The status enum's own classes include an animated pulse; the kit's badge keeps them in line with the rest of the app.
    $statusBadge = ['unpaid' => 'warning', 'partially_paid' => 'info', 'paid' => 'success', 'waived' => 'neutral', 'arrears_warning' => 'danger'];
    $billingYears = [2024, 2025, 2026, 2027];
@endphp

<div class="space-y-6">
    @include('livewire.members._tabs', ['active' => 'subscriptions'])

    <!-- Header -->
    <x-card>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Subscriptions</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">UGLE Rule 181 arrears audit and accounts receivable ledger integration</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-button wire:click="openBillingModal">Run annual billing</x-button>
                <x-button variant="secondary" wire:click="runRule181ArrearsAudit">Rule 181 arrears audit</x-button>
                <x-button variant="secondary" wire:click="openTierModal()">Manage fee tiers</x-button>
            </div>
        </div>
    </x-card>

    @if (session()->has('success'))
        <x-notice variant="success">{{ session('success') }}</x-notice>
    @endif
    @if (session()->has('error'))
        <x-notice variant="danger">{{ session('error') }}</x-notice>
    @endif

    <!-- Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-card padding="sm">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Total dues billed ({{ $selectedYear }})</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ $cs }}{{ number_format($totalBilled, 2) }}</p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Total annual subscription invoicing</p>
        </x-card>
        <x-card padding="sm">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Dues collected</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ $cs }}{{ number_format($totalCollected, 2) }}</p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Payments received and reconciled</p>
        </x-card>
        <x-card padding="sm">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Outstanding balance</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-amber-700 dark:text-amber-300">{{ $cs }}{{ number_format($totalOutstanding, 2) }}</p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Pending member payments</p>
        </x-card>
        <x-card padding="sm">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Rule 181 arrears</p>
                <x-button variant="ghost" size="sm" wire:click="$set('showArrearsModal', true)">Review</x-button>
            </div>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-rose-700 dark:text-rose-300">{{ $arrearsCount }}</p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Members past statutory grace period</p>
        </x-card>
    </div>

    <!-- Membership fee tiers -->
    <x-card>
        <x-slot:header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Membership fee tiers and pricing</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Active fee structures for annual subscription billing and member dues tiers.</p>
                </div>
                <x-button size="sm" wire:click="openTierModal()">Add fee tier</x-button>
            </div>
        </x-slot:header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @forelse($tiers as $t)
                <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl flex flex-col justify-between space-y-3">
                    <div class="space-y-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-900 dark:text-white text-sm">{{ $t->name }}</span>
                            <span class="font-semibold text-slate-900 dark:text-white text-base">{{ $cs }}{{ number_format($t->annual_amount, 2) }}</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t->description ?: 'Standard annual membership subscription rate.' }}</p>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-slate-800">
                        <x-badge :variant="$t->is_active ? 'success' : 'neutral'">{{ $t->is_active ? 'Active tier' : 'Inactive' }}</x-badge>
                        <x-button variant="ghost" size="sm" wire:click="openTierModal({{ $t->id }})">Edit tier</x-button>
                    </div>
                </div>
            @empty
                <div class="md:col-span-3 p-6 text-center text-slate-500 dark:text-slate-400 text-xs bg-slate-50 dark:bg-slate-800/50 rounded-2xl">
                    No custom fee tiers configured. Default {{ $cs }}160.00 rate will apply to annual billing runs.
                </div>
            @endforelse
        </div>
    </x-card>

    <!-- Search and filters -->
    <x-card padding="sm">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
            <x-input
                type="search"
                label="Search"
                name="sub_search_query"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false"
                wire:model.live.debounce.300ms="search"
                placeholder="Member name, email..."
            />
            <x-select label="Billing year" wire:model.live="selectedYear">
                @foreach($billingYears as $yr)
                    <option value="{{ $yr }}" @selected($selectedYear === $yr)>{{ $yr }} / {{ $yr + 1 }}</option>
                @endforeach
            </x-select>
            <x-select label="Status" wire:model.live="statusFilter">
                <option value="">All statuses</option>
                @foreach($statuses as $st)
                    <option value="{{ $st->value }}" @selected($statusFilter === $st->value)>{{ $st->label() }}</option>
                @endforeach
            </x-select>
        </div>
    </x-card>

    <!-- Subscriptions table -->
    <x-card padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-xs font-medium text-slate-500 dark:text-slate-400">
                        <th class="py-3.5 px-4">Member and rank</th>
                        <th class="py-3.5 px-4">Subscription tier</th>
                        <th class="py-3.5 px-4">Invoice ref</th>
                        <th class="py-3.5 px-4 text-right">Amount due</th>
                        <th class="py-3.5 px-4 text-right">Amount paid</th>
                        <th class="py-3.5 px-4 text-right">Balance</th>
                        <th class="py-3.5 px-4">Due date</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs text-slate-700 dark:text-slate-200">
                    @forelse ($subscriptions as $sub)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
                            <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                                <a href="{{ route('admin.club_acc.members.show', ['clubSlug' => $club->slug, 'memberId' => $sub->member->id]) }}" class="hover:underline hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $sub->member->formatted_rank_name }}
                                </a>
                                @if($sub->member->current_office && $sub->member->current_office->value !== 'member')
                                    <span class="block text-xs font-medium text-slate-500 dark:text-slate-400">{{ $sub->member->current_office->label() }}</span>
                                @endif
                            </td>

                            <td class="py-3 px-4 font-medium text-slate-800 dark:text-slate-100">
                                {{ $sub->tier ? $sub->tier->name : ($sub->member->annual_dues_override ? 'Custom dues' : 'Standard dues') }}
                            </td>

                            <td class="py-3 px-4 font-mono text-xs text-slate-600 dark:text-slate-300">
                                {{ $sub->invoice_reference ?: '—' }}
                                @if($sub->member->customerAccount)
                                    <x-badge variant="success" class="font-sans" title="Linked to accounting contact">Linked</x-badge>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-right font-semibold text-slate-900 dark:text-white">
                                {{ $cs }}{{ number_format($sub->amount_due, 2) }}
                            </td>

                            <td class="py-3 px-4 text-right font-semibold text-emerald-700 dark:text-emerald-300">
                                {{ $cs }}{{ number_format($sub->amount_paid, 2) }}
                            </td>

                            <td class="py-3 px-4 text-right font-semibold {{ $sub->balance_due > 0 ? 'text-amber-700 dark:text-amber-300' : 'text-slate-500 dark:text-slate-400' }}">
                                {{ $cs }}{{ number_format($sub->balance_due, 2) }}
                            </td>

                            <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                                {{ $sub->due_date ? $sub->due_date->format('d M Y') : '—' }}
                            </td>

                            <td class="py-3 px-4">
                                <x-badge :variant="$statusBadge[$sub->status->value] ?? 'neutral'">{{ $sub->status->label() }}</x-badge>
                            </td>

                            <td class="py-3 px-4 text-right whitespace-nowrap space-x-1">
                                @if($sub->status->isOutstanding())
                                    <x-button size="sm" wire:click="openPaymentModal({{ $sub->id }})">Record payment</x-button>
                                    <x-button
                                        variant="secondary"
                                        size="sm"
                                        wire:click="waiveDues({{ $sub->id }})"
                                        wire:confirm="Waive subscription dues for {{ $sub->member->full_name }}?"
                                        title="Waive dues"
                                    >Waive</x-button>
                                @else
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Settled</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-500 dark:text-slate-400 text-xs">
                                No subscription records found for {{ $selectedYear }}. Choose <strong class="font-semibold text-slate-700 dark:text-slate-200">Run annual billing</strong> to generate annual invoices.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($subscriptions->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $subscriptions->links() }}
            </div>
        @endif
    </x-card>

    <!-- Annual billing run -->
    @if ($showBillingModal)
    <x-modal :show="$showBillingModal" title="Run annual subscription billing" close="$set('showBillingModal', false)">
        <div class="space-y-4">
            <x-notice variant="warning">This batch action will generate annual dues invoices for all active lodge members who do not yet have an invoice for the selected billing year.</x-notice>

            <form id="billing-run-form" wire:submit.prevent="runAnnualBilling" class="space-y-4">
                <x-select label="Billing year" required wire:model="billing_year">
                    @foreach($billingYears as $yr)
                        <option value="{{ $yr }}" @selected($billing_year === $yr)>{{ $yr }} / {{ $yr + 1 }}</option>
                    @endforeach
                </x-select>

                <x-input type="date" label="Due date" required wire:model="billing_due_date" />
            </form>
        </div>

        <x-slot:footer>
            <x-button variant="secondary" wire:click="$set('showBillingModal', false)">Cancel</x-button>
            <x-button type="submit" form="billing-run-form">Generate annual invoices</x-button>
        </x-slot:footer>
    </x-modal>
    @endif

    <!-- Fee tier manager -->
    @if ($showTierModal)
    <x-modal :show="$showTierModal" title="Subscription fee tiers" size="lg" close="$set('showTierModal', false)">
        <div class="space-y-2">
            <h3 class="text-xs font-semibold text-slate-800 dark:text-slate-100">Active lodge fee structures</h3>
            @forelse($tiers as $t)
                <div wire:key="tier-{{ $t->id }}" class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center justify-between gap-3 text-xs">
                    <div>
                        <span class="font-semibold text-slate-900 dark:text-white block">{{ $t->name }}</span>
                        <span class="text-slate-500 dark:text-slate-400 text-xs">{{ $t->description ?: 'Standard fee structure' }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="font-semibold text-slate-900 dark:text-white text-sm">{{ $cs }}{{ number_format($t->annual_amount, 2) }}</span>
                        <x-button variant="ghost" size="sm" wire:click="openTierModal({{ $t->id }})">Edit</x-button>
                    </div>
                </div>
            @empty
                <div class="p-3 bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-center text-xs rounded-xl">No custom tiers configured. Default {{ $cs }}160.00 will apply.</div>
            @endforelse
        </div>

        <form id="fee-tier-form" wire:submit.prevent="saveTier" class="space-y-4 mt-4 pt-4 border-t border-slate-200 dark:border-slate-800">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $tierId ? 'Edit fee tier' : 'Add a new fee tier' }}</h3>

            <x-input label="Tier name" required wire:model="tier_name" placeholder="e.g. Full member, country member" />
            <x-input type="number" step="0.01" label="Annual amount ({{ $cs }})" required wire:model="tier_amount" />
            <x-input label="Description" wire:model="tier_description" placeholder="Optional details..." />

            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200 cursor-pointer">
                <input type="checkbox" wire:model="tier_active" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950" />
                Active tier
            </label>
        </form>

        <x-slot:footer>
            <x-button variant="secondary" wire:click="$set('showTierModal', false)">Close</x-button>
            <x-button type="submit" form="fee-tier-form">Save tier</x-button>
        </x-slot:footer>
    </x-modal>
    @endif

    <!-- Payment recording -->
    @if ($showPaymentModal)
    <x-modal :show="$showPaymentModal" title="Record dues payment" close="$set('showPaymentModal', false)">
        <form id="dues-payment-form" wire:submit.prevent="recordPayment" class="space-y-4">
            <x-input type="number" step="0.01" label="Payment amount ({{ $cs }})" required wire:model="payment_amount" />
            <x-input label="Payment reference (BACS, cheque or cash)" wire:model="payment_reference" placeholder="e.g. BACS-20260916" />
            <x-textarea label="Notes" rows="2" wire:model="payment_notes" placeholder="Optional notes..." />
        </form>

        <x-slot:footer>
            <x-button variant="secondary" wire:click="$set('showPaymentModal', false)">Cancel</x-button>
            <x-button type="submit" form="dues-payment-form">Record payment</x-button>
        </x-slot:footer>
    </x-modal>
    @endif

    <!-- Confidential Rule 181 arrears review -->
    @if ($showArrearsModal)
    <x-modal :show="$showArrearsModal" title="UGLE Rule 181 confidential arrears review" subtitle="Confidential list for the Almoner and Secretary before statutory exclusion proceedings." size="lg" close="$set('showArrearsModal', false)">
        <div class="space-y-4">
            <x-notice variant="danger" title="UGLE Rule 181 procedure">
                Members overdue past the 90-day grace period must be reviewed by the Almoner to confirm if brotherly relief or hardship support is required before formal exclusion warnings are dispatched.
            </x-notice>

            <div class="space-y-3 text-xs">
                @forelse($arrearsSubscriptions as $arrSub)
                    <div class="p-4 border border-slate-200 dark:border-slate-800 rounded-2xl flex items-center justify-between gap-3">
                        <div>
                            <span class="font-semibold text-slate-900 dark:text-white text-sm block">{{ $arrSub->member->formatted_rank_name }}</span>
                            <span class="text-slate-500 dark:text-slate-400 text-xs block">Due: {{ $arrSub->due_date ? $arrSub->due_date->format('d M Y') : '—' }} (overdue past 90 days)</span>
                            <span class="text-slate-600 dark:text-slate-300 font-medium text-xs">Invoice ref: {{ $arrSub->invoice_reference }}</span>
                        </div>
                        <div class="text-right space-y-1">
                            <span class="text-base font-semibold text-rose-700 dark:text-rose-300 block">{{ $cs }}{{ number_format($arrSub->balance_due, 2) }}</span>
                            <x-button size="sm" wire:click="openPaymentModal({{ $arrSub->id }})">Settle dues</x-button>
                        </div>
                    </div>
                @empty
                    <x-notice variant="success">No members are currently in Rule 181 statutory arrears.</x-notice>
                @endforelse
            </div>
        </div>

        <x-slot:footer>
            <x-button variant="secondary" wire:click="$set('showArrearsModal', false)">Close review</x-button>
        </x-slot:footer>
    </x-modal>
    @endif
</div>
