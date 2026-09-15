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
  memberSummary: {
    type: Object,
    default: null,
  },
});

const isEditing = computed(() => !!props.contact);
const isMember  = computed(() => props.contact?.is_member === true);

const form = useForm({
  first_name:     props.contact?.first_name     || '',
  middle_names:   props.contact?.middle_names   || '',
  last_name:      props.contact?.last_name      || '',
  preferred_name: props.contact?.preferred_name || '',
  email:          props.contact?.email          || '',
  phone:          props.contact?.phone          || '',
  role:           props.contact?.role           || 'Contractor / Coach',
  tax_id:         props.contact?.tax_id         || '',
  address_line_1: props.contact?.address_line_1 || '',
  address_line_2: props.contact?.address_line_2 || '',
  city:           props.contact?.city           || '',
  postcode:       props.contact?.postcode       || '',
  country:        props.contact?.country        || 'United Kingdom',
  notes:          props.contact?.notes          || '',
});

// Members are always "Club Member" – lock the role on submit
if (isMember.value) {
  form.role = 'Club Member';
}

const submitForm = () => {
  if (isMember.value) form.role = 'Club Member';
  if (isEditing.value) {
    form.put(route('admin.accounting.contacts.update', [props.club.slug, props.contact.id]));
  } else {
    form.post(route('admin.accounting.contacts.store', props.club.slug));
  }
};

const deleteContact = () => {
  if (!isEditing.value) return;
  const displayName = [props.contact?.first_name, props.contact?.last_name].filter(Boolean).join(' ') || props.contact?.name || 'this contact';
  if (confirm(`Are you sure you want to remove "${displayName}" from the contact directory?`)) {
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
            <span v-if="isMember">Update billing address, tax info, and notes for this club member.</span>
            <span v-else-if="isEditing">Update address, tax information, role, and notes for this contact.</span>
            <span v-else>Create a new contractor, client, or sponsor in your accounting directory.</span>
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
            v-if="isEditing && !isMember"
            type="button"
            @click="deleteContact"
            class="inline-flex items-center gap-1 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 font-bold text-xs rounded-xl transition-colors cursor-pointer"
          >
            🗑️ Delete
          </button>
        </div>
      </div>


      <!-- ── Member Financial Overview ──────────────────────────────── -->
      <div v-if="isMember && memberSummary" class="space-y-4">

        <!-- Stat Cards Row -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <!-- Amount Owed -->
          <div :class="[
            'rounded-2xl border p-4 flex flex-col gap-1',
            memberSummary.amount_owed > 0
              ? 'bg-red-50 border-red-200'
              : 'bg-slate-50 border-slate-200'
          ]">
            <span class="text-[10px] font-extrabold uppercase tracking-wider" :class="memberSummary.amount_owed > 0 ? 'text-red-500' : 'text-slate-400'">Amount Owed</span>
            <span class="text-xl font-black" :class="memberSummary.amount_owed > 0 ? 'text-red-700' : 'text-slate-400'">{{ memberSummary.amount_owed_formatted }}</span>
            <span class="text-[10px] text-slate-500 font-medium">{{ memberSummary.amount_owed > 0 ? 'Outstanding subs / invoices' : 'All paid up ✓' }}</span>
          </div>

          <!-- Credit Balance -->
          <div :class="[
            'rounded-2xl border p-4 flex flex-col gap-1',
            memberSummary.credit_balance > 0
              ? 'bg-sky-50 border-sky-200'
              : 'bg-slate-50 border-slate-200'
          ]">
            <span class="text-[10px] font-extrabold uppercase tracking-wider" :class="memberSummary.credit_balance > 0 ? 'text-sky-500' : 'text-slate-400'">Credit / Advance</span>
            <span class="text-xl font-black" :class="memberSummary.credit_balance > 0 ? 'text-sky-700' : 'text-slate-400'">{{ memberSummary.credit_balance_formatted }}</span>
            <span class="text-[10px] text-slate-500 font-medium">Paid in advance / refunds</span>
          </div>

          <!-- Total Paid -->
          <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex flex-col gap-1">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-500">Total Paid</span>
            <span class="text-xl font-black text-emerald-700">{{ memberSummary.total_paid_formatted }}</span>
            <span class="text-[10px] text-slate-500 font-medium">Lifetime contributions</span>
          </div>

          <!-- Last Payment -->
          <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-4 flex flex-col gap-1">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-500">Last Payment</span>
            <span class="text-base font-black text-indigo-700">{{ memberSummary.last_payment_amount ?? '—' }}</span>
            <span class="text-[10px] text-slate-500 font-medium">{{ memberSummary.last_payment_date ?? 'No payments yet' }}</span>
          </div>
        </div>

        <!-- Membership Plan + Member Since Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <!-- Active Plan -->
          <div class="bg-white border border-slate-200 rounded-2xl p-4 flex items-start gap-3">
            <span class="text-2xl">🎫</span>
            <div>
              <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-0.5">Membership Plan</p>
              <p v-if="memberSummary.membership_plan" class="text-sm font-extrabold text-slate-900">
                {{ memberSummary.membership_plan }}
              </p>
              <p v-else class="text-sm font-bold text-slate-400">No active plan</p>
              <p v-if="memberSummary.membership_plan" class="text-[11px] text-slate-500 mt-0.5">
                {{ memberSummary.membership_price }} / {{ memberSummary.membership_period }}
                <span v-if="memberSummary.membership_renews"> · Renews {{ memberSummary.membership_renews }}</span>
              </p>
            </div>
          </div>

          <!-- Member Since -->
          <div class="bg-white border border-slate-200 rounded-2xl p-4 flex items-start gap-3">
            <span class="text-2xl">📅</span>
            <div>
              <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-0.5">Member Since</p>
              <p class="text-sm font-extrabold text-slate-900">{{ memberSummary.member_since ?? 'Unknown' }}</p>
              <p class="text-[11px] text-slate-500 mt-0.5">{{ memberSummary.invoice_count }} invoice(s) on record</p>
            </div>
          </div>
        </div>

        <!-- Unpaid Invoices breakdown -->
        <div v-if="memberSummary.unpaid_invoices?.length" class="bg-red-50 border border-red-200 rounded-2xl overflow-hidden">
          <div class="px-4 py-3 border-b border-red-200 flex items-center gap-2">
            <span class="text-xs font-extrabold text-red-700 uppercase tracking-wider">⚠️ Outstanding Invoices</span>
          </div>
          <table class="w-full text-left">
            <thead>
              <tr class="text-[10px] font-extrabold text-red-500 uppercase tracking-wider border-b border-red-100">
                <th class="px-4 py-2">Invoice</th>
                <th class="px-4 py-2">Description</th>
                <th class="px-4 py-2">Issued</th>
                <th class="px-4 py-2 text-right">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-red-100">
              <tr v-for="inv in memberSummary.unpaid_invoices" :key="inv.id" class="text-xs font-semibold text-slate-700">
                <td class="px-4 py-2.5 font-mono text-[11px] text-slate-500">{{ inv.invoice_number }}</td>
                <td class="px-4 py-2.5">{{ inv.title }}</td>
                <td class="px-4 py-2.5 text-slate-500">{{ inv.created_at }}</td>
                <td class="px-4 py-2.5 text-right font-extrabold text-red-700">{{ inv.amount }}</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
      <!-- ─────────────────────────────────────────────────────────────── -->

      <!-- Main Contact Form Card -->
      <form @submit.prevent="submitForm" class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 sm:p-8 space-y-6">

          <!-- Section 1: Name & Classification -->
          <div class="space-y-4">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
              1. Name & Classification
            </h2>

            <!-- Row 1: First + Middle + Last -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-1">
              <div>
                <label for="contact-first-name" class="block text-xs font-bold text-slate-800 mb-1">
                  First Name <span class="text-red-500">*</span>
                </label>
                <input
                  id="contact-first-name"
                  v-model="form.first_name"
                  type="text"
                  placeholder="e.g. Robert"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                  required
                  autocomplete="given-name"
                />
                <p v-if="form.errors.first_name" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.first_name }}</p>
              </div>

              <div>
                <label for="contact-middle-names" class="block text-xs font-bold text-slate-800 mb-1">
                  Middle Name(s)
                </label>
                <input
                  id="contact-middle-names"
                  v-model="form.middle_names"
                  type="text"
                  placeholder="e.g. James"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                  autocomplete="additional-name"
                />
                <p v-if="form.errors.middle_names" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.middle_names }}</p>
              </div>

              <div>
                <label for="contact-last-name" class="block text-xs font-bold text-slate-800 mb-1">
                  Surname
                </label>
                <input
                  id="contact-last-name"
                  v-model="form.last_name"
                  type="text"
                  placeholder="e.g. Sterling"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                  autocomplete="family-name"
                />
                <p v-if="form.errors.last_name" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.last_name }}</p>
              </div>
            </div>

            <!-- Row 2: Preferred name -->
            <div class="sm:w-1/2">
              <label for="contact-preferred-name" class="block text-xs font-bold text-slate-800 mb-1">
                Preferred / Display Name
                <span class="ml-1 text-slate-400 font-medium">(nickname or how they like to be addressed)</span>
              </label>
              <input
                id="contact-preferred-name"
                v-model="form.preferred_name"
                type="text"
                placeholder="e.g. Bob"
                class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                autocomplete="nickname"
              />
              <p v-if="form.errors.preferred_name" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.preferred_name }}</p>
            </div>

            <!-- Role / Category Select -->
            <div>
              <label for="contact-role" class="block text-xs font-bold text-slate-800 mb-1">
                Relationship / Role Category <span class="text-red-500">*</span>
              </label>
              <!-- Locked badge for members -->
              <div v-if="isMember" class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-50 border border-emerald-200 rounded-xl text-xs font-extrabold text-emerald-800">
                💳 Club Member
              </div>
              <select
                v-else
                id="contact-role"
                v-model="form.role"
                class="w-full sm:w-1/2 px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                required
              >
                <option value="Contractor / Coach">Contractor / Coach</option>
                <option value="Volunteer / Staff">Volunteer / Staff</option>
                <option value="Vendor Representative">Vendor Representative</option>
                <option value="Client / Customer">Client / Customer</option>
                <option value="Club Member">Club Member</option>
              </select>
              <p v-if="form.errors.role" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.role }}</p>
            </div>
          </div>

          <!-- Section 2: Contact Methods & Tax Info -->
          <div class="space-y-4 pt-4 border-t border-slate-100">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wider border-b border-slate-100 pb-2">
              2. Contact Details
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label for="contact-email" class="block text-xs font-bold text-slate-800 mb-1">Email Address</label>
                <input
                  id="contact-email"
                  v-model="form.email"
                  type="email"
                  placeholder="robert@example.co.uk"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                  autocomplete="email"
                />
                <p v-if="form.errors.email" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.email }}</p>
              </div>

              <div>
                <label for="contact-phone" class="block text-xs font-bold text-slate-800 mb-1">Phone Number</label>
                <input
                  id="contact-phone"
                  v-model="form.phone"
                  type="text"
                  placeholder="+44 7700 900123"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                  autocomplete="tel"
                />
                <p v-if="form.errors.phone" class="text-xs text-red-600 font-semibold mt-1">{{ form.errors.phone }}</p>
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
                  placeholder="14 Boathouse Lane"
                  class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-xs font-medium text-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                  autocomplete="address-line1"
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
                  autocomplete="address-line2"
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
                  autocomplete="address-level2"
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
                  autocomplete="postal-code"
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
                  autocomplete="country-name"
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
                placeholder="e.g. Membership since 2021. Direct debit set up. Payment terms Net 30."
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
