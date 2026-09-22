@php
    $field = 'w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-amber-500 focus:outline-none text-xs';
    $lbl = 'font-bold text-slate-700 dark:text-slate-200 block mb-1 text-xs';
    $shell = 'bg-white dark:bg-slate-900 rounded-3xl shadow-2xl w-full p-6 space-y-5 border border-slate-100 dark:border-slate-800 max-h-[92vh] overflow-y-auto';
@endphp

<!-- Reject / close / hold / reopen: always asks why -->
@if($showOutcomeModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
        <div class="{{ $shell }} max-w-md">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <h3 class="font-black text-slate-900 dark:text-white text-base">
                    @switch($outcomeType)
                        @case('reject') Reject this candidate @break
                        @case('close') Close this enquiry @break
                        @case('hold') Put on hold @break
                        @default Reopen this enquiry
                    @endswitch
                </h3>
                <button type="button" wire:click="$set('showOutcomeModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg" aria-label="Close">✕</button>
            </div>

            <form wire:submit.prevent="saveOutcome" class="space-y-4">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    @if($outcomeType === 'reject') The lodge or the ballot has decided against the candidate. The reason is kept in the history.
                    @elseif($outcomeType === 'close') The enquiry is ending without a decision against the candidate. You can reopen it later.
                    @elseif($outcomeType === 'hold') Nothing changes stage; the candidate is marked as waiting so it is not forgotten.
                    @else It goes back to the stage it was in. Only an owner or admin can do this.
                    @endif
                </p>

                @if(in_array($outcomeType, ['reject', 'close'], true))
                    <div>
                        <label class="{{ $lbl }}">Reason *</label>
                        <select wire:model="outcome_reason" class="{{ $field }}">
                            <option value="">Choose a reason...</option>
                            @foreach($reasons as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('outcome_reason') <span class="text-rose-600 dark:text-rose-400 text-[11px] font-semibold">{{ $message }}</span> @enderror
                    </div>
                @endif

                @if($outcomeType === 'hold')
                    <div>
                        <label class="{{ $lbl }}">Until (optional)</label>
                        <input type="date" wire:model="hold_until" class="{{ $field }}" />
                        @error('hold_until') <span class="text-rose-600 dark:text-rose-400 text-[11px] font-semibold">{{ $message }}</span> @enderror
                    </div>
                @endif

                <div>
                    <label class="{{ $lbl }}">{{ $outcomeType === 'reopen' ? 'Why is it being reopened? *' : 'Note *' }}</label>
                    <textarea wire:model="outcome_note" rows="3" class="{{ $field }}" placeholder="What happened, in a sentence or two. Only people who manage members can see this."></textarea>
                    @error('outcome_note') <span class="text-rose-600 dark:text-rose-400 text-[11px] font-semibold">{{ $message }}</span> @enderror
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('showOutcomeModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs">Cancel</button>
                    <button type="submit" class="px-5 py-2 {{ $outcomeType === 'reject' ? 'bg-rose-600 hover:bg-rose-700 text-white' : 'bg-amber-500 hover:bg-amber-400 text-slate-950' }} font-black rounded-xl shadow-md text-xs">
                        {{ ['reject' => 'Reject', 'close' => 'Close enquiry', 'hold' => 'Put on hold', 'reopen' => 'Reopen'][$outcomeType] }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

<!-- Ballot result -->
@if($showBallotModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
        <div class="{{ $shell }} max-w-md">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <h3 class="font-black text-slate-900 dark:text-white text-base">🗳️ Record the ballot</h3>
                <button type="button" wire:click="$set('showBallotModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg" aria-label="Close">✕</button>
            </div>

            <form wire:submit.prevent="saveBallot" class="space-y-4">
                <p class="text-xs text-slate-500 dark:text-slate-400">Only the result is recorded, never who voted or how. If elected, the candidate moves to Accepted and has a year to be initiated. If not elected, they are rejected.</p>

                <div>
                    <label class="{{ $lbl }}">Date of the ballot *</label>
                    <input type="date" wire:model="ballot_date" class="{{ $field }}" required />
                    @error('ballot_date') <span class="text-rose-600 dark:text-rose-400 text-[11px] font-semibold">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer {{ $ballot_result === 'elected' ? 'border-emerald-400 bg-emerald-50 dark:bg-emerald-950/40' : 'border-slate-200 dark:border-slate-800' }}">
                        <input type="radio" wire:model.live="ballot_result" value="elected" /> <span class="text-xs font-bold text-slate-800 dark:text-slate-100">Elected</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer {{ $ballot_result === 'not_elected' ? 'border-rose-400 bg-rose-50 dark:bg-rose-950/40' : 'border-slate-200 dark:border-slate-800' }}">
                        <input type="radio" wire:model.live="ballot_result" value="not_elected" /> <span class="text-xs font-bold text-slate-800 dark:text-slate-100">Not elected</span>
                    </label>
                </div>

                <div>
                    <label class="{{ $lbl }}">Note (optional)</label>
                    <textarea wire:model="ballot_note" rows="2" class="{{ $field }}" placeholder="For example the meeting, or who was told"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('showBallotModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-xl shadow-md text-xs">Record result</button>
                </div>
            </form>
        </div>
    </div>
@endif

<!-- Form P and the proposal -->
@if($showFormPModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
        <div class="{{ $shell }} max-w-xl">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <h3 class="font-black text-slate-900 dark:text-white text-base">📜 Proposer, Form P and proposal</h3>
                <button type="button" wire:click="$set('showFormPModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg" aria-label="Close">✕</button>
            </div>

            <form wire:submit.prevent="saveFormPVetting" class="space-y-4">
                <div class="grid grid-cols-2 gap-3 bg-amber-50/50 dark:bg-amber-950/50 p-4 border border-amber-200/80 dark:border-amber-800/80 rounded-2xl">
                    <div>
                        <label class="{{ $lbl }}">Proposer</label>
                        <select wire:model="proposer_member_id" class="{{ $field }}">
                            <option value="">Choose...</option>
                            @foreach($activeMembers as $m)
                                <option value="{{ $m->id }}">{{ $m->formatted_rank_name }}</option>
                            @endforeach
                        </select>
                        @error('proposer_member_id') <span class="text-rose-600 dark:text-rose-400 text-[11px] font-semibold">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Seconder</label>
                        <select wire:model="seconder_member_id" class="{{ $field }}">
                            <option value="">Choose...</option>
                            @foreach($activeMembers as $m)
                                <option value="{{ $m->id }}">{{ $m->formatted_rank_name }}</option>
                            @endforeach
                        </select>
                        @error('seconder_member_id') <span class="text-rose-600 dark:text-rose-400 text-[11px] font-semibold">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $lbl }}">Proposed in open lodge on</label>
                        <input type="date" wire:model="proposed_at" class="{{ $field }}" />
                        @error('proposed_at') <span class="text-rose-600 dark:text-rose-400 text-[11px] font-semibold">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="{{ $lbl }}">Registered with Grand Lodge on</label>
                        <input type="date" wire:model="hermes_clearance_date" class="{{ $field }}" />
                    </div>
                </div>

                <label class="flex items-center gap-2 p-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 rounded-xl cursor-pointer">
                    <input type="checkbox" wire:model="committee_recommended" class="rounded" />
                    <span class="text-xs font-bold text-blue-900 dark:text-blue-200">The lodge committee recommends proceeding</span>
                </label>

                <label class="flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200"><input type="checkbox" wire:model="rule_159_cleared" class="rounded" /> Committee vetting complete</label>

                <p class="text-[11px] text-slate-500 dark:text-slate-400">Any declaration on the form is dealt with by the Province and the Grand Secretary before the candidate is proposed. The details are not kept here, so nothing needs ticking for a candidate to move on.</p>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('showFormPModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-xl shadow-md text-xs">Save</button>
                </div>
            </form>

            @php($formPSignatures = $this->formPSignatureRequests())
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
                <div class="flex items-center justify-between">
                    <h4 class="font-black text-slate-900 dark:text-white text-xs">✍️ Form P signatures</h4>
                    @if($form_p_signed_at)
                        <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-300">Signed {{ \Carbon\Carbon::parse($form_p_signed_at)->format('j M Y') }}</span>
                    @elseif($proposer_member_id && $seconder_member_id)
                        <button type="button" wire:click="requestFormPSignatures" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl text-[11px]">Request signatures</button>
                    @else
                        <span class="text-[11px] text-slate-400">Choose a proposer and seconder first</span>
                    @endif
                </div>
                @foreach(['form_p_proposer' => 'Proposer', 'form_p_seconder' => 'Seconder'] as $purpose => $roleLabel)
                    @if($req = $formPSignatures[$purpose] ?? null)
                        <div class="flex items-center justify-between text-[11px] bg-slate-50 dark:bg-slate-800/50 rounded-xl px-3 py-2">
                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $roleLabel }} — {{ $req['signer_name'] }}</span>
                            <span class="flex items-center gap-2">
                                <span class="font-bold {{ $req['status'] === 'signed' ? 'text-emerald-700 dark:text-emerald-300' : ($req['status'] === 'pending' ? 'text-amber-700 dark:text-amber-300' : 'text-slate-500') }}">{{ $req['label'] }}</span>
                                @if($req['status'] === 'pending')
                                    <button type="button" wire:click="resendFormPSignature('{{ $purpose }}')" class="text-blue-600 dark:text-blue-400 font-bold underline">Resend</button>
                                @endif
                            </span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endif

<!-- Initiation -->
@if($showInitiationModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
        <div class="{{ $shell }} max-w-md">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                <h3 class="font-black text-slate-900 dark:text-white text-base">🏛️ Record the initiation</h3>
                <button type="button" wire:click="$set('showInitiationModal', false)" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg" aria-label="Close">✕</button>
            </div>

            <div class="p-4 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 rounded-2xl text-xs text-blue-900 dark:text-blue-200 space-y-1">
                <p class="font-bold">This creates the candidate's record in the members list.</p>
                <ul class="list-disc pl-4 text-blue-800 dark:text-blue-200">
                    <li>Membership is set to Active and the rank to Bro.</li>
                    <li>Contact details and address are copied across.</li>
                </ul>
            </div>

            <form wire:submit.prevent="confirmInitiation" class="space-y-4">
                <div>
                    <label class="{{ $lbl }}">Date of initiation *</label>
                    <input type="date" wire:model="initiation_date" class="{{ $field }}" required />
                    @error('initiation_date') <span class="text-rose-600 dark:text-rose-400 text-[11px] font-semibold">{{ $message }}</span> @enderror
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
                    <button type="button" wire:click="$set('showInitiationModal', false)" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-blue-700 hover:bg-blue-800 text-white font-black rounded-xl shadow-md text-xs">Record initiation</button>
                </div>
            </form>
        </div>
    </div>
@endif
