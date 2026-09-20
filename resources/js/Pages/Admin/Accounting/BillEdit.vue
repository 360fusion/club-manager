<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: { type: Object, required: true },
  bill: { type: Object, required: true },
});

const form = useForm({
  vendor_name: props.bill.vendor_name,
  category: props.bill.category,
  amount: props.bill.amount,
  due_date: props.bill.due_date,
  notes: props.bill.notes,
  status: props.bill.status,
  attachment: null,
});

const handleFileChange = (e) => {
  form.attachment = e.target.files[0] || null;
};

const submit = () => {
  form.post(route('admin.accounting.bills.update', { clubSlug: props.club.slug, id: props.bill.id }), {
    _method: 'put',
  });
};
</script>

<template>
  <AdminLayout :club="club" :title="`Edit Bill ${bill.bill_number}`" active-tab="accounting">
    <Head :title="`Edit ${bill.bill_number} — ${club.name}`" />

    <div class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">

      <!-- Breadcrumb & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1" aria-label="Breadcrumb">
            <Link :href="route('admin.accounting.index', club.slug)" class="hover:text-slate-900 dark:hover:text-white transition-colors">
              Accounting ERP
            </Link>
            <span>/</span>
            <Link :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'purchases' })" class="hover:text-slate-900 dark:hover:text-white transition-colors">
              Purchases
            </Link>
            <span>/</span>
            <span class="text-slate-900 dark:text-white font-bold" aria-current="page">Edit {{ bill.bill_number }}</span>
          </nav>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Edit Vendor Bill {{ bill.bill_number }}</h1>
            <span
              class="px-2.5 py-0.5 text-xs font-black uppercase tracking-wider rounded-full"
              :class="{
                'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200': bill.status === 'paid',
                'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200': bill.status === 'unpaid',
                'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200': bill.status === 'draft',
              }"
            >
              {{ bill.status }}
            </span>
          </div>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Update vendor bill details, category, due date, or status.</p>
        </div>
        <Link
          :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'purchases' })"
          class="inline-flex items-center gap-1 px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition-colors"
        >
          ← Back to Purchases
        </Link>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="p-6 sm:p-8 space-y-6">

          <!-- Bill Details -->
          <div class="space-y-4">
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-wider">Vendor & Category</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="space-y-1">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Vendor / Supplier Name <span class="text-rose-500">*</span></label>
                <input
                  v-model="form.vendor_name"
                  type="text"
                  required
                  placeholder="e.g. Oxford Waterways Maintenance"
                  class="w-full px-4 py-2.5 text-sm bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400"
                />
                <p v-if="form.errors.vendor_name" class="text-xs text-rose-500 font-semibold">{{ form.errors.vendor_name }}</p>
              </div>

              <div class="space-y-1">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Category / Account <span class="text-rose-500">*</span></label>
                <select
                  v-model="form.category"
                  required
                  class="w-full px-4 py-2.5 text-sm bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400"
                >
                  <option value="Equipment & Maintenance">Equipment & Maintenance</option>
                  <option value="Utilities & Energy">Utilities & Energy</option>
                  <option value="Insurance & Legal">Insurance & Legal</option>
                  <option value="Regatta & Event Costs">Regatta & Event Costs</option>
                  <option value="Catering & Bar Expenses">Catering & Bar Expenses</option>
                  <option value="Coaching & Professional Fees">Coaching & Professional Fees</option>
                  <option value="General Admin & Software">General Admin & Software</option>
                </select>
                <p v-if="form.errors.category" class="text-xs text-rose-500 font-semibold">{{ form.errors.category }}</p>
              </div>

              <div class="space-y-1">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Amount (£) <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <span class="absolute left-3.5 top-2.5 text-slate-400 font-bold">£</span>
                  <input
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    placeholder="0.00"
                    class="w-full pl-8 pr-4 py-2.5 text-sm bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400 font-mono"
                  />
                </div>
                <p v-if="form.errors.amount" class="text-xs text-rose-500 font-semibold">{{ form.errors.amount }}</p>
              </div>

              <div class="space-y-1">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Due Date <span class="text-rose-500">*</span></label>
                <input
                  v-model="form.due_date"
                  type="date"
                  required
                  class="w-full px-4 py-2.5 text-sm bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400"
                />
                <p v-if="form.errors.due_date" class="text-xs text-rose-500 font-semibold">{{ form.errors.due_date }}</p>
              </div>

              <div class="space-y-1 sm:col-span-2">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Status <span class="text-rose-500">*</span></label>
                <select
                  v-model="form.status"
                  required
                  class="w-full px-4 py-2.5 text-sm bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400"
                >
                  <option value="draft">Draft</option>
                  <option value="unpaid">Unpaid (Awaiting Payment)</option>
                  <option value="paid">Paid</option>
                </select>
                <p v-if="form.errors.status" class="text-xs text-rose-500 font-semibold">{{ form.errors.status }}</p>
              </div>

              <div class="space-y-1 sm:col-span-2">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Notes & Reference</label>
                <textarea
                  v-model="form.notes"
                  rows="3"
                  placeholder="Additional notes, invoice reference numbers, or payment terms..."
                  class="w-full px-4 py-2.5 text-sm bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400"
                ></textarea>
                <p v-if="form.errors.notes" class="text-xs text-rose-500 font-semibold">{{ form.errors.notes }}</p>
              </div>
            </div>

            <!-- Attachment -->
            <div class="space-y-2 pt-2">
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Replace / Upload Invoice Document</label>
              <div v-if="bill.attachment_url" class="text-xs text-slate-600 dark:text-slate-300 mb-1">
                Current attachment: <a :href="bill.attachment_url" target="_blank" class="text-blue-600 dark:text-blue-400 underline font-semibold">View File</a>
              </div>
              <input
                type="file"
                accept=".pdf,.png,.jpg,.jpeg,.webp"
                @change="handleFileChange"
                class="block w-full text-xs text-slate-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 dark:file:bg-slate-800 file:text-slate-700 dark:file:text-slate-200 hover:file:bg-slate-200 dark:hover:file:bg-slate-700 cursor-pointer"
              />
              <p class="text-xs text-slate-400">PDF, PNG, JPG up to 10MB.</p>
            </div>
          </div>
        </div>

        <!-- Submit Footer -->
        <div class="p-6 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <Link
            :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'purchases' })"
            class="px-5 py-2.5 text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors"
          >
            Cancel
          </Link>

          <button
            type="submit"
            :disabled="form.processing"
            class="px-6 py-2.5 text-sm font-black text-white bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 rounded-xl shadow-md transition-colors disabled:opacity-50"
          >
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
