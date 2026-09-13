<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  plans: Array,
});

const deletePlan = (planId) => {
  if (confirm('Are you sure you want to delete this membership plan?')) {
    router.delete(route('admin.memberships.destroy', { clubSlug: props.club.slug, id: planId }));
  }
};
</script>

<template>
  <AdminLayout title="Subscriptions" :club="club" active-tab="memberships">
    
    <div class="space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900">Membership Plans & Pricing Tiers</h2>
          <p class="text-xs text-slate-500 mt-1">Configure recurring membership tiers, dues pricing, and member benefits.</p>
        </div>
        <Link :href="route('admin.memberships.create', { clubSlug: club.slug })" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md shadow-emerald-600/20 transition-all text-center">
          + Create Membership Plan
        </Link>
      </div>

      <!-- Plans Cards Grid -->
      <div v-if="plans.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="plan in plans" :key="plan.id" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 hover:border-slate-300 transition-all flex flex-col justify-between space-y-6">
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="px-2.5 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase tracking-wider">
                {{ plan.billing_period }}
              </span>
              <span class="text-2xl font-black text-slate-900">£{{ plan.price }}</span>
            </div>

            <h3 class="text-lg font-bold text-slate-900">{{ plan.name }}</h3>
            <p class="text-xs text-slate-500">{{ plan.description }}</p>

            <div class="pt-2 text-xs text-slate-400">
              Active Subscribers: <strong class="text-slate-800">{{ plan.memberships_count }}</strong>
            </div>
          </div>

          <div class="flex items-center gap-2 pt-4 border-t border-slate-100">
            <Link :href="route('admin.memberships.edit', { clubSlug: club.slug, id: plan.id })" class="flex-1 text-center py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-200 transition-all">
              ✏️ Edit Plan
            </Link>
            <button @click="deletePlan(plan.id)" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-semibold rounded-xl border border-rose-200 transition-all">
              🗑️ Delete
            </button>
          </div>
        </div>
      </div>

      <div v-else class="bg-white rounded-2xl p-12 text-center shadow-sm border border-slate-200/80 space-y-4">
        <p class="text-slate-500 text-sm">No membership plans found for this club.</p>
        <Link :href="route('admin.memberships.create', { clubSlug: club.slug })" class="inline-block px-5 py-2.5 bg-emerald-600 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20">
          Create First Plan
        </Link>
      </div>

    </div>

  </AdminLayout>
</template>
