<script setup>
import { Link } from '@inertiajs/vue3';

// A tab of the accounting screen, in its own file so the page stays readable.
const contactFilter = defineModel('contactFilter', { type: [String, Number, Boolean, Array, Object], default: undefined });

defineProps({
    canManageBilling: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    contacts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredContacts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    members: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 space-y-6">
      <div class="border-b border-slate-100 dark:border-slate-800 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h3 class="text-lg font-black text-slate-900 dark:text-white">Contacts & Directory</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">Manage billing contacts for individual persons, contractors, vendors, sponsors, and club members.</p>
        </div>
        <Link
          v-if="canManageBilling"
          :href="route('admin.accounting.contacts.create', club.slug)"
          class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md transition-all cursor-pointer flex items-center gap-2 shrink-0"
        >
          <span>+ Add contact</span>
        </Link>
      </div>

      <!-- Filter Segmented Tabs -->
      <div class="flex items-center gap-2 text-xs font-bold border-b border-slate-100 dark:border-slate-800 pb-3 overflow-x-auto">
        <button
          type="button"
          @click="contactFilter = 'all'"
          :class="['px-3 py-1.5 rounded-xl transition-all cursor-pointer', contactFilter === 'all' ? 'bg-slate-900 dark:bg-slate-700 text-white font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700']"
        >
          All contacts ({{ filteredContacts.length }})
        </button>
        <button
          type="button"
          @click="contactFilter = 'person'"
          :class="['px-3 py-1.5 rounded-xl transition-all cursor-pointer flex items-center gap-1.5', contactFilter === 'person' ? 'bg-blue-700 text-white font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700']"
        >
          <span>👤 Persons & contractors</span>
        </button>
        <button
          type="button"
          @click="contactFilter = 'member'"
          :class="['px-3 py-1.5 rounded-xl transition-all cursor-pointer flex items-center gap-1.5', contactFilter === 'member' ? 'bg-emerald-700 text-white font-black' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700']"
        >
          <span>💳 Club members</span>
        </button>
      </div>

      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800/80 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              <th class="py-3 px-4">Entity / Contact Name</th>
              <th class="py-3 px-4">Type</th>
              <th class="py-3 px-4">Role / Category</th>
              <th class="py-3 px-4">Email & Phone</th>
              <th class="py-3 px-4">Address / Tax ID</th>
              <th class="py-3 px-4 text-center">Quick Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200">
            <tr v-if="!filteredContacts.length">
              <td colspan="6" class="py-8 text-center text-slate-400">No contacts match the selected filter. Click "+ Add Contact" to add your first contact.</td>
            </tr>
            <tr v-for="c in filteredContacts" :key="c.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
              <td class="py-3 px-4">
                <div class="flex items-center gap-2.5">
                  <span class="text-base p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-800">👤</span>
                  <div>
                    <Link
                      v-if="c.kind === 'contact'"
                      :href="route('admin.accounting.contacts.edit', [club.slug, c.contact_db_id])"
                      class="font-extrabold text-slate-900 dark:text-white hover:text-blue-700 dark:hover:text-blue-300 hover:underline text-left block cursor-pointer transition-colors"
                      title="Click to edit contact details"
                    >
                      {{ c.name }}
                    </Link>
                    <Link
                      v-else-if="c.kind === 'member' && c.user_id"
                      :href="route('admin.accounting.contacts.member.edit', [club.slug, c.user_id])"
                      class="font-extrabold text-slate-900 dark:text-white hover:text-blue-700 dark:hover:text-blue-300 hover:underline text-left block cursor-pointer transition-colors"
                      title="Click to edit member accounting details"
                    >
                      {{ c.name }}
                    </Link>
                    <span v-else class="font-extrabold text-slate-900 dark:text-white block">{{ c.name }}</span>
                    <span v-if="c.contact_person && c.contact_person !== c.name" class="text-[11px] text-slate-500 dark:text-slate-400 block">
                      Alt: {{ c.contact_person }}
                    </span>
                  </div>
                </div>
              </td>
              <td class="py-3 px-4">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-800/60">
                  {{ c.kind === 'member' ? 'member' : 'person' }}
                </span>
              </td>
              <td class="py-3 px-4">
                <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-black bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-800">
                  {{ c.role }}
                </span>
              </td>
              <td class="py-3 px-4">
                <span class="font-mono text-slate-700 dark:text-slate-200 block">{{ c.email }}</span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 block">{{ c.phone }}</span>
              </td>
              <td class="py-3 px-4 text-slate-600 dark:text-slate-300">
                <span class="block text-[11px] font-medium">{{ c.address }}</span>
                <span v-if="c.tax_id && c.tax_id !== '—'" class="font-mono text-[10px] text-slate-400 block">Tax ID: {{ c.tax_id }}</span>
              </td>
              <td class="py-3 px-4 text-center">
                <Link
                  v-if="c.kind === 'member'"
                  :href="route('admin.accounting.invoices.create', { clubSlug: club.slug, user: c.user_id })"
                  class="px-2.5 py-1 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-200 dark:border-emerald-800/60 text-[10px] font-extrabold rounded-xl transition-all"
                >
                  + Issue invoice
                </Link>
                <div v-else class="flex items-center justify-center gap-1.5">
                  <Link
                    :href="route('admin.accounting.contacts.edit', [club.slug, c.contact_db_id])"
                    class="px-2.5 py-1 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-200 dark:border-blue-800/60 text-[10px] font-extrabold rounded-xl transition-all cursor-pointer"
                    title="Edit Contact"
                  >
                    Edit
                  </Link>
                  <Link
                    :href="route('admin.accounting.bills.create', { clubSlug: club.slug, vendor: c.name })"
                    class="px-2.5 py-1 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800/60 text-[10px] font-extrabold rounded-xl transition-all"
                  >
                    + Record bill
                  </Link>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
