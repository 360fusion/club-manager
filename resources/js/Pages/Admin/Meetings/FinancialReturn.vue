<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

import { currencySymbol } from '@/Utils/currency';
const props = defineProps({
  club: {
    type: Object,
    required: true,
  },
  meeting: {
    type: Object,
    required: true,
  },
  confirmedDiningCount: {
    type: Number,
    default: 0,
  },
  financialReturn: {
    type: Object,
    default: null,
  },
});

const defaultDate = computed(() => {
  let d = null;
  if (props.financialReturn?.return_date) {
    d = String(props.financialReturn.return_date);
  } else if (props.meeting?.meeting_date) {
    d = String(props.meeting.meeting_date);
  }
  if (d) {
    return d.includes('T') ? d.split('T')[0] : d.substring(0, 10);
  }
  return new Date().toISOString().split('T')[0];
});

const form = useForm({
  return_date: defaultDate.value,
  kitchen_vendor_name: props.financialReturn?.kitchen_vendor_name || 'Masonic Hall Catering Ltd',
  paid_diners_count: props.financialReturn ? props.financialReturn.paid_diners_count : (props.confirmedDiningCount || 0),
  dining_fee_per_head: props.financialReturn ? parseFloat(props.financialReturn.dining_fee_per_head) : (parseFloat(props.meeting.dining_cost_member) || 35.00),
  kitchen_cost_per_head: props.financialReturn ? parseFloat(props.financialReturn.kitchen_cost_per_head) : 28.00,
  waived_diners_count: props.financialReturn ? props.financialReturn.waived_diners_count : 0,
  waived_reason: props.financialReturn?.waived_reason || 'Official Guests / Visiting Speakers',
  raffle_amount: props.financialReturn ? parseFloat(props.financialReturn.raffle_amount) : 0,
  alms_amount: props.financialReturn ? parseFloat(props.financialReturn.alms_amount) : 0,
  donations_amount: props.financialReturn ? parseFloat(props.financialReturn.donations_amount) : 0,
  bequest_amount: props.financialReturn ? parseFloat(props.financialReturn.bequest_amount) : 0,
  notes: props.financialReturn?.notes || '',
});

// Computed Calculations
const calcTotalMeals = computed(() => {
  return (parseInt(form.paid_diners_count) || 0) + (parseInt(form.waived_diners_count) || 0);
});

const calcDiningRevenue = computed(() => {
  return (parseInt(form.paid_diners_count) || 0) * (parseFloat(form.dining_fee_per_head) || 0);
});

const calcKitchenBill = computed(() => {
  return calcTotalMeals.value * (parseFloat(form.kitchen_cost_per_head) || 0);
});

const calcDiningSurplus = computed(() => {
  return calcDiningRevenue.value - calcKitchenBill.value;
});

const calcTotalCharity = computed(() => {
  return (parseFloat(form.raffle_amount) || 0) +
         (parseFloat(form.alms_amount) || 0) +
         (parseFloat(form.donations_amount) || 0) +
         (parseFloat(form.bequest_amount) || 0);
});

const calcBankDeposit = computed(() => {
  return calcDiningRevenue.value + calcTotalCharity.value;
});

const saveDraft = () => {
  form.transform((data) => ({
    ...data,
    is_draft: true,
  })).post(route('admin.meetings.financial_return.store', { clubSlug: props.club.slug, id: props.meeting.id }));
};

const submit = () => {
  form.transform((data) => ({
    ...data,
    is_draft: false,
  })).post(route('admin.meetings.financial_return.store', { clubSlug: props.club.slug, id: props.meeting.id }));
};

const formatCurrency = (val) => {
  const num = parseFloat(val) || 0;
  return currencySymbol() + num.toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>

<template>
  <AdminLayout :club="club" title="Meeting Financial Return" active-tab="meetings">
    <Head :title="`Meeting Financial Return - ${club.name}`" />

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

      <!-- Breadcrumbs & Header Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
        <div>
          <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 font-semibold mb-1">
            <Link :href="route('admin.meetings.index', club.slug)" class="hover:text-slate-800 dark:hover:text-slate-100 transition-colors">Meetings</Link>
            <span>/</span>
            <Link :href="route('admin.meetings.show', { clubSlug: club.slug, id: meeting.id })" class="hover:text-slate-800 dark:hover:text-slate-100 transition-colors">
              {{ meeting.title || ('Meeting - ' + meeting.meeting_date) }}
            </Link>
            <span>/</span>
            <span class="text-slate-900 dark:text-white font-bold">Financial Return</span>
          </div>
          <h1 class="text-2xl font-black text-slate-900 dark:text-white flex flex-wrap items-center gap-2.5">
            <span>💰 Meeting Financial Return & Dining Calculator</span>
            <span v-if="financialReturn?.is_draft" class="px-2.5 py-0.5 rounded-full text-xs font-black bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700/60">
              📝 Draft Saved
            </span>
          </h1>
          <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-1">
            Record dining receipts, caterer kitchen expenses, and meeting charity collections to automatically post to Accounts Payable and the General Ledger.
          </p>
        </div>

        <Link
          :href="route('admin.meetings.show', { clubSlug: club.slug, id: meeting.id })"
          class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl shadow-sm transition-all flex items-center gap-1.5 self-start sm:self-auto"
        >
          <span>← Back to Meeting Dashboard</span>
        </Link>
      </div>

      <!-- Meeting Overview Card -->
      <div class="bg-slate-900 dark:bg-slate-700 text-white p-5 rounded-2xl shadow-lg border border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-400/30">
              {{ meeting.status }}
            </span>
            <span v-if="financialReturn" class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
              ✓ Posted to Ledger
            </span>
          </div>
          <h2 class="text-lg font-extrabold text-white">{{ meeting.title || ('Meeting - ' + meeting.meeting_date) }}</h2>
          <p class="text-xs text-slate-400">📍 {{ meeting.venue || 'Masonic Hall' }} &nbsp;•&nbsp; 📅 {{ meeting.meeting_date }}</p>
        </div>

        <div class="flex items-center gap-6 border-t md:border-t-0 md:border-l border-slate-800 pt-3 md:pt-0 md:pl-6 text-xs">
          <div>
            <div class="text-[10px] uppercase font-bold text-slate-400">Confirmed RSVPs</div>
            <div class="text-base font-black text-amber-400">{{ confirmedDiningCount }} diners</div>
          </div>
          <div>
            <div class="text-[10px] uppercase font-bold text-slate-400">Dining Fee / Head</div>
            <div class="text-base font-black text-emerald-400">{{ formatCurrency(form.dining_fee_per_head) }}</div>
          </div>
        </div>
      </div>

      <!-- Financial Return Main Form Card -->
      <form @submit.prevent="submit" class="bg-white dark:bg-slate-900 rounded-3xl border-2 border-slate-200 dark:border-slate-800 shadow-md p-6 sm:p-8 space-y-8">
        
        <!-- Section 1: Accounting Return Date & Kitchen Vendor -->
        <div class="space-y-4">
          <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2 border-b-2 border-slate-100 dark:border-slate-800 pb-2">
            <span>🗓️ Return Date & Catering Supplier</span>
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 bg-slate-50/80 dark:bg-slate-800/50/80 p-5 rounded-2xl border-2 border-slate-200 dark:border-slate-800">
            <div class="space-y-1.5">
              <label for="return_date" class="block text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider">
                Accounting Return Date <span class="text-rose-600 dark:text-rose-400">*</span>
              </label>
              <input
                id="return_date"
                type="date"
                v-model="form.return_date"
                class="w-full text-sm rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 hover:border-slate-400 focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20 p-3 font-bold text-slate-900 dark:text-white shadow-sm transition-all"
                required
              />
            </div>
            <div class="space-y-1.5">
              <label for="kitchen_vendor_name" class="block text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider">
                Kitchen Vendor (Accounts Payable) <span class="text-rose-600 dark:text-rose-400">*</span>
              </label>
              <input
                id="kitchen_vendor_name"
                type="text"
                v-model="form.kitchen_vendor_name"
                placeholder="e.g. Masonic Hall Catering Ltd"
                class="w-full text-sm rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 hover:border-slate-400 focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20 p-3 font-bold text-slate-900 dark:text-white shadow-sm transition-all"
                required
              />
            </div>
          </div>
        </div>

        <!-- Section 2: Dining Fees & Kitchen Caterer Calculator -->
        <div class="space-y-4">
          <h3 class="text-sm font-black text-amber-950 dark:text-amber-100 uppercase tracking-wider flex items-center gap-2 border-b-2 border-amber-200 dark:border-amber-800/60 pb-2">
            <span>🍽️ 1. Dining Fees & Kitchen Caterer Calculator</span>
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div class="space-y-1.5 bg-amber-50/40 dark:bg-amber-950/40 p-4 rounded-2xl border-2 border-amber-200/80 dark:border-amber-800/80">
              <label for="paid_diners_count" class="block text-xs font-black text-slate-900 dark:text-white">
                Paid Diners Count <span class="text-rose-600 dark:text-rose-400">*</span>
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 dark:text-slate-400 font-bold text-sm">👥</span>
                <input
                  id="paid_diners_count"
                  type="number"
                  min="0"
                  v-model.number="form.paid_diners_count"
                  class="w-full text-base rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 hover:border-amber-500 focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20 pl-9 pr-3 py-2.5 font-black text-slate-900 dark:text-white shadow-sm transition-all"
                  required
                />
              </div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Number of paying diners</p>
            </div>

            <div class="space-y-1.5 bg-amber-50/40 dark:bg-amber-950/40 p-4 rounded-2xl border-2 border-amber-200/80 dark:border-amber-800/80">
              <label for="dining_fee_per_head" class="block text-xs font-black text-slate-900 dark:text-white">
                Dining Fee / Head ({{ $cs }}) <span class="text-rose-600 dark:text-rose-400">*</span>
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-600 dark:text-slate-300 font-black text-sm">{{ $cs }}</span>
                <input
                  id="dining_fee_per_head"
                  type="number"
                  step="0.01"
                  min="0"
                  v-model.number="form.dining_fee_per_head"
                  class="w-full text-base rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 hover:border-amber-500 focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20 pl-8 pr-3 py-2.5 font-black text-slate-900 dark:text-white shadow-sm transition-all font-mono"
                  required
                />
              </div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Price charged per diner</p>
            </div>

            <div class="space-y-1.5 bg-amber-50/40 dark:bg-amber-950/40 p-4 rounded-2xl border-2 border-amber-200/80 dark:border-amber-800/80">
              <label for="kitchen_cost_per_head" class="block text-xs font-black text-slate-900 dark:text-white">
                Kitchen Cost / Head ({{ $cs }}) <span class="text-rose-600 dark:text-rose-400">*</span>
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-600 dark:text-slate-300 font-black text-sm">{{ $cs }}</span>
                <input
                  id="kitchen_cost_per_head"
                  type="number"
                  step="0.01"
                  min="0"
                  v-model.number="form.kitchen_cost_per_head"
                  class="w-full text-base rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 hover:border-amber-500 focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20 pl-8 pr-3 py-2.5 font-black text-slate-900 dark:text-white shadow-sm transition-all font-mono"
                  required
                />
              </div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Caterer fee per meal prepared</p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-5 gap-4 pt-1">
            <div class="sm:col-span-2 space-y-1.5">
              <label for="waived_diners_count" class="block text-xs font-black text-slate-900 dark:text-white">
                Waived Diners (Guests / Speakers)
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500 dark:text-slate-400 font-bold text-sm">🎟️</span>
                <input
                  id="waived_diners_count"
                  type="number"
                  min="0"
                  v-model.number="form.waived_diners_count"
                  class="w-full text-sm rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 hover:bg-white dark:hover:bg-slate-900 focus:bg-white dark:focus:bg-slate-900 focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20 pl-9 pr-3 py-2.5 font-bold text-slate-900 dark:text-white shadow-sm transition-all"
                />
              </div>
            </div>
            <div class="sm:col-span-3 space-y-1.5">
              <label for="waived_reason" class="block text-xs font-black text-slate-900 dark:text-white">
                Reason for Waived Fee
              </label>
              <input
                id="waived_reason"
                type="text"
                v-model="form.waived_reason"
                placeholder="e.g. Official guests, visiting speakers"
                class="w-full text-sm rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 hover:bg-white dark:hover:bg-slate-900 focus:bg-white dark:focus:bg-slate-900 focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20 px-3 py-2.5 font-medium text-slate-900 dark:text-white shadow-sm transition-all"
              />
            </div>
          </div>

          <!-- Dining Calculation Preview Card -->
          <div class="bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-amber-500/10 p-5 rounded-2xl border-2 border-amber-300/80 dark:border-amber-700/80 shadow-sm space-y-3">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
              <div class="bg-white dark:bg-slate-900 p-3 rounded-xl border-2 border-amber-200/80 dark:border-amber-800/80 shadow-xs">
                <div class="text-[10px] uppercase font-black text-slate-600 dark:text-slate-300">Total Meals Prepared</div>
                <div class="text-xl font-black text-slate-900 dark:text-white">{{ calcTotalMeals }} {{ calcTotalMeals === 1 ? 'meal' : 'meals' }}</div>
              </div>
              <div class="bg-white dark:bg-slate-900 p-3 rounded-xl border-2 border-amber-200/80 dark:border-amber-800/80 shadow-xs">
                <div class="text-[10px] uppercase font-black text-slate-600 dark:text-slate-300">Gross Dining Revenue</div>
                <div class="text-xl font-black text-emerald-800 dark:text-emerald-200 font-mono">{{ formatCurrency(calcDiningRevenue) }}</div>
              </div>
              <div class="bg-white dark:bg-slate-900 p-3 rounded-xl border-2 border-amber-200/80 dark:border-amber-800/80 shadow-xs">
                <div class="text-[10px] uppercase font-black text-slate-600 dark:text-slate-300">Kitchen Caterer Bill</div>
                <div class="text-xl font-black text-rose-800 dark:text-rose-200 font-mono">{{ formatCurrency(calcKitchenBill) }}</div>
              </div>
              <div class="bg-white dark:bg-slate-900 p-3 rounded-xl border-2 border-amber-200/80 dark:border-amber-800/80 shadow-xs">
                <div class="text-[10px] uppercase font-black text-slate-600 dark:text-slate-300">Net Dining Surplus</div>
                <div :class="['text-xl font-black font-mono', calcDiningSurplus >= 0 ? 'text-blue-800 dark:text-blue-200' : 'text-rose-700 dark:text-rose-300']">
                  {{ formatCurrency(calcDiningSurplus) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Section 3: Charity Collections & Contributions -->
        <div class="space-y-4">
          <h3 class="text-sm font-black text-blue-950 dark:text-blue-100 uppercase tracking-wider flex items-center gap-2 border-b-2 border-blue-200 dark:border-blue-800/60 pb-2">
            <span>🎗️ 2. Meeting Charity & Almoner Collections</span>
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <div class="space-y-1.5 bg-blue-50/50 dark:bg-blue-950/50 p-4 rounded-2xl border-2 border-blue-200 dark:border-blue-800/60">
              <label for="raffle_amount" class="block text-xs font-black text-slate-900 dark:text-white">
                Raffle Collection ({{ $cs }})
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-blue-700 dark:text-blue-300 font-black text-sm">{{ $cs }}</span>
                <input
                  id="raffle_amount"
                  type="number"
                  step="0.01"
                  min="0"
                  v-model.number="form.raffle_amount"
                  class="w-full text-base rounded-xl border-2 border-blue-300 dark:border-blue-700/60 bg-white dark:bg-slate-900 hover:border-blue-500 focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 pl-8 pr-3 py-2.5 font-black text-slate-900 dark:text-white shadow-sm transition-all font-mono"
                />
              </div>
            </div>

            <div class="space-y-1.5 bg-blue-50/50 dark:bg-blue-950/50 p-4 rounded-2xl border-2 border-blue-200 dark:border-blue-800/60">
              <label for="alms_amount" class="block text-xs font-black text-slate-900 dark:text-white">
                Alms Box Collection ({{ $cs }})
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-blue-700 dark:text-blue-300 font-black text-sm">{{ $cs }}</span>
                <input
                  id="alms_amount"
                  type="number"
                  step="0.01"
                  min="0"
                  v-model.number="form.alms_amount"
                  class="w-full text-base rounded-xl border-2 border-blue-300 dark:border-blue-700/60 bg-white dark:bg-slate-900 hover:border-blue-500 focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 pl-8 pr-3 py-2.5 font-black text-slate-900 dark:text-white shadow-sm transition-all font-mono"
                />
              </div>
            </div>

            <div class="space-y-1.5 bg-blue-50/50 dark:bg-blue-950/50 p-4 rounded-2xl border-2 border-blue-200 dark:border-blue-800/60">
              <label for="donations_amount" class="block text-xs font-black text-slate-900 dark:text-white">
                Donations ({{ $cs }})
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-blue-700 dark:text-blue-300 font-black text-sm">{{ $cs }}</span>
                <input
                  id="donations_amount"
                  type="number"
                  step="0.01"
                  min="0"
                  v-model.number="form.donations_amount"
                  class="w-full text-base rounded-xl border-2 border-blue-300 dark:border-blue-700/60 bg-white dark:bg-slate-900 hover:border-blue-500 focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 pl-8 pr-3 py-2.5 font-black text-slate-900 dark:text-white shadow-sm transition-all font-mono"
                />
              </div>
            </div>

            <div class="space-y-1.5 bg-blue-50/50 dark:bg-blue-950/50 p-4 rounded-2xl border-2 border-blue-200 dark:border-blue-800/60">
              <label for="bequest_amount" class="block text-xs font-black text-slate-900 dark:text-white">
                Bequests ({{ $cs }})
              </label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-blue-700 dark:text-blue-300 font-black text-sm">{{ $cs }}</span>
                <input
                  id="bequest_amount"
                  type="number"
                  step="0.01"
                  min="0"
                  v-model.number="form.bequest_amount"
                  class="w-full text-base rounded-xl border-2 border-blue-300 dark:border-blue-700/60 bg-white dark:bg-slate-900 hover:border-blue-500 focus:border-blue-600 focus:ring-2 focus:ring-blue-500/20 pl-8 pr-3 py-2.5 font-black text-slate-900 dark:text-white shadow-sm transition-all font-mono"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Section 4: Treasury Notes & Remarks -->
        <div class="space-y-2">
          <label for="notes" class="block text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider">
            Treasury Notes / Remarks
          </label>
          <textarea
            id="notes"
            v-model="form.notes"
            rows="2"
            placeholder="e.g. Raffle receipts to be remitted to Masonic Charity Foundation. Catering invoice matched to kitchen cost."
            class="w-full text-sm rounded-xl border-2 border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 hover:bg-white dark:hover:bg-slate-900 focus:bg-white dark:focus:bg-slate-900 hover:border-amber-500 focus:border-amber-600 focus:ring-2 focus:ring-amber-500/20 p-3 font-semibold text-slate-900 dark:text-white shadow-sm transition-all"
          ></textarea>
        </div>

        <!-- Section 5: Total Expected Bank Deposit Summary Bar -->
        <div class="bg-slate-900 dark:bg-slate-700 text-white p-6 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xl border-2 border-slate-800">
          <div>
            <div class="text-xs font-extrabold text-amber-400 uppercase tracking-wider">Net Bank Deposit Expected</div>
            <div class="text-3xl font-black text-amber-300 font-mono">{{ formatCurrency(calcBankDeposit) }}</div>
            <div class="text-[11px] text-slate-300 mt-1">Includes Gross Dining Income ({{ formatCurrency(calcDiningRevenue) }}) + Total Charity Collections ({{ formatCurrency(calcTotalCharity) }})</div>
          </div>
          <div class="text-right space-y-1 border-t md:border-t-0 md:border-l border-slate-800 pt-3 md:pt-0 md:pl-6">
            <div class="text-xs text-slate-200 font-semibold">Kitchen AP Bill Created: <span class="font-black text-rose-400 font-mono">{{ formatCurrency(calcKitchenBill) }}</span></div>
            <div class="text-xs text-slate-200 font-semibold">Total Charity Collected: <span class="font-black text-blue-300 font-mono">{{ formatCurrency(calcTotalCharity) }}</span></div>
          </div>
        </div>

        <!-- Submit, Save Draft & Cancel Buttons -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4 border-t-2 border-slate-100 dark:border-slate-800">
          <Link
            :href="route('admin.meetings.show', { clubSlug: club.slug, id: meeting.id })"
            class="w-full sm:w-auto text-center px-5 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-black text-xs rounded-xl transition-all"
          >
            Cancel & Return to Meeting
          </Link>

          <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <button
              type="button"
              @click="saveDraft"
              :disabled="form.processing"
              class="w-full sm:w-auto px-5 py-3 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-100 font-black text-xs rounded-xl shadow-sm transition-all cursor-pointer disabled:opacity-50 flex items-center justify-center gap-2"
            >
              <span>💾 Save Draft</span>
            </button>

            <button
              type="submit"
              :disabled="form.processing"
              class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-black text-xs rounded-xl shadow-lg transition-all cursor-pointer disabled:opacity-50 flex items-center justify-center gap-2"
            >
              <span>⚡ Post Financial Return & Ledger Entry</span>
            </button>
          </div>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
