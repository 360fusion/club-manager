<script setup>
// The website builder's "apply a new website theme?" confirmation; `theme` is the { key, label } being applied (null hides it).
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
    theme: { type: Object, default: null },
    themeForm: { type: Object, required: true },
});

const emit = defineEmits(['cancel', 'confirm']);
const cancel = () => emit('cancel');
</script>

<template>
    <!-- Theme Apply Confirmation Modal -->
    <Teleport to="body">
        <div
            v-if="theme"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm animate-fade-in"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="theme-confirm-modal-title"
                v-focus-trap="cancel"
                class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-md w-full p-4 sm:p-6 space-y-5 relative overflow-hidden"
            >
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                        <UiIcon name="palette" :size="20" />
                    </div>
                    <div class="space-y-1 pt-0.5">
                        <h3 id="theme-confirm-modal-title" class="text-base font-semibold text-slate-900 dark:text-white leading-tight">Apply a new website theme?</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            You are about to switch your active design to <strong class="text-slate-900 dark:text-white font-semibold">"{{ theme.label }}"</strong>.
                        </p>
                    </div>
                </div>

                <Alert variant="warning" title="Notice">
                    This will immediately change the visual appearance, color scheme, and layout of your live public site.
                    <strong>All your existing pages, news, events, and forms will stay intact.</strong>
                </Alert>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button variant="secondary" :disabled="themeForm.processing" @click="cancel">
                        Cancel
                    </Button>
                    <Button :disabled="themeForm.processing" @click="emit('confirm')">
                        <template #icon><UiIcon v-if="!themeForm.processing" name="sparkles" /></template>
                        {{ themeForm.processing ? 'Applying theme...' : 'Apply theme to live site' }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
