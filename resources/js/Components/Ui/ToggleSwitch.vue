<script setup>
// A small on/off switch with its state written beside it ("Shown" / "Hidden"). Used where a section can be switched
// off from its own heading, so the switch and what it controls sit together.
defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, required: true },
    onText: { type: String, default: 'Shown' },
    offText: { type: String, default: 'Hidden' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :aria-label="label"
        class="inline-flex items-center gap-2 text-[11px] font-bold text-slate-600 dark:text-slate-300 cursor-pointer select-none"
        @click="$emit('update:modelValue', !modelValue)"
    >
        <span :class="['relative inline-block w-9 h-5 rounded-full transition-colors', modelValue ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-600']">
            <span :class="['absolute top-0.5 left-0.5 w-4 h-4 rounded-full bg-white shadow transition-transform', modelValue ? 'translate-x-4' : '']"></span>
        </span>
        <span class="w-11 text-left">{{ modelValue ? onText : offText }}</span>
    </button>
</template>
