<script setup>
// The website builder's "unsaved changes" dialog, shown when leaving a page with edits; the answers are handled by useLeaveGuard.
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
    open: { type: Boolean, default: false },
    form: { type: Object, required: true },
});

const emit = defineEmits(['save', 'discard', 'stay']);
const stay = () => emit('stay');
</script>

<template>
    <!-- Unsaved Changes Interception Modal -->
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm animate-fade-in"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="unsaved-changes-modal-title"
                v-focus-trap="stay"
                class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-md w-full p-4 sm:p-6 space-y-5 relative overflow-hidden"
            >
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-full bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/50 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                        <UiIcon name="warning" :size="20" />
                    </div>
                    <div class="space-y-1 pt-0.5">
                        <h3 id="unsaved-changes-modal-title" class="text-base font-semibold text-slate-900 dark:text-white leading-tight">Unsaved changes</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            You have modified <strong class="text-slate-900 dark:text-white font-semibold">"{{ form.title || 'this page' }}"</strong>.
                            What would you like to do with your changes before leaving?
                        </p>
                    </div>
                </div>

                <div class="space-y-2 pt-2">
                    <Button block :disabled="form.processing" @click="emit('save')">
                        <template #icon><UiIcon name="save" /></template>
                        <span>{{ form.processing ? 'Saving changes...' : 'Save changes & proceed' }}</span>
                        <span class="rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-medium">Recommended</span>
                    </Button>

                    <Button block variant="danger" :disabled="form.processing" @click="emit('discard')">
                        <template #icon><UiIcon name="trash" /></template>
                        Discard changes & switch page
                    </Button>

                    <Button block variant="ghost" :disabled="form.processing" @click="stay">
                        <template #icon><UiIcon name="pencil" /></template>
                        Stay & continue editing
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
