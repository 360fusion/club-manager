<div>
    @if ($isOpen)
    <x-modal :show="$isOpen" title="Accounts and bill audit (UGLE Rule 158)" subtitle="Audit of supplier accounts and expenditure vouchers before the lodge confirms them." size="lg" close="closeModal">
        @if($bill)
            <div class="space-y-5">
                <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $bill->vendor_name }}</span>
                        <span class="font-mono text-base font-semibold text-slate-900 dark:text-white">{{ $cs }}{{ number_format($bill->amount, 2) }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs text-slate-600 dark:text-slate-300">
                        <div>Bill number: <strong class="font-mono font-medium text-slate-900 dark:text-white">{{ $bill->bill_number }}</strong></div>
                        <div>Category: <strong class="font-medium">{{ $bill->category }}</strong></div>
                        <div>Due date: <strong class="font-medium">{{ $bill->due_date ? $bill->due_date->format('d M Y') : 'N/A' }}</strong></div>
                        <div>
                            Receipt:
                            @if($bill->media)
                                <a href="{{ $bill->media->getUrl() }}" target="_blank" class="font-medium text-blue-600 underline dark:text-blue-400">{{ $bill->media->file_name }}</a>
                            @else
                                <span class="italic text-slate-400">No receipt attached</span>
                            @endif
                        </div>
                    </div>

                    @if($bill->notes)
                        <p class="rounded-xl border border-slate-200 bg-white p-2 text-xs italic text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                            "{{ $bill->notes }}"
                        </p>
                    @endif
                </div>

                <x-textarea
                    label="Audit inquiry notes"
                    wire:model="auditNotes"
                    rows="3"
                    placeholder="Confirm receipt inspection, pricing agreement and budget approval..."
                />

                <x-select label="Audit committee finding" hint="Recommend for payment confirmation or raise a query." wire:model="isApprovedForPayment">
                    <option value="1" @selected($isApprovedForPayment)>Audit passed (recommend payment)</option>
                    <option value="0" @selected(! $isApprovedForPayment)>Query raised (hold payment)</option>
                </x-select>
            </div>
        @endif

        <x-slot:footer>
            <x-button variant="secondary" wire:click="closeModal">Cancel</x-button>
            @if($bill)
                <x-button wire:click="signOffBill">Record bill audit sign-off</x-button>
            @endif
        </x-slot:footer>
    </x-modal>
    @endif
</div>
