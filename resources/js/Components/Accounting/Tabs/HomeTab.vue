<script setup>
import { formatMoney } from '@/Utils/currency';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// The Home tab of the accounting screen: set-up checklist, headline figures, balance sheet and profit & loss previews, and the Gift Aid summary.
defineProps({
    summary: { type: Object, required: true },
    onboarding: { type: Object, default: undefined },
    reconciliation: { type: Object, default: undefined },
    showOnboarding: { type: Boolean, default: false },
    dismissOnboarding: { type: Function, required: true },
    navigateTo: { type: Function, required: true }
});
</script>

<template>
  <div class="space-y-6">
      <!-- Guided first-run setup checklist -->
      <div v-if="showOnboarding" class="bg-blue-50 dark:bg-blue-950/30 rounded-2xl border border-blue-200 dark:border-blue-800/60 shadow-sm p-6 space-y-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h3 class="text-base font-semibold text-blue-900 dark:text-blue-100">Get your accounting set up</h3>
            <p class="text-xs text-blue-700 dark:text-blue-300 mt-0.5">A few quick steps before the figures below mean anything.</p>
          </div>
          <Button variant="ghost" size="sm" @click="dismissOnboarding">
            <template #icon><UiIcon name="x" /></template>
            Dismiss
          </Button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <button
            v-for="step in onboarding.steps"
            :key="step.key"
            type="button"
            @click="navigateTo(step.tab)"
            class="flex items-center gap-3 p-3.5 bg-white dark:bg-slate-900 rounded-xl border border-blue-100 dark:border-blue-900/60 text-left hover:border-blue-300 dark:hover:border-blue-700 transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
          >
            <span
              v-if="step.done"
              class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white"
            ><UiIcon name="check" :size="16" /></span>
            <span v-else class="h-6 w-6 shrink-0 rounded-full border-2 border-slate-300 dark:border-slate-600" aria-hidden="true"></span>
            <span class="min-w-0 flex-1 text-sm font-medium text-slate-900 dark:text-white">{{ step.label }}</span>
            <span class="flex shrink-0 items-center gap-1.5">
              <Badge v-if="step.skippable" variant="neutral">Optional</Badge>
              <Badge :variant="step.done ? 'success' : 'neutral'">{{ step.done ? 'Done' : 'To do' }}</Badge>
            </span>
          </button>
        </div>
      </div>

      <!-- Headline figures -->
      <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <StatCard label="Total assets" :value="formatMoney(summary.total_assets)" caption="Bank and receivables" />
        <StatCard label="Liabilities" :value="formatMoney(summary.total_liabilities)" caption="Payables and prepayments" />
        <StatCard label="Club equity" :value="formatMoney(summary.total_equity)" caption="Retained reserves" />
        <StatCard label="Total income" :value="formatMoney(summary.total_revenue)" caption="Dues and tickets" />
        <StatCard label="Total expenses" :value="formatMoney(summary.total_expenses)" caption="Operational costs" />

        <!-- Net surplus / deficit, coloured by its sign and named in the caption -->
        <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm dark:bg-slate-900 dark:border-slate-800">
          <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Net income</p>
          <p class="mt-1 font-display text-2xl font-semibold lining-nums tabular-nums" :class="summary.net_income >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'">
            {{ formatMoney(summary.net_income) }}
          </p>
          <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ summary.net_income >= 0 ? 'Surplus' : 'Deficit' }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Balance sheet preview -->
        <Card>
          <template #header>
            <div class="flex items-center justify-between">
              <h3 class="text-base font-semibold text-slate-900 dark:text-white">Balance sheet summary</h3>
              <span class="text-xs text-slate-500 dark:text-slate-400">As of today</span>
            </div>
          </template>

          <dl class="space-y-2 text-sm">
            <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
              <dt class="text-slate-700 dark:text-slate-200">Total assets</dt>
              <dd class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatMoney(summary.total_assets) }}</dd>
            </div>

            <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
              <dt class="text-slate-700 dark:text-slate-200">Total liabilities</dt>
              <dd class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatMoney(summary.total_liabilities) }}</dd>
            </div>

            <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
              <dt class="text-slate-700 dark:text-slate-200">Total club equity</dt>
              <dd class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatMoney(summary.total_equity) }}</dd>
            </div>
          </dl>

          <div class="mt-4 p-3.5 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center justify-between gap-3">
            <div>
              <span class="font-medium text-slate-900 dark:text-white block text-sm">Accounting equation</span>
              <span class="text-xs text-slate-500 dark:text-slate-400 block">Assets = liabilities + equity</span>
            </div>
            <Badge variant="success"><UiIcon name="check" :size="16" />Balanced</Badge>
          </div>
        </Card>

        <!-- Profit and loss preview -->
        <Card>
          <template #header>
            <div class="flex items-center justify-between">
              <h3 class="text-base font-semibold text-slate-900 dark:text-white">Income statement (P&amp;L)</h3>
              <span class="text-xs text-slate-500 dark:text-slate-400">Year to date</span>
            </div>
          </template>

          <dl class="space-y-2 text-sm">
            <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
              <dt class="text-slate-700 dark:text-slate-200">Operating income and dues</dt>
              <dd class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatMoney(summary.total_revenue) }}</dd>
            </div>

            <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
              <dt class="text-slate-700 dark:text-slate-200">Operating expenses</dt>
              <dd class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatMoney(summary.total_expenses) }}</dd>
            </div>
          </dl>

          <div class="mt-4 p-3.5 border border-slate-200 dark:border-slate-800 rounded-xl flex items-center justify-between gap-3">
            <div>
              <span class="font-medium text-slate-900 dark:text-white block text-sm">Net operating surplus</span>
              <span class="text-xs text-slate-500 dark:text-slate-400 block">Income minus expenses</span>
            </div>
            <span class="text-base font-semibold tabular-nums" :class="summary.net_income >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400'">
              {{ formatMoney(summary.net_income) }}
            </span>
          </div>
        </Card>
      </div>

      <!-- Donations and Gift Aid summary -->
      <Card>
        <template #header>
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 shrink-0 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center border border-blue-100 dark:border-blue-900/40">
                <UiIcon name="gift" :size="20" />
              </div>
              <div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Donations and Gift Aid tax recovery</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">HMRC 25% tax relief and Relief Chest bank reconciliation</p>
              </div>
            </div>
            <Button variant="secondary" size="sm" class="self-start sm:self-auto" @click="navigateTo('reconciliation')">
              Manage claims and reconciliation
            </Button>
          </div>
        </template>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard
            label="Eligible donations"
            :value="reconciliation.gift_aid_summary?.formatted_total_donations || ($cs + '0.00')"
            :caption="`${reconciliation.gift_aid_summary?.total_eligible_count || 0} charity collections`"
          />
          <StatCard
            label="25% Gift Aid claimable"
            :value="reconciliation.gift_aid_summary?.formatted_claimable_gift_aid || ($cs + '0.00')"
            caption="Standard HMRC reclaim rate"
          />
          <StatCard
            label="Unclaimed relief"
            :value="reconciliation.gift_aid_summary?.formatted_unclaimed_gift_aid || ($cs + '0.00')"
            caption="Awaiting schedule export"
          />
          <StatCard
            label="Reconciled credits"
            :value="reconciliation.gift_aid_summary?.formatted_reconciled_gift_aid || ($cs + '0.00')"
            caption="Matched to bank deposits"
          />
        </div>
      </Card>
  </div>
</template>
