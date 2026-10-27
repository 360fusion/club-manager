<script setup>
import { computed, useAttrs, useId } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    // v-model.number: like on a native field, a value that reads as a number is sent as one, anything else as typed.
    modelModifiers: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const update = (value) => {
    const number = parseFloat(value);
    emit('update:modelValue', props.modelModifiers.number && !Number.isNaN(number) ? number : value);
};

const attrs = useAttrs();
const id = useId();
// Other attributes go to the field itself; `class` and `style` stay on the wrapper.
const fieldAttrs = computed(() => {
    const { class: _class, style: _style, ...rest } = attrs;
    return rest;
});

const fieldClasses = computed(() => [
    'w-full rounded-xl border px-3 py-2 text-sm transition-colors',
    'bg-white text-slate-900 dark:bg-slate-950 dark:text-white',
    'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
    'disabled:opacity-50 disabled:cursor-not-allowed',
    props.error ? 'border-rose-400 dark:border-rose-700' : 'border-slate-300 dark:border-slate-700',
]);
</script>

<template>
    <div :class="['space-y-1.5', $attrs.class]" :style="$attrs.style">
        <label v-if="label" :for="id" class="block text-xs font-medium text-slate-700 dark:text-slate-300">
            {{ label }}
            <span v-if="required" class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span>
        </label>

        <select
            :id="id"
            v-bind="fieldAttrs"
            :value="modelValue"
            :disabled="disabled"
            :required="required"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? `${id}-error` : (hint ? `${id}-hint` : undefined)"
            :class="fieldClasses"
            @change="update($event.target.value)"
        >
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>

        <p v-if="error" :id="`${id}-error`" class="text-xs text-rose-600 dark:text-rose-400">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>
    </div>
</template>
