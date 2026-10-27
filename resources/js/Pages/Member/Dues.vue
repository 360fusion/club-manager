<script setup>
import MembersLayout from '@/Layouts/MembersLayout.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';

defineProps({
  club: { type: Object, required: true },
  memberRole: { type: String, default: 'member' },
  memberNumber: { type: String, default: 'MEM-1001' },
  plans: { type: Array, default: () => [] },
  invoices: { type: Array, default: () => [] },
});
</script>

<template>
  <MembersLayout title="My dues and receipts" :club="club" :member-role="memberRole" active-tab="dues">

    <div class="space-y-6">

      <!-- Top Action & Summary Header -->
      <Card class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Membership dues and billing</h2>
            <Badge variant="success">Good standing #{{ memberNumber }}</Badge>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Review active membership dues, download receipts, and view payment transaction history.</p>
        </div>
      </Card>

      <!-- Membership Plans Grid -->
      <div class="space-y-4">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Available membership plans</h3>

        <div v-if="plans.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <Card
            v-for="plan in plans"
            :key="plan.id"
            class="flex flex-col justify-between space-y-6"
          >
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <Badge class="uppercase tracking-wider">{{ plan.billing_period }}</Badge>
                <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ $cs }}{{ plan.price }}</span>
              </div>

              <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ plan.name }}</h4>
              <p class="text-xs text-slate-500 dark:text-slate-400">{{ plan.description }}</p>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-800">
              <Badge variant="success">Active subscription tier</Badge>
            </div>
          </Card>
        </div>

        <Card v-else class="text-center text-slate-500 dark:text-slate-400 text-xs">
          No membership plans listed.
        </Card>
      </div>

      <!-- Payment Receipts & Invoices Table -->
      <div class="space-y-4 pt-4">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">Payment receipts and invoices</h3>

        <Card padding="none" class="overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50/80 dark:bg-slate-800/80 border-b border-slate-200/80 dark:border-slate-800/80 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                  <th class="py-3.5 px-6">Invoice #</th>
                  <th class="py-3.5 px-4">Title or description</th>
                  <th class="py-3.5 px-4">Amount</th>
                  <th class="py-3.5 px-4">Status</th>
                  <th class="py-3.5 px-4">Date paid</th>
                  <th class="py-3.5 px-6 text-right">Receipt</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs font-medium text-slate-700 dark:text-slate-200">
                <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                  <td class="py-4 px-6 font-mono font-semibold text-slate-900 dark:text-white">{{ inv.invoice_number }}</td>
                  <td class="py-4 px-4 font-semibold text-slate-800 dark:text-slate-100">{{ inv.title }}</td>
                  <td class="py-4 px-4 font-semibold text-slate-900 dark:text-white">{{ $cs }}{{ inv.amount }}</td>
                  <td class="py-4 px-4">
                    <Badge :variant="inv.status === 'paid' ? 'success' : 'warning'" class="capitalize">{{ inv.status }}</Badge>
                  </td>
                  <td class="py-4 px-4 text-slate-500 dark:text-slate-400">{{ inv.paid_at || inv.created_at }}</td>
                  <td class="py-4 px-6 text-right">
                    <a
                      :href="route('invoices.download', { slug: club.slug, id: inv.id })"
                      target="_blank"
                      class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                      <UiIcon name="document" /> PDF receipt
                    </a>
                  </td>
                </tr>

                <tr v-if="invoices.length === 0">
                  <td colspan="6" class="py-10 text-center text-slate-400 text-xs">
                    No billing receipts found for this club.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>
      </div>

    </div>

  </MembersLayout>
</template>
