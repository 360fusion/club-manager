<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed, ref } from 'vue';

const props = defineProps({
  club: { type: Object, required: true },
  invoice: { type: Object, required: true },
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
  user_id: props.invoice.user_id,
  title: props.invoice.title,
  amount: props.invoice.amount,
  status: props.invoice.status,
  attachment: null,
});

const handleFileChange = (e) => {
  form.attachment = e.target.files[0] || null;
};

const selectedMember = computed(() =>
  props.members.find(m => m.id === Number(form.user_id)) ?? null
);

const submit = () => {
  form.post(route('admin.accounting.invoices.update', { clubSlug: props.club.slug, id: props.invoice.id }), {
    _method: 'put',
  });
};
</script>

<template>
  <AdminLayout :club="club" :title="`Edit Invoice ${invoice.invoice_number}`" active-tab="accounting">
    <Head :title="`Edit ${invoice.invoice_number} — ${club.name}`" />

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
            <span class="text-slate-900 font-bold" aria-current="page">Edit {{ invoice.invoice_number }}</span>
          </nav>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Edit Invoice {{ invoice.invoice_number }}</h1>
            <span
              class="px-2.5 py-0.5 text-xs font-black uppercase tracking-wider rounded-full"
              :class="{
                'bg-emerald-100 text-emerald-800': invoice.status === 'paid',
                'bg-amber-100 text-amber-800': invoice.status === 'unpaid',
                'bg-slate-100 text-slate-700': invoice.status === 'draft',
              }"
            >
              {{ invoice.status }}
            </span>
          </div>
          <p class="text-sm text-slate-500 mt-1">Update invoice details, assigned member, or status.</p>
        </div>
        <Link
          :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'sales' })"
          class="inline-flex items-center gap-1 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-colors"
        >
          ← Back to Sales
        </Link>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 sm:p-8 space-y-6">

          <!-- Recipient -->
          <div class="space-y-4">
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-wider">Member / Customer</h3>
            
            <div class="space-y-2">
              <label class="block text-sm font-bold text-slate-700">Select Member <span class="text-rose-500">*</span></label>
              <input
                v-model="memberSearch"
                type="text"
                placeholder="Search member by name or email..."
                class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900"
              />
              
              <select
                v-model="form.user_id"
                required
                size="4"
                class="w-full px-4 py-2.5 text-sm bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900"
              >
                <option v-for="m in filteredMembers" :key="m.id" :value="m.id">
                  {{ m.name }} ({{ m.email }})
                </option>
              </select>
              <p v-if="form.errors.user_id" class="text-xs text-rose-500 font-semibold">{{ form.errors.user_id }}</p>
            </div>

            <div v-if="selectedMember" class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs flex items-center justify-between">
              <div>
                <span class="font-bold text-slate-900">{{ selectedMember.name }}</span>
                <span class="text-slate-500 ml-2">{{ selectedMember.email }}</span>
              </div>
              <span class="text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">Selected</span>
            </div>
          </div>

          <hr class="border-slate-100" />

          <!-- Invoice Details -->
          <div class="space-y-4">
            <h3 class="text-xs font-black text-slate-400 uppercase tracking-wider">Invoice Details</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="space-y-1 sm:col-span-2">
                <label class="block text-sm font-bold text-slate-700">Invoice Title / Description <span class="text-rose-500">*</span></label>
                <input
                  v-model="form.title"
                  type="text"
                  required
                  placeholder="e.g. Annual Membership Dues 2026 / 2027"
                  class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900"
                />
                <p v-if="form.errors.title" class="text-xs text-rose-500 font-semibold">{{ form.errors.title }}</p>
              </div>

              <div class="space-y-1">
                <label class="block text-sm font-bold text-slate-700">Amount (£) <span class="text-rose-500">*</span></label>
                <div class="relative">
                  <span class="absolute left-3.5 top-2.5 text-slate-400 font-bold">£</span>
                  <input
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    placeholder="0.00"
                    class="w-full pl-8 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900 font-mono"
                  />
                </div>
                <p v-if="form.errors.amount" class="text-xs text-rose-500 font-semibold">{{ form.errors.amount }}</p>
              </div>

              <div class="space-y-1">
                <label class="block text-sm font-bold text-slate-700">Status <span class="text-rose-500">*</span></label>
                <select
                  v-model="form.status"
                  required
                  class="w-full px-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-900"
                >
                  <option value="draft">Draft</option>
                  <option value="unpaid">Unpaid (Published)</option>
                  <option value="paid">Paid</option>
                </select>
                <p v-if="form.errors.status" class="text-xs text-rose-500 font-semibold">{{ form.errors.status }}</p>
              </div>
            </div>

            <!-- Attachment -->
            <div class="space-y-2 pt-2">
              <label class="block text-sm font-bold text-slate-700">Replace / Add Supporting Document</label>
              <div v-if="invoice.attachment_url" class="text-xs text-slate-600 mb-1">
                Current attachment: <a :href="invoice.attachment_url" target="_blank" class="text-sky-600 underline font-semibold">View File</a>
              </div>
              <input
                type="file"
                accept=".pdf,.png,.jpg,.jpeg,.webp"
                @change="handleFileChange"
                class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer"
              />
              <p class="text-xs text-slate-400">PDF, PNG, JPG up to 10MB.</p>
            </div>
          </div>
        </div>

        <!-- Submit Footer -->
        <div class="p-6 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
          <Link
            :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'sales' })"
            class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:text-slate-900 transition-colors"
          >
            Cancel
          </Link>

          <button
            type="submit"
            :disabled="form.processing"
            class="px-6 py-2.5 text-sm font-black text-white bg-slate-900 hover:bg-slate-800 rounded-xl shadow-md transition-colors disabled:opacity-50"
          >
            Save Changes
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
