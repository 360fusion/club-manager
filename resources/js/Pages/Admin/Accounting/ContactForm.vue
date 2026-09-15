<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: {
    type: Object,
    required: true,
  },
  contact: {
    type: Object,
    default: null,
  },
});

const isEditing = computed(() => !!props.contact);

const form = useForm({
  type: props.contact?.type || 'business',
  name: props.contact?.name || '',
  contact_person: props.contact?.contact_person || '',
  email: props.contact?.email || '',
  phone: props.contact?.phone || '',
  role: props.contact?.role || (props.contact?.type === 'person' ? 'Contractor / Coach' : 'Vendor / Supplier'),
  tax_id: props.contact?.tax_id || '',
  address_line_1: props.contact?.address_line_1 || '',
  address_line_2: props.contact?.address_line_2 || '',
  city: props.contact?.city || '',
  postcode: props.contact?.postcode || '',
  country: props.contact?.country || 'United Kingdom',
  notes: props.contact?.notes || '',
});

const handleTypeToggle = (newType) => {
  form.type = newType;
  if (newType === 'person' && (!form.role || form.role === 'Vendor / Supplier')) {
    form.role = 'Contractor / Coach';
  } else if (newType === 'business' && (!form.role || form.role === 'Contractor / Coach')) {
    form.role = 'Vendor / Supplier';
  }
};

const submitForm = () => {
  if (isEditing.value) {
    form.put(route('admin.accounting.contacts.update', [props.club.slug, props.contact.id]));
  } else {
    form.post(route('admin.accounting.contacts.store', props.club.slug));
  }
};

const deleteContact = () => {
  if (!isEditing.value) return;
  if (confirm(`Are you sure you want to remove "${props.contact.name}" from the contact directory?`)) {
    router.delete(route('admin.accounting.contacts.destroy', [props.club.slug, props.contact.id]));
  }
};
</script>

<template>
  <AdminLayout :club="club" :title="isEditing ? 'Edit Contact Details' : 'Add New Contact'">
    <Head :title="`${isEditing ? 'Edit Contact Details' : 'Add New Contact'} - ${club.name}`" />

    <div class="max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
      <!-- Top Breadcrumb & Header -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1" aria-label="Breadcrumb">
            <Link
              :href="route('admin.accounting.index', club.slug)"
              class="hover:text-slate-900 transition-colors"
            >
              Accounting ERP
            </Link>
            <span>/</span>
            <Link
              :href="`${route('admin.accounting.index', club.slug)}#contacts`"
              class="hover:text-slate-900 transition-colors"
            >
              Contacts Directory
            </Link>
            <span>/</span>
            <span class="text-slate-900 font-bold" aria-current="page">
              {{ isEditing ? 'Edit Contact' : 'New Contact' }}
            </span>
          </nav>
          <h1 class="text-2xl font-black text-slate-900 tracking-tight">
            {{ isEditing ? 'Edit Contact Details' : 'Add New Directory Contact' }}
          </h1>
          <p class="text-sm text-slate-600 mt-1">
            {{ isEditing ? 'Update address, tax information, role, and notes for this contact.' : 'Create a new vendor, contractor, client, or sponsor in your accounting directory.' }}
          </p>
        </div>

        <div class="flex items-center gap-3">
          <Link
            :href="`${route('admin.accounting.index', club.slug)}#contacts`"
            class="inline-flex items-center gap-1 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-colors"
          >
            ← Back to Directory
          </Link>
          <button
            v-if="isEditing"
            type="button"
            @click="deleteContact"
            class="inline-flex items-center gap-1 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 font-bold text-xs rounded-xl transition-colors cursor-pointer"
          >
            🗑️ Delete
          </button>
        </div>
      </div>

      <!-- Main Contact Form Card -->
      <form @submit.prevent="submitForm" class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 sm:p-8 space-y-6">

          <!-- Section 1: Contact Type & Identity -->
          <div class="space-y-4">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
              1. Contact Type & Classification
            </h2>

            <!-- Contact Type Toggle -->
            <div>
              <label id="contact-type-label" class="block text-xs font-bold text-slate-800 mb-2">
                Contact Entity Type <span class="text-red-500">*</span>
              </label>
              <div class="grid grid-cols-2 gap-3 p-1.5 bg-slate-100 rounded-2xl border border-slate-200 max-w-md" role="group" aria-labelledby="contact-type-label">
                <button
                  type="button"
                  @click="handleTypeToggle('person')"
                  :class="[
                    'py-2.5 px-4 rounded-xl text-xs font-bold flex items-center justify-center gap-2 transition-all cursor-pointer',
                    form.type === 'person' ? 'bg-white text-indigo-950 shadow-sm border border-slate-200' : 'text-slate-600 hover:text-slate-900'
                  ]"
                >
                  <span class="text-base">👤</span> Individual Person
                </button>

                <button
                  type="button"
                  @click="handleTypeToggle('business')"
                  :class="[
                    'py-2.5 px-4 rounded-xl text-xs font-bold flex items-center justify-center gap-2 transition-all cursor-pointer',
                    form.type === 'business' ? 'bg-white text-sky-950 shadow-sm border border-slate-200' : 'text-slate-600 hover:text-slate-900'
                  ]"
                >
                  <span class="text-base">🏢</span> Business / Organization
                </button>
              </div>
            </div>

            <!-- Name and Secondary Contact Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-2">
              <div>
                <label for="contact-name" class="block text-xs font-bold text-slate-800 mb-1">
                  {{ form.type === 'business' ? 'Business / Company Name' : 'Full Name' }} <span class="text-red-500">*</span>
                </label>
                <input
                  id="contact-name"
                  v-model="form.name"
                  type="text"
                  :placeholder="form.type === 'business' ? 'e.g. Oxford Rowing Supplies Ltd' : 'e.g. Robert Sterling'"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                  required
                />
                <p v-if="form.errors.name" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.name }}</p>
              </div>

              <div>
                <label for="contact-person" class="block text-xs font-bold text-slate-800 mb-1">
                  {{ form.type === 'business' ? 'Primary Contact Person Name' : 'Secondary / Preferred Name' }}
                </label>
                <input
                  id="contact-person"
                  v-model="form.contact_person"
                  type="text"
                  :placeholder="form.type === 'business' ? 'e.g. David Miller (Senior Manager)' : 'e.g. Bob Sterling'"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                />
                <p v-if="form.errors.contact_person" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.contact_person }}</p>
              </div>
            </div>

            <!-- Role / Category Select -->
            <div>
              <label for="contact-role" class="block text-xs font-bold text-slate-800 mb-1">
                Relationship / Role Category <span class="text-red-500">*</span>
              </label>
              <select
                id="contact-role"
                v-model="form.role"
                class="w-full sm:w-1/2 px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                required
              >
                <template v-if="form.type === 'business'">
                  <option value="Vendor / Supplier">Vendor / Supplier</option>
                  <option value="Sponsor & Insurer">Sponsor & Insurer</option>
                  <option value="Contractor / Service Provider">Contractor / Service Provider</option>
                  <option value="Client / Corporate Customer">Client / Corporate Customer</option>
                  <option value="Partner">Partner Organization</option>
                </template>
                <template v-else>
                  <option value="Contractor / Coach">Contractor / Coach</option>
                  <option value="Volunteer / Staff">Volunteer / Staff</option>
                  <option value="Vendor Representative">Vendor Representative</option>
                  <option value="Client / Customer">Client / Customer</option>
                  <option value="Member">Club Member</option>
                </template>
              </select>
              <p v-if="form.errors.role" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.role }}</p>
            </div>
          </div>

          <!-- Section 2: Contact Methods & Tax Info -->
          <div class="space-y-4 pt-4 border-t border-slate-100">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
              2. Communication & Tax Registration
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
              <div>
                <label for="contact-email" class="block text-xs font-bold text-slate-800 mb-1">Email Address</label>
                <input
                  id="contact-email"
                  v-model="form.email"
                  type="email"
                  placeholder="sales@oxfordrowingsupplies.co.uk"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                />
                <p v-if="form.errors.email" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.email }}</p>
              </div>

              <div>
                <label for="contact-phone" class="block text-xs font-bold text-slate-800 mb-1">Phone Number</label>
                <input
                  id="contact-phone"
                  v-model="form.phone"
                  type="text"
                  placeholder="+44 1865 240100"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                />
                <p v-if="form.errors.phone" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.phone }}</p>
              </div>

              <div>
                <label for="contact-tax" class="block text-xs font-bold text-slate-800 mb-1">
                  {{ form.type === 'business' ? 'VAT / Tax Registration Number' : 'Tax / UTR Number' }}
                </label>
                <input
                  id="contact-tax"
                  v-model="form.tax_id"
                  type="text"
                  :placeholder="form.type === 'business' ? 'GB 883 9920 11' : 'UTR 982341'"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                />
                <p v-if="form.errors.tax_id" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.tax_id }}</p>
              </div>
            </div>
          </div>

          <!-- Section 3: Billing Address -->
          <div class="space-y-4 pt-4 border-t border-slate-100">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
              3. Billing Address
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label for="address-line-1" class="block text-xs font-bold text-slate-800 mb-1">Address Line 1</label>
                <input
                  id="address-line-1"
                  v-model="form.address_line_1"
                  type="text"
                  placeholder="Unit 4 Meadowside Works"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                />
              </div>

              <div>
                <label for="address-line-2" class="block text-xs font-bold text-slate-800 mb-1">Address Line 2</label>
                <input
                  id="address-line-2"
                  v-model="form.address_line_2"
                  type="text"
                  placeholder="Abingdon Road"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label for="city" class="block text-xs font-bold text-slate-800 mb-1">City / Town</label>
                <input
                  id="city"
                  v-model="form.city"
                  type="text"
                  placeholder="Oxford"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                />
              </div>

              <div>
                <label for="postcode" class="block text-xs font-bold text-slate-800 mb-1">Postcode / Zip</label>
                <input
                  id="postcode"
                  v-model="form.postcode"
                  type="text"
                  placeholder="OX2 0ES"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                />
              </div>

              <div>
                <label for="country" class="block text-xs font-bold text-slate-800 mb-1">Country</label>
                <input
                  id="country"
                  v-model="form.country"
                  type="text"
                  placeholder="United Kingdom"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                />
              </div>
            </div>
          </div>

          <!-- Section 4: Internal Notes -->
          <div class="space-y-4 pt-4 border-t border-slate-100">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
              4. Internal Bookkeeping Notes & Payment Terms
            </h2>

            <div>
              <label for="contact-notes" class="block text-xs font-bold text-slate-800 mb-1">Notes & Terms</label>
              <textarea
                id="contact-notes"
                v-model="form.notes"
                rows="3"
                placeholder="Primary supplier for rowing equipment and maintenance parts. Payment terms Net 30."
                class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Footer Actions Bar -->
        <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex items-center justify-between">
          <Link
            :href="`${route('admin.accounting.index', club.slug)}#contacts`"
            class="px-4 py-2.5 bg-white border border-slate-300 hover:bg-slate-100 text-slate-700 font-bold text-xs rounded-xl shadow-sm transition-colors"
          >
            Cancel & Return
          </Link>

          <button
            type="submit"
            :disabled="form.processing"
            class="px-6 py-2.5 bg-[#007bce] hover:bg-sky-700 text-white font-extrabold text-xs rounded-xl shadow-sm transition-colors cursor-pointer flex items-center gap-2"
          >
            <span v-if="form.processing" class="animate-spin">⌛</span>
            <span>{{ isEditing ? 'Update Contact Details' : 'Save Contact to Directory' }}</span>
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>
