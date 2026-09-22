<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { currencySymbol } from '@/Utils/currency';

const props = defineProps({
  club: { type: Object, required: true },
  meeting: { type: Object, required: true },
  diners: { type: Array, default: () => [] },
  totals: { type: Object, default: () => ({ heads: 0, expected: 0, collected: 0, outstanding: 0 }) },
  paymentMethods: { type: Array, default: () => ['bank', 'online', 'cash'] },
});

const METHOD_LABELS = {
  bank: '🏦 Bank transfer',
  online: '💳 Online payment',
  cash: '💷 Cash on the night',
};

const money = (n) => `${currencySymbol()}${Number(n || 0).toFixed(2)}`;

const statusBadge = (status) => ({
  paid: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60',
  waived: 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/60',
  refunded: 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-800',
}[status] ?? 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60');

const showPaymentModal = ref(false);
const activeDiner = ref(null);

const paymentForm = useForm({
  kind: 'member',
  diner_id: null,
  payment_status: 'paid',
  payment_method: 'bank',
  payment_reference: '',
});

const openPaymentModal = (diner) => {
  activeDiner.value = diner;
  paymentForm.clearErrors();
  paymentForm.kind = diner.kind;
  paymentForm.diner_id = diner.id;
  paymentForm.payment_status = 'paid';
  paymentForm.payment_method = diner.payment_method || 'bank';
  paymentForm.payment_reference = diner.payment_reference || '';
  showPaymentModal.value = true;
};

const closePaymentModal = () => {
  showPaymentModal.value = false;
  activeDiner.value = null;
};

const submitPayment = () => {
  paymentForm.post(route('admin.meetings.dining.payment', { clubSlug: props.club.slug, id: props.meeting.id }), {
    preserveScroll: true,
    onSuccess: closePaymentModal,
  });
};

const markUnpaid = (diner) => {
  paymentForm.kind = diner.kind;
  paymentForm.diner_id = diner.id;
  paymentForm.payment_status = 'unpaid';
  paymentForm.payment_method = null;
  paymentForm.payment_reference = '';
  paymentForm.post(route('admin.meetings.dining.payment', { clubSlug: props.club.slug, id: props.meeting.id }), {
    preserveScroll: true,
  });
};

const outstandingCount = computed(() => props.diners.filter(d => !['paid', 'waived'].includes(d.payment_status)).length);
</script>

<template>
  <AdminLayout :title="`Dining — ${meeting.title}`" :club="club" active-tab="meetings">
    <Head :title="`Dining - ${meeting.title}`" />

    <div class="space-y-6 max-w-6xl mx-auto">

      <!-- Header -->
      <div class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">🍽️ Dining List</h2>
          <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
            {{ meeting.title }}<span v-if="meeting.meeting_date"> · {{ meeting.meeting_date }}</span>
          </p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
          <Link :href="route('admin.meetings.index', { clubSlug: club.slug })" class="py-2.5 px-4 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs border border-slate-300 dark:border-slate-700 transition-all">
            ← All meetings
          </Link>
          <Link :href="route('admin.meetings.show', { clubSlug: club.slug, id: meeting.id })" class="py-2.5 px-4 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-sm transition-all">
            📊 Secretary Dashboard
          </Link>
        </div>
      </div>

      <!-- Totals -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800/80 shadow-sm">
          <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Heads for caterer</div>
          <div class="text-2xl font-black text-slate-900 dark:text-white mt-1 tabular-nums">{{ totals.heads }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200/80 dark:border-slate-800/80 shadow-sm">
          <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Expected</div>
          <div class="text-2xl font-black text-slate-900 dark:text-white mt-1 tabular-nums">{{ money(totals.expected) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-emerald-200/80 dark:border-emerald-800/60 shadow-sm">
          <div class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Collected</div>
          <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300 mt-1 tabular-nums">{{ money(totals.collected) }}</div>
        </div>
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-amber-200/80 dark:border-amber-800/60 shadow-sm">
          <div class="text-[11px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Outstanding</div>
          <div class="text-2xl font-black text-amber-700 dark:text-amber-300 mt-1 tabular-nums">{{ money(totals.outstanding) }}</div>
          <div class="text-[11px] text-slate-400 mt-0.5">{{ outstandingCount }} still to pay</div>
        </div>
      </div>

      <!-- Diner table -->
      <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              <tr>
                <th class="py-3.5 px-6">Diner</th>
                <th class="py-3.5 px-4">Dietary notes</th>
                <th class="py-3.5 px-4 text-right">Fee</th>
                <th class="py-3.5 px-4">Payment</th>
                <th class="py-3.5 px-4 text-right"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="diner in diners" :key="`${diner.kind}-${diner.id}`" class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                <td class="py-4 px-6">
                  <div class="font-extrabold text-slate-900 dark:text-white text-sm">{{ diner.name }}</div>
                  <div v-if="diner.kind === 'guest'" class="text-[11px] text-slate-500 dark:text-slate-400">
                    Guest of {{ diner.host || 'a member' }}
                  </div>
                </td>
                <td class="py-4 px-4 text-slate-600 dark:text-slate-300 max-w-xs">
                  <span v-if="diner.dietary">{{ diner.dietary }}</span>
                  <span v-else class="text-slate-400">—</span>
                </td>
                <td class="py-4 px-4 text-right font-bold text-slate-900 dark:text-white tabular-nums">{{ money(diner.fee) }}</td>
                <td class="py-4 px-4">
                  <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border capitalize', statusBadge(diner.payment_status)]">
                    {{ diner.payment_status }}
                  </span>
                  <div v-if="diner.payment_method" class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                    {{ METHOD_LABELS[diner.payment_method] || diner.payment_method }}
                  </div>
                  <div v-if="diner.paid_at" class="text-[11px] text-slate-400">
                    {{ diner.paid_at }}<span v-if="diner.paid_by"> · {{ diner.paid_by }}</span>
                  </div>
                </td>
                <td class="py-4 px-4 text-right whitespace-nowrap">
                  <button
                    v-if="!['paid', 'waived'].includes(diner.payment_status)"
                    type="button"
                    @click="openPaymentModal(diner)"
                    class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition-colors cursor-pointer"
                  >
                    Mark as paid
                  </button>
                  <div v-else class="flex items-center justify-end gap-2">
                    <button type="button" @click="openPaymentModal(diner)" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-[11px] font-bold border border-slate-200 dark:border-slate-800 cursor-pointer">
                      Edit
                    </button>
                    <button type="button" @click="markUnpaid(diner)" class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-rose-600 dark:text-rose-400 text-[11px] font-bold border border-rose-200 dark:border-rose-800/60 cursor-pointer">
                      Undo
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!diners.length">
                <td colspan="5" class="py-12 text-center space-y-2">
                  <span class="text-2xl block">🍽️</span>
                  <p class="font-bold text-slate-700 dark:text-slate-200">Nobody has booked dining yet.</p>
                  <p class="text-xs text-slate-400">Heads appear here as members respond to the summons.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Record payment -->
    <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="dining-payment-title"
        v-focus-trap="closePaymentModal"
        class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-md w-full p-6 space-y-5"
      >
        <div class="flex items-start justify-between gap-4">
          <div>
            <h3 id="dining-payment-title" class="text-lg font-bold text-slate-900 dark:text-white">Record dining payment</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              {{ activeDiner?.name }} · {{ money(activeDiner?.fee) }}
            </p>
          </div>
          <button type="button" @click="closePaymentModal" aria-label="Close" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg font-bold cursor-pointer">✕</button>
        </div>

        <form @submit.prevent="submitPayment" class="space-y-4">
          <fieldset class="space-y-2">
            <legend class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">How was it paid?</legend>
            <label
              v-for="method in paymentMethods"
              :key="method"
              :class="[
                'flex items-center gap-3 p-3 rounded-2xl border cursor-pointer transition-all text-sm font-semibold',
                paymentForm.payment_method === method
                  ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50/50 dark:bg-blue-950/30 text-slate-900 dark:text-white'
                  : 'border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 hover:border-slate-300 dark:hover:border-slate-700'
              ]"
            >
              <input type="radio" :value="method" v-model="paymentForm.payment_method" class="w-4 h-4 accent-blue-600" />
              <span>{{ METHOD_LABELS[method] || method }}</span>
            </label>
            <p v-if="paymentForm.errors.payment_method" class="text-xs text-rose-500 font-semibold">{{ paymentForm.errors.payment_method }}</p>
          </fieldset>

          <div>
            <label for="dining-payment-reference" class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Reference (optional)</label>
            <input
              id="dining-payment-reference"
              v-model="paymentForm.payment_reference"
              type="text"
              placeholder="e.g. SUMMONS-18-SMITH"
              class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div class="flex justify-end gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
            <button type="button" @click="closePaymentModal" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl cursor-pointer">Cancel</button>
            <button type="submit" :disabled="paymentForm.processing" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md disabled:opacity-60 cursor-pointer">
              {{ paymentForm.processing ? 'Saving…' : '✓ Approve' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
