<script setup>
// Club positions tab of the club settings. `form` is the page's form: this tab edits it in place and the page saves it.
// `positions` is the page's useClubPositions() (reactive), which keeps the list editing and removal state.
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import ToggleSwitch from '@/Components/Ui/ToggleSwitch.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
    form: { type: Object, required: true },
    positions: { type: Object, required: true },
});
</script>

<template>
      <div class="space-y-6">
        <Card>
          <template #header>
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Club positions and member ranks</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Configure customizable member positions, ranks, and skill levels to categorize, select, and filter club members across the platform.</p>
          </template>

          <div class="space-y-4">
            <div class="flex items-center justify-between gap-4">
              <div>
                <h3 class="text-sm font-medium text-slate-900 dark:text-white">Member ranks and skill levels</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Enable customized member ranks (e.g. Novice, Captain, Veteran) to filter and select members across the platform.</p>
              </div>
              <ToggleSwitch v-model="form.enable_member_ranks" label="Member ranks and skill levels" on-text="On" off-text="Off" />
            </div>

            <div v-if="form.enable_member_ranks" class="space-y-4 pt-4 border-t border-slate-200 dark:border-slate-800">
              <div>
                <h3 class="text-sm font-medium text-slate-900 dark:text-white">Positions and ranks</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Define the positions or skill ranks available for members in your organization.</p>
              </div>

              <!-- Vertical Ranks List Container -->
              <div class="max-w-lg space-y-2">
                <div
                  v-for="(rank, idx) in form.member_ranks"
                  :key="idx"
                  class="flex items-center justify-between gap-2 p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl hover:border-slate-300 dark:hover:border-slate-700 transition-colors"
                >
                  <!-- Viewing State -->
                  <template v-if="positions.editingRankIndex !== idx">
                    <div class="flex items-center gap-3">
                      <!-- Reorder Controls -->
                      <div class="flex flex-col items-center justify-center gap-0.5">
                        <button
                          type="button"
                          @click="positions.moveRankUp(idx)"
                          :disabled="idx === 0"
                          title="Move up"
                          aria-label="Move up"
                          class="flex items-center justify-center rounded text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 disabled:opacity-20 disabled:hover:text-slate-400 disabled:hover:bg-transparent cursor-pointer transition-colors"
                        >
                          <UiIcon name="chevron-down" :size="14" class="rotate-180" />
                        </button>
                        <button
                          type="button"
                          @click="positions.moveRankDown(idx)"
                          :disabled="idx === form.member_ranks.length - 1"
                          title="Move down"
                          aria-label="Move down"
                          class="flex items-center justify-center rounded text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-slate-200/60 dark:hover:bg-slate-700/60 disabled:opacity-20 disabled:hover:text-slate-400 disabled:hover:bg-transparent cursor-pointer transition-colors"
                        >
                          <UiIcon name="chevron-down" :size="14" />
                        </button>
                      </div>

                      <span class="w-7 h-7 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/60 flex items-center justify-center">
                        <UiIcon name="award" :size="14" />
                      </span>
                      <span class="text-sm font-medium text-slate-900 dark:text-white">{{ rank }}</span>
                      <Badge v-if="positions.getMemberCountForRank(rank) > 0" variant="info">
                        {{ positions.getMemberCountForRank(rank) }} assigned
                      </Badge>
                    </div>

                    <div class="flex items-center gap-1.5">
                      <Button variant="secondary" size="sm" @click="positions.startEditRank(idx)">Edit</Button>
                      <Button variant="secondary" size="sm" @click="positions.initiateRemoveRank(idx)">Remove</Button>
                    </div>
                  </template>

                  <!-- Editing State -->
                  <template v-else>
                    <div class="flex items-center gap-2 w-full">
                      <span class="w-7 h-7 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center flex-shrink-0">
                        <UiIcon name="pencil" :size="14" />
                      </span>
                      <Input
                        v-model="positions.editingRankValue"
                        @keydown.enter.prevent="positions.saveEditRank(idx)"
                        @keydown.esc="positions.cancelEditRank"
                        aria-label="Position name"
                        class="flex-1"
                      />
                      <Button size="sm" variant="secondary" @click="positions.saveEditRank(idx)">Save</Button>
                      <Button size="sm" variant="ghost" @click="positions.cancelEditRank">Cancel</Button>
                    </div>
                  </template>
                </div>

                <div v-if="form.member_ranks.length === 0" class="p-4 text-center text-sm text-slate-500 dark:text-slate-400 border border-dashed border-slate-300 dark:border-slate-700 rounded-xl">
                  No positions configured yet. Add your first position below.
                </div>
              </div>

              <!-- Add New Rank Form Row -->
              <div class="flex gap-2 max-w-lg pt-1">
                <Input
                  v-model="positions.newRankInput"
                  @keydown.enter.prevent="positions.addRank"
                  placeholder="Add a position or rank (e.g. Captain, Coxswain, Senior)"
                  aria-label="New position or rank"
                  class="flex-1"
                />
                <Button variant="secondary" @click="positions.addRank">
                  <template #icon><UiIcon name="plus" /></template>
                  Add position
                </Button>
              </div>
            </div>
          </div>
        </Card>
      </div>
</template>
