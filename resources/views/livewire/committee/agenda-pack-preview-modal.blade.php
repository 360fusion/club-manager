<div>
    @if($isOpen)
        <!-- Modal Backdrop -->
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4 sm:p-6 transition-all animate-fade-in">
            <!-- Modal Dialog Container -->
            <div
                @click.away="$wire.closeModal()"
                class="bg-white dark:bg-slate-900 w-full max-w-5xl rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden flex flex-col max-h-[92vh] animate-in fade-in zoom-in-95 duration-200"
            >
                <!-- Modal Header -->
                <div class="px-6 py-5 bg-slate-900 dark:bg-slate-700 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm sm:text-base font-bold text-slate-200 truncate max-w-[340px] sm:max-w-xl">{{ $meeting->title }}</span>
                        </div>
                        <h2 class="text-lg font-black text-white mt-2.5 flex items-center gap-2">
                            <span>📄</span>
                            <span>Agenda Pack Preview & Dispatch</span>
                        </h2>
                    </div>

                    <div class="flex flex-col items-end gap-2">
                        <div class="flex items-center gap-3">
                            <!-- Navigation Tabs Pill -->
                            <div class="flex items-center bg-slate-800 p-1 rounded-2xl border border-slate-700/80 text-xs font-bold">
                                <button
                                    type="button"
                                    wire:click="$set('activeTab', 'pdf')"
                                    class="px-3.5 py-1.5 rounded-xl transition-all flex items-center gap-1.5 cursor-pointer {{ $activeTab === 'pdf' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:text-white' }}"
                                >
                                    <span>📄</span>
                                    <span>Preview</span>
                                </button>

                                <button
                                    type="button"
                                    wire:click="$set('activeTab', 'email')"
                                    class="px-3.5 py-1.5 rounded-xl transition-all flex items-center gap-1.5 cursor-pointer {{ $activeTab === 'email' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:text-white' }}"
                                >
                                    <span>✉️</span>
                                    <span>Email Recipients</span>
                                    <span class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] font-black {{ $activeTab === 'email' ? 'bg-white/20 dark:bg-slate-900/20 text-white' : 'bg-slate-700 text-slate-300' }}">
                                        {{ count($selectedRecipientIds) }}
                                    </span>
                                </button>
                            </div>

                            <!-- Close Button -->
                            <button
                                type="button"
                                wire:click="closeModal"
                                class="w-8 h-8 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition-colors cursor-pointer"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Open PDF Button -->
                        <a
                            href="{{ route('committee.pack.pdf', $meeting->id) }}"
                            target="_blank"
                            class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white font-bold text-xs rounded-xl border border-slate-700 transition-all flex items-center gap-1.5 cursor-pointer shadow-sm"
                        >
                            <span>↗</span>
                            <span>Open PDF</span>
                        </a>
                    </div>
                </div>

                <!-- Modal Body (Scrollable) -->
                <div class="p-4 sm:p-5 overflow-y-auto flex-1 bg-slate-50/50 dark:bg-slate-800/50/50 space-y-4">

                    <!-- ========================================================= -->
                    <!-- TAB 1: Formatted PDF Document Preview                     -->
                    <!-- ========================================================= -->
                    @if($activeTab === 'pdf')
                        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 shadow-inner overflow-hidden bg-slate-100 dark:bg-slate-800 relative min-h-[560px]">
                            <iframe
                                src="{{ route('committee.pack.pdf', $meeting->id) }}"
                                class="w-full h-[620px] border-0 rounded-2xl bg-white dark:bg-slate-900"
                                title="Agenda Pack PDF Preview"
                            ></iframe>
                        </div>
                    @endif

                    <!-- ========================================================= -->
                    <!-- TAB 2: Email Body & Recipients Selection                  -->
                    <!-- ========================================================= -->
                    @if($activeTab === 'email')
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                            <!-- Left: Email Content Editor (7 Cols) -->
                            <div class="lg:col-span-7 space-y-4">
                                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                                    <div class="space-y-1">
                                        <label class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider block">
                                            Email Subject Line
                                        </label>
                                        <input
                                            type="text"
                                            wire:model="emailSubject"
                                            class="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 font-bold text-slate-900 dark:text-white"
                                            placeholder="Subject line..."
                                        />
                                        @error('emailSubject') <span class="text-rose-600 dark:text-rose-400 text-[11px] font-bold">{{ $message }}</span> @enderror
                                    </div>

                                    <div class="space-y-1">
                                        <div class="flex items-center justify-between">
                                            <label class="text-xs font-black text-slate-700 dark:text-slate-200 uppercase tracking-wider block">
                                                Message Body (Markdown / Plain Text)
                                            </label>
                                            <span class="text-[10px] text-slate-400 font-medium">Pre-filled with agenda summary</span>
                                        </div>
                                        <textarea
                                            wire:model="emailBody"
                                            rows="15"
                                            class="w-full p-3.5 text-xs font-mono leading-relaxed rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500 text-slate-800 dark:text-slate-100 resize-y min-h-[340px]"
                                            placeholder="Write message to committee members..."
                                        ></textarea>
                                        @error('emailBody') <span class="text-rose-600 dark:text-rose-400 text-[11px] font-bold">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- PDF Attachment Toggle -->
                                    <div class="p-3.5 bg-blue-50/70 dark:bg-blue-950/70 border border-blue-200/80 dark:border-blue-800/80 rounded-xl flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2.5">
                                            <span class="text-xl">📎</span>
                                            <div>
                                                <span class="text-xs font-black text-blue-950 dark:text-blue-100 block">Attach Official PDF Agenda Pack</span>
                                                <span class="text-[11px] text-blue-700 dark:text-blue-300 block">Generates and appends the formatted PDF document directly to each recipient's email.</span>
                                            </div>
                                        </div>

                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                wire:model="includePdfAttachment"
                                                class="sr-only peer"
                                            />
                                            <div class="w-11 h-6 bg-slate-200 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 dark:after:border-slate-700 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Recipients Checklist (5 Cols) -->
                            <div class="lg:col-span-5 space-y-4">
                                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                                    <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                                        <div>
                                            <h3 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                                                <span>👥</span>
                                                <span>Recipients</span>
                                            </h3>
                                            <span class="text-[11px] text-slate-400 font-medium">
                                                {{ count($selectedRecipientIds) }} of {{ $attendees->count() }} selected
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-2 text-[11px] font-bold">
                                            <button
                                                type="button"
                                                wire:click="selectAllRecipients"
                                                class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 cursor-pointer"
                                            >
                                                Select All
                                            </button>
                                            <span class="text-slate-300">•</span>
                                            <button
                                                type="button"
                                                wire:click="deselectAllRecipients"
                                                class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 cursor-pointer"
                                            >
                                                Clear
                                            </button>
                                        </div>
                                    </div>

                                    @error('selectedRecipientIds')
                                        <div class="p-2.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-200 rounded-xl text-xs font-bold">
                                            {{ $message }}
                                        </div>
                                    @enderror

                                    <div class="space-y-2 max-h-[380px] overflow-y-auto pr-1 divide-y divide-slate-100 dark:divide-slate-800">
                                        @forelse($attendees as $att)
                                            @php
                                                $user = $att->user;
                                                $isSelected = in_array((int) $att->id, $selectedRecipientIds);
                                            @endphp
                                            <div
                                                wire:key="attendee-{{ $att->id }}"
                                                wire:click="toggleRecipient({{ $att->id }})"
                                                class="pt-2 pb-2 px-2.5 rounded-xl border transition-all cursor-pointer flex items-center justify-between gap-3 {{ $isSelected ? 'bg-blue-50/40 dark:bg-blue-950/40 border-blue-200 dark:border-blue-800/60' : 'bg-white dark:bg-slate-900 border-transparent hover:bg-slate-50 dark:hover:bg-slate-800/50' }}"
                                            >
                                                <div class="flex items-center gap-3 min-w-0">
                                                    <input
                                                        type="checkbox"
                                                        @if($isSelected) checked @endif
                                                        class="rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 border-slate-300 dark:border-slate-700 pointer-events-none"
                                                    />
                                                    <div class="min-w-0">
                                                        <div class="font-bold text-xs text-slate-900 dark:text-white truncate flex items-center gap-1.5">
                                                            <span>{{ $att->name }}</span>
                                                            @if($att->role_title)
                                                                <span class="text-[10px] font-bold text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 px-1.5 py-0.2 rounded-md">
                                                                    {{ $att->role_title }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <div class="text-[10px] text-slate-400 truncate">
                                                            {{ $user?->email ?: 'Registered member' }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="shrink-0 text-right">
                                                    @if($att->pack_sent_at)
                                                        <span class="text-[9px] font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 px-1.5 py-0.5 rounded-md block" title="{{ $att->pack_sent_at->format('d M Y H:i') }}">
                                                            ✓ Sent {{ $att->pack_sent_at->format('H:i') }}
                                                        </span>
                                                    @else
                                                        <span class="text-[9px] font-bold text-slate-400 bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded-md block">
                                                            Pending
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <div class="p-4 text-center text-xs text-slate-400">
                                                No roll-call attendees registered yet.
                                            </div>
                                        @endforelse
                                    </div>

                                    <!-- Dispatch Button inside Email Tab -->
                                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                                        <button
                                            type="button"
                                            wire:click="sendAgendaPack"
                                            wire:loading.attr="disabled"
                                            class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-600/20 transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
                                        >
                                            <span wire:loading.remove>🚀 Send to {{ count($selectedRecipientIds) }} Selected Brethren</span>
                                            <span wire:loading class="inline-flex items-center gap-2">
                                                <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                <span>Dispatching Emails...</span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    @endif
</div>
