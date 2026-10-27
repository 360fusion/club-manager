<div class="space-y-6">
    <!-- Header -->
    <x-card>
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Bank and payment accounts</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage bank accounts and payment providers (high street banks, Stripe, PayPal, SumUp).</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-button variant="secondary" size="sm" href="{{ route('banking.bank-reconciliation-workspace', $clubSlug) }}">Reconciliation workspace</x-button>
                <x-button size="sm" wire:click="openCreateModal">Add a bank or payment account</x-button>
            </div>
        </div>
    </x-card>

    <!-- Flash Notifications -->
    @if (session()->has('success'))
        <x-notice variant="success">{{ session('success') }}</x-notice>
    @endif

    <!-- Bank Accounts Card Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($bankAccounts as $acc)
            <x-card class="flex flex-col justify-between space-y-5">
                <div>
                    <!-- Account Top Row: Bank Name, Status Badge -->
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold leading-snug text-slate-900 dark:text-white">{{ $acc->bank_name }}</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $acc->account_name }}</p>
                        </div>

                        <x-badge :variant="$acc->is_active ? 'success' : 'neutral'">{{ $acc->is_active ? 'Active' : 'Inactive' }}</x-badge>
                    </div>

                    <!-- Details Row -->
                    <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Account type</span>
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ $acc->formatted_account_type }}</span>
                        </div>
                        <div>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Nominal ledger</span>
                            <span class="font-mono font-medium text-blue-600 dark:text-blue-400">Code {{ $acc->account?->code ?? 'N/A' }}</span>
                        </div>
                        @if ($acc->sort_code || $acc->account_number)
                            <div class="col-span-2 pt-1 text-xs text-slate-500 dark:text-slate-400 font-mono">
                                <span>Sort: {{ $acc->sort_code ?: 'N/A' }}</span> | <span>Acc: {{ $acc->account_number ?: 'N/A' }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Balances Summary -->
                    <div class="mt-5 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-slate-500 dark:text-slate-400">Statement balance</span>
                            <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $cs }}{{ number_format($acc->statement_balance, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-slate-500 dark:text-slate-400">Ledger balance (code {{ $acc->account?->code ?? '1000' }})</span>
                            <span class="font-semibold text-blue-700 dark:text-blue-300">{{ $cs }}{{ number_format($acc->ledger_balance, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Card Footer Actions -->
                <div class="pt-4 flex items-center justify-between gap-3 border-t border-slate-100 dark:border-slate-800">
                    <x-button size="sm" :variant="$acc->unreconciled_count > 0 ? 'primary' : 'secondary'" href="{{ route('banking.bank-reconciliation-workspace', $clubSlug) }}">
                        Reconcile ({{ $acc->unreconciled_count }})
                    </x-button>

                    <x-button variant="ghost" size="sm" wire:click="toggleAccountActive({{ $acc->id }})">
                        {{ $acc->is_active ? 'Deactivate' : 'Activate' }}
                    </x-button>
                </div>
            </x-card>
        @endforeach
    </div>

    <!-- Create Bank Account Modal -->
    @if ($showCreateModal)
    <x-modal :show="$showCreateModal" title="Add a bank or payment account" subtitle="A high street bank, Stripe, PayPal or SumUp account." size="lg" close="$set('showCreateModal', false)">
        <form id="bank-account-form" wire:submit.prevent="saveBankAccount" class="space-y-4">
            <x-input label="Institution or provider name" required wire:model="bank_name" placeholder="e.g. Barclays, Stripe, PayPal, SumUp, Lloyds" />

            <x-input label="Account label" required wire:model="account_name" placeholder="e.g. Main operating account, Online card payments" />

            <div class="grid grid-cols-2 gap-3">
                <x-select label="Account type" required wire:model="account_type">
                    <option value="current">High street current</option>
                    <option value="savings">Savings account</option>
                    <option value="payment_gateway">Payment gateway (Stripe/PayPal)</option>
                    <option value="merchant">Card reader / merchant (SumUp)</option>
                    <option value="cash">Petty cash / cash register</option>
                </x-select>
                <x-input label="Currency" required wire:model="currency" readonly class="uppercase" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <x-input label="Account number" wire:model="account_number" placeholder="e.g. 12345678" />
                <x-input label="Sort code" wire:model="sort_code" placeholder="e.g. 20-00-00" />
            </div>

            <x-input :label="'Opening balance ('.$cs.')'" type="number" step="0.01" wire:model="opening_balance" />

            <x-notice variant="info">
                Saving will automatically create and link a dedicated nominal asset account on the chart of accounts (e.g. nominal code 1010, 1020, etc.).
            </x-notice>
        </form>

        <x-slot:footer>
            <x-button variant="secondary" wire:click="$set('showCreateModal', false)">Cancel</x-button>
            <x-button type="submit" form="bank-account-form">Save and link account</x-button>
        </x-slot:footer>
    </x-modal>
    @endif
</div>
