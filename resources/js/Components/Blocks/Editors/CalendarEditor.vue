<script setup>
// Edit form for the Calendar block. The calendar shows the club's own events that the visitor may see.
import { LABEL, INPUT, SELECT, CARD, CHECK, CHECK_LABEL, HINT } from './styles';

defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
});
</script>

<template>
    <div class="space-y-3 text-xs">
        <div>
            <label :for="`block-${index}-cal-heading`" :class="LABEL">Heading <span class="font-normal text-slate-400">(optional)</span></label>
            <input :id="`block-${index}-cal-heading`" v-model="block.heading" type="text" maxlength="200" placeholder="e.g. What's on" :class="[INPUT, 'font-bold']" />
        </div>

        <div :class="[CARD, 'grid grid-cols-2 sm:grid-cols-4 gap-3 items-end']">
            <div>
                <label :for="`block-${index}-cal-view`" :class="LABEL">Starts as</label>
                <select :id="`block-${index}-cal-view`" v-model="block.default_view" :class="SELECT">
                    <option value="month">Month grid</option>
                    <option value="list">Upcoming list</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-cal-week`" :class="LABEL">Week starts on</label>
                <select :id="`block-${index}-cal-week`" v-model="block.week_starts" :class="SELECT">
                    <option value="monday">Monday</option>
                    <option value="sunday">Sunday</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-cal-length`" :class="LABEL">List shows</label>
                <select :id="`block-${index}-cal-length`" v-model.number="block.list_length" :class="SELECT">
                    <option :value="5">5 events</option>
                    <option :value="10">10 events</option>
                    <option :value="20">20 events</option>
                </select>
            </div>
            <label :class="[CHECK_LABEL, 'pb-2']"><input v-model="block.allow_switch" type="checkbox" :class="CHECK" /> Let visitors switch view</label>
            <label :class="CHECK_LABEL"><input v-model="block.show_times" type="checkbox" :class="CHECK" /> Show times</label>
            <label :class="CHECK_LABEL"><input v-model="block.show_location" type="checkbox" :class="CHECK" /> Show location (list)</label>
            <label :class="CHECK_LABEL"><input v-model="block.show_price" type="checkbox" :class="CHECK" /> Show price (list)</label>
            <label :class="CHECK_LABEL"><input v-model="block.show_subscribe" type="checkbox" :class="CHECK" /> "Add to your calendar" link</label>
        </div>

        <p :class="HINT">Shows this club's published events that the visitor may see: everyone sees public events, signed-in members also see members' events. Draft and cancelled events are left out. A repeating event appears once, on its first date.</p>
    </div>
</template>
