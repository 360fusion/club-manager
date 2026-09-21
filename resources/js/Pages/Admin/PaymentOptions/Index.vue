<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  methods: { type: Array, default: () => [] },
  bankDefaults: { type: Object, default: () => ({}) },
  stripeWebhookUrl: { type: String, default: '' },
  paypalWebhookUrl: { type: String, default: '' },
  onlinePaymentsLive: { type: Boolean, default: false },
});

const TYPES = [
  { value: 'bank_transfer', label: 'Bank transfer', help: 'People pay into your bank account using a reference. You mark them paid once you have checked the account.', fee: false },
  { value: 'card_online', label: 'Pay online by card', help: 'People pay on Stripe\'s secure page with your own Stripe account, and the money goes straight to you.', fee: false },
  { value: 'paypal', label: 'Pay online with PayPal', help: 'People pay on PayPal\'s secure page with your own PayPal business account, and the money goes straight to you.', fee: false },
  { value: 'pay_later', label: 'Pay later', help: 'People book now and pay before a due date you set. They can pay any time before then.', fee: true },
  { value: 'cash_on_door', label: 'Pay on the night', help: 'People pay when they arrive.', fee: true },
];

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
  config: type === 'bank_transfer' ? { ...props.bankDefaults, reference_prefix: '' } : {},
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

const chooseType = (value) => {
  form.type = value;
  form.label = typeInfo(value).label;
  form.config = value === 'bank_transfer' ? { ...props.bankDefaults, reference_prefix: '' } : (value === 'paypal' ? { paypal_mode: 'live' } : {});
  if (!typeInfo(value).fee && form.default_adjustment_kind === 'fee') form.default_adjustment_kind = 'none';
};

const canFee = computed(() => typeInfo(form.type).fee);
const current = computed(() => props.methods.find((m) => m.id === editing.value));

const submit = () => {
  const options = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
  if (editing.value) form.put(route('admin.payment_options.update', { clubSlug: props.club.slug, id: editing.value }), options);
  else form.post(route('admin.payment_options.store', { clubSlug: props.club.slug }), options);
};

const remove = (method) => {
  if (confirm(`Remove "${method.label}"?`)) {
    router.delete(route('admin.payment_options.destroy', { clubSlug: props.club.slug, id: method.id }), { preserveScroll: true });
  }
};

const summary = (m) => {
  if (m.default_adjustment_kind === 'none' || !Number(m.default_adjustment_amount)) return 'Standard price';
  const amount = m.default_adjustment_mode === 'percent' ? `${Number(m.default_adjustment_amount)}%` : `${Number(m.default_adjustment_amount).toFixed(2)}`;
  const scope = m.default_adjustment_mode === 'percent' ? '' : (m.default_adjustment_scope === 'per_person' ? ' per person' : ' per booking');
  return m.default_adjustment_kind === 'discount' ? `${amount} off${scope}` : `+ ${amount} fee${scope}`;
};

const input = 'w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500';
const label = 'block text-[11px] font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1';
</script>

<template>
  <AdminLayout title="Payment options" :club="club" active-tab="events">
    <Head title="Payment options" />

    <div class="max-w-4xl mx-auto space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">Payment options</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Set up how people can pay once, then switch options on for each event. Give a discount to encourage early payment, or add a fee for paying later or on the night.</p>
        </div>
        <div class="flex gap-2">
          <Link :href="route('admin.events.index', { clubSlug: club.slug })" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl">&larr; Events</Link>
          <button type="button" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl" @click="open()">+ Add option</button>
        </div>
      </div>

      <div v-if="!methods.length && editing === null" class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 p-8 text-center text-sm text-slate-500 dark:text-slate-400">
        No payment options yet. Add bank transfer, pay later, pay on the night or online card payments.
      </div>

      <ul class="space-y-3">
        <li v-for="m in methods" :key="m.id" class="flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80">
          <div>
            <div class="flex items-center gap-2">
              <span class="font-bold text-slate-900 dark:text-white text-sm">{{ m.label }}</span>
              <span class="text-[10px] font-bold uppercase tracking-wider rounded px-1.5 py-0.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">{{ typeInfo(m.type).label }}</span>
              <span v-if="!m.is_active" class="text-[10px] font-bold uppercase rounded px-1.5 py-0.5 bg-amber-100 text-amber-800">Off</span>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              {{ summary(m) }}
              <span v-if="m.type === 'pay_later' && m.due_days !== null"> · due {{ m.due_days }} days {{ m.due_basis === 'before_event' ? 'before the event' : 'after booking' }}</span>
              <span v-if="m.type === 'card_online'"> · {{ m.ready_for_cards ? 'Ready for card payments' : 'Needs the Stripe keys and webhook secret' }}</span>
            </div>
          </div>
          <div class="flex gap-3 text-xs font-semibold">
            <button type="button" class="text-blue-600 dark:text-blue-400 hover:underline" @click="open(m)">Edit</button>
            <button type="button" class="text-rose-600 dark:text-rose-400 hover:underline" @click="remove(m)">Remove</button>
          </div>
        </li>
      </ul>

      <form v-if="editing !== null" class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-5" @submit.prevent="submit">
        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ editing ? 'Edit payment option' : 'New payment option' }}</h3>

        <div v-if="!editing" class="grid gap-2 sm:grid-cols-2">
          <button v-for="t in TYPES" :key="t.value" type="button" :class="['text-left rounded-xl border p-3 text-xs transition-all', form.type === t.value ? 'border-blue-500 bg-blue-50 dark:bg-blue-950/40' : 'border-slate-200 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50']" @click="chooseType(t.value)">
            <span class="font-bold text-slate-900 dark:text-white block">{{ t.label }}</span>
            <span class="text-slate-500 dark:text-slate-400">{{ t.help }}</span>
          </button>
        </div>
        <p v-else class="text-xs text-slate-500 dark:text-slate-400">{{ typeInfo(form.type).help }}</p>
        <p v-if="form.errors.type" class="text-[11px] font-semibold text-rose-600">{{ form.errors.type }}</p>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label :class="label">Name people see</label>
            <input v-model="form.label" type="text" maxlength="100" required :class="input" />
            <p v-if="form.errors.label" class="mt-1 text-[11px] font-semibold text-rose-600">{{ form.errors.label }}</p>
          </div>
          <label class="flex items-center gap-2 text-xs sm:pt-6">
            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded" /> <span class="font-bold text-slate-900 dark:text-white">Available to use</span>
          </label>
        </div>

        <div>
          <label :class="label">Instructions shown to the person paying (optional)</label>
          <textarea v-model="form.instructions" rows="2" maxlength="2000" :class="input" placeholder="e.g. Please use your surname as the reference."></textarea>
        </div>

        <!-- Bank transfer -->
        <div v-if="form.type === 'bank_transfer'" class="grid gap-4 sm:grid-cols-2">
          <div><label :class="label">Account name</label><input v-model="form.config.account_name" type="text" maxlength="150" :class="input" /></div>
          <div><label :class="label">Sort code</label><input v-model="form.config.sort_code" type="text" maxlength="20" :class="input" placeholder="00-00-00" /></div>
          <div><label :class="label">Account number</label><input v-model="form.config.account_number" type="text" maxlength="34" :class="input" /></div>
          <div><label :class="label">Reference prefix</label><input v-model="form.config.reference_prefix" type="text" maxlength="20" :class="input" placeholder="e.g. DINNER" /><p v-if="form.errors['config.reference_prefix']" class="mt-1 text-[11px] font-semibold text-rose-600">{{ form.errors['config.reference_prefix'] }}</p></div>
        </div>

        <!-- Stripe -->
        <div v-if="form.type === 'card_online'" class="grid gap-4 sm:grid-cols-2">
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
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
