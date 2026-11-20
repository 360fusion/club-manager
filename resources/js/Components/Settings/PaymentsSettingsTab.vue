<script setup>
// Payment gateways tab of the club settings: the Stripe account that takes the club's platform subscription, and a link
// to the lodge's payment options for events and bookings.
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Card from '@/Components/Ui/Card.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
    club: { type: Object, required: true },
    stripeConfigured: { type: Boolean, default: false },
    paymentOptionsUrl: { type: Function, required: true },
});
</script>

<template>
      <div class="space-y-6">
        <Alert title="Payment options for events and bookings">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <p>Bank transfer, card, PayPal, pay later and pay on the night. Set them up and switch them on once here, and every event offers them.</p>
            <a :href="paymentOptionsUrl('payments')" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Manage payment options</a>
          </div>
        </Alert>

        <Card>
          <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Subscription payments</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                  Stripe takes the club's subscription to the platform. Payments for events and bookings are set up separately, in Payment options above.
                </p>
              </div>

              <div class="flex items-center gap-3 flex-wrap">

                <a :href="route('billing.portal', { clubSlug: club.slug })" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                  <UiIcon name="settings" />
                  Customer portal
                </a>
              </div>
            </div>
          </template>

          <!-- Gateway Specs Grid -->
          <div class="grid grid-cols-1 gap-4">
            <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-slate-900 dark:text-white">Stripe</span>
                <Badge :variant="stripeConfigured ? 'success' : 'warning'">
                  {{ stripeConfigured ? 'Keys active' : 'Sandbox demo mode' }}
                </Badge>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                Direct card processing with Stripe Elements &amp; Cashier subscription webhooks. Ideal for direct card payments.
              </p>
            </div>
          </div>
        </Card>
      </div>
</template>
