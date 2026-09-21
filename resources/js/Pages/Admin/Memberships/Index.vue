<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  plans: Array,
  activeProvider: String,
  stripeConfigured: Boolean,
  paddleConfigured: Boolean,
  flashStatus: String,
});

const selectedProvider = ref(props.activeProvider || 'stripe');
const processingProvider = ref(false);

const switchProvider = (provider) => {
  selectedProvider.value = provider;
  processingProvider.value = true;
  router.post(route('billing.provider.update', { clubSlug: props.club.slug }), { provider }, {
    onFinish: () => { processingProvider.value = false; }
  });
};

const deletePlan = (planId) => {
  if (confirm('Are you sure you want to delete this membership plan?')) {
    router.delete(route('admin.memberships.destroy', { clubSlug: props.club.slug, id: planId }));
  }
};
</script>

<template>
  <AdminLayout title="Subscriptions & Payment Gateway" :club="club" active-tab="memberships">
    
    <div class="space-y-6">

      <!-- Flash status notification -->
      <div v-if="flashStatus" class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-700 dark:text-emerald-300 rounded-xl text-xs font-semibold">
        <span>Payment status update: {{ flashStatus }}</span>
      </div>

      <!-- Payment Gateway & Billing Provider Configuration Card -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 md:p-8 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
              <span>💳 Active Payment Gateway Provider</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
              Select which payment gateway handles member subscription checkouts and recurring dues for this club.
            </p>
          </div>

          <!-- Gateway Toggle Buttons & Settings Tab Link -->
          <div class="flex items-center gap-3 flex-wrap">
            <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 p-1.5 rounded-xl border border-slate-200 dark:border-slate-800">
              <button
                @click="switchProvider('stripe')"
                :disabled="processingProvider"
                :class="[
                  'px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2 cursor-pointer',
                  selectedProvider === 'stripe'
                    ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20'
                    : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                ]"
              >
                <span class="w-2 h-2 rounded-full bg-blue-300"></span>
                Stripe (Direct / Cards)
              </button>

              <button
                @click="switchProvider('paddle')"
                :disabled="processingProvider"
                :class="[
                  'px-4 py-2 rounded-lg text-xs font-bold transition-all flex items-center gap-2 cursor-pointer',
                  selectedProvider === 'paddle'
                    ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20'
                    : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                ]"
              >
                <span class="w-2 h-2 rounded-full bg-blue-300"></span>
                Paddle (MoR / Tax Compliant)
              </button>
            </div>

            <a :href="route('admin.settings.show', { clubSlug: props.club.slug }) + '#payments'" class="px-4 py-2 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs font-bold rounded-xl border border-blue-200 dark:border-blue-800/60 transition-all flex items-center gap-1.5">
              <span>⚙️ Gateway Settings Tab ➔</span>
            </a>

            <a :href="route('billing.portal', { clubSlug: props.club.slug })" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl border border-slate-300 dark:border-slate-700 transition-all flex items-center gap-1.5">
              <span>⚙️ Customer Portal</span>
            </a>
          </div>
        </div>

        <!-- Gateway status specs grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
          <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-800 dark:text-slate-100 text-xs">Stripe Integration</span>
              <span :class="stripeConfigured ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/60' : 'text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/60'" class="px-2 py-0.5 rounded text-[10px] font-bold border">
                {{ stripeConfigured ? 'Keys Active' : 'Sandbox Demo Mode' }}
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Direct card processing with Stripe Elements &amp; Cashier subscription hooks.</p>
          </div>

          <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-800 dark:text-slate-100 text-xs">Paddle Integration</span>
              <span :class="paddleConfigured ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/60' : 'text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/60'" class="px-2 py-0.5 rounded text-[10px] font-bold border">
                {{ paddleConfigured ? 'Keys Active' : 'Sandbox Demo Mode' }}
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Merchant of Record billing, automatic international sales tax &amp; VAT remittance.</p>
          </div>
        </div>
      </div>
      
      <!-- Top Action Bar for Membership Plans -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">Membership Plans & Pricing Tiers</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configure recurring membership tiers, dues pricing, and member benefits.</p>
        </div>
        <Link :href="route('admin.memberships.create', { clubSlug: club.slug })" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 transition-all text-center">
          + Create Membership Plan
        </Link>
      </div>

      <!-- Plans Cards Grid -->
      <div v-if="plans.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="plan in plans" :key="plan.id" class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700 transition-all flex flex-col justify-between space-y-6">
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="px-2.5 py-0.5 rounded text-xs font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 uppercase tracking-wider">
                {{ plan.billing_period }}
              </span>
              <span class="text-2xl font-black text-slate-900 dark:text-white">{{ $cs }}{{ plan.price }}</span>
            </div>

            <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ plan.name }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ plan.description }}</p>

            <div class="pt-2 text-xs text-slate-400">
              Active Subscribers: <strong class="text-slate-800 dark:text-slate-100">{{ plan.memberships_count }}</strong>
            </div>
          </div>

          <div class="flex items-center gap-2 pt-4 border-t border-slate-100 dark:border-slate-800">
            <Link :href="route('admin.memberships.edit', { clubSlug: club.slug, id: plan.id })" class="flex-1 text-center py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-800 transition-all">
              ✏️ Edit Plan
            </Link>
            <button @click="deletePlan(plan.id)" class="px-3 py-2 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-600 dark:text-rose-400 text-xs font-semibold rounded-xl border border-rose-200 dark:border-rose-800/60 transition-all cursor-pointer">
              🗑️ Delete
            </button>
          </div>
        </div>
      </div>

      <div v-else class="bg-white dark:bg-slate-900 rounded-2xl p-12 text-center shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
        <p class="text-slate-500 dark:text-slate-400 text-sm">No membership plans found for this club.</p>
        <Link :href="route('admin.memberships.create', { clubSlug: club.slug })" class="inline-block px-5 py-2.5 bg-emerald-600 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20">
          Create First Plan
        </Link>
      </div>

    </div>

  </AdminLayout>
</template>
