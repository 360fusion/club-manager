<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PaymentSummary from '@/Components/Events/PaymentSummary.vue';
import SiteShell from '@/Components/Site/SiteShell.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
  club: Object,
  site: { type: Object, default: () => ({}) },
  navigation: { type: Array, default: () => [] },
  footerNavigation: { type: Array, default: () => [] },
  expired: Boolean,
  token: String,
  event: Object,
  contactName: String,
});

const page = usePage();
const flash = computed(() => page.props.flash?.success);
const error = computed(() => page.props.errors?.registration || page.props.errors?.payment);
const booking = computed(() => props.event?.user_rsvp);
const cancelled = computed(() => booking.value?.attendance_status === 'cancelled');
const waiting = computed(() => booking.value?.attendance_status === 'waitlisted');

const paying = ref(false);
const payNow = (option = null) => {
  paying.value = true;
  router.post(route('public.event.booking.pay', { clubSlug: props.club.slug, token: props.token }), { option }, { onFinish: () => { paying.value = false; } });
};

// Stripe and PayPal send people back here with ?payment=success or ?payment=cancelled.
const returned = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('payment') : null;

const cancelBooking = () => {
  if (confirm('Cancel this booking? This cannot be undone.')) {
    router.post(route('public.event.booking.cancel', { clubSlug: props.club.slug, token: props.token }), {}, { preserveScroll: true });
  }
};
</script>

<template>
  <Head :title="`Your booking - ${club.name}`" />

  <SiteShell :club="club" :site="site" :navigation="navigation" :footer-navigation="footerNavigation" v-slot="{ theme }">
    <div class="mx-auto max-w-5xl px-6 py-10 space-y-6">
      <Link :href="`/site/${club.slug}`" :class="['text-xs font-semibold hover:underline', theme.accentText]">&larr; {{ club.name }}</Link>

      <div v-if="expired" :class="['border p-6 space-y-2', theme.radiusLg || 'rounded-2xl', theme.cardBg]">
        <h1 :class="['text-xl font-black', theme.headingText]">This link isn't valid</h1>
        <p :class="['text-sm', theme.bodyText]">It may have been replaced by a newer email, or mistyped. If you booked again, use the link in the most recent email. Otherwise please contact {{ club.name }}.</p>
      </div>

      <template v-else>
        <Alert v-if="flash" variant="success">{{ flash }}</Alert>
        <Alert v-if="returned === 'success'" variant="success">Thank you. Your payment is being confirmed and will show here in a moment. Refresh if it hasn't yet.</Alert>
        <Alert v-else-if="returned === 'cancelled'" variant="warning">Payment cancelled. You have not been charged, and you can pay any time from this page.</Alert>
        <Alert v-if="error" variant="danger" role="alert">{{ error }}</Alert>

        <div :class="['border p-6 space-y-4', theme.radiusLg || 'rounded-2xl', theme.cardBg]">
          <div>
            <p :class="['text-xs font-bold uppercase tracking-wider', theme.accentText]">Your booking</p>
            <h1 :class="['mt-1 text-2xl sm:text-3xl font-black', theme.headingText]">{{ event.title }}</h1>
            <Badge v-if="cancelled" variant="danger" class="mt-2">Cancelled</Badge>
            <Badge v-else-if="waiting" variant="warning" class="mt-2">On the waiting list</Badge>
            <Badge v-else variant="success" class="mt-2">Confirmed</Badge>
          </div>

          <div :class="['space-y-1 text-sm', theme.bodyText]">
            <div class="flex items-start gap-2"><UiIcon name="calendar" class="mt-0.5 opacity-70" /><span>{{ event.starts_at }}</span></div>
            <div v-if="event.location" class="flex items-start gap-2"><UiIcon name="building" class="mt-0.5 opacity-70" /><span>{{ event.location }}</span></div>
            <div>Booked by {{ contactName }}</div>
          </div>

          <div class="border-t border-current/10 pt-4">
            <h2 :class="['text-sm font-bold', theme.headingText]">Who is coming</h2>
            <ul class="mt-2 space-y-2 text-sm">
              <li v-for="person in booking.attendees" :key="person.id">
                <span :class="['font-semibold', theme.headingText]">{{ person.name }}</span>
                <span v-if="person.is_guest" class="ml-1 text-[11px] opacity-60">(guest)</span>
                <span v-if="person.attending_dining" class="block text-xs opacity-70">{{ [person.meal.starter, person.meal.main, person.meal.dessert].filter(Boolean).join(' · ') }}</span>
                <span v-if="person.dietary_requirements" class="block text-xs text-amber-700 dark:text-amber-300">Dietary: {{ person.dietary_requirements }}</span>
              </li>
            </ul>
          </div>

          <PaymentSummary v-if="booking.payment && !cancelled" :payment="booking.payment" :paying="paying" @pay="payNow" />

          <p v-if="event.cancellation_policy" class="border-t border-current/10 pt-4 text-xs opacity-70">{{ event.cancellation_policy }}</p>

          <Button v-if="!cancelled" variant="danger" size="sm" @click="cancelBooking">Cancel my booking</Button>
        </div>
      </template>
    </div>
  </SiteShell>
</template>
