<script setup>
// The warning before a club position is removed (and what happens to the members holding it), and the undo notice
// after. `positions` is the page's useClubPositions() (reactive); `form` is the page's form.
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
    form: { type: Object, required: true },
    positions: { type: Object, required: true },
});
</script>

<template>
    <ConfirmDialog
      :open="positions.showDeleteWarningModal"
      title="Remove this position?"
      confirm-label="Remove position"
      @confirm="positions.confirmDeleteRank"
      @close="positions.showDeleteWarningModal = false"
    >
      <div v-if="positions.pendingDeleteRank" class="space-y-4">
        <!-- Case 1: Assigned to active members -->
        <template v-if="positions.pendingDeleteRank.affectedMembers.length > 0">
          <Alert variant="warning" :title="`“${positions.pendingDeleteRank.rank}” is assigned to ${positions.pendingDeleteRank.affectedMembers.length} member(s).`">
            Affected members: <span class="font-medium">{{ positions.pendingDeleteRank.affectedMembers.map(m => m.name).slice(0, 4).join(', ') }}{{ positions.pendingDeleteRank.affectedMembers.length > 4 ? ` +${positions.pendingDeleteRank.affectedMembers.length - 4} more` : '' }}</span>.
          </Alert>

          <Select
            v-model="positions.pendingDeleteRank.reassignRank"
            label="What happens to these members"
            :options="[
              { value: '', label: 'Clear their position' },
              ...form.member_ranks.filter(r => r !== positions.pendingDeleteRank.rank).map(r => ({ value: r, label: `Reassign to ${r}` })),
            ]"
          />
        </template>

        <!-- Case 2: 0 members assigned -->
        <template v-else>
          <p>
            Remove the position <span class="font-medium text-slate-900 dark:text-white">“{{ positions.pendingDeleteRank.rank }}”</span>?
          </p>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            This position will be removed from your club's active positions list. You can undo this action immediately after deleting.
          </p>
        </template>
      </div>
    </ConfirmDialog>

    <!-- Toast Banner: Undo Position Deletion -->
    <div
      v-if="positions.showUndoToast && positions.lastDeletedRank"
      role="status"
      class="fixed bottom-6 right-6 z-50 bg-slate-900 text-white px-4 sm:px-5 py-3.5 rounded-2xl shadow-2xl border border-slate-700 flex items-center justify-between gap-4 text-sm"
    >
      <div class="flex items-center gap-2.5">
        <UiIcon name="trash" class="text-slate-500 dark:text-slate-400" />
        <span>Position <strong class="font-semibold">“{{ positions.lastDeletedRank.rank }}”</strong> was removed.</span>
      </div>
      <div class="flex items-center gap-3">
        <Button size="sm" @click="positions.undoDeleteRank">
          <template #icon><UiIcon name="restore" :size="14" /></template>
          Undo
        </Button>
        <button
          type="button"
          @click="positions.showUndoToast = false"
          aria-label="Dismiss"
          class="rounded-xl p-1 text-slate-500 dark:text-slate-400 hover:text-white cursor-pointer"
        >
          <UiIcon name="x" />
        </button>
      </div>
    </div>
</template>
