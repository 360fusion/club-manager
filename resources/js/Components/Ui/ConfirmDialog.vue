<script setup>
// "Are you sure?" before an action that is hard to undo, in place of the browser's confirm() box. The page owns the open
// state: `confirm` runs the action, `close` (Cancel, Escape, the backdrop) just closes it.
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    // 'danger' for deleting or removing something, 'primary' otherwise.
    variant: { type: String, default: 'danger' },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'close']);
</script>

<template>
    <Modal :open="open" :title="title" size="sm" @close="emit('close')">
        <div class="text-sm text-slate-700 dark:text-slate-300"><slot /></div>

        <template #footer>
            <Button variant="secondary" :disabled="processing" @click="emit('close')">{{ cancelLabel }}</Button>
            <Button :variant="variant" :loading="processing" @click="emit('confirm')">{{ confirmLabel }}</Button>
        </template>
    </Modal>
</template>
