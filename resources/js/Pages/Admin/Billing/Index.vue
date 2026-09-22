<script setup>
import { ref } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

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
  company_number: props.businessDetails?.company_number || '12948573',
  tax_id: props.businessDetails?.tax_id || 'GB 987 6543 21',
  billing_contact_email: props.businessDetails?.billing_contact_email || props.club.email || '',
  billing_phone: props.businessDetails?.billing_phone || '+44 20 7946 0912',
  address: props.businessDetails?.address || '100 Boathouse Way, Oxford, OX1 1AA, UK',
  stripe_publishable_key: props.businessDetails?.stripe_publishable_key || '',
  stripe_secret_key: props.businessDetails?.stripe_secret_key || '',
  stripe_webhook_secret: props.businessDetails?.stripe_webhook_secret || '',
});

const updateProvider = (providerName) => {
  providerForm.provider = providerName;
  providerForm.post(route('billing.provider.update', { clubSlug: props.club.slug }), {
    preserveScroll: true,
    onSuccess: () => {
      successToast.value = `Active payment gateway set to ${providerName.toUpperCase()}.`;
      setTimeout(() => { successToast.value = ''; }, 3000);
    }
  });
};

const saveBusinessAccount = () => {
  businessForm.post(route('billing.business.update', { clubSlug: props.club.slug }), {
    preserveScroll: true,
    onSuccess: () => {
      successToast.value = 'Main Business Account and Merchant Gateway credentials saved successfully!';
      setTimeout(() => { successToast.value = ''; }, 3000);
    },
  });
};
</script>

<template>
  <AdminLayout title="Main Business Account & Billing Setup" :club="club" active-tab="settings">
    <div class="max-w-5xl mx-auto space-y-6">

      <!-- Toast Notification -->
      <div v-if="successToast" class="p-4 bg-emerald-600 text-white font-bold text-xs rounded-2xl shadow-lg flex items-center justify-between animate-in fade-in duration-200">
        <div class="flex items-center gap-2">
          <span>✅</span>
          <span>{{ successToast }}</span>
        </div>
        <button type="button" @click="successToast = ''" class="text-white hover:opacity-80">✕</button>
      </div>

      <!-- Main Header Banner -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 bg-slate-900 dark:bg-slate-700 text-white font-extrabold text-[10px] rounded-full uppercase tracking-wider">Business Administration</span>
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">• {{ club.name }}</span>
          </div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white mt-1">Main Business Account & Merchant Gateway Administration</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Configure your lodge/club legal corporate business entity, merchant payment gateway API credentials for collecting member dues, and manage SaaS platform subscription billing.</p>
        </div>

        <div class="flex items-center gap-2">
          <a
            :href="route('billing.portal', { clubSlug: club.slug })"
            class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-600/20 transition-all flex items-center gap-2 cursor-pointer"
          >
            <span>🚀</span>
            <span>External Stripe Portal</span>
          </a>
        </div>
      </div>

      <!-- Main Business Entity Setup Form Card -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
          <div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <span>🏛️</span> Organization Legal Business Entity Account
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Official company, lodge, or charity business details used for invoicing, tax receipts, and merchant registration.</p>
          </div>
          <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 bg-slate-200/80 dark:bg-slate-700/80 px-2.5 py-1 rounded-lg">Corporate Entity</span>
        </div>

        <form @submit.prevent="saveBusinessAccount" class="p-6 space-y-5 text-xs">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            <!-- Legal Business / Lodge Name -->
            <div class="space-y-1">
              <label class="block font-bold text-slate-700 dark:text-slate-200">Legal Business / Organization Name <span class="text-rose-500">*</span></label>
              <input
                v-model="businessForm.business_name"
                type="text"
                required
                placeholder="e.g. Lodge of Fraternity No. 1418 Ltd"
                class="w-full p-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-medium text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
              />
            </div>

            <!-- Company / Charity Registration No. -->
            <div class="space-y-1">
              <label class="block font-bold text-slate-700 dark:text-slate-200">Company / Charity Registration Number</label>
              <input
                v-model="businessForm.company_number"
                type="text"
                placeholder="e.g. 12948573 or Charity No. 110928"
                class="w-full p-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-medium text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
              />
            </div>

            <!-- Tax / VAT Registration Number -->
            <div class="space-y-1">
              <label class="block font-bold text-slate-700 dark:text-slate-200">Tax / VAT Registration ID</label>
              <input
                v-model="businessForm.tax_id"
                type="text"
                placeholder="e.g. GB 987 6543 21"
                class="w-full p-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-medium text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
              />
            </div>

            <!-- Official Billing Contact Email -->
            <div class="space-y-1">
              <label class="block font-bold text-slate-700 dark:text-slate-200">Official Accounts / Billing Email <span class="text-rose-500">*</span></label>
              <input
                v-model="businessForm.billing_contact_email"
                type="email"
                required
                placeholder="accounts@lodge-of-fraternity.example"
                class="w-full p-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-medium text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
              />
            </div>

            <!-- Official Billing Contact Phone -->
            <div class="space-y-1">
              <label class="block font-bold text-slate-700 dark:text-slate-200">Accounts / Treasurer Phone</label>
              <input
                v-model="businessForm.billing_phone"
                type="text"
                placeholder="+44 20 7946 0912"
                class="w-full p-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-medium text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
              />
            </div>

            <!-- Registered Legal Business Address -->
            <div class="space-y-1">
              <label class="block font-bold text-slate-700 dark:text-slate-200">Registered Business / Clubhouse Address</label>
              <input
                v-model="businessForm.address"
                type="text"
                placeholder="100 Boathouse Way, Oxford, OX1 1AA, UK"
                class="w-full p-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-medium text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
              />
            </div>

          </div>

          <!-- Merchant Gateway Credentials Header & Divider -->
          <div class="pt-5 border-t border-slate-100 dark:border-slate-800">
            <div class="flex items-center justify-between mb-3">
              <div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                  <span>💳</span> Merchant Payment Gateway API Credentials
                </h4>
                <p class="text-xs text-slate-500 dark:text-slate-400">Provide live API keys for your club's merchant account so member payments and event ticket fees flow directly to your business bank account.</p>
              </div>
              <button
                type="button"
                @click="showSecretKeys = !showSecretKeys"
                class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-lg transition-all"
              >
                {{ showSecretKeys ? '🙈 Hide API Keys' : '👁️ Show API Keys' }}
              </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800/80">
              <div class="space-y-1">
                <label class="block font-bold text-slate-700 dark:text-slate-200">Stripe Publishable Key</label>
                <input
                  v-model="businessForm.stripe_publishable_key"
                  :type="showSecretKeys ? 'text' : 'password'"
                  placeholder="pk_live_..."
                  class="w-full p-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg font-mono text-[11px] text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
                />
              </div>

              <div class="space-y-1">
                <label class="block font-bold text-slate-700 dark:text-slate-200">Stripe Secret Key</label>
                <input
                  v-model="businessForm.stripe_secret_key"
                  :type="showSecretKeys ? 'text' : 'password'"
                  :placeholder="businessDetails?.has_stripe_secret_key ? 'Saved - leave blank to keep' : 'sk_live_...'"
                  class="w-full p-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg font-mono text-[11px] text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
                />
              </div>

              <div class="space-y-1">
                <label class="block font-bold text-slate-700 dark:text-slate-200">Stripe Webhook Secret</label>
                <input
                  v-model="businessForm.stripe_webhook_secret"
                  :type="showSecretKeys ? 'text' : 'password'"
                  :placeholder="businessDetails?.has_stripe_webhook_secret ? 'Saved - leave blank to keep' : 'whsec_...'"
                  class="w-full p-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg font-mono text-[11px] text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
                />
              </div>
            </div>
          </div>

          <div class="flex justify-end pt-2">
            <button
              type="submit"
              :disabled="businessForm.processing"
              class="px-5 py-2.5 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold text-xs rounded-xl shadow-md transition-all flex items-center gap-2 cursor-pointer"
            >
              <span v-if="businessForm.processing">Saving...</span>
              <span v-else>💾 Save Main Business Account Details</span>
            </button>
          </div>
        </form>
      </div>

      <!-- Active Plan Overview & Gateway Selector Card -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- Left: Current Plan Summary -->
        <div class="md:col-span-2 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
            <div>
              <span class="text-[10px] font-extrabold uppercase tracking-wider text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 px-2.5 py-1 rounded-full border border-blue-100 dark:border-blue-900/40">
                Active Platform Subscription
              </span>
              <h3 class="text-lg font-bold text-slate-900 dark:text-white mt-2">{{ currentPlan.name }}</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ currentPlan.billing_cycle }}</p>
            </div>
            <div class="text-right">
              <span class="text-2xl font-black text-slate-900 dark:text-white block">{{ currentPlan.price }}</span>
              <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 flex items-center justify-end gap-1 mt-0.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Active & In Good Standing</span>
              </span>
            </div>
          </div>

          <!-- Included Platform Modules -->
          <div>
            <h4 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-3">Included Platform Features & Modules</h4>
            <div class="flex flex-wrap gap-2">
              <span v-for="(module, idx) in currentPlan.modules" :key="idx" class="px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl border border-slate-200 dark:border-slate-800 flex items-center gap-1.5">
                <span class="text-emerald-500">✓</span>
                <span>{{ module }}</span>
              </span>
            </div>
          </div>

          <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
            <span>Next Automated Renewal Date: <strong class="text-slate-900 dark:text-white">{{ currentPlan.next_billing_date }}</strong></span>
          </div>
        </div>

        <!-- Right: Active Payment Gateway Selector Card -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 flex flex-col justify-between">
          <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <span>💳</span> Active Payment Gateway
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Select your preferred payment processor for member dues & SaaS checkouts.</p>

            <div class="space-y-2 mt-4">
              <!-- Stripe Option -->
              <button
                type="button"
                @click="updateProvider('stripe')"
                :class="[
                  'w-full p-3 rounded-xl border text-left text-xs font-bold transition-all flex items-center justify-between cursor-pointer',
                  providerForm.provider === 'stripe'
                    ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-300 dark:border-blue-700/60 text-blue-900 dark:text-blue-200 shadow-sm'
                    : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                ]"
              >
                <div class="flex items-center gap-2.5">
                  <span class="text-base">💳</span>
                  <div>
                    <span class="block">Stripe Gateway</span>
                    <span class="text-[10px] font-semibold text-slate-400">Cards, Apple Pay, Google Pay</span>
                  </div>
                </div>
                <span v-if="providerForm.provider === 'stripe'" class="text-blue-600 dark:text-blue-400 font-black">✓ Active</span>
              </button>

              <!-- Paddle Option -->
              <button
                type="button"
                @click="updateProvider('paddle')"
                :class="[
                  'w-full p-3 rounded-xl border text-left text-xs font-bold transition-all flex items-center justify-between cursor-pointer',
                  providerForm.provider === 'paddle'
                    ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-300 dark:border-blue-700/60 text-blue-900 dark:text-blue-200 shadow-sm'
                    : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                ]"
              >
                <div class="flex items-center gap-2.5">
                  <span class="text-base">🌐</span>
                  <div>
                    <span class="block">Paddle Billing</span>
                    <span class="text-[10px] font-semibold text-slate-400">Merchant of Record & International Tax</span>
                  </div>
                </div>
                <span v-if="providerForm.provider === 'paddle'" class="text-blue-600 dark:text-blue-400 font-black">✓ Active</span>
              </button>
            </div>
          </div>

          <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200/80 dark:border-slate-800/80 text-[11px] text-slate-500 dark:text-slate-400">
            🔒 Gateways use 256-bit SSL encryption. Payment data is securely stored.
          </div>
        </div>

      </div>

      <!-- SaaS Invoices & Billing History Table -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden space-y-4 p-6">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
          <div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">📄 SaaS Platform Invoices & Receipts</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">View recent platform subscription payments and download tax receipts.</p>
          </div>
          <button type="button" class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 transition-all cursor-pointer">
            Download All Receipts (PDF)
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase tracking-wider border-b border-slate-200/80 dark:border-slate-800/80">
                <th class="p-3">Invoice Ref</th>
                <th class="p-3">Date</th>
                <th class="p-3">Description</th>
                <th class="p-3">Amount</th>
                <th class="p-3">Payment Status</th>
                <th class="p-3 text-right">Receipt</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr v-for="inv in billingHistory" :key="inv.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
                <td class="p-3 font-mono font-bold text-slate-900 dark:text-white">{{ inv.id }}</td>
                <td class="p-3 font-medium text-slate-600 dark:text-slate-300">{{ inv.date }}</td>
                <td class="p-3 font-medium text-slate-800 dark:text-slate-100">{{ inv.description }}</td>
                <td class="p-3 font-bold text-slate-900 dark:text-white">{{ inv.amount }}</td>
                <td class="p-3">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800/60">
                    ✓ {{ inv.status }}
                  </span>
                </td>
                <td class="p-3 text-right">
                  <span class="text-blue-600 dark:text-blue-400 font-bold hover:underline cursor-pointer">
                    PDF Receipt &rarr;
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

  </AdminLayout>
</template>
