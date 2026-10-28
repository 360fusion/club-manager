<script setup>
import { computed, nextTick, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import ToggleSwitch from '@/Components/Ui/ToggleSwitch.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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
  { value: 'bank_transfer', icon: 'bank', label: 'Bank transfer', help: 'People pay into your bank account using a reference. You mark them paid once you have checked the account.', fee: false },
  { value: 'card_online', icon: 'lock', label: 'Pay online by card', help: 'People pay on Stripe\'s secure page with your own Stripe account, and the money goes straight to you.', fee: false },
  { value: 'paypal', icon: 'globe', label: 'Pay online with PayPal', help: 'People pay on PayPal\'s secure page with your own PayPal business account, and the money goes straight to you.', fee: false },
  { value: 'pay_later', icon: 'calendar', label: 'Pay later', help: 'People book now and pay before a due date you set. They can pay any time before then.', fee: true },
  { value: 'cash_on_door', icon: 'cash', label: 'Pay on the night', help: 'People pay when they arrive.', fee: true },
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
</script>

<template>
  <AdminLayout title="Payment options" :club="club" active-tab="events">
    <Head title="Payment options" />

    <div class="max-w-4xl mx-auto space-y-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="max-w-2xl text-sm text-slate-500 dark:text-slate-400">Switch on the ways people can pay. Details are set here once and every event offers the options you switch on. Each event can then add a discount or fee to an option.</p>
        <Button variant="secondary" size="sm" :href="returnTo ?? route('admin.events.index', { clubSlug: club.slug })">
          <template #icon><UiIcon name="arrow-left" /></template>
          {{ returnTo ? backLabel : 'Events' }}
        </Button>
      </div>

      <ul class="space-y-3">
        <li v-for="card in cards" :key="card.key" class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
          <div class="flex flex-wrap items-center gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><UiIcon :name="card.type.icon" :size="20" /></span>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-semibold text-slate-900 dark:text-white text-sm">{{ card.method?.label ?? card.type.label }}</span>
                <Badge v-if="!card.method">Not set up</Badge>
                <Badge v-else-if="!card.method.complete" variant="warning">Needs details</Badge>
                <Badge v-else-if="card.method.is_active" variant="success">In use</Badge>
              </div>
              <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ statusLine(card) }}</div>
              <p v-if="toggleError(card)" class="mt-1 text-xs font-medium text-rose-600 dark:text-rose-400" role="alert">{{ toggleError(card) }}</p>
            </div>

            <div class="flex items-center gap-2">
              <template v-if="card.method && methods.length > 1">
                <Button variant="secondary" size="sm" :aria-label="`Move ${card.method.label} up`" @click="move(card, -1)">&uarr;</Button>
                <Button variant="secondary" size="sm" :aria-label="`Move ${card.method.label} down`" @click="move(card, 1)">&darr;</Button>
              </template>
              <Button variant="secondary" size="sm" :aria-label="`Edit ${card.method?.label ?? card.type.label}`" title="Edit details" @click="edit(card)">
                <UiIcon name="pencil" />
              </Button>
              <label class="flex cursor-pointer items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
                <span>Use this option</span>
                <ToggleSwitch :model-value="!!card.method?.is_active" :label="`Use ${card.method?.label ?? card.type.label}`" on-text="On" off-text="Off" @update:model-value="toggle(card)" />
              </label>
            </div>
          </div>
        </li>
      </ul>

      <form v-if="editing !== null" ref="panelRef" class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-800 space-y-5" @submit.prevent="submit">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ editing ? 'Edit payment option' : 'New payment option' }}</h3>

        <p class="text-sm text-slate-500 dark:text-slate-400">{{ typeInfo(form.type).help }}</p>
        <p v-if="form.errors.type" class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ form.errors.type }}</p>

        <div class="grid gap-4 sm:grid-cols-2">
          <Input v-model="form.label" label="Name people see" maxlength="100" required :error="form.errors.label" />
        </div>

        <Textarea v-model="form.instructions" label="Instructions shown to the person paying (optional)" :rows="2" maxlength="2000" placeholder="e.g. Please use your surname as the reference." />

        <!-- Bank transfer -->
        <div v-if="form.type === 'bank_transfer'" class="grid gap-4 sm:grid-cols-2">
          <Input v-model="form.config.account_name" label="Account name" maxlength="150" />
          <Input v-model="form.config.bank_name" label="Bank name (optional)" maxlength="150" />
          <Input v-model="form.config.sort_code" :label="bankCodeLabel" maxlength="20" />
          <Input v-model="form.config.account_number" label="Account number" maxlength="34" />
          <Input v-model="form.config.iban" label="IBAN (optional)" maxlength="40" />
          <Input v-model="form.config.reference_prefix" label="Reference prefix" maxlength="20" placeholder="e.g. DINNER" :error="form.errors['config.reference_prefix']" />
        </div>

        <!-- Stripe: connect an account, or use pasted keys -->
        <div v-if="form.type === 'card_online' && platform.enabled" class="space-y-2">
          <p class="text-xs font-medium text-slate-700 dark:text-slate-300">How do you want to take card payments?</p>
          <div class="grid gap-2 sm:grid-cols-2">
            <button type="button" :class="['rounded-xl border p-3 text-left text-xs transition-colors', form.config.stripe_mode === 'connect' ? 'border-blue-500 bg-blue-50 dark:border-blue-600 dark:bg-blue-950/40' : 'border-slate-300 hover:border-slate-400 dark:border-slate-700']" @click="form.config.stripe_mode = 'connect'">
              <span class="font-semibold text-slate-900 dark:text-white">Connect with Stripe (recommended)</span>
              <span class="mt-0.5 block text-slate-500 dark:text-slate-400">No keys to copy. Connect a Stripe account you already have, or have one created for you. A small platform fee is taken from each payment.</span>
            </button>
            <button type="button" :class="['rounded-xl border p-3 text-left text-xs transition-colors', form.config.stripe_mode !== 'connect' ? 'border-blue-500 bg-blue-50 dark:border-blue-600 dark:bg-blue-950/40' : 'border-slate-300 hover:border-slate-400 dark:border-slate-700']" @click="form.config.stripe_mode = 'keys'">
              <span class="font-semibold text-slate-900 dark:text-white">Use my own Stripe keys</span>
              <span class="mt-0.5 block text-slate-500 dark:text-slate-400">Paste your Stripe keys and set up your own webhook. No platform fee.</span>
            </button>
          </div>
        </div>

        <div v-if="form.type === 'card_online' && platform.enabled && form.config.stripe_mode === 'connect'" class="space-y-3 rounded-xl border border-slate-200 p-4 text-xs dark:border-slate-800">
          <Alert v-if="stripeError" variant="danger" role="alert">{{ stripeError }}</Alert>
          <template v-if="!platform.account">
            <p class="text-slate-600 dark:text-slate-300">Payments go straight to your own Stripe account and Stripe pays you out. The platform takes <span class="font-semibold">{{ platform.fee }}</span>.</p>
            <div class="flex flex-wrap gap-2">
              <Button v-if="platform.can_connect_existing" variant="secondary" size="sm" @click="stripeAction('connect')">Connect my Stripe account</Button>
              <Button variant="secondary" size="sm" @click="stripeAction('express')">I don't have one, create one for me</Button>
            </div>
          </template>
          <template v-else>
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-semibold text-slate-900 dark:text-white">Stripe {{ platform.account.type === 'express' ? '(created for you)' : '(your account)' }}</span>
              <Badge :variant="platform.account.charges_enabled ? 'success' : 'warning'">{{ platform.account.charges_enabled ? 'Can take payments' : 'Setup not finished' }}</Badge>
              <Badge :variant="platform.account.payouts_enabled ? 'success' : 'warning'">{{ platform.account.payouts_enabled ? 'Payouts on' : 'Payouts not on yet' }}</Badge>
            </div>
            <div v-if="!platform.account.charges_enabled && platform.account.type === 'express'">
              <p class="mb-2 text-slate-600 dark:text-slate-300">Stripe needs a few more details (identity and bank account) before it can take payments.</p>
              <Button variant="secondary" size="sm" @click="stripeAction('express')">Continue setup on Stripe</Button>
            </div>
            <p v-else-if="!platform.account.charges_enabled" class="text-slate-600 dark:text-slate-300">Stripe hasn't enabled payments on this account yet. Finish any steps in your Stripe dashboard.</p>
            <div v-if="!platform.account.terms_accepted" class="space-y-2 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50">
              <label class="flex items-start gap-2 font-medium text-slate-700 dark:text-slate-200"><input v-model="agreeTerms" type="checkbox" class="mt-0.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950" /> I agree that the platform takes {{ platform.fee }}, and to the platform payment terms<template v-if="platform.terms_url"> (<a :href="platform.terms_url" target="_blank" rel="noopener" class="text-blue-600 underline dark:text-blue-400">read them</a>)</template>.</label>
              <Button variant="secondary" size="sm" :disabled="!agreeTerms" @click="stripeAction('terms', { agree: true })">Accept</Button>
            </div>
            <p v-else-if="platform.account.ready" class="font-medium text-emerald-700 dark:text-emerald-400">Card payments are ready. The platform takes {{ platform.fee }}.</p>
            <button type="button" class="font-medium text-rose-600 hover:underline dark:text-rose-400" @click="disconnectStripe">Disconnect Stripe</button>
          </template>
          <p v-if="!onlinePaymentsLive" class="font-medium text-amber-700 dark:text-amber-300">Online payments are not switched on for this site yet, so this option won't be offered to people until they are.</p>
        </div>

        <!-- Stripe with the lodge's own keys -->
        <div v-if="form.type === 'card_online' && (!platform.enabled || form.config.stripe_mode !== 'connect')" class="grid gap-4 sm:grid-cols-2">
          <Input v-model="form.config.stripe_publishable_key" label="Stripe publishable key" class="sm:col-span-2" maxlength="255" placeholder="pk_live_..." />
          <Input v-model="form.config.stripe_secret_key" label="Stripe secret key" type="password" maxlength="500" autocomplete="off" :placeholder="current?.has_stripe_secret_key ? 'Saved - leave blank to keep' : 'sk_live_...'" />
          <Input v-model="form.config.stripe_webhook_secret" label="Stripe webhook secret" type="password" maxlength="500" autocomplete="off" :placeholder="current?.has_stripe_webhook_secret ? 'Saved - leave blank to keep' : 'whsec_...'" />
          <div class="sm:col-span-2 space-y-1 rounded-xl bg-slate-50 dark:bg-slate-800/50 p-3 text-xs text-slate-600 dark:text-slate-300">
            <p class="font-semibold text-slate-900 dark:text-white">Set this up in your Stripe dashboard</p>
            <p>Add a webhook endpoint pointing to:</p>
            <p class="break-all font-mono text-[11px] text-slate-900 dark:text-white">{{ stripeWebhookUrl }}</p>
            <p>Send it these events: <span class="font-mono">checkout.session.completed</span> and <span class="font-mono">checkout.session.async_payment_succeeded</span>. Then paste its signing secret (starts <span class="font-mono">whsec_</span>) above. People are only offered card payment once both keys are saved.</p>
            <p v-if="!onlinePaymentsLive" class="font-medium text-amber-700 dark:text-amber-300">Card payments are not switched on for this site yet, so this option won't be offered to people until they are.</p>
          </div>
          <p class="sm:col-span-2 text-xs text-slate-500 dark:text-slate-400">Keys are stored encrypted and never shown again.</p>
        </div>

        <!-- PayPal -->
        <div v-if="form.type === 'paypal'" class="grid gap-4 sm:grid-cols-2">
          <Input v-model="form.config.paypal_client_id" label="PayPal client ID" class="sm:col-span-2" maxlength="255" autocomplete="off" />
          <Input v-model="form.config.paypal_client_secret" label="PayPal client secret" type="password" maxlength="500" autocomplete="off" :placeholder="current?.has_paypal_secret ? 'Saved - leave blank to keep' : ''" />
          <Input v-model="form.config.paypal_webhook_id" label="PayPal webhook ID" maxlength="100" autocomplete="off" />
          <Select v-model="form.config.paypal_mode" label="Mode" :options="[{ value: 'live', label: 'Live' }, { value: 'sandbox', label: 'Sandbox (testing)' }]" />
          <div class="sm:col-span-2 space-y-1 rounded-xl bg-slate-50 dark:bg-slate-800/50 p-3 text-xs text-slate-600 dark:text-slate-300">
            <p class="font-semibold text-slate-900 dark:text-white">Set this up in your PayPal developer dashboard</p>
            <p>Create an app (a REST API app) for your business account and copy its client ID and secret above. Then add a webhook pointing to:</p>
            <p class="break-all font-mono text-[11px] text-slate-900 dark:text-white">{{ paypalWebhookUrl }}</p>
            <p>Subscribe it to <span class="font-mono">Payment capture completed</span>, then paste the webhook's ID above. People are only offered PayPal once all three are saved, and only in currencies PayPal supports (GBP, EUR, USD, AUD, NZD, CAD, CHF, BRL).</p>
            <p v-if="!onlinePaymentsLive" class="font-medium text-amber-700 dark:text-amber-300">Online payments are not switched on for this site yet, so this option won't be offered to people until they are.</p>
          </div>
          <p class="sm:col-span-2 text-xs text-slate-500 dark:text-slate-400">The secret is stored encrypted and never shown again. Like card payments, PayPal can be discounted but not given a fee.</p>
        </div>

        <!-- Pay later -->
        <div v-if="form.type === 'pay_later'" class="grid gap-4 sm:grid-cols-2">
          <Input v-model.number="form.due_days" label="Payment is due (days)" type="number" min="0" max="365" />
          <Select v-model="form.due_basis" label="Counted from" :options="[{ value: 'before_event', label: 'before the event' }, { value: 'after_booking', label: 'after booking' }]" />
        </div>

        <!-- Discount or fee -->
        <div class="space-y-3 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
          <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Discount or fee for choosing this option</h4>
          <p class="text-xs text-slate-500 dark:text-slate-400">Encourage people to pay early: a discount for paying online, a fee for paying later or on the night. Each event can change this.</p>
          <div class="grid gap-3 sm:grid-cols-4">
            <Select v-model="form.default_adjustment_kind" label="Type" :options="[{ value: 'none', label: 'None' }, { value: 'discount', label: 'Discount' }, ...(canFee ? [{ value: 'fee', label: 'Fee' }] : [])]" />
            <template v-if="form.default_adjustment_kind !== 'none'">
              <Select v-model="form.default_adjustment_mode" label="How" :options="[{ value: 'fixed', label: 'Fixed amount' }, { value: 'percent', label: 'Percentage' }]" />
              <Input v-model.number="form.default_adjustment_amount" :label="form.default_adjustment_mode === 'percent' ? 'Percent' : 'Amount (' + $cs.trim() + ')'" type="number" step="0.01" min="0" />
              <Select v-if="form.default_adjustment_mode === 'fixed'" v-model="form.default_adjustment_scope" label="Applies" :options="[{ value: 'per_person', label: 'per person' }, { value: 'per_booking', label: 'per booking' }]" />
            </template>
          </div>
          <p v-if="!canFee" class="text-xs text-slate-500 dark:text-slate-400">Card and bank payments can only be discounted: charging extra for paying by card or bank is not allowed in the UK and many other countries.</p>
          <p v-if="form.errors.default_adjustment_kind" class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ form.errors.default_adjustment_kind }}</p>
        </div>

        <div class="flex gap-3">
          <Button type="submit" :loading="form.processing">{{ form.processing ? 'Saving...' : 'Save' }}</Button>
          <Button variant="secondary" @click="editing = null">Cancel</Button>
          <button v-if="current" type="button" class="ml-auto px-3 py-2 text-sm font-medium text-rose-600 hover:underline dark:text-rose-400" @click="remove(current)">Remove this option</button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
