<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: { type: Object, required: true },
  initialVendor: { type: String, default: '' },
});

const form = useForm({
  vendor_name: props.initialVendor || '',
  category: 'Facility & Clubhouse Maintenance',
  amount: '',
  due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  notes: '',
  attachment: null,
  is_draft: false,
});

const handleFileChange = (e) => {
  form.attachment = e.target.files[0] || null;
};

const submit = (isDraft = false) => {
  form.is_draft = isDraft;
  form.post(route('admin.accounting.bills.store', props.club.slug));
};
</script>

<template>
  <AdminLayout :club="club" title="Add Vendor Bill" active-tab="accounting">
    <Head :title="`Add Vendor Bill — ${club.name}`" />

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
            <span class="text-slate-900 dark:text-white font-bold" aria-current="page">Add Vendor Bill</span>
          </nav>
          <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">Add Vendor Bill (A/P)</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Record a supplier or vendor bill to manage Accounts Payable.</p>
        </div>
        <Link
          :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'purchases' })"
          class="inline-flex items-center gap-1 px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition-colors shrink-0"
        >
          ← Back to Purchases
        </Link>
      </div>

      <!-- Explainer Banner -->
      <div class="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-2xl p-4 flex gap-3">
        <span class="text-xl shrink-0">📄</span>
        <div class="text-xs text-amber-900 dark:text-amber-200 space-y-1">
          <p class="font-extrabold">Accounts Payable & Bill Tracking</p>
          <p>Recording a bill creates an outstanding liability for your club. Once approved/posted, it can be reconciled directly against your bank transactions or marked as paid.</p>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit(false)" class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="p-6 sm:p-8 space-y-6">

          <!-- Section 1: Vendor & Category -->
          <div class="space-y-4">
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-wider">Vendor & Account Category</h3>

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
                  <option value="Facility & Clubhouse Maintenance">Facility & Clubhouse Maintenance</option>
                  <option value="Boat Yard Supplies & Repair">Boat Yard Supplies & Repair</option>
                  <option value="Bar & Dining Supplies">Bar & Dining Supplies</option>
                  <option value="Admin & Software Subscriptions">Admin & Software Subscriptions</option>
                  <option value="Insurance & Legal Fees">Insurance & Legal Fees</option>
                  <option value="Utilities & Fuel">Utilities & Fuel</option>
                  <option value="Event & Regatta Supplies">Event & Regatta Supplies</option>
                  <option value="Other Operating Expenses">Other Operating Expenses</option>
                </select>
                <p v-if="form.errors.category" class="text-xs text-rose-500 font-semibold">{{ form.errors.category }}</p>
              </div>
            </div>
          </div>

          <hr class="border-slate-100 dark:border-slate-800" />

          <!-- Section 2: Bill Amount & Due Date -->
          <div class="space-y-4">
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-wider">Amount & Payment Terms</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="space-y-1">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Total Amount (£) <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-sm">£</span>
                  <input
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    required
                    placeholder="0.00"
                    class="w-full pl-8 pr-4 py-2.5 text-sm bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400 font-semibold"
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
            </div>
          </div>

          <hr class="border-slate-100 dark:border-slate-800" />

          <!-- Section 3: Notes & Attachment -->
          <div class="space-y-4">
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-wider">Notes & Receipt Upload</h3>

            <div class="space-y-1">
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Internal Notes / Description</label>
              <textarea
                v-model="form.notes"
                rows="3"
                placeholder="Add invoice reference number, PO number, or item breakdown..."
                class="w-full px-4 py-2.5 text-sm bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900 dark:focus:ring-slate-400"
              ></textarea>
              <p v-if="form.errors.notes" class="text-xs text-rose-500 font-semibold">{{ form.errors.notes }}</p>
            </div>

            <div class="space-y-1">
              <label class="block text-sm font-bold text-slate-700 dark:text-slate-200">Attach Supplier Invoice / Receipt (PDF or Image)</label>
              <input
                type="file"
                accept=".pdf,.png,.jpg,.jpeg,.webp"
                @change="handleFileChange"
                class="w-full text-sm text-slate-500 dark:text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 dark:file:bg-slate-800 file:text-slate-700 dark:file:text-slate-200 hover:file:bg-slate-200 dark:hover:file:bg-slate-700"
              />
              <p v-if="form.errors.attachment" class="text-xs text-rose-500 font-semibold">{{ form.errors.attachment }}</p>
            </div>
          </div>

        </div>

        <!-- Footer Actions -->
        <div class="p-6 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
          <Link
            :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'purchases' })"
            class="w-full sm:w-auto px-5 py-2.5 text-center text-sm font-bold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors"
          >
            Cancel
          </Link>
          <div class="flex items-center gap-3 w-full sm:w-auto">
            <button
              type="button"
              :disabled="form.processing"
              @click="submit(true)"
              class="flex-1 sm:flex-none px-5 py-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-sm rounded-xl transition-all disabled:opacity-50"
            >
              Save as Draft
            </button>
            <button
              type="submit"
              :disabled="form.processing"
              class="flex-1 sm:flex-none px-6 py-2.5 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold text-sm rounded-xl transition-all shadow-md shadow-slate-900/10 disabled:opacity-50"
            >
              <span v-if="form.processing">Recording...</span>
              <span v-else>Record & Post Bill</span>
            </button>
          </div>
        </div>
      </form>

    </div>
  </AdminLayout>
</template>
