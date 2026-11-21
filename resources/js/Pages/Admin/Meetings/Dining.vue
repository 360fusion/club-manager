<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Input from '@/Components/Ui/Input.vue';
import Modal from '@/Components/Ui/Modal.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import { formatMoney } from '@/Utils/currency';

const props = defineProps({
  club: { type: Object, required: true },
  meeting: { type: Object, required: true },
  diners: { type: Array, default: () => [] },
  totals: { type: Object, default: () => ({ heads: 0, expected: 0, collected: 0, outstanding: 0 }) },
  paymentMethods: { type: Array, default: () => ['bank', 'online', 'cash'] },
  refundable: { type: Array, default: () => [] },
  canRefund: { type: Boolean, default: false },
});

const METHOD_LABELS = {
  bank: 'Bank transfer',
  online: 'Online payment',
  cash: 'Cash on the night',
};

const statusVariant = (status) => ({ paid: 'success', waived: 'info', refunded: 'neutral' }[status] ?? 'warning');

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

// Refunding a place that is no longer dining: back through Stripe or PayPal when it was paid online.
const refunding = ref(null);
const refundForm = useForm({ kind: 'member', diner_id: null });
const refund = () => {
  refundForm.kind = refunding.value.kind;
  refundForm.diner_id = refunding.value.id;
  refundForm.post(route('admin.meetings.dining.refund', { clubSlug: props.club.slug, id: props.meeting.id }), {
    preserveScroll: true,
    onFinish: () => { refunding.value = null; },
  });
};

const outstandingCount = computed(() => props.diners.filter(d => !['paid', 'waived'].includes(d.payment_status)).length);
</script>

<template>
  <AdminLayout :title="`Dining — ${meeting.title}`" :club="club" active-tab="meetings">
    <Head :title="`Dining - ${meeting.title}`" />

    <div class="mx-auto max-w-6xl space-y-6">
      <Card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Dining list</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              {{ meeting.title }}<span v-if="meeting.meeting_date"> · {{ meeting.meeting_date }}</span>
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <a :href="route('admin.meetings.dining.caterer', { clubSlug: club.slug, id: meeting.id })" target="_blank" rel="noopener" class="inline-flex min-h-8 items-center rounded-xl border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Caterer sheet</a>
            <Button variant="secondary" size="sm" :href="route('admin.meetings.index', { clubSlug: club.slug })">All meetings</Button>
            <Button size="sm" :href="route('admin.meetings.show', { clubSlug: club.slug, id: meeting.id })">Secretary dashboard</Button>
          </div>
        </div>
      </Card>

      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard label="Heads for caterer" :value="totals.heads" />
        <StatCard label="Expected" :value="formatMoney(totals.expected)" />
        <StatCard label="Collected" :value="formatMoney(totals.collected)" />
        <StatCard label="Outstanding" :value="formatMoney(totals.outstanding)" :caption="`${outstandingCount} still to pay`" />
      </div>

      <Card padding="none" class="overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm text-slate-700 dark:text-slate-200">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
              <tr>
                <th scope="col" class="px-4 sm:px-6 py-3 font-medium">Diner</th>
                <th scope="col" class="px-4 py-3 font-medium">Dietary notes</th>
                <th scope="col" class="px-4 py-3 text-right font-medium">Fee</th>
                <th scope="col" class="px-4 py-3 font-medium">Payment</th>
                <th scope="col" class="relative px-4 py-3 text-right font-medium"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="diner in diners" :key="`${diner.kind}-${diner.id}`">
                <td class="px-4 sm:px-6 py-4">
                  <div class="font-medium text-slate-900 dark:text-white">{{ diner.name }}</div>
                  <div v-if="diner.kind === 'guest'" class="text-xs text-slate-500 dark:text-slate-400">
                    Guest of {{ diner.host || 'a member' }}
                  </div>
                </td>
                <td class="max-w-xs px-4 py-4 text-slate-600 dark:text-slate-300">
                  <span v-if="diner.dietary">{{ diner.dietary }}</span>
                  <span v-else class="text-slate-500 dark:text-slate-400">—</span>
                </td>
                <td class="px-4 py-4 text-right font-medium tabular-nums text-slate-900 dark:text-white">{{ formatMoney(diner.fee) }}</td>
                <td class="px-4 py-4">
                  <Badge v-if="diner.said_paid_at" variant="warning">Says paid by bank {{ diner.said_paid_at }}</Badge>
                  <Badge v-else :variant="statusVariant(diner.payment_status)" class="capitalize">{{ diner.payment_status }}</Badge>
                  <div v-if="diner.said_paid_at" class="mt-1 text-xs text-slate-500 dark:text-slate-400">Not seen in the bank yet</div>
                  <div v-if="diner.payment_method" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ METHOD_LABELS[diner.payment_method] || diner.payment_method }}<span v-if="diner.seen_in_bank"> · seen in the bank</span>
                  </div>
                  <div v-if="diner.paid_at" class="text-xs text-slate-500 dark:text-slate-400">
                    {{ diner.paid_at }}<span v-if="diner.paid_by"> · {{ diner.paid_by }}</span>
                  </div>
                </td>
                <td class="whitespace-nowrap px-4 py-4 text-right">
                  <Button v-if="!['paid', 'waived'].includes(diner.payment_status)" size="sm" @click="openPaymentModal(diner)">Mark as paid</Button>
                  <div v-else class="flex items-center justify-end gap-2">
                    <Button variant="secondary" size="sm" @click="openPaymentModal(diner)">Edit</Button>
                    <Button variant="secondary" size="sm" @click="markUnpaid(diner)">Undo</Button>
                  </div>
                </td>
              </tr>
              <tr v-if="!diners.length">
                <td colspan="5" class="space-y-1 py-12 text-center">
                  <p class="font-medium text-slate-700 dark:text-slate-200">Nobody has booked dining yet.</p>
                  <p class="text-sm text-slate-500 dark:text-slate-400">Heads appear here as members respond to the summons.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>

      <Card v-if="refundable.length">
        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Paid but no longer dining</h2>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">These places were paid for, then dropped. Refund them if the lodge is not being charged for them.</p>
        <p v-if="refundForm.errors.refund || $page.props.errors?.amount" class="mt-2 text-xs text-rose-700 dark:text-rose-400" role="alert">{{ refundForm.errors.refund || $page.props.errors.amount }}</p>
        <ul class="mt-3 divide-y divide-slate-100 text-sm dark:divide-slate-800">
          <li v-for="place in refundable" :key="`${place.kind}-${place.id}`" class="flex flex-wrap items-center justify-between gap-2 py-2">
            <span class="text-slate-700 dark:text-slate-200">
              {{ place.name }}<span v-if="place.host" class="text-slate-500 dark:text-slate-400"> (guest of {{ place.host }})</span>
              <span class="text-slate-500 dark:text-slate-400"> · {{ formatMoney(place.fee) }} · now {{ place.now }}</span>
            </span>
            <Button v-if="canRefund" variant="secondary" size="sm" @click="refunding = place">{{ place.online ? 'Refund' : 'Mark refunded' }}</Button>
          </li>
        </ul>
      </Card>
    </div>

    <ConfirmDialog :open="refunding !== null" :title="refunding?.online ? 'Refund this place?' : 'Mark this place refunded?'" :confirm-label="refunding?.online ? 'Refund' : 'Mark refunded'" :processing="refundForm.processing" @confirm="refund" @close="refunding = null">
      <template v-if="refunding?.online">{{ formatMoney(refunding?.fee) }} goes back to {{ refunding?.name }}'s card or PayPal account. This cannot be undone.</template>
      <template v-else>This was paid by bank transfer or cash, so the money goes back by hand. Mark {{ refunding?.name }}'s {{ formatMoney(refunding?.fee) }} as refunded?</template>
    </ConfirmDialog>

    <Modal :open="showPaymentModal" title="Record dining payment" :subtitle="`${activeDiner?.name} · ${formatMoney(activeDiner?.fee)}`" @close="closePaymentModal">
      <form id="dining-payment-form" class="space-y-4" @submit.prevent="submitPayment">
        <fieldset class="space-y-2">
          <legend class="mb-1.5 text-xs font-medium text-slate-700 dark:text-slate-300">How was it paid?</legend>
          <label
            v-for="method in paymentMethods"
            :key="method"
            :class="[
              'flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm font-medium transition-colors',
              paymentForm.payment_method === method
                ? 'border-blue-500 bg-blue-50 text-slate-900 dark:bg-blue-950/40 dark:text-white'
                : 'border-slate-200 text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:text-slate-200 dark:hover:border-slate-700'
            ]"
          >
            <input v-model="paymentForm.payment_method" type="radio" :value="method" class="h-4 w-4 accent-blue-600" />
            <span>{{ METHOD_LABELS[method] || method }}</span>
          </label>
          <p v-if="paymentForm.errors.payment_method" class="text-xs text-rose-600 dark:text-rose-400">{{ paymentForm.errors.payment_method }}</p>
        </fieldset>

        <Input v-model="paymentForm.payment_reference" label="Reference (optional)" placeholder="e.g. SUMMONS-18-SMITH" :error="paymentForm.errors.payment_reference" />
      </form>
      <template #footer>
        <Button variant="secondary" @click="closePaymentModal">Cancel</Button>
        <Button type="submit" form="dining-payment-form" :loading="paymentForm.processing">Approve</Button>
      </template>
    </Modal>
  </AdminLayout>
</template>
