<script setup>
// A labelled text field. Other attributes (step, min, autocomplete...) go to the <input>; `class` stays on the wrapper.
// `prefix` shows a fixed mark inside the field, such as the currency symbol.
import { computed, useAttrs, useId } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    prefix: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const attrs = useAttrs();
const id = useId();
const fieldAttrs = computed(() => {
    const { class: _class, style: _style, ...rest } = attrs;
    return rest;
});

const fieldClasses = computed(() => [
    'w-full rounded-xl border py-2 text-sm transition-colors',
    props.prefix ? 'pl-8 pr-3' : 'px-3',
    'bg-white text-slate-900 placeholder-slate-400',
    'dark:bg-slate-950 dark:text-white dark:placeholder-slate-500',
    'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
    'disabled:opacity-50 disabled:cursor-not-allowed',
    props.error
        ? 'border-rose-400 dark:border-rose-700'
        : 'border-slate-300 dark:border-slate-700',
]);
</script>

<template>
    <div :class="['space-y-1.5', $attrs.class]" :style="$attrs.style">
        <label v-if="label" :for="id" class="block text-xs font-medium text-slate-700 dark:text-slate-300">
            {{ label }}
            <span v-if="required" class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span>
        </label>

        <div class="relative">
            <span v-if="prefix" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500 dark:text-slate-400" aria-hidden="true">{{ prefix }}</span>
            <input
                :id="id"
                v-bind="fieldAttrs"
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                :disabled="disabled"
                :required="required"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? `${id}-error` : (hint ? `${id}-hint` : undefined)"
                :class="fieldClasses"
                @input="$emit('update:modelValue', $event.target.value)"
            />
        </div>

        <p v-if="error" :id="`${id}-error`" class="text-xs text-rose-600 dark:text-rose-400">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>
    </div>
</template>
