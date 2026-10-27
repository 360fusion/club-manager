<div>
    @if ($isOpen)
    <x-modal :show="$isOpen" title="Agenda pack preview and sending" :subtitle="$meeting->title" size="xl" close="closeModal">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div role="group" aria-label="Agenda pack view" class="flex w-fit items-center gap-1 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 dark:border-slate-700/60 dark:bg-slate-800/80">
                    <button
                        type="button"
                        wire:click="$set('activeTab', 'pdf')"
                        aria-pressed="{{ $activeTab === 'pdf' ? 'true' : 'false' }}"
                        @class([
                            'inline-flex cursor-pointer items-center rounded-xl px-4 py-2 text-xs font-semibold whitespace-nowrap transition-colors',
                            'bg-slate-900 text-white shadow-sm dark:bg-slate-700' => $activeTab === 'pdf',
                            'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white' => $activeTab !== 'pdf',
                        ])
                    >
                        Preview
                    </button>

                    <button
                        type="button"
                        wire:click="$set('activeTab', 'email')"
                        aria-pressed="{{ $activeTab === 'email' ? 'true' : 'false' }}"
                        @class([
                            'inline-flex cursor-pointer items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-semibold whitespace-nowrap transition-colors',
                            'bg-slate-900 text-white shadow-sm dark:bg-slate-700' => $activeTab === 'email',
                            'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white' => $activeTab !== 'email',
                        ])
                    >
                        Email recipients
                        <x-badge>{{ count($selectedRecipientIds) }}</x-badge>
                    </button>
                </div>

                <x-button variant="secondary" size="sm" href="{{ route('committee.pack.pdf', $meeting->id) }}" target="_blank">
                    Open PDF
                </x-button>
            </div>

            @if($activeTab === 'pdf')
                <div class="relative min-h-[560px] overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 dark:border-slate-800 dark:bg-slate-800">
                    <iframe
                        src="{{ route('committee.pack.pdf', $meeting->id) }}"
                        class="h-[620px] w-full rounded-2xl border-0 bg-white dark:bg-slate-900"
                        title="Agenda pack PDF preview"
                    ></iframe>
                </div>
            @endif

            @if($activeTab === 'email')
                <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                    <x-card padding="sm" class="space-y-4 lg:col-span-7">
                        <x-input
                            label="Email subject"
                            wire:model="emailSubject"
                            placeholder="Subject line..."
                        />

                        <x-textarea
                            label="Message body (Markdown or plain text)"
                            hint="Pre-filled with the agenda summary."
                            wire:model="emailBody"
                            rows="15"
                            class="min-h-[340px] resize-y font-mono leading-relaxed"
                            placeholder="Write a message to committee members..."
                        />

                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5 dark:border-slate-800 dark:bg-slate-800/50">
                            <span>
                                <span class="block text-sm font-medium text-slate-900 dark:text-white">Attach the PDF agenda pack</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">Generates the formatted PDF and attaches it to each recipient's email.</span>
                            </span>

                            <span class="relative inline-flex shrink-0 items-center">
                                <input
                                    type="checkbox"
                                    wire:model="includePdfAttachment"
                                    class="peer sr-only"
                                />
                                <span aria-hidden="true" class="h-6 w-11 rounded-full bg-slate-200 peer-checked:bg-blue-600 peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500 peer-focus-visible:ring-offset-2 after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-slate-700 dark:after:border-slate-700 dark:peer-focus-visible:ring-offset-slate-900"></span>
                            </span>
                        </label>
                    </x-card>

                    <x-card padding="sm" class="space-y-3 lg:col-span-5">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2.5 dark:border-slate-800">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Recipients</h3>
                                <span class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ count($selectedRecipientIds) }} of {{ $attendees->count() }} selected
                                </span>
                            </div>

                            <div class="flex items-center gap-1">
                                <x-button variant="ghost" size="sm" wire:click="selectAllRecipients">Select all</x-button>
                                <x-button variant="ghost" size="sm" wire:click="deselectAllRecipients">Clear</x-button>
                            </div>
                        </div>

                        @error('selectedRecipientIds')
                            <x-notice variant="danger">{{ $message }}</x-notice>
                        @enderror

                        <div class="max-h-[380px] space-y-2 overflow-y-auto pr-1">
                            @forelse($attendees as $att)
                                @php
                                    $user = $att->user;
                                    $isSelected = in_array((int) $att->id, $selectedRecipientIds);
                                @endphp
                                <label
                                    wire:key="attendee-{{ $att->id }}"
                                    @class([
                                        'flex cursor-pointer items-center justify-between gap-3 rounded-xl border px-2.5 py-2 transition-colors',
                                        'border-blue-200 bg-blue-50/40 dark:border-blue-800/60 dark:bg-blue-950/40' => $isSelected,
                                        'border-transparent bg-white hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/50' => ! $isSelected,
                                    ])
                                >
                                    <span class="flex min-w-0 items-center gap-3">
                                        <input
                                            type="checkbox"
                                            wire:click="toggleRecipient({{ $att->id }})"
                                            @checked($isSelected)
                                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-700"
                                        />
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-1.5 truncate text-sm font-medium text-slate-900 dark:text-white">
                                                <span>{{ $att->name }}</span>
                                                @if($att->role_title)
                                                    <x-badge variant="info">{{ $att->role_title }}</x-badge>
                                                @endif
                                            </span>
                                            <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                                                {{ $user?->email ?: 'Registered member' }}
                                            </span>
                                        </span>
                                    </span>

                                    <span class="shrink-0">
                                        @if($att->pack_sent_at)
                                            <x-badge variant="success" title="{{ $att->pack_sent_at->format('d M Y H:i') }}">Sent {{ $att->pack_sent_at->format('H:i') }}</x-badge>
                                        @else
                                            <x-badge>Pending</x-badge>
                                        @endif
                                    </span>
                                </label>
                            @empty
                                <p class="p-4 text-center text-xs text-slate-500 dark:text-slate-400">
                                    No roll-call attendees registered yet.
                                </p>
                            @endforelse
                        </div>

                        <x-slot:footer>
                            <x-button block wire:click="sendAgendaPack" wire:loading.attr="disabled">
                                <span wire:loading.remove>Send to {{ count($selectedRecipientIds) }} selected brethren</span>
                                <span wire:loading class="inline-flex items-center gap-2">
                                    <svg class="h-3.5 w-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Sending emails...</span>
                                </span>
                            </x-button>
                        </x-slot:footer>
                    </x-card>
                </div>
            @endif
        </div>
    </x-modal>
    @endif
</div>
