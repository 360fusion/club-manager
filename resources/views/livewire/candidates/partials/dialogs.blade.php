@php
    $outcomeTitle = ['reject' => 'Reject this candidate', 'close' => 'Close this enquiry', 'hold' => 'Put on hold'][$outcomeType] ?? 'Reopen this enquiry';
    $checkbox = 'rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950';
@endphp

<!-- Reject / close / hold / reopen: always asks why -->
@if ($showOutcomeModal)
<x-modal :show="$showOutcomeModal" :title="$outcomeTitle" close="$set('showOutcomeModal', false)">
    <form id="candidate-outcome-form" wire:submit.prevent="saveOutcome" class="space-y-4">
        <p class="text-sm text-slate-500 dark:text-slate-400">
            @if($outcomeType === 'reject') The lodge or the ballot has decided against the candidate. The reason is kept in the history.
            @elseif($outcomeType === 'close') The enquiry is ending without a decision against the candidate. You can reopen it later.
            @elseif($outcomeType === 'hold') Nothing changes stage; the candidate is marked as waiting so it is not forgotten.
            @else It goes back to the stage it was in. Only an owner or admin can do this.
            @endif
        </p>

        @if(in_array($outcomeType, ['reject', 'close'], true))
            <x-select label="Reason" required wire:model="outcome_reason">
                <option value="">Choose a reason...</option>
                @foreach($reasons as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </x-select>
        @endif

        @if($outcomeType === 'hold')
            <x-input type="date" label="Until (optional)" wire:model="hold_until" />
        @endif

        <x-textarea :label="$outcomeType === 'reopen' ? 'Why is it being reopened?' : 'Note'" required rows="3" wire:model="outcome_note" placeholder="What happened, in a sentence or two. Only people who manage members can see this." />
    </form>

    <x-slot:footer>
        <x-button variant="secondary" wire:click="$set('showOutcomeModal', false)">Cancel</x-button>
        <x-button type="submit" form="candidate-outcome-form" :variant="$outcomeType === 'reject' ? 'danger' : 'primary'">
            {{ ['reject' => 'Reject', 'close' => 'Close enquiry', 'hold' => 'Put on hold', 'reopen' => 'Reopen'][$outcomeType] }}
        </x-button>
    </x-slot:footer>
</x-modal>
@endif

<!-- Ballot result -->
@if ($showBallotModal)
<x-modal :show="$showBallotModal" title="Record the ballot" close="$set('showBallotModal', false)">
    <form id="candidate-ballot-form" wire:submit.prevent="saveBallot" class="space-y-4">
        <p class="text-sm text-slate-500 dark:text-slate-400">Only the result is recorded, never who voted or how. If elected, the candidate moves to Accepted and has a year to be initiated. If not elected, they are rejected.</p>

        <x-input type="date" label="Date of the ballot" required wire:model="ballot_date" />

        <fieldset>
            <legend class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Result</legend>
            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer {{ $ballot_result === 'elected' ? 'border-emerald-400 bg-emerald-50 dark:bg-emerald-950/40' : 'border-slate-200 dark:border-slate-800' }}">
                    <input type="radio" wire:model.live="ballot_result" value="elected" /> <span class="text-sm font-medium text-slate-800 dark:text-slate-100">Elected</span>
                </label>
                <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer {{ $ballot_result === 'not_elected' ? 'border-rose-400 bg-rose-50 dark:bg-rose-950/40' : 'border-slate-200 dark:border-slate-800' }}">
                    <input type="radio" wire:model.live="ballot_result" value="not_elected" /> <span class="text-sm font-medium text-slate-800 dark:text-slate-100">Not elected</span>
                </label>
            </div>
        </fieldset>

        <x-textarea label="Note (optional)" rows="2" wire:model="ballot_note" placeholder="For example the meeting, or who was told" />
    </form>

    <x-slot:footer>
        <x-button variant="secondary" wire:click="$set('showBallotModal', false)">Cancel</x-button>
        <x-button type="submit" form="candidate-ballot-form">Record result</x-button>
    </x-slot:footer>
</x-modal>
@endif

<!-- Form P and the proposal -->
@if ($showFormPModal)
<x-modal :show="$showFormPModal" title="Proposer, Form P and proposal" size="lg" close="$set('showFormPModal', false)">
    <form wire:submit.prevent="saveFormPVetting" class="space-y-4">
        <div class="grid grid-cols-2 gap-3 bg-slate-50 dark:bg-slate-800/40 p-4 border border-slate-200 dark:border-slate-800 rounded-2xl">
            <x-select label="Proposer" wire:model="proposer_member_id">
                <option value="">Choose...</option>
                @foreach($activeMembers as $m)
                    <option value="{{ $m->id }}" @selected($proposer_member_id === $m->id)>{{ $m->formatted_rank_name }}</option>
                @endforeach
            </x-select>
            <x-select label="Seconder" wire:model="seconder_member_id">
                <option value="">Choose...</option>
                @foreach($activeMembers as $m)
                    <option value="{{ $m->id }}" @selected($seconder_member_id === $m->id)>{{ $m->formatted_rank_name }}</option>
                @endforeach
            </x-select>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <x-input type="date" label="Proposed in open lodge on" wire:model="proposed_at" />
            <x-input type="date" label="Registered with Grand Lodge on" wire:model="hermes_clearance_date" />
        </div>

        <label class="flex items-center gap-2 p-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 rounded-xl cursor-pointer">
            <input type="checkbox" wire:model="committee_recommended" class="{{ $checkbox }}" />
            <span class="text-sm font-medium text-blue-900 dark:text-blue-200">The lodge committee recommends proceeding</span>
        </label>

        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" wire:model="rule_159_cleared" class="{{ $checkbox }}" /> Committee vetting complete</label>

        <p class="text-xs text-slate-500 dark:text-slate-400">Any declaration on the form is dealt with by the Province and the Grand Secretary before the candidate is proposed. The details are not kept here, so nothing needs ticking for a candidate to move on.</p>

        <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2">
            <x-button variant="secondary" wire:click="$set('showFormPModal', false)">Cancel</x-button>
            <x-button type="submit">Save</x-button>
        </div>
    </form>

    {{-- The modal's slot is rendered even while it is hidden, and these need the chosen candidate. --}}
    @php($formPSignatures = $showFormPModal ? $this->formPSignatureRequests() : [])
    @php($openFormPRoles = $showFormPModal ? $this->formPSignatureCandidates() : [])
    <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-800 space-y-2">
        <div class="flex items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Form P signatures</h3>
            @if($form_p_signed_at)
                <span class="text-xs font-medium text-emerald-700 dark:text-emerald-300">Signed {{ \Carbon\Carbon::parse($form_p_signed_at)->format('j M Y') }}</span>
            @elseif(! $proposer_member_id || ! $seconder_member_id)
                @if(! $showFormPSignatureConfirm)
                    <span class="text-xs text-slate-500 dark:text-slate-400">Choose a proposer and seconder first</span>
                @endif
            @elseif(count($openFormPRoles) && ! $showFormPSignatureConfirm)
                <x-button size="sm" wire:click="openFormPSignatureConfirm">Request signatures</x-button>
            @elseif(! count($openFormPRoles) && ! $showFormPSignatureConfirm)
                <span class="text-xs text-slate-500 dark:text-slate-400">Both requested — cancel one below to reassign it</span>
            @endif
        </div>

        @if($showFormPSignatureConfirm)
            <div class="bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800/60 rounded-xl p-3 space-y-2">
                <p class="text-xs font-medium text-blue-900 dark:text-blue-200">Each person below will be emailed a private link to sign Form P themselves.</p>
                @foreach($openFormPRoles as $roleLabel => $person)
                    <div class="flex items-center justify-between gap-2 text-xs bg-white dark:bg-slate-900 rounded-lg px-3 py-2">
                        <span class="font-medium text-slate-900 dark:text-white">{{ $roleLabel }} — {{ $person['name'] }}</span>
                        <span class="text-slate-500 dark:text-slate-400">{{ $person['email'] ?? 'no email on file' }}</span>
                    </div>
                @endforeach
                <div class="flex items-center justify-end gap-2 pt-1">
                    <x-button variant="secondary" size="sm" wire:click="$set('showFormPSignatureConfirm', false)">Cancel</x-button>
                    <x-button size="sm" wire:click="requestFormPSignatures">Send signature requests</x-button>
                </div>
            </div>
        @endif

        @foreach(['form_p_proposer' => 'Proposer', 'form_p_seconder' => 'Seconder'] as $purpose => $roleLabel)
            @if($req = $formPSignatures[$purpose] ?? null)
                <div class="flex items-center justify-between gap-2 text-xs bg-slate-50 dark:bg-slate-800/50 rounded-xl px-3 py-2">
                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ $roleLabel }} — {{ $req['signer_name'] }}</span>
                    <span class="flex items-center gap-2">
                        <span class="font-medium {{ $req['status'] === 'signed' ? 'text-emerald-700 dark:text-emerald-300' : ($req['status'] === 'pending' ? 'text-amber-700 dark:text-amber-300' : 'text-slate-500') }}">{{ $req['label'] }}</span>
                        @if($req['status'] === 'pending')
                            <button type="button" wire:click="resendFormPSignature('{{ $purpose }}')" class="text-blue-600 dark:text-blue-400 font-medium underline cursor-pointer">Resend</button>
                            <button type="button" wire:click="cancelFormPSignature('{{ $purpose }}')" wire:confirm="Cancel this signature request?" class="text-rose-600 dark:text-rose-400 font-medium underline cursor-pointer">Cancel</button>
                        @elseif($req['status'] === 'signed')
                            <a href="{{ route('admin.candidates.signatures.pdf', ['clubSlug' => $this->candidateClubSlug(), 'id' => $req['id']]) }}" class="text-blue-600 dark:text-blue-400 font-medium underline">Download PDF</a>
                        @endif
                    </span>
                </div>
            @endif
        @endforeach
    </div>
</x-modal>
@endif

<!-- Initiation -->
@if ($showInitiationModal)
<x-modal :show="$showInitiationModal" title="Record the initiation" close="$set('showInitiationModal', false)">
    <div class="space-y-4">
        <x-notice variant="info" title="This creates the candidate's record in the members list.">
            <ul class="list-disc pl-4">
                <li>Membership is set to Active and the rank to Bro.</li>
                <li>Contact details and address are copied across.</li>
            </ul>
        </x-notice>

        <form id="candidate-initiation-form" wire:submit.prevent="confirmInitiation">
            <x-input type="date" label="Date of initiation" required wire:model="initiation_date" />
        </form>
    </div>

    <x-slot:footer>
        <x-button variant="secondary" wire:click="$set('showInitiationModal', false)">Cancel</x-button>
        <x-button type="submit" form="candidate-initiation-form">Record initiation</x-button>
    </x-slot:footer>
</x-modal>
@endif
