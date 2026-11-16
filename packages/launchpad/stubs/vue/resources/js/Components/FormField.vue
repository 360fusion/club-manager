<script setup>
// One labelled input with its error, as Launchpad's Blade <x-launchpad::field> draws it.
defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    error: { type: String, default: null },
    autocomplete: { type: String, default: null },
    required: { type: Boolean, default: true },
    inputmode: { type: String, default: null },
    autofocus: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="lp-field">
        <label :for="id">{{ label }}</label>
        <input
            :id="id"
            :name="id"
            :type="type"
            :value="modelValue"
            :autocomplete="autocomplete"
            :required="required"
            :inputmode="inputmode"
            :autofocus="autofocus"
            :aria-invalid="error ? 'true' : null"
            :aria-describedby="error ? `${id}-error` : null"
            @input="$emit('update:modelValue', $event.target.value)"
        >
        <p v-if="error" :id="`${id}-error`" class="lp-error">{{ error }}</p>
    </div>
</template>
