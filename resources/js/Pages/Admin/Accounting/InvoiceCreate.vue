<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed, ref } from 'vue';

const props = defineProps({
  club: { type: Object, required: true },
  members: { type: Array, default: () => [] },
});

const memberSearch = ref('');

const filteredMembers = computed(() => {
  const q = memberSearch.value.trim().toLowerCase();
  if (!q) return props.members;
  return props.members.filter(m =>
    m.name.toLowerCase().includes(q) || m.email?.toLowerCase().includes(q)
  );
});

const form = useForm({
  user_id: '',
  title: '',
  amount: '',
  notes: '',
  due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  attachment: null,
});

const handleFileChange = (e) => {
  form.attachment = e.target.files[0] || null;
};

const selectedMember = computed(() =>
  props.members.find(m => m.id === Number(form.user_id)) ?? null
);

const submit = (isDraft = false) => {
  form.transform((data) => ({
    ...data,
    title: data.title || (isDraft ? 'Draft Invoice' : ''),
    amount: data.amount !== '' && data.amount !== null && data.amount !== undefined ? data.amount : (isDraft ? 0 : data.amount),
    is_draft: isDraft,
  })).post(route('admin.accounting.invoices.store', props.club.slug));
};

// Invoice number preview
const previewNumber = computed(() => {
  const y = new Date().getFullYear();
  return `INV-${y}-XXXX`;
});
</script>

<template>
  <AdminLayout :club="club" title="Create Invoice" active-tab="accounting">
    <Head :title="`Create Invoice — ${club.name}`" />

    <div class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">

      <!-- Breadcrumb & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1" aria-label="Breadcrumb">
            <Link :href="route('admin.accounting.index', club.slug)" class="hover:text-slate-900 transition-colors">
              Accounting ERP
            </Link>
            <span>/</span>
            <Link :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'sales' })" class="hover:text-slate-900 transition-colors">
              Sales
            </Link>
            <span>/</span>
            <span class="text-slate-900 font-bold" aria-current="page">Create Invoice</span>
          </nav>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">Create Invoice</h1>
          <p class="text-sm text-slate-500 mt-1">Issue an invoice to a member for dues, fees, or other charges.</p>
        </div>
        <Link
          :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'sales' })"
          class="inline-flex items-center gap-1 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-colors"
        >
          ← Back to Sales
        </Link>
      </div>

      <!-- What's the difference explainer -->
      <div class="bg-sky-50 border border-sky-200 rounded-2xl p-4 flex gap-3">
        <span class="text-xl shrink-0">💡</span>
        <div class="text-xs text-sky-900 space-y-1">
          <p class="font-extrabold">What's the difference between an Invoice and a Journal Entry?</p>
          <p><strong>Invoice</strong> — A document issued to a member requesting payment (e.g. annual subs £120). It automatically creates a receivable in the ledger and tracks whether it's been paid.</p>
          <p><strong>Journal Entry</strong> — A manual internal ledger adjustment used by bookkeepers for corrections, accruals, or complex double-entry transactions. Never sent to a member.</p>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 sm:p-8 space-y-6">

          <!-- Section 1: Recipient -->
          <div class="space-y-4">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
              1. Bill To (Recipient)
            </h2>

            <!-- Member search -->
            <div>
              <label class="block text-xs font-bold text-slate-800 mb-1">
                Member <span class="text-red-500">*</span>
              </label>

              <input
                v-model="memberSearch"
                type="text"
                placeholder="Search by name or email…"
                class="w-full px-3.5 py-2.5 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400 mb-2"
              />

              <div class="border border-slate-200 rounded-xl overflow-hidden max-h-48 overflow-y-auto">
                <label
                  v-for="m in filteredMembers"
                  :key="m.id"
                  :class="[
                    'flex items-center gap-3 px-4 py-2.5 cursor-pointer transition-colors',
                    form.user_id == m.id ? 'bg-indigo-50 border-l-2 border-indigo-500' : 'hover:bg-slate-50 border-l-2 border-transparent'
                  ]"
                >
                  <input
                    type="radio"
                    :value="m.id"
                    v-model="form.user_id"
                    class="accent-indigo-600"
                  />
                  <div>
                    <span class="text-xs font-bold text-slate-900">{{ m.name }}</span>
                    <span class="text-[11px] text-slate-400 ml-2 font-mono">{{ m.email }}</span>
                  </div>
                </label>
                <div v-if="!filteredMembers.length" class="px-4 py-3 text-xs text-slate-400 font-semibold">
                  No members match "{{ memberSearch }}"
                </div>
              </div>

              <p v-if="form.errors.user_id" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.user_id }}</p>
            </div>

            <!-- Selected member preview -->
            <div v-if="selectedMember" class="flex items-center gap-3 px-4 py-3 bg-indigo-50 border border-indigo-200 rounded-xl">
              <span class="text-xl">👤</span>
              <div>
                <p class="text-xs font-extrabold text-indigo-900">{{ selectedMember.name }}</p>
                <p class="text-[11px] text-indigo-600 font-mono">{{ selectedMember.email }}</p>
              </div>
            </div>
          </div>

          <!-- Section 2: Invoice Details -->
          <div class="space-y-4 pt-4 border-t border-slate-100">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
              2. Invoice Details
            </h2>

            <!-- Invoice number preview -->
            <div class="flex items-center gap-2 px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl sm:w-1/2">
              <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Invoice #</span>
              <span class="text-xs font-black font-mono text-slate-600">{{ previewNumber }}</span>
              <span class="text-[10px] text-slate-400 italic">(auto-assigned)</span>
            </div>

            <!-- Title / Description -->
            <div>
              <label for="invoice-title" class="block text-xs font-bold text-slate-800 mb-1">
                Title / Description <span class="text-red-500">*</span>
              </label>
              <input
                id="invoice-title"
                v-model="form.title"
                type="text"
                placeholder="e.g. Annual Membership Subscription 2026–27"
                class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                required
              />
              <p v-if="form.errors.title" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.title }}</p>
            </div>

            <!-- Amount + Due Date -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="invoice-amount" class="block text-xs font-bold text-slate-800 mb-1">
                  Amount (£) <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 font-bold text-xs">£</span>
                  <input
                    id="invoice-amount"
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    placeholder="0.00"
                    class="w-full pl-7 pr-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-mono font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                    required
                  />
                </div>
                <p v-if="form.errors.amount" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.amount }}</p>
              </div>

              <div>
                <label for="invoice-due" class="block text-xs font-bold text-slate-800 mb-1">Due Date</label>
                <input
                  id="invoice-due"
                  v-model="form.due_date"
                  type="date"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                />
              </div>
            </div>
          </div>

          <!-- Section 3: Notes -->
          <div class="space-y-4 pt-4 border-t border-slate-100">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
              3. Notes (Optional)
            </h2>
            <textarea
              id="invoice-notes"
              v-model="form.notes"
              rows="3"
              placeholder="e.g. Payable by BACS. Bank details: Sort code 12-34-56, Account 12345678."
              class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
            ></textarea>
          </div>

          <!-- Section 4: File Attachment -->
          <div class="space-y-4 pt-4 border-t border-slate-100">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
              4. Document Attachment (Optional)
            </h2>
            <div>
              <label for="invoice-attachment" class="block text-xs font-bold text-slate-800 mb-1">
                Upload File (PDF, PNG, JPG, WEBP — Max 10MB)
              </label>
              <input
                id="invoice-attachment"
                type="file"
                accept=".pdf,.png,.jpg,.jpeg,.webp"
                @change="handleFileChange"
                class="w-full text-xs text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer"
              />
              <p v-if="form.errors.attachment" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.attachment }}</p>
              <p v-if="form.attachment" class="text-[11px] text-emerald-600 font-bold mt-1">
                Selected file: {{ form.attachment.name }} ({{ (form.attachment.size / 1024 / 1024).toFixed(2) }} MB)
              </p>
            </div>
          </div>

          <!-- Ledger preview callout -->
          <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-xs text-slate-600 space-y-1">
            <p class="font-extrabold text-slate-800 mb-1">📒 What happens in the ledger when you save:</p>
            <div class="flex items-center justify-between font-mono">
              <span class="text-slate-500">DR  Accounts Receivable (1200)</span>
              <span class="font-black text-slate-900">£{{ form.amount || '0.00' }}</span>
            </div>
            <div class="flex items-center justify-between font-mono">
              <span class="text-slate-500">CR  Membership Income (4000)</span>
              <span class="font-black text-slate-900">£{{ form.amount || '0.00' }}</span>
            </div>
          </div>

        </div>

        <!-- Footer -->
        <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex items-center justify-between">
          <Link
            :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'sales' })"
            class="px-4 py-2.5 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 font-bold text-xs rounded-xl shadow-sm transition-colors"
          >
            Cancel
          </Link>

          <div class="flex items-center gap-3">
            <button
              type="button"
              @click="submit(true)"
              :disabled="form.processing"
              class="px-5 py-2.5 bg-slate-200 hover:bg-slate-300 disabled:opacity-40 disabled:cursor-not-allowed text-slate-800 font-extrabold text-xs rounded-xl transition-colors cursor-pointer flex items-center gap-1.5"
            >
              <span>📝 Save as Draft</span>
            </button>

            <button
              type="button"
              @click="submit(false)"
              :disabled="form.processing"
              class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-extrabold text-xs rounded-xl shadow-sm transition-colors cursor-pointer flex items-center gap-2"
            >
              <span v-if="form.processing" class="animate-spin">⌛</span>
              <span>🧾 Issue Invoice</span>
            </button>
          </div>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
