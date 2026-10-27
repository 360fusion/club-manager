<div>
    @if ($isOpen)
    <x-modal :show="$isOpen" title="Candidate vetting (UGLE Rule 159)" subtitle="Statutory committee inquiry before the summons ballot is printed." size="lg" close="closeModal">
        @if($candidate)
            <div class="space-y-5">
                <div class="space-y-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $candidate->name }}</span>
                        <x-badge variant="info">Applicant</x-badge>
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-300">Email: <strong class="font-medium">{{ $candidate->email }}</strong></p>
                    @if(isset($candidate->created_at))
                        <p class="text-xs text-slate-500 dark:text-slate-400">Registered in the club system on {{ $candidate->created_at->format('d M Y') }}</p>
                    @endif
                </div>

                <x-textarea
                    label="Vetting inquiry findings and committee notes"
                    wire:model="vettingNotes"
                    rows="4"
                    placeholder="Detail proposer and seconder discussions, character references and identity checks..."
                />

                <x-select label="Committee recommendation" hint="Approve to proceed to the open lodge ballot, or defer for further inquiry." wire:model="isRecommended">
                    <option value="1" @selected($isRecommended)>Recommended (proceed)</option>
                    <option value="0" @selected(! $isRecommended)>Deferred (further enquiry)</option>
                </x-select>
            </div>
        @endif

        <x-slot:footer>
            <x-button variant="secondary" wire:click="closeModal">Cancel</x-button>
            @if($candidate)
                <x-button wire:click="signOffCandidate">Record vetting sign-off</x-button>
            @endif
        </x-slot:footer>
    </x-modal>
    @endif
</div>
