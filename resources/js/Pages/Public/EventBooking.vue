<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PaymentSummary from '@/Components/Events/PaymentSummary.vue';

const props = defineProps({
  club: Object,
  expired: Boolean,
  token: String,
  event: Object,
  contactName: String,
});

const page = usePage();
const flash = computed(() => page.props.flash?.success);
const error = computed(() => page.props.errors?.registration);
const booking = computed(() => props.event?.user_rsvp);
const cancelled = computed(() => booking.value?.attendance_status === 'cancelled');
const waiting = computed(() => booking.value?.attendance_status === 'waitlisted');

const cancelBooking = () => {
  if (confirm('Cancel this booking? This cannot be undone.')) {
    router.post(route('public.event.booking.cancel', { clubSlug: props.club.slug, token: props.token }), {}, { preserveScroll: true });
  }
};
</script>

<template>
  <Head :title="`Your booking - ${club.name}`" />

  <div class="min-h-screen bg-slate-950 text-slate-200 font-sans">
    <div class="mx-auto max-w-2xl px-4 py-8 space-y-6">
      <Link :href="`/site/${club.slug}`" class="text-xs font-semibold text-slate-400 hover:text-white">&larr; {{ club.name }}</Link>

      <div v-if="expired" class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 space-y-2">
        <h1 class="text-xl font-black text-white">This link isn't valid</h1>
        <p class="text-sm text-slate-400">It may have been replaced by a newer email, or mistyped. If you booked again, use the link in the most recent email. Otherwise please contact {{ club.name }}.</p>
      </div>

      <template v-else>
        <div v-if="flash" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-200" role="status">{{ flash }}</div>
        <div v-if="error" class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-300" role="alert">{{ error }}</div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 space-y-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-blue-400">Your booking</p>
            <h1 class="mt-1 text-2xl font-black text-white">{{ event.title }}</h1>
            <span v-if="cancelled" class="mt-2 inline-block rounded bg-rose-500/20 px-2 py-0.5 text-xs font-bold text-rose-300">Cancelled</span>
            <span v-else-if="waiting" class="mt-2 inline-block rounded bg-blue-500/20 px-2 py-0.5 text-xs font-bold text-blue-300">On the waiting list</span>
            <span v-else class="mt-2 inline-block rounded bg-emerald-500/20 px-2 py-0.5 text-xs font-bold text-emerald-300">Confirmed</span>
          </div>

          <div class="space-y-1 text-sm text-slate-300">
            <div>📅 {{ event.starts_at }}</div>
            <div v-if="event.location">📍 {{ event.location }}</div>
            <div>Booked by {{ contactName }}</div>
          </div>

          <div class="border-t border-slate-800 pt-4">
            <h2 class="text-sm font-bold text-white">Who is coming</h2>
            <ul class="mt-2 space-y-2 text-sm">
              <li v-for="person in booking.attendees" :key="person.id">
                <span class="font-semibold text-white">{{ person.name }}</span>
                <span v-if="person.is_guest" class="ml-1 text-[11px] text-slate-500">(guest)</span>
                <span v-if="person.attending_dining" class="block text-xs text-slate-400">{{ [person.meal.starter, person.meal.main, person.meal.dessert].filter(Boolean).join(' · ') }}</span>
                <span v-if="person.dietary_requirements" class="block text-xs text-amber-300">Dietary: {{ person.dietary_requirements }}</span>
              </li>
            </ul>
          </div>

          <PaymentSummary v-if="booking.payment && !cancelled" :payment="booking.payment" tone="dark" />

          <p v-if="event.cancellation_policy" class="border-t border-slate-800 pt-4 text-xs text-slate-400">{{ event.cancellation_policy }}</p>

          <button v-if="!cancelled" type="button" class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-2 text-xs font-bold text-rose-300 hover:bg-rose-500/20" @click="cancelBooking">Cancel my booking</button>
        </div>
      </template>
    </div>
  </div>
</template>
