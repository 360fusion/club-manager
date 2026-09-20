<div>
    @if($isOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5 text-xs">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">🔍</span>
                        <div>
                            <h3 class="text-sm font-black text-slate-900 dark:text-white">Accounts & Bill Audit (UGLE Rule 158)</h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Audit of vendor accounts and expenditure vouchers prior to lodge confirmation.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold">✕</button>
                </div>

                @if($bill)
                    <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-100 dark:border-slate-800 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $bill->vendor_name }}</span>
                            <span class="font-mono font-black text-slate-900 dark:text-white text-base">£{{ number_format($bill->amount, 2) }}</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-600 dark:text-slate-300">
                            <div>Bill Number: <strong class="font-mono text-slate-900 dark:text-white">{{ $bill->bill_number }}</strong></div>
                            <div>Category: <strong>{{ $bill->category }}</strong></div>
                            <div>Due Date: <strong>{{ $bill->due_date ? $bill->due_date->format('d M Y') : 'N/A' }}</strong></div>
                            <div>
                                Receipt:
                                @if($bill->media)
                                    <a href="{{ $bill->media->getUrl() }}" target="_blank" class="text-blue-600 dark:text-blue-400 font-bold underline">
                                        📎 {{ $bill->media->file_name }}
                                    </a>
                                @else
                                    <span class="text-slate-400 italic">No receipt attached</span>
                                @endif
                            </div>
                        </div>

                        @if($bill->notes)
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 italic bg-white dark:bg-slate-900 p-2 rounded-xl border border-slate-100 dark:border-slate-800">
                                "{{ $bill->notes }}"
                            </p>
                        @endif
                    </div>

                    <div class="space-y-2">
                        <label class="block font-bold text-slate-700 dark:text-slate-200">Audit Inquiry Notes</label>
                        <textarea
                            wire:model="auditNotes"
                            rows="3"
                            class="w-full p-3 border border-slate-200 dark:border-slate-800 rounded-xl text-xs focus:ring-2 focus:ring-blue-500 focus:outline-none resize-none leading-relaxed"
                            placeholder="Confirm receipt inspection, pricing agreement, and budget approval..."
                        ></textarea>
                    </div>

                    <div class="p-3 bg-blue-50/70 dark:bg-blue-950/70 border border-blue-200 dark:border-blue-800/60 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="font-bold text-blue-900 dark:text-blue-200 block">Audit Committee Finding</span>
                            <span class="text-[10px] text-blue-800 dark:text-blue-200">Recommend for payment confirmation or raise query</span>
                        </div>

                        <select wire:model="isApprovedForPayment" class="px-3 py-1.5 border border-blue-300 dark:border-blue-700/60 rounded-lg text-xs font-bold text-blue-900 dark:text-blue-200 bg-white dark:bg-slate-900">
                            <option :value="true">Audit Passed (Recommend Payment)</option>
                            <option :value="false">Query Raised (Hold Payment)</option>
                        </select>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-2">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl">Cancel</button>
                        <button
                            type="button"
                            wire:click="signOffBill"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-sm"
                        >
                            Record Bill Audit Sign-Off
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
