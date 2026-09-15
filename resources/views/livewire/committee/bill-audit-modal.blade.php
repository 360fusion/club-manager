<div>
    @if($isOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-5 text-xs">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">🔍</span>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Accounts & Bill Audit (UGLE Rule 158)</h3>
                            <p class="text-[11px] text-slate-500">Audit of vendor accounts and expenditure vouchers prior to lodge confirmation.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-slate-400 hover:text-slate-700 font-bold">✕</button>
                </div>

                @if($bill)
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900 text-sm">{{ $bill->vendor_name }}</span>
                            <span class="font-mono font-black text-slate-900 text-base">£{{ number_format($bill->amount, 2) }}</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-600">
                            <div>Bill Number: <strong class="font-mono text-slate-900">{{ $bill->bill_number }}</strong></div>
                            <div>Category: <strong>{{ $bill->category }}</strong></div>
                            <div>Due Date: <strong>{{ $bill->due_date ? $bill->due_date->format('d M Y') : 'N/A' }}</strong></div>
                            <div>
                                Receipt:
                                @if($bill->media)
                                    <a href="{{ $bill->media->getUrl() }}" target="_blank" class="text-indigo-600 font-bold underline">
                                        📎 {{ $bill->media->file_name }}
                                    </a>
                                @else
                                    <span class="text-slate-400 italic">No receipt attached</span>
                                @endif
                            </div>
                        </div>

                        @if($bill->notes)
                            <p class="text-[10px] text-slate-500 italic bg-white p-2 rounded-xl border border-slate-100">
                                "{{ $bill->notes }}"
                            </p>
                        @endif
                    </div>

                    <div class="space-y-2">
                        <label class="block font-bold text-slate-700">Audit Inquiry Notes</label>
                        <textarea
                            wire:model="auditNotes"
                            rows="3"
                            class="w-full p-3 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none resize-none leading-relaxed"
                            placeholder="Confirm receipt inspection, pricing agreement, and budget approval..."
                        ></textarea>
                    </div>

                    <div class="p-3 bg-indigo-50/70 border border-indigo-200 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="font-bold text-indigo-900 block">Audit Committee Finding</span>
                            <span class="text-[10px] text-indigo-800">Recommend for payment confirmation or raise query</span>
                        </div>

                        <select wire:model="isApprovedForPayment" class="px-3 py-1.5 border border-indigo-300 rounded-lg text-xs font-bold text-indigo-900 bg-white">
                            <option :value="true">Audit Passed (Recommend Payment)</option>
                            <option :value="false">Query Raised (Hold Payment)</option>
                        </select>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl">Cancel</button>
                        <button
                            type="button"
                            wire:click="signOffBill"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-sm"
                        >
                            Record Bill Audit Sign-Off
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
