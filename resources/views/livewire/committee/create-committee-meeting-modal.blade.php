<div>
    @if ($isOpen)
    <x-modal :show="$isOpen" title="Schedule committee meeting" :subtitle="$club->name" size="lg" close="closeModal">
        <form wire:submit="save" id="create-committee-meeting-form" class="space-y-4">
            @if($upcomingRegularMeetings->isNotEmpty())
                <x-select
                    label="Link to a regular lodge meeting (optional)"
                    hint="Picking a regular lodge meeting moves the committee date to 9 days before it."
                    wire:model.live="linked_regular_meeting_id"
                >
                    <option value="">Standalone committee meeting</option>
                    @foreach($upcomingRegularMeetings as $regMeeting)
                        <option value="{{ $regMeeting->id }}">
                            {{ $regMeeting->title ?: 'Regular lodge meeting' }} ({{ $regMeeting->meeting_date?->format('d M Y') }})
                        </option>
                    @endforeach
                </x-select>
            @endif

            <x-input
                type="date"
                label="Meeting date"
                hint="Sets the standard title."
                wire:model.live="meeting_date"
                required
            />

            <x-input
                label="Meeting title"
                hint="Filled in from the date. You can change it for special or standing committees."
                wire:model.live="title"
                placeholder="e.g. Committee Meeting – 15th September 2026"
                required
            />

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="sm:col-span-1">
                    <x-input type="time" label="Time opened" wire:model="time_opened" />
                </div>

                <div class="sm:col-span-2">
                    <x-input
                        label="Location or venue"
                        hint="Defaults to the lodge's usual venue."
                        wire:model="location"
                        placeholder="Masonic Hall, Wellington Street..."
                        required
                    />
                </div>
            </div>
        </form>

        <x-slot:footer>
            <x-button variant="secondary" wire:click="closeModal">Cancel</x-button>
            <x-button type="submit" form="create-committee-meeting-form">Schedule committee meeting</x-button>
        </x-slot:footer>
    </x-modal>
    @endif
</div>
