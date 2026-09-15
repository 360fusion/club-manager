<div>
    @if($isOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-sm p-4">
            <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-5 text-xs">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">👤</span>
                        <div>
                            <h3 class="text-sm font-black text-slate-900">Candidate Vetting (UGLE Rule 159)</h3>
                            <p class="text-[11px] text-slate-500">Statutory committee inquiry prior to summons ballot printing.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-slate-400 hover:text-slate-700 font-bold">✕</button>
                </div>

                @if($candidate)
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900 text-sm">{{ $candidate->name }}</span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800">
                                Applicant
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-600">
                            <span>Email: <strong>{{ $candidate->email }}</strong></span>
                        </div>
                        <p class="text-[10px] text-slate-400">
                            Registered in club system on {{ $candidate->created_at ? $candidate->created_at->format('d M Y') : 'N/A' }}
                        </p>
                    </div>

                    <div class="space-y-3">
                        <label class="block font-bold text-slate-700">Vetting Inquiry Findings & Committee Notes</label>
                        <textarea
                            wire:model="vettingNotes"
                            rows="4"
                            class="w-full p-3 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none resize-none leading-relaxed"
                            placeholder="Detail proposer/seconder discussions, character references, and identity verification..."
                        ></textarea>
                    </div>

                    <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="font-bold text-amber-900 block">Committee Recommendation</span>
                            <span class="text-[10px] text-amber-800">Approve to proceed to open lodge ballot or defer inquiry</span>
                        </div>

                        <select wire:model="isRecommended" class="px-3 py-1.5 border border-amber-300 rounded-lg text-xs font-bold text-amber-900 bg-white">
                            <option :value="true">Recommended (Proceed)</option>
                            <option :value="false">Deferred (Further Enquiry)</option>
                        </select>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" wire:click="closeModal" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl">Cancel</button>
                        <button
                            type="button"
                            wire:click="signOffCandidate"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-sm"
                        >
                            Record Vetting Sign-Off
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
