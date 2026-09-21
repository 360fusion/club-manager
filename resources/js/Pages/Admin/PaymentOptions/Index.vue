<script setup>
import { computed, nextTick, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  methods: { type: Array, default: () => [] },
  bankDefaults: { type: Object, default: () => ({}) },
  stripeWebhookUrl: { type: String, default: '' },
  paypalWebhookUrl: { type: String, default: '' },
  platform: { type: Object, default: () => ({ enabled: false, account: null }) },
  onlinePaymentsLive: { type: Boolean, default: false },
  returnTo: { type: String, default: null },
  bankCodeLabel: { type: String, default: 'Sort code' },
});

const TYPES = [
  { value: 'bank_transfer', icon: '🏦', label: 'Bank transfer', help: 'People pay into your bank account using a reference. You mark them paid once you have checked the account.', fee: false },
  { value: 'card_online', icon: '💳', label: 'Pay online by card', help: 'People pay on Stripe\'s secure page with your own Stripe account, and the money goes straight to you.', fee: false },
  { value: 'paypal', icon: '🅿️', label: 'Pay online with PayPal', help: 'People pay on PayPal\'s secure page with your own PayPal business account, and the money goes straight to you.', fee: false },
  { value: 'pay_later', icon: '🗓️', label: 'Pay later', help: 'People book now and pay before a due date you set. They can pay any time before then.', fee: true },
  { value: 'cash_on_door', icon: '🚪', label: 'Pay on the night', help: 'People pay when they arrive.', fee: true },
];

// A new card option starts on "Connect with Stripe" when the platform offers it, otherwise on pasted keys.
const configFor = (type) => {
  if (type === 'bank_transfer') return { ...props.bankDefaults, reference_prefix: '' };
  if (type === 'card_online') return { stripe_mode: props.platform?.enabled ? 'connect' : 'keys' };
  if (type === 'paypal') return { paypal_mode: 'live' };
  return {};
};

const typeInfo = (value) => TYPES.find((t) => t.value === value) ?? TYPES[0];

const editing = ref(null); // null = closed, 0 = new, id = editing
const blank = (type = 'bank_transfer') => ({
  type,
  label: typeInfo(type).label,
  instructions: '',
  default_adjustment_kind: 'none',
  default_adjustment_mode: 'fixed',
  default_adjustment_amount: 0,
  default_adjustment_scope: 'per_person',
  due_days: 7,
  due_basis: 'before_event',
  is_active: true,
  config: configFor(type),
});

const form = useForm(blank());

const open = (method = null) => {
  form.clearErrors();
  const data = method
    ? { ...blank(method.type), ...method, config: { ...method.config, stripe_secret_key: '', stripe_webhook_secret: '', paypal_client_secret: '' } }
    : blank();
  form.defaults(data).reset();
  Object.assign(form, data);
  editing.value = method ? method.id : 0;
};

const panelRef = ref(null);
const showPanel = () => nextTick(() => panelRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }));

// One card per kind of option: the lodge's own row(s), or a "not set up" card for a kind it has not started.
const cards = computed(() => TYPES.flatMap((type) => {
  const rows = props.methods.filter((m) => m.type === type.value);
  return rows.length ? rows.map((m) => ({ key: `m${m.id}`, type, method: m })) : [{ key: `t${type.value}`, type, method: null }];
}));

const openNew = (type) => {
  form.clearErrors();
  const data = blank(type);
  form.defaults(data).reset();
  Object.assign(form, data);
  editing.value = 0;
  showPanel();
};

const edit = (card) => {
  if (card.method) open(card.method); else openNew(card.type.value);
  showPanel();
};

// Pay later and pay on the night need nothing more, so they switch on straight away; the others ask for their details first.
const toggle = (card) => {
  if (!card.method) {
    if (['pay_later', 'cash_on_door'].includes(card.type.value)) {
      router.post(route('admin.payment_options.store', { clubSlug: props.club.slug }), blank(card.type.value), { preserveScroll: true });
    } else {
      openNew(card.type.value);
    }
    return;
  }

  router.put(route('admin.payment_options.toggle', { clubSlug: props.club.slug, id: card.method.id }), { is_active: !card.method.is_active }, { preserveScroll: true });
};

const page = usePage();
const toggleError = (card) => (card.method ? page.props.errors?.[`option_${card.method.id}`] : null);

// The order people booking see the options in.
const move = (card, offset) => {
  const ids = props.methods.map((m) => m.id);
  const from = ids.indexOf(card.method.id);
  const to = from + offset;
  if (to < 0 || to >= ids.length) return;
  ids.splice(to, 0, ids.splice(from, 1)[0]);
  router.post(route('admin.payment_options.order', { clubSlug: props.club.slug }), { ids }, { preserveScroll: true });
};

const backLabel = computed(() => (props.returnTo?.includes('/admin/events') ? 'Back to event' : props.returnTo?.includes('/admin/settings') ? 'Back to settings' : 'Back'));

const chooseType = (value) => {
  form.type = value;
  form.label = typeInfo(value).label;
  form.config = configFor(value);
  if (!typeInfo(value).fee && form.default_adjustment_kind === 'fee') form.default_adjustment_kind = 'none';
};

const stripeError = computed(() => page.props.errors?.stripe);
const agreeTerms = ref(false);
const stripeAction = (name, data = {}) => {
  router.post(route(`admin.payment_options.stripe.${name}`, { clubSlug: props.club.slug }), data, { preserveScroll: true });
};
const disconnectStripe = () => {
  if (confirm('Disconnect Stripe? Card payments through the platform will stop until you connect again.')) stripeAction('disconnect');
};

const canFee = computed(() => typeInfo(form.type).fee);
const current = computed(() => props.methods.find((m) => m.id === editing.value));

const submit = () => {
  const options = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
  if (editing.value) form.put(route('admin.payment_options.update', { clubSlug: props.club.slug, id: editing.value }), options);
  else form.post(route('admin.payment_options.store', { clubSlug: props.club.slug }), options);
};

const remove = (method) => {
  if (confirm(`Remove "${method.label}"? If bookings used it, it is switched off instead.`)) {
    router.delete(route('admin.payment_options.destroy', { clubSlug: props.club.slug, id: method.id }), { preserveScroll: true, onSuccess: () => { editing.value = null; } });
  }
};

const summary = (m) => {
  if (m.default_adjustment_kind === 'none' || !Number(m.default_adjustment_amount)) return 'Standard price';
  const amount = m.default_adjustment_mode === 'percent' ? `${Number(m.default_adjustment_amount)}%` : `${Number(m.default_adjustment_amount).toFixed(2)}`;
  const scope = m.default_adjustment_mode === 'percent' ? '' : (m.default_adjustment_scope === 'per_person' ? ' per person' : ' per booking');
  return m.default_adjustment_kind === 'discount' ? `${amount} off${scope}` : `+ ${amount} fee${scope}`;
};

const statusLine = (card) => {
  const m = card.method;
  if (!m) return card.type.help;
  const parts = [summary(m)];
  if (m.type === 'pay_later' && m.due_days !== null) parts.push(`due ${m.due_days} days ${m.due_basis === 'before_event' ? 'before the event' : 'after booking'}`);
  return parts.join(' · ');
};

const input = 'w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500';
const label = 'block text-[11px] font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1';
</script>

<template>
  <AdminLayout title="Payment options" :club="club" active-tab="events">
    <Head title="Payment options" />

    <div class="max-w-4xl mx-auto space-y-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="max-w-2xl text-xs text-slate-500 dark:text-slate-400">Switch on the ways people can pay. Details are set here once and every event offers the options you switch on. Each event can then add a discount or fee to an option.</p>
        <Link :href="returnTo ?? route('admin.events.index', { clubSlug: club.slug })" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl">&larr; {{ returnTo ? backLabel : 'Events' }}</Link>
      </div>

      <ul class="space-y-3">
        <li v-for="card in cards" :key="card.key" class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80">
          <div class="flex flex-wrap items-center gap-3">
            <span class="text-2xl" aria-hidden="true">{{ card.type.icon }}</span>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-bold text-slate-900 dark:text-white text-sm">{{ card.method?.label ?? card.type.label }}</span>
                <span v-if="!card.method" class="text-[10px] font-bold uppercase tracking-wider rounded px-1.5 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-500">Not set up</span>
                <span v-else-if="!card.method.complete" class="text-[10px] font-bold uppercase tracking-wider rounded px-1.5 py-0.5 bg-amber-100 text-amber-800">Needs details</span>
                <span v-else-if="card.method.is_active" class="text-[10px] font-bold uppercase tracking-wider rounded px-1.5 py-0.5 bg-emerald-100 text-emerald-800">In use</span>
              </div>
              <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ statusLine(card) }}</div>
              <p v-if="toggleError(card)" class="mt-1 text-[11px] font-semibold text-rose-600" role="alert">{{ toggleError(card) }}</p>
            </div>

            <div class="flex items-center gap-2">
              <template v-if="card.method && methods.length > 1">
                <button type="button" class="rounded-lg border border-slate-200 px-2 py-1 text-xs text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" :aria-label="`Move ${card.method.label} up`" @click="move(card, -1)">&uarr;</button>
                <button type="button" class="rounded-lg border border-slate-200 px-2 py-1 text-xs text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" :aria-label="`Move ${card.method.label} down`" @click="move(card, 1)">&darr;</button>
              </template>
              <button type="button" class="rounded-lg border border-slate-200 p-2 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800" :aria-label="`Edit ${card.method?.label ?? card.type.label}`" title="Edit details" @click="edit(card)">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
              </button>
              <label class="flex cursor-pointer items-center gap-2 text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                <span>Use this option</span>
                <button type="button" role="switch" :aria-checked="!!card.method?.is_active" :aria-label="`Use ${card.method?.label ?? card.type.label}`" :class="['relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors', card.method?.is_active ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-700']" @click="toggle(card)">
                  <span :class="['inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform', card.method?.is_active ? 'translate-x-5' : 'translate-x-0.5']"></span>
                </button>
              </label>
            </div>
          </div>
        </li>
      </ul>

      <form v-if="editing !== null" ref="panelRef" class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-5" @submit.prevent="submit">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ editing ? 'Edit payment option' : 'New payment option' }}</h3>

        <p class="text-xs text-slate-500 dark:text-slate-400">{{ typeInfo(form.type).help }}</p>
        <p v-if="form.errors.type" class="text-[11px] font-semibold text-rose-600">{{ form.errors.type }}</p>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label :class="label">Name people see</label>
            <input v-model="form.label" type="text" maxlength="100" required :class="input" />
            <p v-if="form.errors.label" class="mt-1 text-[11px] font-semibold text-rose-600">{{ form.errors.label }}</p>
          </div>
        </div>

        <div>
          <label :class="label">Instructions shown to the person paying (optional)</label>
          <textarea v-model="form.instructions" rows="2" maxlength="2000" :class="input" placeholder="e.g. Please use your surname as the reference."></textarea>
        </div>

        <!-- Bank transfer -->
        <div v-if="form.type === 'bank_transfer'" class="grid gap-4 sm:grid-cols-2">
          <div><label :class="label">Account name</label><input v-model="form.config.account_name" type="text" maxlength="150" :class="input" /></div>
          <div><label :class="label">Bank name (optional)</label><input v-model="form.config.bank_name" type="text" maxlength="150" :class="input" /></div>
          <div><label :class="label">{{ bankCodeLabel }}</label><input v-model="form.config.sort_code" type="text" maxlength="20" :class="input" /></div>
          <div><label :class="label">Account number</label><input v-model="form.config.account_number" type="text" maxlength="34" :class="input" /></div>
          <div><label :class="label">IBAN (optional)</label><input v-model="form.config.iban" type="text" maxlength="40" :class="input" /></div>
          <div><label :class="label">Reference prefix</label><input v-model="form.config.reference_prefix" type="text" maxlength="20" :class="input" placeholder="e.g. DINNER" /><p v-if="form.errors['config.reference_prefix']" class="mt-1 text-[11px] font-semibold text-rose-600">{{ form.errors['config.reference_prefix'] }}</p></div>
        </div>

        <!-- Stripe: connect an account, or use pasted keys -->
        <div v-if="form.type === 'card_online' && platform.enabled" class="space-y-2">
          <label :class="label">How do you want to take card payments?</label>
          <div class="grid gap-2 sm:grid-cols-2">
            <button type="button" :class="['rounded-xl border p-3 text-left text-xs', form.config.stripe_mode === 'connect' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40' : 'border-slate-200 dark:border-slate-800']" @click="form.config.stripe_mode = 'connect'">
              <span class="font-bold text-slate-900 dark:text-white">Connect with Stripe (recommended)</span>
              <span class="mt-0.5 block text-slate-500 dark:text-slate-400">No keys to copy. Connect a Stripe account you already have, or have one created for you. A small platform fee is taken from each payment.</span>
            </button>
            <button type="button" :class="['rounded-xl border p-3 text-left text-xs', form.config.stripe_mode !== 'connect' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40' : 'border-slate-200 dark:border-slate-800']" @click="form.config.stripe_mode = 'keys'">
              <span class="font-bold text-slate-900 dark:text-white">Use my own Stripe keys</span>
              <span class="mt-0.5 block text-slate-500 dark:text-slate-400">Paste your Stripe keys and set up your own webhook. No platform fee.</span>
            </button>
          </div>
        </div>

        <div v-if="form.type === 'card_online' && platform.enabled && form.config.stripe_mode === 'connect'" class="space-y-3 rounded-xl border border-slate-200 p-4 text-xs dark:border-slate-800">
          <p v-if="stripeError" class="rounded-lg bg-rose-500/10 p-2 font-semibold text-rose-600" role="alert">{{ stripeError }}</p>
          <template v-if="!platform.account">
            <p class="text-slate-600 dark:text-slate-300">Payments go straight to your own Stripe account and Stripe pays you out. The platform takes <span class="font-semibold">{{ platform.fee }}</span>.</p>
            <div class="flex flex-wrap gap-2">
              <button v-if="platform.can_connect_existing" type="button" class="rounded-lg bg-indigo-600 px-3 py-2 font-bold text-white hover:bg-indigo-500" @click="stripeAction('connect')">Connect my Stripe account</button>
              <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 font-bold text-slate-700 dark:border-slate-700 dark:text-slate-200" @click="stripeAction('express')">I don't have one, create one for me</button>
            </div>
          </template>
          <template v-else>
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-bold text-slate-900 dark:text-white">Stripe {{ platform.account.type === 'express' ? '(created for you)' : '(your account)' }}</span>
              <span :class="['rounded px-2 py-0.5 text-[10px] font-bold uppercase', platform.account.charges_enabled ? 'bg-emerald-500/15 text-emerald-600' : 'bg-amber-500/15 text-amber-600']">{{ platform.account.charges_enabled ? 'Can take payments' : 'Setup not finished' }}</span>
              <span :class="['rounded px-2 py-0.5 text-[10px] font-bold uppercase', platform.account.payouts_enabled ? 'bg-emerald-500/15 text-emerald-600' : 'bg-amber-500/15 text-amber-600']">{{ platform.account.payouts_enabled ? 'Payouts on' : 'Payouts not on yet' }}</span>
            </div>
            <div v-if="!platform.account.charges_enabled && platform.account.type === 'express'">
              <p class="mb-2 text-slate-600 dark:text-slate-300">Stripe needs a few more details (identity and bank account) before it can take payments.</p>
              <button type="button" class="rounded-lg bg-indigo-600 px-3 py-2 font-bold text-white hover:bg-indigo-500" @click="stripeAction('express')">Continue setup on Stripe</button>
            </div>
            <p v-else-if="!platform.account.charges_enabled" class="text-slate-600 dark:text-slate-300">Stripe hasn't enabled payments on this account yet. Finish any steps in your Stripe dashboard.</p>
            <div v-if="!platform.account.terms_accepted" class="space-y-2 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
              <label class="flex items-start gap-2 font-semibold text-slate-700 dark:text-slate-200"><input v-model="agreeTerms" type="checkbox" class="mt-0.5 rounded" /> I agree that the platform takes {{ platform.fee }}, and to the platform payment terms<template v-if="platform.terms_url"> (<a :href="platform.terms_url" target="_blank" rel="noopener" class="text-blue-600 underline">read them</a>)</template>.</label>
              <button type="button" :disabled="!agreeTerms" class="rounded-lg bg-blue-600 px-3 py-2 font-bold text-white hover:bg-blue-500 disabled:opacity-50" @click="stripeAction('terms', { agree: true })">Accept</button>
            </div>
            <p v-else-if="platform.account.ready" class="font-semibold text-emerald-600">Card payments are ready. The platform takes {{ platform.fee }}.</p>
            <button type="button" class="font-semibold text-rose-600 hover:underline" @click="disconnectStripe">Disconnect Stripe</button>
          </template>
          <p v-if="!onlinePaymentsLive" class="font-semibold text-amber-700 dark:text-amber-300">Online payments are not switched on for this site yet, so this option won't be offered to people until they are.</p>
        </div>

        <!-- Stripe with the lodge's own keys -->
        <div v-if="form.type === 'card_online' && (!platform.enabled || form.config.stripe_mode !== 'connect')" class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2"><label :class="label">Stripe publishable key</label><input v-model="form.config.stripe_publishable_key" type="text" maxlength="255" :class="input" placeholder="pk_live_..." /></div>
          <div><label :class="label">Stripe secret key</label><input v-model="form.config.stripe_secret_key" type="password" maxlength="500" autocomplete="off" :class="input" :placeholder="current?.has_stripe_secret_key ? 'Saved - leave blank to keep' : 'sk_live_...'" /></div>
          <div><label :class="label">Stripe webhook secret</label><input v-model="form.config.stripe_webhook_secret" type="password" maxlength="500" autocomplete="off" :class="input" :placeholder="current?.has_stripe_webhook_secret ? 'Saved - leave blank to keep' : 'whsec_...'" /></div>
          <div class="sm:col-span-2 space-y-1 rounded-lg bg-slate-50 dark:bg-slate-800/50 p-3 text-[11px] text-slate-600 dark:text-slate-300">
            <p class="font-bold text-slate-900 dark:text-white">Set this up in your Stripe dashboard</p>
            <p>Add a webhook endpoint pointing to:</p>
            <p class="break-all font-mono text-[11px] text-slate-900 dark:text-white">{{ stripeWebhookUrl }}</p>
            <p>Send it these events: <span class="font-mono">checkout.session.completed</span> and <span class="font-mono">checkout.session.async_payment_succeeded</span>. Then paste its signing secret (starts <span class="font-mono">whsec_</span>) above. People are only offered card payment once both keys are saved.</p>
            <p v-if="!onlinePaymentsLive" class="font-semibold text-amber-700 dark:text-amber-300">Card payments are not switched on for this site yet, so this option won't be offered to people until they are.</p>
          </div>
          <p class="sm:col-span-2 text-[11px] text-slate-500 dark:text-slate-400">Keys are stored encrypted and never shown again.</p>
        </div>

        <!-- PayPal -->
        <div v-if="form.type === 'paypal'" class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2"><label :class="label">PayPal client ID</label><input v-model="form.config.paypal_client_id" type="text" maxlength="255" autocomplete="off" :class="input" /></div>
          <div><label :class="label">PayPal client secret</label><input v-model="form.config.paypal_client_secret" type="password" maxlength="500" autocomplete="off" :class="input" :placeholder="current?.has_paypal_secret ? 'Saved - leave blank to keep' : ''" /></div>
          <div><label :class="label">PayPal webhook ID</label><input v-model="form.config.paypal_webhook_id" type="text" maxlength="100" autocomplete="off" :class="input" /></div>
          <div>
            <label :class="label">Mode</label>
            <select v-model="form.config.paypal_mode" :class="input"><option value="live">Live</option><option value="sandbox">Sandbox (testing)</option></select>
          </div>
          <div class="sm:col-span-2 space-y-1 rounded-lg bg-slate-50 dark:bg-slate-800/50 p-3 text-[11px] text-slate-600 dark:text-slate-300">
            <p class="font-bold text-slate-900 dark:text-white">Set this up in your PayPal developer dashboard</p>
            <p>Create an app (a REST API app) for your business account and copy its client ID and secret above. Then add a webhook pointing to:</p>
            <p class="break-all font-mono text-[11px] text-slate-900 dark:text-white">{{ paypalWebhookUrl }}</p>
            <p>Subscribe it to <span class="font-mono">Payment capture completed</span>, then paste the webhook's ID above. People are only offered PayPal once all three are saved, and only in currencies PayPal supports (GBP, EUR, USD, AUD, NZD, CAD, CHF, BRL).</p>
            <p v-if="!onlinePaymentsLive" class="font-semibold text-amber-700 dark:text-amber-300">Online payments are not switched on for this site yet, so this option won't be offered to people until they are.</p>
          </div>
          <p class="sm:col-span-2 text-[11px] text-slate-500 dark:text-slate-400">The secret is stored encrypted and never shown again. Like card payments, PayPal can be discounted but not given a fee.</p>
        </div>

        <!-- Pay later -->
        <div v-if="form.type === 'pay_later'" class="grid gap-4 sm:grid-cols-2">
          <div><label :class="label">Payment is due (days)</label><input v-model="form.due_days" type="number" min="0" max="365" :class="input" /></div>
          <div>
            <label :class="label">Counted from</label>
            <select v-model="form.due_basis" :class="input"><option value="before_event">before the event</option><option value="after_booking">after booking</option></select>
          </div>
        </div>

        <!-- Discount or fee -->
        <div class="space-y-3 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
          <h4 class="text-sm font-bold text-slate-900 dark:text-white">Discount or fee for choosing this option</h4>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">Encourage people to pay early: a discount for paying online, a fee for paying later or on the night. Each event can change this.</p>
          <div class="grid gap-3 sm:grid-cols-4">
            <div>
              <label :class="label">Type</label>
              <select v-model="form.default_adjustment_kind" :class="input">
                <option value="none">None</option>
                <option value="discount">Discount</option>
                <option v-if="canFee" value="fee">Fee</option>
              </select>
            </div>
            <template v-if="form.default_adjustment_kind !== 'none'">
              <div>
                <label :class="label">How</label>
                <select v-model="form.default_adjustment_mode" :class="input"><option value="fixed">Fixed amount</option><option value="percent">Percentage</option></select>
              </div>
              <div>
                <label :class="label">{{ form.default_adjustment_mode === 'percent' ? 'Percent' : 'Amount (' + $cs.trim() + ')' }}</label>
                <input v-model="form.default_adjustment_amount" type="number" step="0.01" min="0" :class="input" />
              </div>
              <div v-if="form.default_adjustment_mode === 'fixed'">
                <label :class="label">Applies</label>
                <select v-model="form.default_adjustment_scope" :class="input"><option value="per_person">per person</option><option value="per_booking">per booking</option></select>
              </div>
            </template>
          </div>
          <p v-if="!canFee" class="text-[11px] text-slate-500 dark:text-slate-400">Card and bank payments can only be discounted: charging extra for paying by card or bank is not allowed in the UK and many other countries.</p>
          <p v-if="form.errors.default_adjustment_kind" class="text-[11px] font-semibold text-rose-600">{{ form.errors.default_adjustment_kind }}</p>
        </div>

        <div class="flex gap-3">
          <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl">{{ form.processing ? 'Saving...' : 'Save' }}</button>
          <button type="button" class="px-5 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-800" @click="editing = null">Cancel</button>
          <button v-if="current" type="button" class="ml-auto px-3 py-2.5 text-xs font-bold text-rose-600 hover:underline dark:text-rose-400" @click="remove(current)">Remove this option</button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
