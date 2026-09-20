<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  plan: Object,
});

const form = useForm({
  id: props.plan.id || null,
  name: props.plan.name || '',
  description: props.plan.description || '',
  price: props.plan.price || 25.00,
  billing_period: props.plan.billing_period || 'monthly',
});

const submit = () => {
  form.post(route('admin.memberships.store', { clubSlug: props.club.slug }));
};
</script>

<template>
  <AdminLayout :title="`${plan.id ? 'Edit' : 'Create'} Membership Plan`" :club="club" active-tab="memberships">
    
    <div class="max-w-4xl mx-auto space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex items-center justify-between bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ plan.id ? 'Edit Membership Plan' : 'Create Membership Plan' }}</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Configure membership dues and access benefits.</p>
        </div>
        <Link :href="route('admin.memberships.index', { clubSlug: club.slug })" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl transition-all">
          &larr; Back to Plans
        </Link>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Plan Name</label>
          <input v-model="form.name" type="text" required placeholder="Senior Rower Membership" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500" />
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Description</label>
          <textarea v-model="form.description" rows="3" placeholder="Includes full equipment access, boathouse locker, and dinner summons..." class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500"></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Price (£)</label>
            <input v-model="form.price" type="number" step="0.01" required placeholder="35.00" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500" />
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Billing Period</label>
            <select v-model="form.billing_period" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500">
              <option value="monthly">Monthly</option>
              <option value="yearly">Yearly</option>
              <option value="one_time">One-time</option>
            </select>
          </div>
        </div>

        <button type="submit" :disabled="form.processing" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-all shadow-md shadow-emerald-600/20">
          {{ form.processing ? 'Saving Plan...' : 'Save Membership Plan' }}
        </button>

      </form>

    </div>

  </AdminLayout>
</template>
