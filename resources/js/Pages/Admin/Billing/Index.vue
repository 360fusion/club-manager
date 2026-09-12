<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  activeProvider: String,
  stripeConfigured: Boolean,
  paddleConfigured: Boolean,
  plans: Array,
  flashStatus: String,
});

const selectedProvider = ref(props.activeProvider || 'stripe');
const processingProvider = ref(false);

const switchProvider = (provider) => {
  selectedProvider.value = provider;
  processingProvider.value = true;
  router.post(route('billing.provider.update'), { provider }, {
    onFinish: () => { processingProvider.value = false; }
  });
};

const initiateCheckout = (plan) => {
  const priceId = selectedProvider.value === 'stripe' ? plan.stripe_price_id : plan.paddle_price_id;
  router.post(route('billing.checkout'), { price_id: priceId });
};
</script>

<template>
  <AdminLayout title="Billing" :club="club" active-tab="billing">
    
    <div class="space-y-6">
      
      <!-- Flash status notification -->
      <div v-if="flashStatus" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-xs font-semibold">
        <span>Payment status update: {{ flashStatus }}</span>
      </div>

      <!-- Provider Selector Card -->
      <div class="bg-white rounded-2xl p-6 md:p-8 shadow-sm border border-slate-200/80 space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
              <span>💳 Active Payment Gateway Provider</span>
            </h2>
            <p class="text-xs text-slate-500 mt-1">
              Select which gateway handles member billing and subscription checkouts for this club.
            </p>
          </div>

          <!-- Gateway Toggle Buttons -->
          <div class="flex items-center gap-2 bg-slate-100 p-1.5 rounded-xl border border-slate-200">
            <button
              @click="switchProvider('stripe')"
              :disabled="processingProvider"
              :class="[
                'px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2',
                selectedProvider === 'stripe'
                  ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                  : 'text-slate-600 hover:text-slate-900'
              ]"
            >
              <span class="w-2 h-2 rounded-full bg-indigo-300"></span>
              Stripe (Direct / Cards)
            </button>

            <button
              @click="switchProvider('paddle')"
              :disabled="processingProvider"
              :class="[
                'px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2',
                selectedProvider === 'paddle'
                  ? 'bg-cyan-600 text-white shadow-md shadow-cyan-600/20'
                  : 'text-slate-600 hover:text-slate-900'
              ]"
            >
              <span class="w-2 h-2 rounded-full bg-cyan-300"></span>
              Paddle (MoR / Tax Compliant)
            </button>
          </div>
        </div>

        <!-- Provider Specs grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-slate-100">
          <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-800 text-xs">Stripe Integration</span>
              <span :class="stripeConfigured ? 'text-emerald-700 bg-emerald-50' : 'text-amber-700 bg-amber-50'" class="px-2 py-0.5 rounded text-[10px] font-bold border border-current">
                {{ stripeConfigured ? 'Keys Active' : 'Sandbox Demo Mode' }}
              </span>
            </div>
            <p class="text-xs text-slate-500">Direct card processing with Stripe Elements & Cashier subscription hooks.</p>
          </div>

          <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-800 text-xs">Paddle Integration</span>
              <span :class="paddleConfigured ? 'text-emerald-700 bg-emerald-50' : 'text-amber-700 bg-amber-50'" class="px-2 py-0.5 rounded text-[10px] font-bold border border-current">
                {{ paddleConfigured ? 'Keys Active' : 'Sandbox Demo Mode' }}
              </span>
            </div>
            <p class="text-xs text-slate-500">Merchant of Record billing, automatic international sales tax & VAT remittance.</p>
          </div>
        </div>
      </div>

      <!-- Subscription Plans Section -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-lg font-bold text-slate-900">Available SaaS & Club Membership Plans</h2>
            <p class="text-xs text-slate-500">Active gateway: <strong class="text-indigo-600 uppercase font-mono">{{ selectedProvider }}</strong></p>
          </div>

          <a :href="route('billing.portal')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-300 transition-all flex items-center gap-2">
            <span>⚙️ Customer Portal</span>
          </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div v-for="plan in plans" :key="plan.id" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 hover:border-slate-300 transition-all flex flex-col justify-between space-y-6">
            <div>
              <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900">{{ plan.name }}</h3>
                <span class="text-2xl font-black text-indigo-600">{{ plan.price }}</span>
              </div>

              <ul class="mt-4 space-y-2">
                <li v-for="feature in plan.features" :key="feature" class="text-xs text-slate-600 flex items-center gap-2">
                  <span class="text-emerald-600 font-bold">✓</span> {{ feature }}
                </li>
              </ul>
            </div>

            <button
              @click="initiateCheckout(plan)"
              class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-all shadow-md shadow-indigo-600/20 flex items-center justify-center gap-2"
            >
              <span>Subscribe via {{ selectedProvider === 'stripe' ? 'Stripe' : 'Paddle' }}</span>
              <span>&rarr;</span>
            </button>
          </div>
        </div>
      </div>

    </div>

  </AdminLayout>
</template>
