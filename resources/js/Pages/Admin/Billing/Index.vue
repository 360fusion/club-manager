<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Alert from '@/Components/Ui/Alert.vue';

const props = defineProps({
  club: Object,
  businessDetails: Object,
  currentProvider: String,
  currentPlan: Object,
  billingHistory: Array,
});

const successToast = ref('');
const showSecretKeys = ref(false);

const providerForm = useForm({
  provider: props.currentProvider || 'stripe',
});

const businessForm = useForm({
  business_name: props.businessDetails?.business_name || props.club.name || '',
  company_number: props.businessDetails?.company_number || '',
  tax_id: props.businessDetails?.tax_id || '',
  billing_contact_email: props.businessDetails?.billing_contact_email || props.club.email || '',
  billing_phone: props.businessDetails?.billing_phone || '',
  address: props.businessDetails?.address || '',
  stripe_publishable_key: props.businessDetails?.stripe_publishable_key || '',
  stripe_secret_key: props.businessDetails?.stripe_secret_key || '',
  stripe_webhook_secret: props.businessDetails?.stripe_webhook_secret || '',
});

const updateProvider = (providerName) => {
  providerForm.provider = providerName;
  providerForm.post(route('billing.provider.update', { clubSlug: props.club.slug }), {
    preserveScroll: true,
    onSuccess: () => {
      successToast.value = `Active payment gateway set to ${providerName.charAt(0).toUpperCase()}${providerName.slice(1)}.`;
      setTimeout(() => { successToast.value = ''; }, 3000);
    }
  });
};

const saveBusinessAccount = () => {
  businessForm.post(route('billing.business.update', { clubSlug: props.club.slug }), {
    preserveScroll: true,
    onSuccess: () => {
      successToast.value = 'Main business account and merchant gateway credentials saved.';
      setTimeout(() => { successToast.value = ''; }, 3000);
    },
  });
};
</script>

<template>
  <AdminLayout title="Main business account and billing setup" :club="club" active-tab="settings">
    <div class="max-w-5xl mx-auto space-y-6">

      <!-- Toast Notification -->
      <Alert v-if="successToast" variant="success">
        <div class="flex items-center justify-between gap-3">
          <span class="flex items-center gap-2"><UiIcon name="check" /> {{ successToast }}</span>
          <button type="button" @click="successToast = ''" class="rounded-xl p-1 hover:bg-emerald-100 dark:hover:bg-emerald-900/40" aria-label="Dismiss"><UiIcon name="x" /></button>
        </div>
      </Alert>

      <!-- Main Header Banner -->
      <Card class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Business administration</span>
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">• {{ club.name }}</span>
          </div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white mt-1">Main business account and merchant gateway administration</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Configure your lodge/club legal corporate business entity, merchant payment gateway API credentials for collecting member dues, and manage SaaS platform subscription billing.</p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <a
            :href="route('billing.portal', { clubSlug: club.slug })"
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
          >
            <UiIcon name="external-link" />
            <span>External Stripe portal</span>
          </a>
        </div>
      </Card>

      <!-- Main Business Entity Setup Form Card -->
      <Card padding="none" class="overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3 bg-slate-50/50 dark:bg-slate-800/50">
          <div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">
              Organization legal business entity account
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Official company, lodge, or charity business details used for invoicing, tax receipts, and merchant registration.</p>
          </div>
          <Badge>Corporate entity</Badge>
        </div>

        <form @submit.prevent="saveBusinessAccount" class="p-6 space-y-5 text-xs">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <Input
              v-model="businessForm.business_name"
              label="Legal business / organization name"
              required
              placeholder="e.g. Lodge of Fraternity No. 1418 Ltd"
            />
            <Input
              v-model="businessForm.company_number"
              label="Company / charity registration number"
              placeholder="e.g. 12948573 or Charity No. 110928"
            />
            <Input
              v-model="businessForm.tax_id"
              label="Tax / VAT registration ID"
              placeholder="e.g. GB 987 6543 21"
            />
            <Input
              v-model="businessForm.billing_contact_email"
              type="email"
              label="Official accounts / billing email"
              required
              placeholder="accounts@lodge-of-fraternity.example"
            />
            <Input
              v-model="businessForm.billing_phone"
              label="Accounts / Treasurer phone"
              placeholder="+44 20 7946 0912"
            />
            <Input
              v-model="businessForm.address"
              label="Registered business / clubhouse address"
              placeholder="100 Boathouse Way, Oxford, OX1 1AA, UK"
            />
          </div>

          <!-- Merchant Gateway Credentials Header & Divider -->
          <div class="pt-5 border-t border-slate-100 dark:border-slate-800">
            <div class="flex items-center justify-between gap-3 mb-3">
              <div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                  Merchant payment gateway API credentials
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Provide live API keys for your club's merchant account so member payments and event ticket fees flow directly to your business bank account.</p>
              </div>
              <Button variant="secondary" size="sm" class="shrink-0" @click="showSecretKeys = !showSecretKeys">
                <UiIcon :name="showSecretKeys ? 'eye-off' : 'eye'" /> {{ showSecretKeys ? 'Hide API keys' : 'Show API keys' }}
              </Button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800/80">
              <Input
                v-model="businessForm.stripe_publishable_key"
                :type="showSecretKeys ? 'text' : 'password'"
                label="Stripe publishable key"
                placeholder="pk_live_..."
              />
              <Input
                v-model="businessForm.stripe_secret_key"
                :type="showSecretKeys ? 'text' : 'password'"
                label="Stripe secret key"
                :placeholder="businessDetails?.has_stripe_secret_key ? 'Saved - leave blank to keep' : 'sk_live_...'"
              />
              <Input
                v-model="businessForm.stripe_webhook_secret"
                :type="showSecretKeys ? 'text' : 'password'"
                label="Stripe webhook secret"
                :placeholder="businessDetails?.has_stripe_webhook_secret ? 'Saved - leave blank to keep' : 'whsec_...'"
              />
            </div>
          </div>

          <div class="flex justify-end pt-2">
            <Button type="submit" :loading="businessForm.processing">
              <UiIcon v-if="!businessForm.processing" name="save" />
              {{ businessForm.processing ? 'Saving...' : 'Save main business account details' }}
            </Button>
          </div>
        </form>
      </Card>

      <!-- Active Plan Overview & Gateway Selector Card -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- Left: Current Plan Summary -->
        <Card class="md:col-span-2 space-y-5">
          <div class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
            <div>
              <Badge variant="info">Active platform subscription</Badge>
              <h3 class="text-lg font-semibold text-slate-900 dark:text-white mt-2">{{ currentPlan.name }}</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ currentPlan.billing_cycle }}</p>
            </div>
            <div class="text-right space-y-1">
              <span class="text-2xl font-bold text-slate-900 dark:text-white block">{{ currentPlan.price }}</span>
              <Badge variant="success">Active and in good standing</Badge>
            </div>
          </div>

          <!-- Included Platform Modules -->
          <div>
            <h4 class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3">Included platform features and modules</h4>
            <div class="flex flex-wrap gap-2">
              <Badge v-for="(module, idx) in currentPlan.modules" :key="idx">
                <UiIcon name="check" :size="12" class="text-emerald-600 dark:text-emerald-400" />
                <span>{{ module }}</span>
              </Badge>
            </div>
          </div>

          <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            <span>Next automated renewal date: <strong class="text-slate-900 dark:text-white">{{ currentPlan.next_billing_date }}</strong></span>
          </div>
        </Card>

        <!-- Right: Active Payment Gateway Selector Card -->
        <Card class="space-y-4 flex flex-col justify-between">
          <div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">
              Active payment gateway
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Select your preferred payment processor for member dues and SaaS checkouts.</p>

            <div class="space-y-2 mt-4">
              <!-- Stripe Option -->
              <button
                type="button"
                @click="updateProvider('stripe')"
                :aria-pressed="providerForm.provider === 'stripe'"
                :class="[
                  'w-full p-3 rounded-xl border text-left text-xs font-medium transition-colors flex items-center justify-between cursor-pointer',
                  providerForm.provider === 'stripe'
                    ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-300 dark:border-blue-700/60 text-blue-900 dark:text-blue-200 shadow-sm'
                    : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                ]"
              >
                <div class="flex items-center gap-2.5">
                  <UiIcon name="cash" :size="18" />
                  <div>
                    <span class="block">Stripe gateway</span>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400">Cards, Apple Pay, Google Pay</span>
                  </div>
                </div>
                <span v-if="providerForm.provider === 'stripe'" class="inline-flex items-center gap-1 font-medium text-blue-600 dark:text-blue-400"><UiIcon name="check" :size="14" /> Active</span>
              </button>

              <!-- Paddle Option -->
              <button
                type="button"
                @click="updateProvider('paddle')"
                :aria-pressed="providerForm.provider === 'paddle'"
                :class="[
                  'w-full p-3 rounded-xl border text-left text-xs font-medium transition-colors flex items-center justify-between cursor-pointer',
                  providerForm.provider === 'paddle'
                    ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-300 dark:border-blue-700/60 text-blue-900 dark:text-blue-200 shadow-sm'
                    : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                ]"
              >
                <div class="flex items-center gap-2.5">
                  <UiIcon name="globe" :size="18" />
                  <div>
                    <span class="block">Paddle Billing</span>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400">Merchant of record and international tax</span>
                  </div>
                </div>
                <span v-if="providerForm.provider === 'paddle'" class="inline-flex items-center gap-1 font-medium text-blue-600 dark:text-blue-400"><UiIcon name="check" :size="14" /> Active</span>
              </button>
            </div>
          </div>

          <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200/80 dark:border-slate-800/80 text-[11px] text-slate-500 dark:text-slate-400 flex items-start gap-2">
            <UiIcon name="lock" :size="14" class="shrink-0 mt-px" />
            <span>Gateways use 256-bit SSL encryption. Payment data is securely stored.</span>
          </div>
        </Card>

      </div>

      <!-- SaaS Invoices & Billing History Table -->
      <Card class="overflow-hidden space-y-4">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-4">
          <div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">SaaS platform invoices and receipts</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">View recent platform subscription payments and download tax receipts.</p>
          </div>
          <Button variant="secondary" size="sm" class="shrink-0">
            Download all receipts (PDF)
          </Button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider border-b border-slate-200/80 dark:border-slate-800/80">
                <th class="p-3">Invoice ref</th>
                <th class="p-3">Date</th>
                <th class="p-3">Description</th>
                <th class="p-3">Amount</th>
                <th class="p-3">Payment status</th>
                <th class="p-3 text-right">Receipt</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="inv in billingHistory" :key="inv.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
                <td class="p-3 font-mono font-semibold text-slate-900 dark:text-white">{{ inv.id }}</td>
                <td class="p-3 font-medium text-slate-600 dark:text-slate-300">{{ inv.date }}</td>
                <td class="p-3 font-medium text-slate-800 dark:text-slate-100">{{ inv.description }}</td>
                <td class="p-3 font-semibold text-slate-900 dark:text-white">{{ inv.amount }}</td>
                <td class="p-3">
                  <Badge variant="success" class="capitalize">
                    <UiIcon name="check" :size="12" /> {{ inv.status }}
                  </Badge>
                </td>
                <td class="p-3 text-right">
                  <span class="text-blue-600 dark:text-blue-400 font-medium hover:underline cursor-pointer">
                    PDF receipt
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </Card>

    </div>

  </AdminLayout>
</template>
