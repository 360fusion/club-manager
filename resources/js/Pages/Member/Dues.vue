<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MemberLayout from '@/Layouts/MemberLayout.vue';

const props = defineProps({
  club: { type: Object, required: true },
  memberRole: { type: String, default: 'member' },
  memberNumber: { type: String, default: 'MEM-1001' },
  plans: { type: Array, default: () => [] },
  invoices: { type: Array, default: () => [] },
});
</script>

<template>
  <MemberLayout title="My Dues & Receipts" :club="club" :member-role="memberRole" active-tab="dues">
    
    <div class="space-y-6">
      
      <!-- Top Action & Summary Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-xl font-bold text-slate-900">Membership Dues & Billing</h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
              Good Standing #{{ memberNumber }}
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">Review active membership dues, download receipts, and view payment transaction history.</p>
        </div>
      </div>

      <!-- Membership Plans Grid -->
      <div class="space-y-4">
        <h3 class="text-base font-bold text-slate-900">Available Membership Plans</h3>
        
        <div v-if="plans.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="plan in plans"
            :key="plan.id"
            class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 hover:border-emerald-300 transition-all flex flex-col justify-between space-y-6"
          >
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200 tracking-wider">
                  {{ plan.billing_period }}
                </span>
                <span class="text-2xl font-black text-slate-900">£{{ plan.price }}</span>
              </div>

              <h4 class="text-lg font-bold text-slate-900">{{ plan.name }}</h4>
              <p class="text-xs text-slate-500">{{ plan.description }}</p>
            </div>

            <div class="pt-4 border-t border-slate-100">
              <span class="block w-full text-center py-2 bg-emerald-50 text-emerald-700 font-bold text-xs rounded-xl border border-emerald-200">
                Active Subscription Tier
              </span>
            </div>
          </div>
        </div>

        <div v-else class="bg-white rounded-2xl p-8 text-center border border-slate-200/80 text-slate-500 text-xs">
          No membership plans listed.
        </div>
      </div>

      <!-- Payment Receipts & Invoices Table -->
      <div class="space-y-4 pt-4">
        <h3 class="text-base font-bold text-slate-900">Payment Receipts & Invoices</h3>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                  <th class="py-3.5 px-6">Invoice #</th>
                  <th class="py-3.5 px-4">Title / Description</th>
                  <th class="py-3.5 px-4">Amount</th>
                  <th class="py-3.5 px-4">Status</th>
                  <th class="py-3.5 px-4">Date Paid</th>
                  <th class="py-3.5 px-6 text-right">Receipt</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-slate-50/50 transition-colors">
                  <td class="py-4 px-6 font-mono font-bold text-slate-900">{{ inv.invoice_number }}</td>
                  <td class="py-4 px-4 font-semibold text-slate-800">{{ inv.title }}</td>
                  <td class="py-4 px-4 font-black text-slate-900">£{{ inv.amount }}</td>
                  <td class="py-4 px-4">
                    <span
                      :class="[
                        'px-2.5 py-0.5 rounded-full text-[11px] font-bold border uppercase tracking-wider',
                        inv.status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'
                      ]"
                    >
                      {{ inv.status }}
                    </span>
                  </td>
                  <td class="py-4 px-4 text-slate-500">{{ inv.paid_at || inv.created_at }}</td>
                  <td class="py-4 px-6 text-right">
                    <a
                      :href="route('invoices.download', { slug: club.slug, id: inv.id })"
                      target="_blank"
                      class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 text-xs font-bold rounded-lg transition-all inline-flex items-center gap-1"
                    >
                      📄 PDF Receipt
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
        </div>
      </div>

    </div>

  </MemberLayout>
</template>
