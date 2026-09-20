<div class="space-y-6">
    <!-- Header Banner -->
    <div class="p-6 bg-slate-900 rounded-3xl text-white shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2.5 bg-sky-500/20 text-sky-400 rounded-2xl border border-sky-500/30 text-xl">🏦</span>
                <div>
                    <h1 class="text-2xl font-black tracking-tight">Bank &amp; Payment Accounts Hub</h1>
                    <p class="text-xs text-slate-400 mt-1 font-medium">Multi-Bank &amp; Payment Gateway Management (High Street Banks, Stripe, PayPal, SumUp)</p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('banking.bank-reconciliation-workspace', $clubSlug) }}" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl border border-slate-700 transition-all flex items-center gap-1.5 shadow-sm">
                <span>⚡</span>
                <span>Reconciliation Workspace</span>
            </a>
            <button wire:click="openCreateModal" class="px-4 py-2.5 bg-sky-500 hover:bg-sky-400 text-slate-950 font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-2 cursor-pointer">
                <span>➕</span>
                <span>Add Bank / Payment Account</span>
            </button>
        </div>
    </div>

    <!-- Flash Notifications -->
    @if (session()->has('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold rounded-2xl shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✅</span>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Bank Accounts Card Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($bankAccounts as $acc)
            <div class="bg-white rounded-3xl border border-slate-200/90 p-6 shadow-sm flex flex-col justify-between space-y-5 hover:border-sky-300 transition-all">
                <div>
                    <!-- Account Top Row: Icon, Bank Name, Account Type Badge -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-2xl flex items-center justify-center text-xl font-bold {{ $acc->account_type === 'payment_gateway' ? 'bg-indigo-50 text-indigo-600 border border-indigo-200' : ($acc->account_type === 'merchant' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-sky-50 text-sky-600 border border-sky-200') }}">
                                @if ($acc->account_type === 'payment_gateway')
                                    💳
                                @elseif ($acc->account_type === 'merchant')
                                    📱
                                @elseif ($acc->account_type === 'savings')
                                    📈
                                @elseif ($acc->account_type === 'cash')
                                    💵
                                @else
                                    🏦
                                @endif
                            </div>
                            <div>
                                <h3 class="font-extrabold text-slate-900 text-base leading-snug">{{ $acc->bank_name }}</h3>
                                <p class="text-xs font-semibold text-slate-500">{{ $acc->account_name }}</p>
                            </div>
                        </div>

                        <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wide border {{ $acc->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200' }}">
                            {{ $acc->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <!-- Details Row -->
                    <div class="mt-4 pt-3 border-t border-slate-100 grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 block uppercase">Account Type</span>
                            <span class="font-bold text-slate-700">{{ $acc->formatted_account_type }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 block uppercase">Nominal Ledger</span>
                            <span class="font-mono font-bold text-indigo-600">Code {{ $acc->account?->code ?? 'N/A' }}</span>
                        </div>
                        @if ($acc->sort_code || $acc->account_number)
                            <div class="col-span-2 pt-1 text-[11px] text-slate-500 font-mono">
                                <span>Sort: {{ $acc->sort_code ?: 'N/A' }}</span> | <span>Acc: {{ $acc->account_number ?: 'N/A' }}</span>
                            </div>
                        @endif
                    </div>

                    <!-- Balances Summary -->
                    <div class="mt-5 p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-500">Statement Balance</span>
                            <span class="font-black text-slate-900 text-sm">£{{ number_format($acc->statement_balance, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-slate-500">Ledger Balance (Code {{ $acc->account?->code ?? '1000' }})</span>
                            <span class="font-bold text-indigo-700">£{{ number_format($acc->ledger_balance, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Card Footer Actions -->
                <div class="pt-2 flex items-center justify-between gap-3 border-t border-slate-100">
                    <a href="{{ route('banking.bank-reconciliation-workspace', $clubSlug) }}" class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 {{ $acc->unreconciled_count > 0 ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-md shadow-amber-500/20' : 'bg-slate-100 hover:bg-slate-200 text-slate-700' }}">
                        <span>⚡</span>
                        <span>Reconcile ({{ $acc->unreconciled_count }})</span>
                    </a>

                    <button wire:click="toggleAccountActive({{ $acc->id }})" class="px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-500 hover:bg-slate-100 transition-colors">
                        {{ $acc->is_active ? 'Deactivate' : 'Activate' }}
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Create Bank Account Modal -->
    @if ($showCreateModal)
        <div class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5 animate-in fade-in zoom-in duration-150">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="p-2 bg-sky-50 text-sky-600 rounded-xl text-lg">🏦</span>
                        <div>
                            <h3 class="font-extrabold text-slate-900 text-lg">Add Bank / Payment Account</h3>
                            <p class="text-xs text-slate-500 font-medium">Create High Street Bank, Stripe, PayPal, or SumUp Account</p>
                        </div>
                    </div>
                    <button wire:click="$set('showCreateModal', false)" class="text-slate-400 hover:text-slate-600 font-bold text-lg">✕</button>
                </div>

                <form wire:submit.prevent="saveBankAccount" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Institution / Provider Name *</label>
                        <input wire:model="bank_name" placeholder="e.g. Barclays, Stripe, PayPal, SumUp, Lloyds" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
                        @error('bank_name') <span class="text-rose-600 text-[10px] font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Account Label / Title *</label>
                        <input wire:model="account_name" placeholder="e.g. Main Operating Account, Online Card Payments" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
                        @error('account_name') <span class="text-rose-600 text-[10px] font-bold block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Account Type *</label>
                            <select wire:model="account_type" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white">
                                <option value="current">High Street Current</option>
                                <option value="savings">Savings Account</option>
                                <option value="payment_gateway">Payment Gateway (Stripe/PayPal)</option>
                                <option value="merchant">Card Reader / Merchant (SumUp)</option>
                                <option value="cash">Petty Cash / Cash Register</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Currency *</label>
                            <input wire:model="currency" placeholder="GBP" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white uppercase" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Account Number</label>
                            <input wire:model="account_number" placeholder="e.g. 12345678" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Sort Code</label>
                            <input wire:model="sort_code" placeholder="e.g. 20-00-00" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Opening Balance (£)</label>
                        <input type="number" step="0.01" wire:model="opening_balance" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
                    </div>

                    <div class="p-3 bg-sky-50 rounded-2xl border border-sky-200 text-sky-900 text-[11px]">
                        ℹ️ Saving will automatically create and link a dedicated nominal asset account on the Chart of Accounts (e.g. Nominal Code 1010, 1020, etc.).
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-md shadow-sky-600/20">
                            Save &amp; Link Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
