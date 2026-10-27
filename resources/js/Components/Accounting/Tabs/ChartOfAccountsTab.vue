<script setup>
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// The Chart of Accounts tab of the accounting screen: the nominal ledger with each account's balance and its opening-balance shortcut.
defineProps({
    accounts: { type: Array, default: () => [] },
    getTypeBadge: { type: Function, required: true },
    openOpeningBalanceModal: { type: Function, required: true }
});

const showAccountModal = defineModel('showAccountModal', { type: Boolean, default: false });
</script>

<template>
  <div class="space-y-6">
    <Card padding="none" class="overflow-hidden">
      <div class="p-6 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h3 class="text-base font-semibold text-slate-900 dark:text-white">Chart of accounts</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">The nominal ledger accounts your club's books are kept in.</p>
        </div>
        <div class="flex items-center gap-2">
          <Button variant="secondary" size="sm" @click="openOpeningBalanceModal()">Set opening balances</Button>
          <Button size="sm" @click="showAccountModal = true">
            <template #icon><UiIcon name="plus" /></template>
            Add account
          </Button>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="py-3 px-6 font-medium">Code</th>
              <th scope="col" class="py-3 px-6 font-medium">Account name</th>
              <th scope="col" class="py-3 px-6 font-medium">Account type</th>
              <th scope="col" class="py-3 px-6 font-medium text-right">Current balance</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200">
            <tr v-for="acc in accounts" :key="acc.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
              <td class="py-3 px-6 tabular-nums text-slate-500 dark:text-slate-400">{{ acc.code }}</td>
              <td class="py-3 px-6 font-medium text-slate-900 dark:text-white">{{ acc.name }}</td>
              <td class="py-3 px-6">
                <span :class="['inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-medium capitalize border', getTypeBadge(acc.type)]">
                  {{ acc.type }}
                </span>
              </td>
              <td class="py-3 px-6 text-right font-medium text-slate-900 dark:text-white tabular-nums">
                <div class="flex items-center justify-end gap-3">
                  <span>{{ acc.formatted_balance }}</span>
                  <Button
                    variant="ghost"
                    size="sm"
                    title="Set the opening or carry-over balance for this account"
                    @click="openOpeningBalanceModal(acc.id)"
                  >
                    Carry over
                  </Button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>
  </div>
</template>
