<div>
    @if($isOpen)
        <div
            class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 sm:p-6"
            x-data
            @keydown.escape.window="$wire.closeModal()"
        >
            <div
                @click.outside="$wire.closeModal()"
                class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-6 relative text-slate-800"
            >
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-lg shrink-0">
                            🏛️
                        </div>
                        <div>
                            <h3 class="text-base font-black text-slate-900">Schedule Committee Meeting</h3>
                            <p class="text-xs text-slate-500">{{ $club->name }}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition cursor-pointer"
                        title="Close modal"
                    >
                        ✕
                    </button>
                </div>

                <!-- Form -->
                <form wire:submit="save" class="space-y-4 text-xs">
                    <!-- Helper: Link to Regular Lodge Meeting (Optional) -->
                    @if($upcomingRegularMeetings->isNotEmpty())
                        <div class="p-3.5 bg-indigo-50/50 border border-indigo-200/70 rounded-2xl space-y-1.5">
                            <label class="block font-bold text-indigo-950 flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <span>🔗</span>
                                    <span>Link to Regular Lodge Meeting (Optional)</span>
                                </span>
                                <span class="text-[10px] font-semibold text-indigo-600 bg-indigo-100/70 px-2 py-0.5 rounded-full">Auto-Suggests -9 Days</span>
                            </label>
                            <select
                                wire:model.live="linked_regular_meeting_id"
                                class="w-full px-3 py-2 bg-white border border-indigo-200 rounded-xl text-xs text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            >
                                <option value="">-- Standalone Committee Meeting --</option>
                                @foreach($upcomingRegularMeetings as $regMeeting)
                                    <option value="{{ $regMeeting->id }}">
                                        {{ $regMeeting->title ?: 'Regular Lodge Meeting' }} ({{ $regMeeting->meeting_date?->format('d M Y') }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-[10px] text-indigo-700/80">
                                Selecting a regular lodge meeting automatically offsets the committee date to 9 days prior.
                            </p>
                        </div>
                    @endif

                    <!-- Meeting Date -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-slate-700">Meeting Date</label>
                            <span class="text-[10px] text-slate-400">Triggers standardized title</span>
                        </div>
                        <input
                            type="date"
                            wire:model.live="meeting_date"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-semibold focus:ring-2 focus:ring-indigo-500 focus:outline-none bg-slate-50 focus:bg-white transition-all"
                            required
                        />
                        @error('meeting_date') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <!-- Meeting Title (Auto-Generated from Date) -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="block font-bold text-slate-700">Meeting Title</label>
                            <span class="text-[10px] text-emerald-600 font-bold">✓ Auto-Generated</span>
                        </div>
                        <input
                            type="text"
                            wire:model.live="title"
                            placeholder="e.g. Committee Meeting – 15th September 2026"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-900 focus:ring-2 focus:ring-indigo-500 focus:outline-none bg-white transition-all"
                            required
                        />
                        <p class="text-[10px] text-slate-400">
                            Auto-populates from the selected date. You can freely edit this for special or standing committees.
                        </p>
                        @error('title') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                    </div>

                    <!-- Grid: Time & Location -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="space-y-1 sm:col-span-1">
                            <label class="block font-bold text-slate-700">Time Opened</label>
                            <input
                                type="time"
                                wire:model="time_opened"
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                            />
                            @error('time_opened') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                        </div>

                        <div class="space-y-1 sm:col-span-2">
                            <div class="flex items-center justify-between">
                                <label class="block font-bold text-slate-700">Location / Venue</label>
                                <span class="text-[10px] text-indigo-600 font-semibold">Defaulted</span>
                            </div>
                            <input
                                type="text"
                                wire:model="location"
                                placeholder="Masonic Hall, Wellington Street..."
                                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                required
                            />
                            @error('location') <span class="text-rose-500 text-[11px] font-bold">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-all cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-md shadow-indigo-600/20 transition-all cursor-pointer flex items-center gap-1.5"
                        >
                            <span>📅</span>
                            <span>Schedule Committee Meeting</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
